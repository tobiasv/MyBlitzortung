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

if (!defined('BO_VER'))
	exit('No BO_VER');

// Login to blitzortung.org an return login-string
function bo_get_login_str()
{
	$file = bo_get_file('http://www.blitzortung.org/Webpages/index.php?lang=de&page=3&username='.BO_USER.'&password='.BO_PASS, $code, 'login');

	if ($file === false)
	{
		echo "<p>ERROR: Couldn't get file to login! Code: $code</p>\n";
		bo_update_error('archivelogin', 'Login to archive failed. '.$code);
		return false;
	}
	
	bo_update_error('archivelogin', true);

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

	$file = bo_get_file('http://www.blitzortung.org/cgi-bin/archiv.cgi?login_string='.$bo_login_id.'&'.$args, $code, 'archive');

	if ($file === false)
	{
		echo "<p>ERROR: Couldn't get file from archive! Code: $code</p>\n";
		bo_update_error('archivedata', 'Download of archive data failed. '.$code);
		return false;
	}
	
	bo_update_error('archivedata', true);

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
		$file = bo_get_archive('lang=de&page=3&subpage_3=1&mode=4');

		if ($file === false)
			return false;

		$loadcount = bo_get_conf('upcount_raw');
		bo_set_conf('upcount_raw', $loadcount+1);


		$old_data = null;
		
		$lines = explode("\n", $file);
		foreach($lines as $l)
		{
			if (preg_match('/([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ([0-9]{2}:[0-9]{2}:[0-9]{2})\.([0-9]+) ([-0-9\.]+) ([-0-9\.]+) ([-0-9\.]+) ([0-9]+) ([0-9]+) ([0-9]+) ([0-9]+) ([A-F0-9]+)/', $l, $r))
			{
				$date = $r[1];
				$time = $r[2];

				$utime = strtotime("$date $time UTC");

				// update strike-data only some seconds *before* the *last download*
				if ($utime < $last - 120)
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
				$channels = $r[7];
				$values = $r[8];
				$bpv = $r[9];
				$ntime = $r[10];
				$data = $r[11];


				/*** signal examinations ***/
				//from hex to binary
				$bdata = '';
				for ($j=0;$j < strlen($data);$j+=2)
				{
					$bdata .= chr(hexdec(substr($data,$j,2)));
				}
		
				$sql = "	time='$date $time',
							time_ns='$time_ns',
							lat='$lat',lon='$lon',
							height='$height',
							strike_id='0',
							data=x'$data'
							";
				
				$sql .= ",".bo_examine_signal($bdata);

				$id = array_search("$date $time.$time_ns", $old_data['time']);
				
				if ($id)
				{
					$sql = "UPDATE ".BO_DB_PREF."raw SET $sql WHERE id='$id'";
					bo_db($sql, false);
					$u++;
				}
				else
				{
					$sql = "INSERT INTO ".BO_DB_PREF."raw SET $sql";
					bo_db($sql, false);
					$i++;
				}
			}
		}

		echo "\n<p>Lines: ".count($lines)." *** New Raw Data: $i *** Updated: $u *** Already read: $a</p>\n";

		if ($channels && $values && $bpv)
		{
			bo_set_conf('raw_channels', $channels);
			bo_set_conf('raw_values', $values);
			bo_set_conf('raw_bitspervalue', $bpv);
			bo_set_conf('raw_ntime', $ntime);
		}
		
		//Longtime
		$count = bo_get_conf('count_raw_signals');
		bo_set_conf('count_raw_signals', $count + $i);
		
		bo_set_conf('uptime_raw', time());
		bo_match_strike2raw();
		$updated = true;
	}
	else
	{
		echo "\n<p>Internal timer says: No update, because the last update was ".(time() - $last)." seconds ago. This is normal and no error message!</p>\n";
		$updated = false;
	}

	return $updated;
}

