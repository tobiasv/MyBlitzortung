<?php

/*
    MyBlitzortung - a tool for participants of blitzortung.org
	to display lightning data on their web sites.

    Copyright (C) 2011  Tobias Volgnandt

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Login to blitzortung.org an return login-string
function bo_get_login_str()
{
	$file = file_get_contents('http://www.blitzortung.org/Webpages/index.php?lang=de&page=3&username='.BO_USER.'&password='.BO_PASS);

	if ($file === false)
		return false;

	if (preg_match('/login_string=([A-Z0-9]+)/', $file, $r))
	{
		$bo_login_id = $r[1];
		bo_set_conf('bo_login_id', $bo_login_id);
		echo "\n<p>New Login ID: ".substr($bo_login_id,0,4)."...</p>\n";
	}

	return $bo_login_id;
}


// Get archive data from blitzortung cgi
function bo_get_archive($args='', $bo_login_id='')
{
	if (!$bo_login_id)
	{
		$bo_login_id = bo_get_conf('bo_login_id');
		$auto_id = true;
	}

	$file = file_get_contents('http://www.blitzortung.org/cgi-bin/archiv.cgi?login_string='.$bo_login_id.'&'.$args);

	if ($file === false)
		return false;

	if (strlen($file) < 100) //Login not successful --> new login ID
	{
		if ($auto_id)
		{
			$bo_login_id = bo_get_login_str();

			if ($bo_login_id)
				return bo_get_archive($args,$bo_login_id);
			else
				return false;
		}
		else
		{
			return false;
		}
	}
	else
		return $file;

}

// Get raw data from blitzortung.org
function bo_update_raw_signals($force = false)
{
	echo '<h3>Raw-Data</h3>';

	$last = bo_get_conf('uptime_raw');

	$i = 0;
	$a = 0;
	$u = 0;

	if (time() - $last > BO_UP_INTVL_RAW * 60 - 30 || $force)
	{
		bo_set_conf('uptime_raw', time());

		$file = bo_get_archive('lang=de&page=3&subpage_3=1&mode=4');

		if ($file === false)
			return false;

		$loadcount = bo_get_conf('upcount_raw');
		bo_set_conf('upcount_raw', $loadcount+1);


		$old_data = null;
		
		$lines = explode("\n", $file);
		foreach($lines as $l)
		{
			if (preg_match('/([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ([0-9]{2}:[0-9]{2}:[0-9]{2})\.([0-9]+) ([-0-9\.]+) ([-0-9\.]+) ([-0-9\.]+)(( [0-9\.]+){4}) ([A-F0-9]+)/', $l, $r))
			{
				$date = $r[1];
				$time = $r[2];

				$utime = strtotime("$date $time UTC");

				// update strike-data only some seconds *before* the *last download*
				if ($utime < $last - 20)
				{
					$a++;
					continue;
				}

				if ($old_data === null) // anti-duplicate without using unique-db keys
				{
					$old_data['time'] = array();
					
					$date_start = gmdate('Y-m-d H:i:s', $utime - 120); //120s back to be sure
					$date_end = gmdate('Y-m-d H:i:s', $utime + 3600 * 6); //6h to the future to be sure 
					
					//Searching for old Strikes (to avoid duplicates)
					//ToDo: fuzzy search for lat/lon AND time
					$sql = "SELECT id, time, time_ns
							FROM ".BO_DB_PREF."raw
							WHERE time BETWEEN '$date_start' AND '$date_end'";
					$res = bo_db($sql);
					while ($row = $res->fetch_assoc())
					{
						$id = $row['id'];
						$old_data['time'][$row['id']] = $row['time'].'.'.$row['time_ns'];
					}
				}
				
				$time_ns = intval($r[3]);
				$lat = $r[4];
				$lon = $r[5];
				$height = $r[6];
				$data = $r[9];

				$sql = "
							time='$date $time',
							time_ns='$time_ns',
							lat='$lat',lon='$lon',
							height='$height',
							data=x'$data'
							";
							
				$id = array_search("$date $time.$time_ns", $old_data['time']);
				
				if ($id)
				{
					bo_db("UPDATE ".BO_DB_PREF."raw SET $sql WHERE id='$id'");
					$u++;
				}
				else
				{
					bo_db("INSERT INTO ".BO_DB_PREF."raw SET $sql");
					$i++;
				}
			}
		}

		echo "\nLines: ".count($lines)." *** New Raw Data: $i *** Updated: $u *** Already read: $a\n";

		//Longtime
		$count = bo_get_conf('count_raw_signals');
		bo_set_conf('count_raw_signals', $count + $i);

		bo_match_strike2raw();
	}
	else
	{
		echo "\nNO UPDATE! Last update ".(time() - $last)." seconds ago.\n";
	}

	return true;
}

// Get new strikes from blitzortung.org
function bo_update_strikes($force = false)
{
	global $LATITUDE, $LONGITUDE;

	$last = bo_get_conf('uptime_strikes');

	echo '<h3>Strikes</h3>';

	if (time() - $last > BO_UP_INTVL_STRIKES * 60 - 30 || $force)
	{
		bo_set_conf('uptime_strikes', time());

		$stations = bo_stations('user');

		$u = 0;
		$i = 0;
		$a = 0;
		$own = 0;

		$old_data = null;
		$dist_data = array();
		$bear_data = array();
		$dist_data_own = array();
		$bear_data_own = array();
		$max_dist_all = 0;
		$min_dist_all = 9E12;
		$max_dist_own = 0;
		$min_dist_own = 9E12;

		$file = file_get_contents('http://'.BO_USER.':'.BO_PASS.'@blitzortung.tmt.de/Data/Protected/participants.txt');

		if ($file === false)
			return false;

		$loadcount = bo_get_conf('upcount_strikes');
		bo_set_conf('upcount_strikes', $loadcount+1);

		$lines = explode("\n", $file);
		foreach($lines as $l)
		{
			if (preg_match('/([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ([0-9]{2}:[0-9]{2}:[0-9]{2})\.([0-9]+) ([-0-9\.]+) ([-0-9\.]+) ([0-9\.]+)kA.* ([0-9]+)m [0-9]+ (.*)/', $l, $r))
			{
				$date = $r[1];
				$time = $r[2];

				$utime = strtotime("$date $time UTC");

				// update strike-data only some seconds min *before* the *last download*
				if ($utime < $last - 10)
				{
					$a++;
					continue;
				}
				
				if ($old_data === null)
				{
					$old_data['part'] = array();
					$old_data['time'] = array();
					$old_data['loc'] = array();
					
					$date_start = gmdate('Y-m-d H:i:s', $utime - 120); //120s back to be sure
					$date_end = gmdate('Y-m-d H:i:s', $utime + 3600 * 6); //6h to the future to be sure
					
					//Searching for old Strikes (to avoid duplicates)
					//ToDo: fuzzy search for lat/lon AND time
					$sql = "SELECT id, part, time, time_ns, lat, lon
							FROM ".BO_DB_PREF."strikes
							WHERE time BETWEEN '$date_start' AND '$date_end'";
					$res = bo_db($sql);
					while ($row = $res->fetch_assoc())
					{
						$id = $row['id'];
						
						$old_data['part'][$id] = $row['part'];
						$old_data['time'][$id] = $row['time'].'.'.$row['time_ns'];
						$old_data['loc'][$id] = $row['lat'].'/'.$row['lon'];
					}
				}
				
				$time_ns = intval($r[3]);
				$lat = $r[4];
				$lon = $r[5];
				$cur = $r[6];
				$deviation = $r[7];

				$participants = explode(' ', $r[8]);
				$users = count($participants);
				$part = strpos($r[8], BO_USER) !== false ? 1 : 0;
				$dist = bo_latlon2dist($lat, $lon);
				$bear = bo_latlon2bearing($lat, $lon);

				$sql = "
							time='$date $time',
							time_ns='$time_ns',
							lat='$lat',lon='$lon',
							distance='$dist',
							bearing='$bear',
							deviation='$deviation',
							current='$cur',
							users='$users',
							part='$part'
							";

				//Searching for old Strikes (to avoid duplicates)
				//ToDo: fuzzy search for lat/lon AND time
				/*
				$sql2 = "SELECT id, part, time, time_ns, lat, lon
						FROM ".BO_DB_PREF."strikes
						WHERE (time='$date $time' AND time_ns='$time_ns')";
				$row2 = bo_db($sql2)->fetch_assoc();
				$id = $row2['id'];
				$old_part = $row2['part'];
				*/
				
				//search for older entries of the same strike
				$id = array_search("$date $time.$time_ns", $old_data['time']);
				
				if ($id === false)
					$id = array_search("$lat/$lon", $old_data['loc']);

				if ($id)
					$old_part = $old_data['part'][$id];
				else
					$old_part = 0;

				//for statistics
				$bear_id = intval($bear);
				$dist_id = intval($dist / 10 / 1000);

				if (!$id)
				{
					$id = bo_db("INSERT INTO ".BO_DB_PREF."strikes SET $sql", false);
					$i++;

					//Long-Time Statistics
					$bear_data[$bear_id]++;
					$dist_data[$dist_id]++;
				}
				else
				{
					bo_db("UPDATE ".BO_DB_PREF."strikes SET $sql WHERE id='$id'");
					$u++;
				}

				//Own Long-Time Statistics
				if ( (!$id || !$old_part) && $part)
				{
					$bear_data_own[$bear_id]++;
					$dist_data_own[$dist_id]++;

					$max_dist_own = max($dist, $max_dist_own);
					$min_dist_own = min($dist, $min_dist_own);

					$own++;
				}

				//Update Strike <-> All Participated Stations
				if ($id)
				{
					$sql = '';
					foreach($participants as $user)
					{
						$stId = $stations[$user]['id'];
						$stLat = $stations[$user]['lat'];
						$stLon = $stations[$user]['lon'];

						$sql .= ($sql ? ',' : '')." ('$id', '$stId') ";
					}

					if ($sql)
					{
						$sql = "INSERT IGNORE INTO ".BO_DB_PREF."stations_strikes (strike_id, station_id) VALUES $sql";
						bo_db($sql);
					}
				}

				$max_users = max($max_users, $users);
				$max_dist_all = max($dist, $max_dist_all);
				$min_dist_all = min($dist, $min_dist_all);

			}
		}

		echo "\nLines: ".count($lines)." *** New Strikes: $i *** Updated: $u *** Already read: $a\n";

		$count = bo_get_conf('count_strikes');
		bo_set_conf('count_strikes', $count + $i);

		$count = bo_get_conf('count_strikes_own');
		bo_set_conf('count_strikes_own', $count + $own);

		//Update Longtime stat
		if ($own)
		{
			$bear_data_all = unserialize(bo_get_conf('longtime_bear_own'));
			$dist_data_all = unserialize(bo_get_conf('longtime_dist_own'));

			foreach($bear_data_own as $bear_id => $bear_count)
				$bear_data_all[$bear_id] += $bear_count;

			foreach($dist_data_own as $dist_id => $dist_count)
				$dist_data_all[$dist_id] += $dist_count;

			bo_set_conf('longtime_bear_own', serialize($bear_data_all));
			bo_set_conf('longtime_dist_own', serialize($dist_data_all));
		}

		$bear_data_all = unserialize(bo_get_conf('longtime_bear'));
		$dist_data_all = unserialize(bo_get_conf('longtime_dist'));

		foreach($bear_data as $bear_id => $bear_count)
			$bear_data_all[$bear_id] += $bear_count;

		foreach($dist_data as $dist_id => $dist_count)
			$dist_data_all[$dist_id] += $dist_count;

		bo_set_conf('longtime_bear', serialize($bear_data_all));
		bo_set_conf('longtime_dist', serialize($dist_data_all));

		$count = bo_get_conf('longtime_max_participants');
		if ($count < $max_users)
			bo_set_conf('longtime_max_participants', $max_users);

		$max = bo_get_conf('longtime_max_dist_all');
		if ($max < $max_dist_all)
			bo_set_conf('longtime_max_dist_all', $max_dist_all);

		$min = bo_get_conf('longtime_min_dist_all');
		if (!$min || $min > $min_dist_all)
			bo_set_conf('longtime_min_dist_all', $min_dist_all);

		$max = bo_get_conf('longtime_max_dist_own');
		if ($max < $max_dist_own)
			bo_set_conf('longtime_max_dist_own', $max_dist_own);

		$min = bo_get_conf('longtime_min_dist_own');
		if (!$min || $min > $min_dist_own)
			bo_set_conf('longtime_min_dist_own', $min_dist_own);


	}
	else
	{
		echo "\nNO UPDATE! Last update ".(time() - $last)." seconds ago.\n";
	}

	return true;
}


