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
	}

	return $bo_login_id;
}


// Get archive data from blitzortung cgi
function bo_get_archive($args='', $bo_login_id=false)
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
			
			echo "\n<p>New Login ID: ".substr($bo_login_id,0,4)."...</p>\n";
			
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
	echo "<h3>Raw-Data</h3>\n";

	if (!defined('BO_UP_INTVL_RAW') || !BO_UP_INTVL_RAW)
	{
		echo "<p>Disabled!</p>\n";
		return true;
	}
	
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
							strike_id='0',
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

		echo "\n<p>Lines: ".count($lines)." *** New Raw Data: $i *** Updated: $u *** Already read: $a</p>\n";

		//Longtime
		$count = bo_get_conf('count_raw_signals');
		bo_set_conf('count_raw_signals', $count + $i);

		bo_match_strike2raw();
		
		$updated = true;
	}
	else
	{
		echo "\n<p>NO UPDATE! Last update ".(time() - $last)." seconds ago.</p>\n";
		$updated = false;
	}

	return $updated;
}

// Get new strikes from blitzortung.org
function bo_update_strikes($force = false)
{
	$last = bo_get_conf('uptime_strikes');

	echo "<h3>Strikes</h3>\n";

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

		$res = bo_db("SELECT MAX(time) mtime FROM ".BO_DB_PREF."strikes");
		$row = $res->fetch_assoc();
		$last_strike = strtotime($row['mtime'].' UTC');
		
		if ($last_strike > time())
			$last_strike = time() - 3600 * 24;
		else if ($last_strike <= 0 || !$last_strike)
			$last_strike = strtotime('2000-01-01');
		
		$time_update = $last_strike - 10;
		
		$loadcount = bo_get_conf('upcount_strikes');
		bo_set_conf('upcount_strikes', $loadcount+1);

		echo "\n".'<p>Last strike: '.date('Y-m-d H:i:s', $last_strike). 
				' *** Importing only strikes newer than: '.date('Y-m-d H:i:s', $time_update).
				' *** This is update #'.$loadcount.'</p>'."\n";
		
		$lines = explode("\n", $file);
		foreach($lines as $l)
		{
			if (preg_match('/([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ([0-9]{2}:[0-9]{2}:[0-9]{2})\.([0-9]+) ([-0-9\.]+) ([-0-9\.]+) ([0-9\.]+)kA.* ([0-9]+)m [0-9]+ (.*)/', $l, $r))
			{
				$date = $r[1];
				$time = $r[2];

				$utime = strtotime("$date $time UTC");

				// update strike-data only some seconds *before* the *last strike in Database*
				if ($utime < $time_update)
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
				if ($id && !(defined('BO_STATION_STAT_DISABLE') && BO_STATION_STAT_DISABLE == true) )
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

		echo "\n<p>Lines: ".count($lines)." *** New Strikes: $i *** Updated: $u *** Already read: $a</p>\n";

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

		$updated = true;
		
	}
	else
	{
		echo "\n<p>NO UPDATE! Last update ".(time() - $last)." seconds ago.</p>\n";
		$updated = false;
	}

	return $updated;
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

	echo "\n<p>Assign raw data to strikes: ".
			(count($u) + count($n) + count($m))." strikes analyzed".
			" *** Unique: ".count($u)." *** Not found: ".count($n)." *** Multiple: ".count($m)."</p>";

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
	$last = bo_get_conf('uptime_stations');

	echo "<h3>Stations</h3>\n";

	if (!defined('BO_UP_INTVL_STATIONS') || !BO_UP_INTVL_STATIONS)
	{
		echo "<p>Disabled!</p>\n";
	}
	
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
			$stCountry 	= html_entity_decode($cols[4]);
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

		echo "\n<p>Stations: ".(count($lines)-2)." *** New Stations: $i *** Updated: $u</p>\n";

		//Update Statistics
		$datetime      = gmdate('Y-m-d H:i:s', time());
		$datetime_back = gmdate('Y-m-d H:i:s', time() - 3600);

		$only_own = false;
		if ((defined('BO_STATION_STAT_DISABLE') && BO_STATION_STAT_DISABLE == true))
			 $only_own = bo_station_id();
			

		$sql = "SELECT a.station_id sid, COUNT(a.station_id) cnt
				FROM ".BO_DB_PREF."stations_strikes a, ".BO_DB_PREF."strikes b
				WHERE a.strike_id=b.id AND b.time > '$datetime_back'
					".($only_own ? " AND a.station_id='".$only_own."'" : "")."
				GROUP BY a.station_id";
		$res = bo_db($sql);
		while ($row = $res->fetch_assoc())
			$Count[intval($row['sid'])]['strikes'] = $row['cnt'];
		
		$active_stations = 0;
		$active_sig_stations = 0;
		foreach($Count as $id => $data)
		{
			if ($only_own && $only_own != $id)
				continue;
			
			if ($id && $data['active']) 
			{
				bo_db("INSERT INTO ".BO_DB_PREF."stations_stat
					SET station_id='$id', time='$datetime', signalsh='".intval($data['sig'])."', strikesh='".intval($data['strikes'])."'");
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

		$updated = true;
	}
	else
	{
		echo "\n<p>NO UPDATE! Last update ".(time() - $last)." seconds ago.</p>\n";
		$updated = false;
	}

	return $updated;
}


function bo_update_all($force)
{
	session_write_close();

	echo "<h2>Getting lightning data from blitzortung.org</h2>\n";

	$start_time = time();
	$max_time = 50;
	
	@set_time_limit($max_time+10);

	if (!$force)
		sleep(rand(0,30)); // to avoid to much connections from different stations to blitzortung.org at the same time

	ini_set('default_socket_timeout', 10);
	$is_updating = bo_get_conf('is_updating');

	//Check if sth. went wrong on the last update (if older than 120sec continue)
	if ($is_updating && time() - $is_updating < 120)
	{
		echo "\n<p>Error: Another update is running</p>\n";
		return;
	}

	bo_set_conf('is_updating', time());

	if (!bo_get_conf('first_update_time'))
		bo_set_conf('first_update_time', time());

		
	//check if we should do a async update
	if ( !(BO_UP_INTVL_STRIKES <= BO_UP_INTVL_STATIONS && BO_UP_INTVL_STATIONS <= BO_UP_INTVL_RAW) )
	{
		if (!$force)
			echo '<p>Asynchronous update!</p>';
		
		$async = true;
	}
	else
		$async = false;
	
	/*** Get the data! ***/
	//Update signals/stations only after strikes where imported
	
	flush();
	$t = time();
	
	$strikes_imported = bo_update_strikes($force);
	
	if ($strikes_imported !== false || $async || $force)
	{	
		flush();
		$stations_imported = bo_update_stations($force);
		
		flush();
		$signals_imported = bo_update_raw_signals($force);
		

		/*** Check and send strike alerts ***/
		if (defined('BO_ALERTS') && BO_ALERTS)
		{
			flush();
			echo "\n<h2>Checking and sending strike alerts.</h2>\n";
			bo_alert_send();
		}
	}
	
	if (time() - $start_time > $max_time)
	{
		echo '<p>TIMEOUT!</p>';
		return;
	}

	/*** Update MyBlitzortung stations ***/
	if (!$strikes_imported && !$stations_imported && !$signals_imported)
	{
		bo_my_station_autoupdate($force);
	}

	if (time() - $start_time > $max_time)
	{
		echo '<p>TIMEOUT!</p>';
		return;
	}

	/*** Purge old data ***/
	echo "\n<h2>Purging data</h2>\n";
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
				echo "<p>Raw signals (with no strikes assigned): $num</p>\n";

				bo_db("OPTIMIZE TABLE ".BO_DB_PREF."raw");

				flush();
			}

			//All Raw-Signals
			if (defined('BO_PURGE_SIG_ALL') && BO_PURGE_SIG_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_SIG_ALL * 3600);
				$num = bo_db("DELETE FROM ".BO_DB_PREF."raw WHERE time < '$dtime'");

				bo_db("OPTIMIZE TABLE ".BO_DB_PREF."raw");

				echo "<p>Raw signals: $num</p>\n";
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

				echo "<p>Strikes (not participated): $num</p>\n";
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

				echo "<p>Strikes (over ".BO_PURGE_STR_DIST_KM."km away): $num</p>\n";
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

				echo "<p>Strikes: $num</p>\n";
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

				echo "<p>Strike <-> Station table: $num</p>\n";
				flush();
			}

			//Station statistics (not own and whole strike count)
			if (defined('BO_PURGE_STA_OTHER') && BO_PURGE_STA_OTHER)
			{
				$stId = bo_station_id();

				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STA_OTHER * 3600);
				$num = bo_db("DELETE FROM ".BO_DB_PREF."stations_stat WHERE time < '$dtime' AND station_id != '$stId' AND station_id != 0");
				echo "<p>Station statistics (not yours): $num</p>\n";
				flush();
			}

			//All station statistics
			if (defined('BO_PURGE_STA_ALL') && BO_PURGE_STA_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STA_ALL * 3600);
				bo_db("DELETE FROM ".BO_DB_PREF."stations_stat WHERE time < '$dtime'");
				echo "<p>Station statistics: $num</p>\n";
				flush();
			}

		}


	}
	else
	{
		echo "<p>Purging disabled!</p>\n";
	}
	
	if (time() - $start_time > $max_time)
	{
		echo '<p>TIMEOUT!</p>';
		return;
	}

	bo_update_densities($max_time + $start_time - time());

	
	bo_set_conf('is_updating', 0);

	return;
}