// Get new strikes from blitzortung.org
function bo_update_strikes($force = false)
{
	global $_BO;
	
	$last = bo_get_conf('uptime_strikes');

	echo "<h3>Strikes</h3>\n";

	if (time() - $last > BO_UP_INTVL_STRIKES * 60 - 30 || $force)
	{
		$stations = bo_stations('user');
		$own_id = bo_station_id();

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
		
		$file = bo_get_file('http://'.BO_USER.':'.BO_PASS.'@blitzortung.tmt.de/Data/Protected/participants.txt', $code, 'strikes');

		if ($file === false)
		{
			bo_update_error('strikedata', 'Download of strike data failed. '.$code);
			echo "<p>ERROR: Couldn't get file for strikes! Code: $code</p>\n";
			return false;
		}
		
		bo_update_error('strikedata', true);

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

		$last_strikes = unserialize(bo_get_conf('last_strikes_stations'));
		
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
				$time_key = floor($utime / (3600*12));
				
				$lat = $r[4];
				$lon = $r[5];
				$cur = $r[6];
				$deviation = $r[7];

				$participants = explode(' ', $r[8]);
				$users = count($participants);
				$part = strpos($r[8], BO_USER) !== false ? 1 : 0;
				$dist = bo_latlon2dist($lat, $lon);
				$bear = bo_latlon2bearing($lat, $lon);
				$lat2 = floor($lat);
				$lon2 = floor($lon/180 * 128);

				$sql = "
							time='$date $time',
							time_ns='$time_ns',
							time_key='$time_key',
							lat='$lat',lon='$lon',
							lat2='$lat2',lon2='$lon2',
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

					$new_strike = true;
					
					//Long-Time Statistics
					$bear_data[$bear_id]++;
					$dist_data[$dist_id]++;
				}
				else
				{
					bo_db("UPDATE ".BO_DB_PREF."strikes SET $sql WHERE id='$id'");
					$u++;
					$new_strike = false;
				}

				//Own Long-Time Statistics
				if ($new_strike && $part)
				{
					$bear_data_own[$bear_id]++;
					$dist_data_own[$dist_id]++;

					$max_dist_own = max($dist, $max_dist_own);
					$min_dist_own = min($dist, $min_dist_own);

					$last_strikes[$own_id] = array($utime, $time_ns, $id);
					
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
						
						$last_strikes[$stId] = array($utime, $time_ns, $id);

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

		bo_set_conf('last_strikes_stations', serialize($last_strikes));
		
		
		//strike count per region
		$_BO['region'][] = array(); //dummy for "all"

		$time_start = gmdate('Y-m-d H:i:s', time() - 120 - BO_GRAPH_STAT_STRIKES_NOW_GROUP_MINUTES*60);
		$time_end = gmdate('Y-m-d H:i:s', time() - 120);
		$sql_template = "SELECT MAX(time) mtime, COUNT(id) cnt 
			FROM ".BO_DB_PREF."strikes s
			WHERE time BETWEEN '$time_start' AND '$time_end' {where} ";

		$last_strikes_region = unserialize(bo_get_conf('last_strikes_region'));
		$rate_strikes_region = unserialize(bo_get_conf('rate_strikes_region'));
			
		foreach ($_BO['region'] as $reg_id => $d)
		{
			$sql = strtr($sql_template,array('{where}' => bo_region2sql($reg_id)));
			$row = bo_db($sql)->fetch_assoc();
			$rate_strikes_region[$reg_id] = $row['cnt'] / BO_GRAPH_STAT_STRIKES_NOW_GROUP_MINUTES;
			
			if ($row['mtime'])
				$last_strikes_region[$reg_id] = strtotime($row['mtime'].' UTC');
		}
	
		bo_set_conf('last_strikes_region', serialize($last_strikes_region));
		bo_set_conf('rate_strikes_region', serialize($rate_strikes_region));

		
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
		bo_set_conf('uptime_strikes', time());		
	}
	else
	{
		echo "\n<p>Internal timer says: No update, because the last update was ".(time() - $last)." seconds ago. This is normal and no error message!</p>\n";
		$updated = false;
	}

	return $updated;
}