function bo_match_strike2raw()
{
	// Update strike_id <-> raw_id
	$c = 299792458;
	$fuzz = 0.0006;

	$sql = "SELECT MAX(time) mtime FROM ".BO_DB_PREF."raw";
	$row = bo_db($sql)->fetch_assoc();
	$mtime = $row['mtime'];

	$u = array();
	$n = array();
	$m = array();

	$sql = "SELECT id, time, time_ns, distance, bearing
			FROM ".BO_DB_PREF."strikes
			WHERE part=1 AND raw_id IS NULL
					AND time < '$mtime'
			ORDER BY time DESC
			";
	$res = bo_db($sql);
	while($row = $res->fetch_assoc())
	{
		$time_strike  = strtotime($row['time']." UTC");
		$ntime_strike = 1E-9 * $row['time_ns'];

		$time_raw  = $time_strike - 1;      //Raw times are 1 second behind strike time
		$ntime_raw = $ntime_strike + $row['distance'] / $c;  //Speed of Light

		if ($ntime_raw > 1)
		{
			$time_raw  += 1;
			$ntime_raw -= 1;
		}

		//fuzzy search
		$search_from  = $time_raw;
		$search_to    = $time_raw;
		$nsearch_from = $ntime_raw - $fuzz;
		$nsearch_to   = $ntime_raw + $fuzz;

		if ($nsearch_from < 0) { $nsearch_from++; $search_from--; }
		else if ($nsearch_from > 1) { $nsearch_from--; $search_from++; }

		if ($nsearch_to < 0) { $nsearch_to++; $search_to--; }
		else if ($nsearch_to > 1) { $nsearch_to--; $search_to++; }

		$search_date_from = gmdate('Y-m-d H:i:s', intval($search_from));
		$search_date_to   = gmdate('Y-m-d H:i:s', intval($search_to));

		$nsearch_from *= 1E9;
		$nsearch_to   *= 1E9;

		if ($nsearch_from > $nsearch_to)
			$nsql = " time_ns > '$nsearch_from'  OR time_ns < '$nsearch_to' ";
		else
			$nsql = " time_ns > '$nsearch_from' AND time_ns < '$nsearch_to' ";

		$sql = "SELECT id, time, time_ns, data
				FROM ".BO_DB_PREF."raw
				WHERE 	time    BETWEEN '$search_date_from' AND '$search_date_to'
						AND $nsql
				LIMIT 2";
		$res2 = bo_db($sql);
		$num = $res2->num_rows;
		$row2 = $res2->fetch_assoc();

		switch($num)
		{
			case 0:  $n[$row2['id']] = $row['id']; break; //no raw data found
			default: $m[$row2['id']] = $row['id']; break; //too much lines matched

			case 1:  //exact match

				$u[$row2['id']] = $row['id'];

				if (BO_EXPERIMENTAL_POLARITY_CHECK === true) //experimental polarity checking
				{
					$polarity[$row['id']] = bo_strike2polarity($row2['data'], $row['bearing']);

					if ($polarity[$row['id']] === null)
						$polarity[$row['id']] = "NULL";
				}

				break;

		}

		//echo "<br>Strike: ".$row['time'].".".$row['time_ns']." <-> Your Station: ".$row2['time'].".".$row2['time_ns']." Num Rows: ".$num;
	}

	echo "\n<p>Assign raw data to strikes:
			".(count($u) + count($n) + count($m))." strikes analyzed
			*** Unique: ".count($u)." *** Not found: ".count($n)." *** Multiple: ".count($m)."</p>";

	//Update matched
	foreach($u as $raw_id => $strike_id)
	{
		if (BO_EXPERIMENTAL_POLARITY_CHECK === true)
		{
			$sql = "UPDATE ".BO_DB_PREF."strikes SET raw_id='$raw_id', polarity=".$polarity[$strike_id]."  WHERE id='$strike_id'";
			bo_db($sql);
		}
		else
			bo_db("UPDATE ".BO_DB_PREF."strikes SET raw_id='$raw_id'   WHERE id='$strike_id'");

		bo_db("UPDATE ".BO_DB_PREF."raw SET strike_id='$strike_id' WHERE id='$raw_id'");
	}

	//Update unmatched
	$d = array_merge($n, $m);
	$sql = '';
	foreach($d as $strike_id)
		$sql .= " OR id='$strike_id' ";
	bo_db("UPDATE ".BO_DB_PREF."strikes SET raw_id='0' WHERE 1=0 $sql");

	return true;
}