function bo_update_densities($max_time)
{
	global $_BO;
	
	$start_time = time();

	if (!defined('BO_CALC_DENSITIES') || !BO_CALC_DENSITIES)
		return true;
	
	if (!is_array($_BO['density']))
		return true;
	
	$stations = array();
	$stations[0] = 0;
	$stations[bo_station_id()] = bo_station_id();
	
	if (defined('BO_DENSITY_STATIONS') && BO_DENSITY_STATIONS)
	{
		$tmp = explode(',', BO_DENSITY_STATIONS);
		foreach($tmp as $station_id)
			$stations[$station_id] = $station_id;
	}
	
	echo "\n<h2>Updating densities.</h2>\n";
	
	//Min/Max strike times
	$row = bo_db("SELECT MIN(time) mintime, MAX(time) maxtime FROM ".BO_DB_PREF."strikes")->fetch_assoc();
	$min_strike_time = strtotime($row['mintime'].' UTC');
	$max_strike_time = strtotime($row['maxtime'].' UTC');
	$delete_time = 0;
	
	//Which time ranges to create
	$ranges = array();
	
	//last month
	$ranges[] = array(mktime(0,0,0,date('m')-1,1,date('Y')), mktime(0,0,0,date('m'),0,date('Y')), 0);
	
	//last year 
	if (date('Y', $min_strike_time) <= date('Y') - 1)
		$ranges[] = array(strtotime( (date('Y')-1).'-01-01'), strtotime( (date('Y')-1).'-12-31'), -1 ); 

	//current month and year
	if (defined('BO_CALC_DENSITIES_CURRENT') && BO_CALC_DENSITIES_CURRENT)
	{
		//month
		$ranges[] = array(mktime(0,0,0,date('m'),1,date('Y')), mktime(0,0,0,date('m'),date('d'),date('Y')), -2 );
		
		//year
		$ranges[] = array(mktime(0,0,0,1,1,date('Y')), mktime(0,0,0,date('m'),date('d'),date('Y')), -3 );
		
		//delete old data, if it's not the end day of the month
		$delete_time = mktime(0,0,0,date('m'),date('d')-2,date('Y'));
		if (date('t', $delete_time) == date('d', $delete_time))
			$delete_time = 0;
	}
	
	//insert the ranges
	foreach($ranges as $r)
	{
		if ($max_strike_time < $date_end)
			continue;
		
		$date_start = date('Y-m-d', $r[0]);
		$date_end   = date('Y-m-d', $r[1]);
		$status  = intval($r[2]);
		
		//check if rows already exists
		$sql = "SELECT COUNT(*) cnt FROM ".BO_DB_PREF."densities 
					WHERE date_start='$date_start' AND date_end='$date_end'";
		$row = bo_db($sql)->fetch_assoc();
		
		// if rows missing --> insert to prepare for getting the data
		if (count($stations) * count($_BO['density']) > $row['cnt'])
		{
			foreach($_BO['density'] as $type_id => $d)
			{
				if (!isset($d) || !is_array($d) || empty($d))
					continue;
				
				$lat_min = $d['coord'][2];
				$lon_min = $d['coord'][3];
				$lat_max = $d['coord'][0];
				$lon_max = $d['coord'][1];
				$length  = $d['length'];
				
				foreach($stations as $station_id)
				{
					$sql = "INSERT IGNORE INTO ".BO_DB_PREF."densities 
							SET date_start='$date_start', date_end='$date_end', 
							type='$type_id', station_id='$station_id', status=$status,
							lat_min='$lat_min',lon_min='$lon_min',
							lat_max='$lat_max',lon_max='$lon_max',
							length='$length', info='', data=''
							";
					bo_db($sql);
				}
			}
		}
	}

	//check which densities are pending
	$pending = array();
	$res = bo_db("SELECT id, type, date_start, date_end, station_id, info, status
					FROM ".BO_DB_PREF."densities 
					WHERE status<=0 
					ORDER BY status DESC, date_start, date_end");
	while ($row = $res->fetch_assoc())
	{
		$max_status = max($max_status, $row['status']);
		$pending[$row['type']][$row['id']] = $row;
	}
	
	//create densities by type
	$timeout = false;
	foreach($_BO['density'] as $type_id => $a)
	{
		if (!isset($pending[$type_id]) || empty($a))
			continue;
		
		// length in meters of an element
		$length = $a['length'] * 1000;
		
		// area of each element
		$area = pow($length, 2);
		
		// data bytes of each sample
		$bps = $a['bps'];
		
		//zero strikes
		$zero = str_repeat('00', $bps);
		
		echo "\n<h3>".$a['name'].": Length: $length m / Area: $area square meters / Bytes per Area: $bps</h3>\n";
		flush();

		
		//calculate densities for every pending database entry
		foreach($pending[$type_id] as $id => $b)
		{
			//create entries with higher status first
			if ($b['status'] != $max_status)
				continue;
		
			$info = unserialize($b['info']);

			echo '<p>Station: #'.$b['station_id'];
			echo ' *** Range: '.$b['date_start'].' to '.$b['date_end'].' ';
			flush();
			
			//with status -1/-3 ==> calculate from other density data
			if ($b['status'] == -1 || $b['status'] == -3)
			{
				$max_count = 0;
				
				echo ' Calculate from month data! ';
				
				if ($info['calc_date_start']) // there was a timeout the last run
				{
					$date_start_add = $info['calc_date_start'];
					$b['date_start'] = $info['calc_date_start'];
					
					echo ' Starting at '.$b['date_start'];
					
					$sql = "SELECT data FROM ".BO_DB_PREF."densities WHERE id='$id'";
					$row = bo_db($sql)->fetch_assoc();
					$DATA = $row['data'] ? gzinflate($row['data']) : '';

				}
				else
				{
					$date_start_add = 0;
					$DATA = '';
				}
				flush();
				$sql = "SELECT data, date_start, date_end
						FROM ".BO_DB_PREF."densities 
						WHERE 1
							AND type='$type_id' 
							AND (status = 1 OR status = 3)
							AND date_start >= '".$b['date_start']."'
							AND date_end   <= '".$b['date_end']."'
							AND station_id = '".$b['station_id']."'
						ORDER BY status, date_start";
				$res = bo_db($sql);
				while ($row = $res->fetch_assoc())
				{
					if (!$date_start_add || $row['date_start'] == $date_start_add)
					{
						$date_start_add = date('Y-m-d', strtotime($row['date_end'].' + 1 day'));
						
						$OLDDATA = gzinflate($row['data']);
						$NEWDATA = $DATA;
						$DATA = '';
						
						for ($i=0; $i<=strlen($OLDDATA) / $bps / 2; $i++)
						{
							$val  = substr($OLDDATA, $i * $bps * 2, $bps * 2);
							
							// combine the two data streams
							if (strtolower($val) != str_repeat('ff', $bps))
							{
								$val = hexdec($val);
								
								if ($NEWDATA)
									$val += hexdec(substr($NEWDATA, $i * $bps * 2, $bps * 2));
								
								if ($val >= pow(2, $bps * 8)-1)
									$val = pow(2, $bps * 8)-2;
								
								$max_count = max($max_count, $val);
					
								$val = sprintf("%0".(2*$bps)."d", dechex($val));
							}
							
							$DATA .= $val;
						}
						
					}
					
					//Check for timeout
					if (time() - $start_time > $max_time - 1)
					{
						$info['calc_date_start'] = $date_start_add;
						$timeout = true;
						break;
					}
					
				}
				
			}
			else //calculate from strike database
			{

				if ($info && isset($info['last_lat']) && isset($info['last_lon']))
				{
					// get start position from var settings (begin southwest)
					$lat = $info['last_lat'];
					$lon = $info['last_lon'];
					
					//collect old data and decompress
					$sql = "SELECT data FROM ".BO_DB_PREF."densities WHERE id='$id'";
					$row = bo_db($sql)->fetch_assoc();
					$OLDDATA = $row['data'] ? gzinflate($row['data']) : '';
				}
				else
				{
					//start positions from database
					$lat = $a['coord'][2];
					$lon = $a['coord'][3];
					$OLDDATA = '';
				}

				$lat_end = $a['coord'][0];
				$lon_end = $a['coord'][1];

		
				echo " *** Start: $lat&deg; / $lon&deg; *** End: $lat_end&deg; / $lon_end&deg; *** <em>... Calculating ...</em>";
				flush();
				
				$sql_where = '';
				$sql_join  = '';
				
				if ($b['station_id'] == bo_station_id())
				{
					$sql_where = " AND s.part=1 ";
				}
				elseif ($b['station_id'])
				{
					$sql_join  = ",".BO_DB_PREF."stations_strikes ss ";
					$sql_where = " AND ss.strike_id=s.id AND ss.station_id='".intval($b['station_id'])."'";
				}

				$max_count = 0;
				$DATA = '';
				
				// counting strikes from west to east and south to north
				$i = 500000;
				while ($lat < $lat_end)
				{
					//difference to current lat/lon
					list($dlat, $dlon) = bo_distbearing2latlong($length * sqrt(2), 45, $lat, $lon);
					$dlat -= $lat;
					$dlon -= $lon;

					$last_lon_id = 0;
					
					// line by line
					$sql = "SELECT COUNT(*) cnt, FLOOR((s.lon+".(-$lon).")/(".$dlon.")) lon_id
							FROM ".BO_DB_PREF."strikes s
								$sql_join
							WHERE 1
								AND NOT (s.lat < ".($lat)." OR s.lat > ".($lat+$dlat)." OR s.lon < ".$lon." OR s.lon > ".$lon_end.") 
								AND s.time BETWEEN '".$b['date_start']."' AND '".$b['date_end']."'
								$sql_where
							GROUP BY lon_id
							";
					$res = bo_db($sql);
					while ($row = $res->fetch_assoc())
					{
						$max_count = max($max_count, $row['cnt']);
						
						//add zero strikes
						$DATA .= str_repeat($zero, ($row['lon_id'] - $last_lon_id));

						if ($row['cnt'] >= pow(2, $bps * 8)-1)
							$row['cnt'] = pow(2, $bps * 8)-2;
						
						//add strike count
						$DATA .= sprintf("%0".(2*$bps)."d", dechex($row['cnt']));
						
						$last_lon_id = $row['lon_id'] + 1;
					
					}

					// fill rest of the string
					$DATA .= str_repeat($zero, floor(($lon_end-$lon)/$dlon) - $last_lon_id + 1);
					
					// new line (= new lat)
					$DATA .= str_repeat('ff', $bps);

					$lat += $dlat;
					
					if ($i-- <= 0 || time() - $start_time > $max_time - 1)
					{
						echo " *** Stopped at $lat&deg / $lon_end&deg (delta: $dlat&deg / $dlon&deg) ";
						$timeout = true;
						break;
					}
				}
				
				echo " New data collected: ".(strlen($DATA) / 2).'bytes ';
				
				//new data string
				$DATA = $OLDDATA.$DATA;
			}
			
			echo ' *** Whole data: '.(strlen($DATA)).'bytes ';
			
			//database storage 
			$DATA = BoDb::esc(gzdeflate($DATA));
			
			if ($timeout)
				$status = $b['status'];
			else if ($b['status'] == 0)
				$status = 1;
			else
				$status = abs($b['status']) + 1;
			
			$info['last_lat'] = $lat;
			$info['last_lon'] = $lon; // currently no change from start value
			$info['bps'] = $bps;
			$info['max'] = max($max_count, $info['max']);

			$sql = "UPDATE ".BO_DB_PREF."densities 
							SET data='$DATA', info='".serialize($info)."', status='$status'
							WHERE id='$id'";
			$res = bo_db($sql);
			
			echo ' *** Max strike count: '.$info['max'].' *** Whole data compressed: '.(strlen($DATA)).'bytes ';
			
			if ($timeout)
				echo ' *** NOT YET READY! ';
			else
				echo ' *** FINISHED! ';
			
			echo "</p>\n";

			//Check again for timeout
			if (time() - $start_time > $max_time - 1)
				$timeout = true;

			
			if ($timeout)
				return;
			
			if ($delete_time)
			{
				$cnt = bo_db("DELETE FROM ".BO_DB_PREF."densities WHERE date_end='".date('Y-m-d', $delete_time)."'");
				
				if ($cnt)
					echo "<p>Deleted $cnt entries</p>";
			}
			
			
		}
	}
	
	return;
}

function bo_my_station_autoupdate($force)
{
	if (bo_get_conf('mybo_stations_autoupdate'))
	{
		$last = bo_get_conf('uptime_mybo_stations');
		
		if (time() - $last > 24 * 3600 - 30 || $force)
		{
			bo_set_conf('uptime_mybo_stations', time());
			
			$st_urls = unserialize(bo_get_conf('mybo_stations'));
		
			if (is_array($st_urls) && trim($st_urls[bo_station_id()]))
				bo_my_station_update(trim($st_urls[bo_station_id()]));
			else
				echo '<p>Error: Own URL is empty!</p>';
			
		}
	
	}

}

function bo_my_station_update($url)
{
	echo '<h2>'._BL('Linking with other MyBlitzortung stations').'</h2>';
	echo '<h3>'._BL('Getting Login string').'</h3>';
	
	$login_id = bo_get_login_str();
	
	$ret = false;
	
	if (!$login_id)
	{
		echo '<p>'._BL('Couldn\'t get login id').'.</p>';
	}
	else
	{
		echo '<p>'._BL('String is').': <em>'.$login_id.'</em></p>';
		echo '<h3>'._BL('Requesting data').'</h3>';
		echo '<p>'._BL('Connecting to ').' <em>'.BO_LINK_HOST.'</em></p>';
		
		$request = 'id='.bo_station_id().'&login='.$login_id.'&url='.urlencode($url).'&lat='.((double)BO_LAT).'&lon='.((double)BO_LON.'&rad='.(double)BO_RADIUS);
		$data_url = 'http://'.BO_LINK_HOST.BO_LINK_URL.'?mybo_link&'.$request;
		
		$content = file_get_contents($data_url);
		
		$R = unserialize($content);
		
		if (!$R || !is_array($R))
		{
			echo '<p>'._BL('Error talking to the server. Please try again later.').'</p>';
		}
		else
		{
			switch($R['status'])
			{
				case 'auth_fail':
					echo '<p>'._BL('Authentication failure').'.</p>';
					break;

				case 'request_fail':
					echo '<p>'._BL('Failure in Request URL: ').'<em>'._BC($data_url).'</em></p>';
					break;
				
				case 'ok':
					$urls = $R['urls'];
					
					$info['lats'] = $R['lats'];
					$info['lons'] = $R['lons'];
					$info['rads'] = $R['rads'];

					if (is_array($urls))
					{
						bo_set_conf('mybo_stations', serialize($urls));
						bo_set_conf('mybo_stations_info', serialize($info));
						
						echo '<p>'._BL('Received urls').': '.count($urls).'</p>';
						echo '<p>'._BL('DONE').'!</p>';
						echo '<ul>';
						ksort($urls);
						foreach($urls as $id => $st_url)
						{
							echo '<li>'.$id.': '._BC($st_url).'</url>';
						}
						echo '</ul>';
						
						$ret = true;
					
					}
					else
					{
						echo '<p>'._BL('Cannot read url data').'!</p>';
					}
					
					break;
			}
		}
	}

	return $ret;
}


?>