// Update strike_id <-> raw_id
function bo_match_strike2raw()
{
	//not really physical values but determined empirical
	$c = 299792458;
	$fuzz = 0.00001; //minimum fuzzy-seconds
	$dist_fuzz = 0.0001; //fuzzy-seconds per meter (seconds)
	$offset_fuzz = 0;
	
	$amp_trigger = (BO_TRIGGER_VOLTAGE / BO_MAX_VOLTAGE) * 256 / 2;

	$sql = "SELECT MAX(time) mtime FROM ".BO_DB_PREF."raw";
	$row = bo_db($sql)->fetch_assoc();
	$maxtime = gmdate('Y-m-d H:i:s', strtotime($row['mtime'].' UTC'));
	$mintime = gmdate('Y-m-d H:i:s', strtotime($row['mtime'].' UTC') - 3600 * 74);
	
	$u = array();
	$n = array();
	$m = array();
	$d = $d2 = array();
	$polarity = array();
	$part = array();
	$own_found = 0;
	$own_strikes = 0;

	$sql = "SELECT id, time, time_ns, distance, bearing, polarity, part
			FROM ".BO_DB_PREF."strikes
			WHERE 1
					AND raw_id IS NULL
					AND time BETWEEN '$mintime' AND '$maxtime'
			ORDER BY part DESC
			LIMIT 10000
			";
	$res = bo_db($sql);
	while($row = $res->fetch_assoc())
	{
		if ($row['part'] > 0)
			$own_strikes++;
		
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
		$nsearch_from = $ntime_raw - $fuzz * (1 + $row['distance'] * $dist_fuzz) + $offset_fuzz;
		$nsearch_to   = $ntime_raw + $fuzz * (1 + $row['distance'] * $dist_fuzz) + $offset_fuzz;

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

		$sql = "SELECT id, time, time_ns, data, amp1, amp2, amp1_max, amp2_max
				FROM ".BO_DB_PREF."raw
				WHERE 	time BETWEEN '$search_date_from' AND '$search_date_to'
						AND strike_id=0
						AND $nsql
				LIMIT 2";
		$res2 = bo_db($sql);
		$num = $res2->num_rows;
		$row2 = $res2->fetch_assoc();

		switch($num)
		{
			case 0:  $n[] = $row['id'];	break; //no raw data found
			default: $m[] = $row['id']; break; //too much lines matched

			case 1:  //exact match

				//echo round($row2['time_ns'] - $nsearch_from).' '.abs(round($row2['time_ns'] - $nsearch_to)).' '.$row2['time_ns'].'<br>';
			
				if ($raw2assigned[$row2['id']] && $row['part'] <= 0) // if signal already assigned and not participated
				{
					$d2[] = $row['id'];
					continue;
				}
				else
					$raw2assigned[$row2['id']] = true;
				
				$u[] = array($row2['id'], $row['id']);
				$polarity[$row['id']] = $row['polarity'];
				$part[$row['id']] = $row['part'] > 0 ? 1 : 0;
				
				//channel examination
				$trigger1_first = abs($row2['amp1']     - 128) >= $amp_trigger;
				$trigger2_first = abs($row2['amp2']     - 128) >= $amp_trigger;
				$trigger1_later = abs($row2['amp1_max'] - 128) >= $amp_trigger;
				$trigger2_later = abs($row2['amp2_max'] - 128) >= $amp_trigger;
				
				$part[$row['id']] += $trigger1_first ? pow(2, 1) : 0;
				$part[$row['id']] += $trigger2_first ? pow(2, 2) : 0;
				$part[$row['id']] += $trigger1_later ? pow(2, 3) : 0;
				$part[$row['id']] += $trigger2_later ? pow(2, 4) : 0;
				
				if (!($part[$row['id']] & 1)) //mark negative --> got strike but not tracked by blitzortung
					$part[$row['id']] = abs($part[$row['id']]) * -1;
				else
					$own_found++;
					
				//experimental polarity checking
				if (BO_EXPERIMENTAL_POLARITY_CHECK === true) 
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
			" *** Own strikes: ".$own_strikes." *** Own unique found: ".$own_found." (Rate ".round($own_strikes ? $own_found / $own_strikes * 100 : 0,1)."%) *** Unique found: ".count($u)." *** Not found: ".count($n)." *** Multiple Signals to Strike: ".count($m)." *** Multiple Strikes to Signal: ".count($d2)."</p>";
			
	//Update matched
	foreach($u as $data)
	{
		$strike_id = $data[1];
		$raw_id = $data[0];
		$sql = "UPDATE ".BO_DB_PREF."strikes 
				SET 
					raw_id='$raw_id', 
					polarity=".$polarity[$strike_id].",
					part=".$part[$strike_id]."
				WHERE id='$strike_id'";
		bo_db($sql);

		bo_db("UPDATE ".BO_DB_PREF."raw SET strike_id='$strike_id' WHERE id='$raw_id'");
	}

	//Update unmatched
	$d = array_merge($n, $m);
	$sql = '';
	foreach($d as $strike_id)
		$sql .= " OR id='$strike_id' ";
	$sql = "UPDATE ".BO_DB_PREF."strikes SET raw_id='0' WHERE 1=0 $sql";
	bo_db($sql);

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
		$u = 0;
		$i = 0;
		$Count = array();
		$signal_count = 0;

		$file = bo_get_file('http://'.BO_USER.':'.BO_PASS.'@blitzortung.tmt.de/Data/Protected/stations.txt', $code, 'stations');

		if ($file === false)
		{
			bo_update_error('stationdata', 'Download of station data failed. '.$code);
			echo "<p>ERROR: Couldn't get file for stations! Code: $code</p>\n";
			return false;
		}


		$lines = explode("\n", $file);
		
				
		//check if sth went wrong
		$all_stations = bo_stations('user');
		if ($lines < count($all_stations) * 0.5)
		{
			bo_update_error('stationcount', 'Station count differs too much: '.count($all_stations).'Database / '.$lines.' stations.txt');
			bo_set_conf('uptime_stations', time());
			
			return;
		}

		$loadcount = bo_get_conf('upcount_stations');
		bo_set_conf('upcount_stations', $loadcount+1);
		
		//reset error counter
		bo_update_error('stationdata', true); 
		bo_update_error('stationcount', true); 
		
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
			$stSignals	= (int)$cols[10];
			
			$stTimeU = strtotime($stTime);
			
			$Count[$stId]['sig'] = $stSignals;
			$Count[$stId]['active'] = $stStatus == 'A'; //GPS is (or was) active
			$Count[$stId]['available'] = $stStatus != '-'; //has sent some data some time ago
			
			//station has been active by user (~ a year ago)
			if (time() - $stTimeU < 3600 * 24 * 366)
				$activebyuser[$stUser] = array('id' => $stId, 'sig' => $stSignals, 'lat' => $stLat, 'lon' => $stLon);
			
			//mark Offline?
			if ($stStatus != '-' && $stSignals == 0 && time() - $stTime > intval(BO_STATION_OFFLINE_HOURS) * 3600)
				$stStatus = 'O';  //Special offline status
			
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

		
		//New stations (by user name)
		$data = unserialize(bo_get_conf('stations_new_date'));
		if (!$data) //first call!
		{
			foreach($activebyuser as $user => $d)
				$data[$user] = false; // mark as not new

			bo_set_conf('stations_new_date', serialize($data));
		}
		else
		{
			$new = false;
			foreach($activebyuser as $user => $d)
			{
				if (!isset($data[$user]) && $d['sig'] && $d['lat'] && $d['lon']) //no old entry but new signals and a fixed
				{
					$data[$user] = time(); // mark as NEW STATION
					$new = true;
					
					echo "<p>Found NEW station: <b>$user</b></p>\n";
				}
			}
		
			if ($new)
			{
				bo_set_conf('stations_new_date', serialize($data));
			}
		}
		
		
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
		$active_avail_stations = 0;
		foreach($Count as $id => $data)
		{
			if ($only_own && $only_own != $id)
				continue;
			
			if ($id && $data['sig'])
			{
				bo_db("INSERT INTO ".BO_DB_PREF."stations_stat
					SET station_id='$id', time='$datetime', signalsh='".intval($data['sig'])."', strikesh='".intval($data['strikes'])."'");
			}

			if ($data['active']) //GPS is/was active
				$active_stations++;

			if ($data['sig']) //Station is sending (really active)
				$active_sig_stations++;

			if ($data['available']) //Station is available (no dummy entry, has sent some data some time ago)
				$active_avail_stations++;
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

		//max available stations (had sent some signales, no matter when)
		$max = bo_get_conf('longtime_count_max_avail_stations');
		bo_set_conf('longtime_count_max_avail_stations', max($max, $active_avail_stations));

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
		if ($Count[$own_id]['sig'])
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
		
		bo_set_conf('uptime_stations', time());
		$updated = true;
	}
	else
	{
		echo "\n<p>Internal timer says: No update, because the last update was ".(time() - $last)." seconds ago. This is normal and no error message!</p>\n";		
		$updated = false;
	}

	return $updated;
}

// Daily and longtime strike counts
function bo_update_daily_stat()
{
	global $_BO;
	
	$ret = false;
	
	//Update strike-count per day
	$ytime = mktime(date('H')-3,0,0,date("n"),date('j')-1);
	$day_id = gmdate('Ymd', $ytime);
	$yesterday_count = bo_get_conf('strikes_'.$day_id);
	
	// Daily Statistics and longtime strike-count
	if (!$yesterday_count)
	{
		echo "\n<h2>Updating daily statistics</h2>\n";
		
		$radius = BO_RADIUS * 1000;
		$yesterday_start = gmdate('Y-m-d H:i:s', strtotime(gmdate('Y-m-d 00:00:00', $ytime)));
		$yesterday_end =   gmdate('Y-m-d H:i:s', strtotime(gmdate('Y-m-d 23:59:59', $ytime)));
		
		$data = unserialize(bo_get_conf('strikes_temp_'.$day_id));

		// Strikes SQL template
		$sql_template = "SELECT COUNT(id) cnt FROM ".BO_DB_PREF."strikes WHERE {where} time BETWEEN '$yesterday_start' AND '$yesterday_end'";
		
		if (!is_array($data) || count($data) < 7)
		{
			/*** Strikes ***/
			//whole strike count
			$row_all = bo_db(strtr($sql_template,array('{where}' => '')))->fetch_assoc();
			//own strike count
			$row_own = bo_db(strtr($sql_template,array('{where}' => 'part > 0 AND ')))->fetch_assoc();
			//whole strike count (in radius)
			$row_all_rad = bo_db(strtr($sql_template,array('{where}' => 'distance < "'.$radius.'" AND ')))->fetch_assoc();
			//own strike count (in radius)
			$row_own_rad = bo_db(strtr($sql_template,array('{where}' => 'part > 0 AND distance < "'.$radius.'" AND ')))->fetch_assoc();

			/*** Signals ***/
			//own exact value
			$signals_exact = bo_db("SELECT COUNT(id) cnt FROM ".BO_DB_PREF."raw WHERE time BETWEEN '$yesterday_start' AND '$yesterday_end'")->fetch_assoc();
			
			//all from station statistics
			$sql = "SELECT SUM(signalsh) cnt, COUNT(DISTINCT time) entries, station_id=".bo_station_id()." own
							FROM ".BO_DB_PREF."stations_stat 
							WHERE time BETWEEN '$yesterday_start' AND '$yesterday_end' AND station_id != 0
							GROUP BY own, HOUR(time)";
			$res = bo_db($sql);
			while ($row = $res->fetch_assoc())
			{
				if (intval($row['entries']))
					$signals[(int)$row['own']] += $row['cnt'] / $row['entries'];
			}
			
			/*** Stations ***/
			$stations = array();
			// available stations
			$row = bo_db("SELECT COUNT(*) cnt FROM ".BO_DB_PREF."stations WHERE status != '-'")->fetch_assoc();
			$stations['available'] = $row->cnt;
			
			// active stations
			$row = bo_db("SELECT COUNT(*) cnt FROM ".BO_DB_PREF."stations WHERE status = 'A'")->fetch_assoc();
			$stations['active'] = $row->cnt;
			
			
			/*** Save the data ***/
			$data = array(	0 => $row_all['cnt'], 
							1 => $row_own['cnt'], 
							2 => $row_all_rad['cnt'], 
							3 => $row_own_rad['cnt'],
							4 => $signals_exact['cnt'],
							5 => round($signals[0]),
							6 => round($signals[1]),
							10 => $stations);
			
			bo_set_conf('strikes_temp_'.$day_id, serialize($data));
		}
		else if (count($data) == 8)
		{
			//strike count per region
			if (isset($_BO['region']) && is_array($_BO['region']))
			{
				foreach ($_BO['region'] as $reg_id => $d)
				{
					//all strikes in a region
					$row = bo_db(strtr($sql_template,array('{where}' => '1 '.bo_region2sql($reg_id).' AND ')))->fetch_assoc();
					$strikes_region[$reg_id]['all'] = $row['cnt'];
					
					//own strikes in a region
					$row = bo_db(strtr($sql_template,array('{where}' => 'part>0 '.bo_region2sql($reg_id).' AND ')))->fetch_assoc();
					$strikes_region[$reg_id]['own'] = $row['cnt'];
				}
			}
			
			$data[7] = $strikes_region;
			bo_set_conf('strikes_temp_'.$day_id, serialize($data));
		}
		else if (count($data) >= 9)
		{
			$channels = bo_get_conf('raw_channels');
			$max_lines = 500;
			$limit = intval($data['raw_limit']);
			
			if (!$limit) //first call
			{
				$amps  = array();
				$freqs = array();
			}
			else
			{
				$amps = $data[8];
				$freqs = $data[9];
			}
			
			$rows = 0;
			$sql ="SELECT data, amp1, amp2, amp1_max, amp2_max, freq1, freq2, freq1_amp, freq2_amp
					FROM ".BO_DB_PREF."raw 
					WHERE time BETWEEN '$yesterday_start' AND '$yesterday_end'
					LIMIT $limit,$max_lines";
			$res = bo_db($sql);
			while ($row = $res->fetch_assoc())
			{
				$d = raw2array($row['data'], true);

				if ($row['amp1_max'] || $row['amp2_max'])
				{
					$freq1_index = round($row['freq1'] / 10);
					$freq2_index = round($row['freq2'] / 10);

					//count of first amplitudes
					$amps['count'][round($row['amp1'] / 10)][0]++;
					$amps['count'][round($row['amp2'] / 10)][1]++;
					
					//count of max amplitudes
					$amps['count_max'][round($row['amp1_max'] / 10)][0]++;
					$amps['count_max'][round($row['amp2_max'] / 10)][1]++;
					
					//count of main frequency
					$freqs['count'][$freq1_index][0]++;
					$freqs['count'][$freq2_index][1]++;

					//amp sum of main frequency
					$freqs['sum_main'][$freq1_index][0] += $row['freq1_amp'];
					$freqs['sum_main'][$freq2_index][1] += $row['freq2_amp'];
				}
				
				//spectrum analyzation
				for ($i=0;$i<$channels;$i++)
				{
					foreach($d['spec_freq'] as $freq_id => $freq)
					{
						$freq_amp = $d['spec'][$i][$freq_id];
						
						//sum of spectrum
						$freqs['spec_sum'][$freq][$i] += round($freq_amp,3);
						
						//count of specific freq amplitudes
						$freqs['spec_cnt'][$freq][$i][round($freq_amp * 2) / 2]++;
					}
				}

				$rows++;
			}

			$data[8] = $amps;
			$data[9] = $freqs;
			
			if ($rows < $max_lines) //Done!
			{
				unset($data['raw_limit']);
				
				//delete old temp var
				bo_set_conf('strikes_temp_'.$day_id, serialize(array()));
				
				//finally, save it
				bo_set_conf('strikes_'.$day_id, serialize($data));
			}
			else
			{
				$data['raw_limit'] = $limit + $max_lines;
				
				//delete old temp var
				bo_set_conf('strikes_temp_'.$day_id, serialize($data));
			}
			
		}

		echo "\n<p>Datasets: ".count($data)."</p>\n";

		
		/*** Longtime statistics ***/
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

		$D = unserialize(bo_get_conf('longtime_max_signals_day_own'));
		if ($D[0] < $signals_exact['cnt'])
			bo_set_conf('longtime_max_signals_day_own', serialize(array($signals_exact['cnt'], $yesterday)));
			
		$ret = true;
	}

	//Update strike-count per month
	$ytime = mktime(date('H')-1,0,0,date("n")-1,date('j'));
	$month_id = gmdate('Ym', $ytime);
	$last_month_count = bo_get_conf('strikes_month_'.$month_id);
	
	// Monthly Statistics 
	if (!$last_month_count)
	{
		$data = array();
		$data['daycount'] = 0;
		
		$sql = "SELECT data
				FROM ".BO_DB_PREF."conf
				WHERE name LIKE 'strikes_".$month_id."%'";
		$res = bo_db($sql);
		while($row = $res->fetch_assoc())
		{
			$d = unserialize($row['data']);
			
			foreach($d as $id => $cnt)
				$data[$id] += $cnt;
			
			$data['daycount']++;
		}

		bo_set_conf('strikes_month_'.$month_id, serialize($data));
		
		$ret = true;
	}
	
	return $ret;
}

function bo_update_shutdown()
{
	bo_set_conf('is_updating', 0);
}

function bo_update_all($force = false)
{
	$overall_timeout = intval(BO_UP_MAX_TIME);
	if (!$overall_timeout)
		$overall_timeout = 55;
		
	
	session_write_close();
	ignore_user_abort(true);
	
	echo "<h2>Getting lightning data from blitzortung.org</h2>\n";

	$start_time = time();
	$debug = defined('BO_DEBUG') && BO_DEBUG;
	$is_updating = (int)bo_get_conf('is_updating');

	//Check if sth. went wrong on the last update (if older continue)
	if ($is_updating && time() - $is_updating < $overall_timeout + 120 && !($force && $debug))
	{
		echo "\n<p>ERROR: Another update is running *** Begin: ".date('Y-m-d H:i:s', $is_updating)." *** Now: ".date('Y-m-d H:i:s')."</p>\n";
		return;
	}

	bo_set_conf('is_updating', time());
	register_shutdown_function('bo_update_shutdown');
	
	//timeouts
	$max_time = intval(ini_get('max_execution_time')) - 10;
	if ($max_time < 20 || $debug) //allow infinite exec time in debug mode
	{
		$max_time = 50;
	}
	
	
	@set_time_limit($max_time+10);
	
	//recheck the new timeout
	$exec_timeout = intval(ini_get('max_execution_time'));
	
	$max_time = $exec_timeout - 10;
	if ($max_time < 5)
		$max_time = 20;
	else if ($max_time > $overall_timeout)
		$max_time = $overall_timeout;
	
	if (!$force)
	{
		echo '<p>Information: PHP Execution timeout is '.$exec_timeout.'s ';
		echo $exec_timeout < 15 ? ' - Not good :( ' : ' --> Fine :)  ';
		echo '- Setting MyBlitzortung timeout to: '.$max_time.'s</p>';
		flush();
	}
	
	ini_set('default_socket_timeout', 10);
	
	// to avoid to much connections from different stations to blitzortung.org at the same time
	if (!$force && $max_time > 20)
	{
		$max_sleep = BO_UP_MAX_SLEEP;
		$sleep = rand(0,$max_sleep);
		echo '<p>Waiting '.$sleep.' seconds, to avoid too hight load on Blitzortung servers ...</p>';
		flush();
		sleep($sleep); 
	}
	

	if (!bo_get_conf('first_update_time'))
		bo_set_conf('first_update_time', time());

		
	//check if we should do an async update
	if ( !(BO_UP_INTVL_STRIKES <= BO_UP_INTVL_STATIONS && BO_UP_INTVL_STATIONS <= BO_UP_INTVL_RAW) )
	{
		if (!$force)
			echo '<p>Info: Asynchronous update. No problem, but untestet. To avoid set strike timer < station timer < signal timer (or equal).</p>';
		
		$async = true;
	}
	else
		$async = false;
	
	/*** Get the data! ***/
	flush();
	$t = time();

	$strikes_imported = bo_update_strikes($force);
	
	//Update signals/stations only after strikes where imported
	if ($strikes_imported !== false || $async || $force)
	{	
		flush();
		$stations_imported = bo_update_stations($force);
		
		flush();
		$signals_imported = bo_update_raw_signals($force);
		
		bo_update_daily_stat();
		
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
		bo_set_conf('is_updating', 0);
		echo '<p>TIMEOUT! We will continue the next time.</p>';
		return;
	}

	/*** Update strike tracks ***/
	if ($strikes_imported)
	{
		flush();
		bo_update_tracks($force, $max_time + $start_time - time());
	}
	/*** Update MyBlitzortung stations ***/
	else if (!$strikes_imported && !$stations_imported && !$signals_imported)
	{
		flush();
		bo_my_station_autoupdate($force);
	}

	if (time() - $start_time > $max_time)
	{
		bo_set_conf('is_updating', 0);
		echo '<p>TIMEOUT! We will continue the next time.</p>';
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
		echo '<p>TIMEOUT! We will continue the next time.</p>';
		bo_set_conf('is_updating', 0);
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
	$ranges[] = array(gmmktime(0,0,0,gmdate('m')-1,1,gmdate('Y')), gmmktime(0,0,0,date('m'),0,gmdate('Y')), 0);
	
	//last year 
	if (gmdate('Y', $min_strike_time) <= gmdate('Y') - 1)
		$ranges[] = array(strtotime( (gmdate('Y')-1).'-01-01'), strtotime( (gmdate('Y')-1).'-12-31'), -1 ); 

	//current month and year
	if (defined('BO_CALC_DENSITIES_CURRENT') && BO_CALC_DENSITIES_CURRENT)
	{
		$end_time = gmmktime(0,0,0,gmdate('m'),gmdate('d'),gmdate('Y'))-1;
		
		if (gmdate('t', $end_time) != gmdate('d', $end_time))
		{
			$ranges[] = array(gmmktime(0,0,0,gmdate('m'),1,gmdate('Y')), $end_time, -2 ); //month
			$ranges[] = array(gmmktime(0,0,0,1,1,gmdate('Y')),         $end_time, -3 ); //year
		}
		
		//delete old data, if it's not the end day of the month
		$delete_time = gmmktime(0,0,0,gmdate('m'),gmdate('d')-3,gmdate('Y'));
		if (gmdate('t', $delete_time) == gmdate('d', $delete_time))
			$delete_time = 0;
	}

	//insert the ranges
	foreach($ranges as $r)
	{
		if ($max_strike_time < $date_end)
			continue;
		
		$date_start = gmdate('Y-m-d', $r[0]);
		$date_end   = gmdate('Y-m-d', $r[1]);
		$status  = intval($r[2]);
		
		if ($r[0] >= $r[1])
			continue;
		
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
	$calc_count = 0;
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
		
			$calc_count++;
		
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
					
								$val = sprintf("%0".(2*$bps)."s", dechex($val));
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

				$time_min = strtotime($b['date_start'].' 00:00:00 UTC');
				$time_max = strtotime($b['date_end'].' 23:59:59 UTC');
		
				echo " *** Start: $lat&deg; / $lon&deg; *** End: $lat_end&deg; / $lon_end&deg; *** <em>... Calculating ...</em>";
				flush();
				
				$sql_where = '';
				$sql_join  = '';
				
				if (intval($b['station_id']) && $b['station_id'] == bo_station_id())
				{
					$sql_where_station = " AND s.part > 0 ";
				}
				elseif ($b['station_id'])
				{
					$sql_join  = ",".BO_DB_PREF."stations_strikes ss ";
					$sql_where_station = " AND ss.strike_id=s.id AND ss.station_id='".intval($b['station_id'])."'";
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
					
					//the where clause
					$sql_where = bo_strikes_sqlkey($index_sql, min($times_min), max($times_max), $lat, $lat+$dlat, $lon, $lon_end);

					
					// line by line
					$sql = "SELECT COUNT(*) cnt, FLOOR((s.lon+".(-$lon).")/(".$dlon.")) lon_id
							FROM ".BO_DB_PREF."strikes s $index_sql
								$sql_join
							WHERE 1
								AND s.time BETWEEN '".$b['date_start']." 00:00:00' AND '".$b['date_end']." 23:59:59'
								$sql_where_station
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
						$DATA .= sprintf("%0".(2*$bps)."s", dechex($row['cnt']));
						
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
			
			//for displaying antenna direction in ratio map
			if ($b['station_id'] == bo_station_id())
			{
				$ant[0] = bo_get_conf('antenna1_bearing');
				$ant[1] = bo_get_conf('antenna2_bearing');
	
				if ($ant[0] !== '' && $ant[0] !== null && $ant[1] !== '' && $ant[1] !== null)
				{
					$info['antennas']['bearing'] = $ant;
					$info['antennas']['bearing_elec'][0] = bo_get_conf('antenna1_bearing_elec');
					$info['antennas']['bearing_elec'][1] = bo_get_conf('antenna2_bearing_elec');
				}
			}

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
	
	if (!$calc_count)
		echo '<p>Nothing to do</p>';
	
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

function bo_my_station_update($url, $force_bo_login = false)
{
	echo '<h2>'._BL('Linking with other MyBlitzortung stations').'</h2>';
	
	
	if (!$force_bo_login)
	{
		$authid = bo_get_conf('mybo_authid');
		
		if ($authid)
		{
			echo '<h3>'._BL('Using auth ID').'</h3>';
			echo '<p>'.$authid.'</p>';
		}
	}
	
	if (!$authid)
	{
		echo '<h3>'._BL('Getting Login string').'</h3>';
		$login_id = bo_get_login_str();
		echo '<p>'._BL('Login string is').': <em>'.$login_id.'</em></p>';
	}
	
	$ret = false;
	
	if (!$authid && !$login_id)
	{
		echo '<p>'._BL('Couldnt get login id').'.</p>';
	}
	else
	{
		echo '<h3>'._BL('Requesting data').'</h3>';
		echo '<p>'._BL('Connecting to ').' <em>'.BO_LINK_HOST.'</em></p>';
		
		$request = 'id='.bo_station_id().'&login='.$login_id.'&authid='.$authid.'&url='.urlencode($url).'&lat='.((double)BO_LAT).'&lon='.((double)BO_LON.'&rad='.(double)BO_RADIUS.'&zoom='.(double)BO_MAX_ZOOM_LIMIT);
		$data_url = 'http://'.BO_LINK_HOST.BO_LINK_URL.'?mybo_link&'.$request;
		
		$content = bo_get_file($data_url, $error, 'mybo_stations');
		
		$R = unserialize($content);
		
		if (!$R || !is_array($R))
		{
			echo '<p>'._BL('Error talking to the server. Please try again later.').($error ? ' Code: '.$error : '').'</p>';
		}
		else
		{
			switch($R['status'])
			{
				case 'auth_fail':
				
					echo '<p>'._BL('Authentication failure').'.</p>';
					
					if ($authid && $force_bo_login === false)
					{
						echo '<p>Fallback to Blitzortung login!</p>';
						return bo_my_station_update($url,true);
					}
					
					break;

				case 'content_error':
					echo '<p>'._BL('Cannot access your website').'!</p>';
					break;
					
				case 'request_fail':
					echo '<p>'._BL('Failure in Request URL: ').'<em>'._BC($data_url).'</em></p>';
					break;

				case 'rad_limit': case 'zoom_limit':
					echo '<p>'._BL('You exceeded max. radius or zoom limit. Please change your settings in config.php!').'</p>';
					break;
				
				case 'ok':
					$urls = $R['urls'];
					
					$info['lats'] = $R['lats'];
					$info['lons'] = $R['lons'];
					$info['rads'] = $R['rads'];

					bo_set_conf('mybo_authid', $R['authid']);
					
					if (is_array($urls))
					{
						bo_set_conf('mybo_stations', serialize($urls));
						bo_set_conf('mybo_stations_info', serialize($info));
						
						if (!$authid)
							echo '<p>'._BL('Auth ID is').': '.$R['authid'].'</p>';
						
						echo '<p>'._BL('Received urls').': '.count($urls).'</p>';
						echo '<ul>';
						ksort($urls);
						foreach($urls as $id => $st_url)
						{
							echo '<li>'.$id.': '._BC($st_url).'</url>';
						}
						echo '</ul>';
						echo '<p>'._BL('DONE').'!</p>';
						
						
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
	
	//logout
	if ($login_id)
	{
		echo '<h3>'._BL('Logging out from Blitzortung.org').'</h3>';
		$file = bo_get_file('http://www.blitzortung.org/Webpages/index.php?page=3&login_string='.$login.'&logout=1', $code, 'logout');
		
		if (!$file)
			echo "<p>ERROR: Couldn't get file! Code: $code</p>\n";
	}

	return $ret;
}

function bo_update_tracks($force = false, $max_time = 0)
{
	/**
	/*  Warning: "Quick and dirty" calculation of lightning/cell tracks
	/*  This calculation is currently a bit confusing and I'm sure there
	/*  are better (faster and more accurate) ways to calculate that.
	/*
	/*  WARNING! WARNING! WARNING! WARNING! WARNING! 
	/*  The calculation time raises exponentially with scanning tim
	/*  and strike count!
	 */
	 
	$scantime = intval(BO_TRACKS_SCANTIME);
	$divisor = intval(BO_TRACKS_DIVISOR);
	$cellsize  = intval(BO_TRACKS_RADIUS_SEARCH_STRIKES);
	$cellsize2 = intval(BO_TRACKS_RADIUS_SEARCH_NGBR_CELLS);
	$cellsize3 = intval(BO_TRACKS_RADIUS_SEARCH_OLD_CELLS);
	$MinStrikeCount = 3;
	
	if (!$scantime || !$divisor)
		return;

	$start_time = time();
		
	$last = bo_get_conf('uptime_tracks');

	echo "<h3>Tracks</h3>\n";

	if (time() - $last > BO_UP_INTVL_TRACKS * 60 - 30 || $force)
	{
		bo_set_conf('uptime_tracks', time());
		$time = time();
		
		//Debug
		//$time = strtotime('2011-06-16 17:00:00 UTC');
		
		//divide time range 
		for ($i=0;$i<$divisor;$i++)
			$stime[] = $time + $scantime * 60 * ($i / $divisor - 1);

		$stime[] = $time;

		for ($i = 0; $i < count($stime)-1; $i++)
		{
			$cells[$i] = array();

			$date_start = gmdate('Y-m-d H:i:s', $stime[$i]);
			$date_end   = gmdate('Y-m-d H:i:s', $stime[$i+1]);

			$cells_time[$i] = array('start' => $stime[$i], 'end' => $stime[$i+1]);
			
			$sql = "SELECT id, time, lat, lon
					FROM ".BO_DB_PREF."strikes
					WHERE time BETWEEN '$date_start' AND '$date_end'
					";
			$res = bo_db($sql);
			while($row = $res->fetch_assoc())
			{
				//End this ugly calculation to prevent running in php-timeout
				if (time() - $start_time > $max_time - 3)
					break;

				$time_strike = strtotime($row['time']." UTC");
				
				//for weighting the strike time
				$time_weight = ($time_strike - $stime[$i]) / ($stime[$i+1] - $stime[$i]);
				
				$found = false;
				
				//search for cells
				foreach($cells[$i] as $cellid => $cell)
				{
					if (!is_array($cell['strikes']))
						break;
						
					//search for point in cell close to strike
					foreach($cell['strikes'] as $points)
					{
						$dist = bo_latlon2dist($points[0], $points[1], $row['lat'], $row['lon']);
						
						if ($dist < $cellsize * 1000)
						{
							//add strike to cell
							$cells[$i][$cellid]['strikes'][] = array($row['lat'], $row['lon'], $time_weight);
							
							$found = true;
							break;
						}
					}
				}
				
				//create new cell
				if (!$found)
				{
					$cells[$i][] = array('strikes' => array(array($row['lat'], $row['lon'], $time_weight)));
				}
			}
			
			//consider only cells with enough strikes
			$realcells[$i] = array();
			foreach($cells[$i] as $cell)
			{
				if (count($cell['strikes']) > $MinStrikeCount)
				{
					$lat = 0;
					$lon = 0;
					$count = 0;
					
					//calculate the "gravity center" of each cell
					//mathematically not correct, but a good approximation for small areas
					foreach($cell['strikes'] as $data)
					{
						$wfactor = 0.5;
						$weight = $data[2] * $wfactor + $wfactor; //newer strikes have more "weight" than older ones
						
						$lat += $data[0] * $weight;
						$lon += $data[1] * $weight;
						$count += $weight;
					}
					
					$lat /= $count;
					$lon /= $count;
					
					$lat = round($lat, 3);
					$lon = round($lon, 3);
					
					//dimensions - ToDo: (cell "borders") missing
					$realcells[$i][] = array( 'lat'     => $lat, 'lon' => $lon, 
					                          'count'   => count($cell['strikes']), 
											  'strikes' => $cell['strikes']);
				}
			}
			
			$cells[$i] = $realcells[$i];

						
			//combine splitted cells
			$realcells[$i] = array();
			ksort($cells[$i]);
			foreach($cells[$i] as $cellid1=>$cell)
			{
				$center1 = $cell;
				for ($cellid2=$cellid1+1; $cellid2<count($cells[$i]); $cellid2++)
				{
					$center2 = $cells[$i][$cellid2];
					$dist = bo_latlon2dist($center1['lat'], $center1['lon'], $center2['lat'], $center2['lon']);
					if ($dist < $cellsize2 * 1000)
					{
						$cells[$i][$cellid1]['sameid'][] = $cellid2;
						$cells[$i][$cellid2]['sameid'][] = $cellid1;
					}
				}
			}

			$realcells[$i] = array();
			$done = array();
			krsort($cells[$i]);
			foreach($cells[$i] as $cellid=>$cell)
			{
				if (isset($done[$cellid]))
					continue;

				$count = $cell['count'];
				$lat   = $cell['lat'] * $count;
				$lon   = $cell['lon'] * $count;
				
				if (isset($cell['sameid']))
				{
					foreach($cell['sameid'] as $cellid2)
					{
						if (isset($done[$cellid2]))
							continue;
							
						$cell2 = $cells[$i][$cellid2];
						$lat   += $cell2['lat'] * $cell2['count'];
						$lon   += $cell2['lon'] * $cell2['count'];
						$count += $cell2['count'];
						
						$done[$cellid2] = 1;
					}
				}
				
				$realcells[$i][] = array(
											'lat'   => $lat / $count, 
											'lon'   => $lon / $count, 
											'count' => $count);
			}
			
			$cells[$i] = $realcells[$i];
			
			//connect the cells
			if ($i > 0)
			{
				foreach($cells[$i] as $cellid1 => $newcell)
				{
				
					foreach($cells[$i-1] as $cellid2 => $oldcell)
					{
						$dist = bo_latlon2dist($oldcell['lat'], $oldcell['lon'], $newcell['lat'], $newcell['lon']);
						
						if ($dist < $cellsize3 * 1000)
						{
							// found the old cell --> assing movement values
							
							// Bearing
							$bear = bo_latlon2bearing($newcell['lat'], $newcell['lon'], $oldcell['lat'], $oldcell['lon']);
							$cells[$i][$cellid1]['bear'][] = $bear;
							
							// "Speed"
							$cells[$i][$cellid1]['dist'][] = $dist;
							
							// Old cell connection
							$cells[$i][$cellid1]['old'][] = $cellid2;
						
						}

					
					}
				
				}
			}
		}

		$data = array('time' => $time, 'cells_time' => $cells_time, 'cells' => $cells);
		
		echo '<pre>';
		print_r($data);
		echo '</pre>';
		
		bo_set_conf('strike_cells', gzdeflate(serialize($data)));
	}
}


	
function bo_update_error($type, $extra = null)
{
	// reset
	if ($extra === true) 
	{
		bo_set_conf('uperror_'.$type, serialize(array()));
		return;
	}
	
	//Read
	$data = unserialize(bo_get_conf('uperror_'.$type));

	//Update
	if (!$data['first'])
		$data['first'] = time();
		
	$data['last'] = time();
	$data['count']++;
	
	//Send?
	if ($data['count'] > BO_UP_ERR_MIN_COUNT && intval(BO_UP_ERR_MIN_MINUTES)
		&& time() - $data['first']     > BO_UP_ERR_MIN_MINUTES * 60
		&& time() - $data['last_send'] > BO_UP_ERR_SEND_INTERVAL * 60)
	{
		$data['last_send'] = time();
		
		$mail = bo_user_get_mail(1);
		
		if ($mail)
		{
			$text = $extra;
			$text .= "\n\nLast error: ".date('Y-m-d H:i:s', $data['last']);
			$text .= "\nFirst error: ".date('Y-m-d H:i:s', $data['first']);
			$text .= "\nError count: ".$data['count'];
			
			mail($mail, 
		     _BL('MyBlitzortung_notags').' - '._BL('Error').': '.$type, 
			 $text);
			
			echo '<p>Sent E-Mail to '.$mail.'</p>';
		}
	}
	
	//Write
	bo_set_conf('uperror_'.$type, serialize($data));
}	

?>