// Get stations-data and statistics from blitzortung.org
function bo_update_stations($force = false)
{
	global $LATITUDE, $LONGITUDE;

	$last = bo_get_conf('uptime_stations');

	echo '<h3>Stations</h3>';

	$i = 0;
	$u = 0;

	if (time() - $last > BO_UP_INTVL_STATIONS * 60 - 30 || $force)
	{
		bo_set_conf('uptime_stations', time());

		$u = 0;
		$i = 0;
		$Count = array();
		$signal_count = 0;

		$file = file_get_contents('http://'.BO_USER.':'.BO_PASS.'@blitzortung.tmt.de/Data/Protected/stations.txt');

		if ($file === false)
			return false;

		$loadcount = bo_get_conf('upcount_stations');
		bo_set_conf('upcount_stations', $loadcount+1);

		$lines = explode("\n", $file);
		foreach($lines as $l)
		{
			$cols = explode(" ", $l);

			$stId 		= intval($cols[0]);

			if (!$stId)
				continue;

			$stUser 	= $cols[1];
			$stCity 	= html_entity_decode($cols[3]);
			$stCountry 	= $cols[4];
			$stLat	 	= $cols[5];
			$stLon 		= $cols[6];
			$stTime 	= substr($cols[7], 0, 10).' '.substr($cols[7], 16, 8);
			$stTimeMs 	= substr($cols[7], 25);
			$stStatus 	= $cols[8];
			$stDist 	= bo_latlon2dist($stLat, $stLon);

			$Count[$stId]['sig'] = $cols[10];
			$Count[$stId]['active'] = $stStatus == 'A';

			$sql = " 	id='$stId',
						user='$stUser',
						city='$stCity',
						country='$stCountry',
						lat='$stLat',
						lon='$stLon',
						distance='$stDist',
						last_time='$stTime',
						last_time_ns='$stTimeMs',
						status='$stStatus' ";

			$sql = strtr($sql, array('\null' => ''));

			if (bo_db("INSERT INTO ".BO_DB_PREF."stations SET $sql", false))
			{
				$i++;
			}
			else
			{
				bo_db("UPDATE ".BO_DB_PREF."stations SET $sql WHERE id='$stId'");
				$u++;
			}

			$signal_count += $Count[$stId]['sig'];

		}

		echo "\nStations: ".(count($lines)-2)." *** New Stations: $i *** Updated: $u\n";

		//Update Statistics
		$datetime      = gmdate('Y-m-d H:i:s', time());
		$datetime_back = gmdate('Y-m-d H:i:s', time() - 3600);

		$sql = "SELECT a.station_id sid, COUNT(a.station_id) cnt
				FROM ".BO_DB_PREF."stations_strikes a, ".BO_DB_PREF."strikes b
				WHERE a.strike_id=b.id AND b.time > '$datetime_back'
				GROUP BY a.station_id";
		$res = bo_db($sql);
		while ($row = $res->fetch_assoc())
			$Count[intval($row['sid'])]['strikes'] = $row['cnt'];

		$active_stations = 0;
		$active_sig_stations = 0;
		foreach($Count as $id => $data)
		{
			if ($id && $data['active']) //($data['sig'] || $data['strikes'] || $data['active']) )
			{
				bo_db("INSERT INTO ".BO_DB_PREF."stations_stat
					SET station_id='$id', time='$datetime', signalsh='".$data['sig']."', strikesh='".$data['strikes']."'");
			}

			if ($data['active'])
				$active_stations++;

			if ($data['sig'])
				$active_sig_stations++;

		}

		//Update whole strike count for dummy station "0"
		$sql = "SELECT COUNT(id) cnt
				FROM ".BO_DB_PREF."strikes
				WHERE time > '$datetime_back'";
		$row = bo_db($sql)->fetch_assoc();
		$strike_count = $row['cnt'];
		bo_db("INSERT INTO ".BO_DB_PREF."stations_stat
				SET station_id='0', time='$datetime', signalsh='$signal_count', strikesh='$strike_count'");


		/*** Update Longtime statistics ***/
		//max active stations ever
		$max = bo_get_conf('longtime_count_max_active_stations');
		bo_set_conf('longtime_count_max_active_stations', max($max, $active_stations));

		//max active stations (sending signals) ever
		$max = bo_get_conf('longtime_count_max_active_stations_sig');
		bo_set_conf('longtime_count_max_active_stations_sig', max($max, $active_sig_stations));

		//max signals/h
		$max = bo_get_conf('longtime_max_signalsh');
		bo_set_conf('longtime_max_signalsh', max($max, $signal_count));

		//max strikes/h
		$max = bo_get_conf('longtime_max_strikesh');
		bo_set_conf('longtime_max_strikesh', max($max, $strike_count));


		/*** Longtime stat. for own Station ***/
		$own_id = bo_station_id();

		//max signals/h (own)
		$max = bo_get_conf('longtime_max_signalsh_own');
		bo_set_conf('longtime_max_signalsh_own', max($max, $Count[$own_id]['sig']));

		//max strikes/h (own)
		$max = bo_get_conf('longtime_max_strikesh_own');
		bo_set_conf('longtime_max_strikesh_own', max($max, $Count[$own_id]['strikes']));

		//Activity/inactivity counter for own station
		$time_interval = $last ? time() - $last : 0;
		if ($Count[$own_id]['active'])
		{
			bo_set_conf('station_last_active', time());
			$time_interval += bo_get_conf('longtime_station_active_time');
			bo_set_conf('longtime_station_active_time', $time_interval);
		}
		else
		{
			bo_set_conf('station_last_inactive', time());
			$time_interval += bo_get_conf('longtime_station_inactive_time');
			bo_set_conf('longtime_station_inactive_time', $time_interval);
		}

		//Update strike-count per day
		$ytime = mktime(date("H"),date("i"),date("s"),date("n"),date('j')-1);
		$day_id = gmdate('Ymd', $ytime);
		$yesterday_count = bo_get_conf('strikes_'.$day_id);
		$radius = BO_RADIUS * 1000;

		// Daily Statistics and longtime strike-count
		if (!$yesterday_count)
		{

			$yesterday = gmdate('Y-m-d', $ytime);
			$sql = "SELECT COUNT(id) cnt FROM ".BO_DB_PREF."strikes WHERE {where} time BETWEEN '$yesterday 00:00:00' AND '$yesterday 23:59:59'";

			//whole strike count
			$row_all = bo_db(strtr($sql,array('{where}' => '')))->fetch_assoc();
			//own strike count
			$row_own = bo_db(strtr($sql,array('{where}' => 'part=1 AND ')))->fetch_assoc();
			//whole strike count (in radius)
			$row_all_rad = bo_db(strtr($sql,array('{where}' => 'distance < "'.$radius.'" AND ')))->fetch_assoc();
			//own strike count (in radius)
			$row_own_rad = bo_db(strtr($sql,array('{where}' => 'part=1 AND distance < "'.$radius.'" AND ')))->fetch_assoc();

			$strikes_day = array(0 => $row_all['cnt'], 1 => $row_own['cnt'], 2 => $row_all_rad['cnt'], 3 => $row_own_rad['cnt']);

			bo_set_conf('strikes_'.$day_id, serialize($strikes_day));


			$D = unserialize(bo_get_conf('longtime_max_strikes_day_all'));
			if ($D[0] < $row_all['cnt'])
				bo_set_conf('longtime_max_strikes_day_all', serialize(array($row_all['cnt'], $yesterday)));

			$D = unserialize(bo_get_conf('longtime_max_strikes_day_all_rad'));
			if ($D[0] < $row_all_rad['cnt'])
				bo_set_conf('longtime_max_strikes_day_all_rad', serialize(array($row_all_rad['cnt'], $yesterday)));

			$D = unserialize(bo_get_conf('longtime_max_strikes_day_own'));
			if ($D[0] < $row_own['cnt'])
				bo_set_conf('longtime_max_strikes_day_own', serialize(array($row_own['cnt'], $yesterday)));

			$D = unserialize(bo_get_conf('longtime_max_strikes_day_own_rad'));
			if ($D[0] < $row_own_rad['cnt'])
				bo_set_conf('longtime_max_strikes_day_own_rad', serialize(array($row_own_rad['cnt'], $yesterday)));


		}

	}
	else
	{
		echo "\nNO UPDATE! Last update ".(time() - $last)." seconds ago.\n";
	}

	return true;
}


function bo_update_all($force)
{

	echo '<h1>MyBlitzortung</h1>';

	echo '<h2>Getting lightning data from blitzortung.org</h2>';

	set_time_limit(50);

	if (!$force)
		sleep(rand(0,30));

	ini_set('default_socket_timeout', 10);
	bo_set_conf('is_updating', 0);
	$is_updating = bo_get_conf('is_updating');

	//Check if sth. went wrong on the last update (if older than 120sec continue)
	if ($is_updating && time() - $is_updating < 120)
	{
		echo '<p>Error: Another update is running</p>';
		return;
	}

	bo_set_conf('is_updating', time());

	if (!bo_get_conf('first_update_time'))
		bo_set_conf('first_update_time', time());

	/*** Get the data! ***/
	flush();
	$t = time();
	if (bo_update_strikes($force) !== false)
	{	
		flush();
		if (bo_update_stations($force) !== false)
		{
			flush();
			bo_update_raw_signals($force);
		}
	}

	/*** Purge old data ***/
	echo '<h2>Purging data</h2>';
	flush();

	if (BO_PURGE_ENABLE === true)
	{
		$last = bo_get_conf('purge_time');

		if ( (defined('BO_PURGE_MAIN_INTVL') && BO_PURGE_MAIN_INTVL && time() - $last > 3600 * BO_PURGE_MAIN_INTVL) || $force)
		{
			bo_set_conf('purge_time', time());

			//Raw-Signals, where no strike assigned
			if (defined('BO_PURGE_SIG_NS') && BO_PURGE_SIG_NS)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_SIG_NS * 3600);
				$num = bo_db("DELETE FROM ".BO_DB_PREF."raw WHERE time < '$dtime' AND strike_id=0");
				echo "<p>Raw signals (with no strikes assigned): $num</p>";

				bo_db("OPTIMIZE TABLE ".BO_DB_PREF."raw");

				flush();
			}

			//All Raw-Signals
			if (defined('BO_PURGE_SIG_ALL') && BO_PURGE_SIG_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_SIG_ALL * 3600);
				$num = bo_db("DELETE FROM ".BO_DB_PREF."raw WHERE time < '$dtime'");

				bo_db("OPTIMIZE TABLE ".BO_DB_PREF."raw");

				echo "<p>Raw signals: $num</p>";
				flush();
			}

			//Strikes (not participated)
			if (defined('BO_PURGE_STR_NP') && BO_PURGE_STR_NP)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STR_NP * 3600);
				$num  = bo_db("DELETE a,b FROM ".BO_DB_PREF."strikes a, ".BO_DB_PREF."stations_strikes b
						WHERE time < '$dtime' AND a.id=b.strike_id AND part=0");
				$num += bo_db("DELETE FROM ".BO_DB_PREF."strikes WHERE time < '$dtime' AND part=0"); //to be sure

				bo_db("OPTIMIZE TABLE ".BO_DB_PREF."stations_strikes, ".BO_DB_PREF."strikes");

				echo "<p>Strikes (not participated): $num</p>";
				flush();
			}

			//Strikes (far away)
			if (defined('BO_PURGE_STR_DIST') && BO_PURGE_STR_DIST && defined('BO_PURGE_STR_DIST_KM') && BO_PURGE_STR_DIST_KM)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STR_DIST * 3600);
				$num  = bo_db("DELETE a,b FROM ".BO_DB_PREF."strikes a, ".BO_DB_PREF."stations_strikes b
						WHERE time < '$dtime' AND a.id=b.strike_id AND distance > '".(BO_PURGE_STR_DIST_KM * 1000)."'");
				$num += bo_db("DELETE FROM ".BO_DB_PREF."strikes WHERE time < '$dtime' AND distance > '".(BO_PURGE_STR_DIST_KM * 1000)."'"); //to be sure

				bo_db("OPTIMIZE TABLE ".BO_DB_PREF."stations_strikes, ".BO_DB_PREF."strikes");

				echo "<p>Strikes (over ".BO_PURGE_STR_DIST_KM."km away): $num</p>";
				flush();
			}

			//All Strikes
			if (defined('BO_PURGE_STR_ALL') && BO_PURGE_STR_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STR_ALL * 3600);
				$num  = bo_db("DELETE a,b FROM ".BO_DB_PREF."strikes a, ".BO_DB_PREF."stations_strikes b
						WHERE time < '$dtime' AND a.id=b.strike_id");
				$num += bo_db("DELETE FROM ".BO_DB_PREF."strikes WHERE time < '$dtime'"); //to be sure

				bo_db("OPTIMIZE TABLE ".BO_DB_PREF."stations_strikes, ".BO_DB_PREF."strikes");

				echo "<p>Strikes: $num</p>";
				flush();
			}

			//Strike <-> Station table
			if (defined('BO_PURGE_STRSTA_ALL') && BO_PURGE_STRSTA_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STRSTA_ALL * 3600);

				$row = bo_db("SELECT MAX(id) id FROM ".BO_DB_PREF."strikes WHERE time < '$dtime'")->fetch_assoc();
				$strId = $row['id'];

				$num = bo_db("DELETE FROM ".BO_DB_PREF."stations_strikes WHERE strike_id < '$strId'");

				bo_db("OPTIMIZE TABLE ".BO_DB_PREF."stations_strikes");

				echo "<p>Strike <-> Station table: $num</p>";
				flush();
			}

			//Station statistics (not own and whole strike count)
			if (defined('BO_PURGE_STA_OTHER') && BO_PURGE_STA_OTHER)
			{
				$stId = bo_station_id();

				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STA_OTHER * 3600);
				$num = bo_db("DELETE FROM ".BO_DB_PREF."stations_stat WHERE time < '$dtime' AND station_id != '$stId' AND station_id != 0");
				echo "<p>Station statistics (not yours): $num</p>";
				flush();
			}

			//All station statistics
			if (defined('BO_PURGE_STA_ALL') && BO_PURGE_STA_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STA_ALL * 3600);
				bo_db("DELETE FROM ".BO_DB_PREF."stations_stat WHERE time < '$dtime'");
				echo "<p>Station statistics: $num</p>";
				flush();
			}

		}


	}
	else
	{
		echo '<p>Purgin disabled!</p>';
	}

	bo_set_conf('is_updating', 0);

	return;
}


?>