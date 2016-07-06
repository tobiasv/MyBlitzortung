<?php


// returns array of all stations with index from database column
function bo_stations($index = 'id', $only = '', $only_own = false)
{
	$S = array();

	$sql = '';

	if ($only)
		$sql .= " AND $index='".BoDb::esc($only)."' ";

	if ($only_own)
	{
		$own = bo_stations_own();
		
		if (!empty($own))
			$sql .= " AND id IN (".implode(',', $own)." ) ";
	}
		
	$sql = "SELECT * FROM ".BO_DB_PREF."stations WHERE 1 $sql AND bo_station_id > 0 AND status != 'D'
			AND id < ".intval(BO_DELETED_STATION_MIN_ID);
	$res = BoDb::query($sql);
	while($row = $res->fetch_assoc())
		$S[$row[$index]] = $row;

	return $S;
}

//returns your station_id
function bo_station_id($ret_bo = false)
{
	static $id = -1, $bo_id = -1;
	
	if (!$ret_bo && $id != -1)
		return $id;
	else if ($ret_bo && $bo_id != -1)
		return $bo_id;
	
	if (BO_NO_DEFAULT_STATION === true)
		return -1; // -1 ==> does not interfer with station statistic table (0 = all stations)

	if ($bo_id == -1)
	{
		if (BO_STATION_ID > 0)
			$bo_id = (int)BO_STATION_ID;
		else if (BoData::get('bo_station_id'))
			$bo_id = BoData::get('bo_station_id');
	}	
	
	if ($bo_id > 0)
	{
		if ($ret_bo)
			return $ret_bo;
		
		$sql = "SELECT id FROM ".BO_DB_PREF."stations WHERE bo_station_id='$bo_id'";
		$row = BoDb::query($sql)->fetch_assoc();
		
		if ($row['id'])
			$id = $row['id'];
	}	
	
	return $id;
}

//return stations to show
function bo_stations_own()
{
	static $ids = null;

	if (!defined("BO_SHOW_STATIONS") || !(int)BO_SHOW_STATIONS)
	{
		if (bo_station_id() == -1)
			return array();
		else
			return array(bo_station_id(true) => bo_station_id());
	}

	if (is_array($ids))
		return $ids;
	
	$ids = array();
	$res = BoDb::query("SELECT id, bo_station_id FROM ".BO_DB_PREF."stations WHERE bo_station_id IN (".BO_SHOW_STATIONS.")");
	
	while ($row = $res->fetch_assoc())
		$ids[ $row['bo_station_id'] ] = $row['id'];
	
	if (!count($ids))
		$ids[bo_station_id(true)] = bo_station_id();
	
	return $ids;
}

//returns your station name
function bo_station_city($id=0, $force_name = '')
{
	static $name = array();

	if ($force_name)
		$name[$id] = $force_name;

	if ($name[$id])
		return $name[$id];

	$tmp = bo_station_info($id);
	$name[$id] = $tmp['city'];

	return $name[$id];
}

//return info-array of a station
function bo_station_info($id = 0)
{
	static $info = array();

	if (isset($info[$id]))
		return $info[$id];

	if (BO_CACHE_DATA === true)
	{
		$cache_dir = BO_DIR.'/'.BO_CACHE_DIR.'/data/stations/';
		$cache_file = $cache_dir.$id;
		if (file_exists($cache_file) && time() - filemtime($cache_file) < 7200)
		{
			$info[$id] = unserialize(file_get_contents($cache_file));
			return $info[$id];
		}
	}
	
	if ($id)
	{
		$tmp = bo_stations('id', $id);
		$ret = $tmp[$id];
	}
	else //own station info
	{
		if (BO_NO_DEFAULT_STATION === true)
			return false;

		$tmp = bo_stations('id', bo_station_id());

		if (defined('BO_STATION_NAME') && BO_STATION_NAME)
		{
			$tmp[bo_station_id()]['city'] = BO_STATION_NAME;
			
			if (BO_CONFIG_IS_UTF8 === false)
				$tmp[bo_station_id()]['city'] = utf8_encode($tmp[bo_station_id()]['city']);
		}

		$ret = $tmp[bo_station_id()];
	}

	if (BO_CACHE_DATA === true)
	{
		if (!file_exists($cache_file) || $ret)
		{
			@mkdir($cache_dir, 0777, true);
			file_put_contents($cache_file, serialize($ret));
		}
	}
	
	$info[$id] = $ret;

	return $info[$id];
}

function bo_get_old_status($status)
{
	if (bo_status($status, STATUS_RUNNING))
		return 'A';
	elseif (bo_status($status, STATUS_OFFLINE))
		return 'O';
	elseif (bo_status($status, STATUS_BAD_GPS))
		return 'V';
	else
		return '-';
}

function bo_get_station_list(&$style_class = array(), $only_own = false)
{
	$stations = bo_stations('id', '', $only_own);
	$opts = array();
	foreach($stations as $id => $d)
	{
		$age = time() - strtotime($d['last_time'].' UTC');
		
		if ($d['lat'] == 0.0 && $d['lon'] == 0.0 && $age > 1800)
			continue;

		if ($age > 3600 * 24 * BO_STATION_INACTIVE_DAYS)
			continue;
			
		if (!bo_station_data_valid($d))
			continue;

		if ($d['country'])
			$opts[$id] = _BL($d['country'], false, true).': ';
		else
			$opts[$id] = '- ';
			
		if (!trim(_BC($d['city'])))
			$opts[$id] .= "#".$d['bo_station_id'];
		else
			$opts[$id] .= _BC($d['city']);
		
		$style_class[$id] = 'bo_select_station';
		
		if (bo_status($d['status'], STATUS_RUNNING))
			$style_class[$id] .= '_active';
		elseif (bo_status($d['status'], STATUS_OFFLINE))
			$style_class[$id] .= '_offline';
		elseif (bo_status($d['status'], STATUS_BAD_GPS))
			$style_class[$id] .= '_nogps';
			
		if ((int)$d['controller_pcb'] >= 10)
			$style_class[$id] .= ' bo_station_red';
	}

	asort($opts);

	return $opts;
}


function bo_get_current_stationid()
{
	$station_id = intval($_GET['bo_station_id']);

	if (!$station_id && intval($_COOKIE['bo_select_stationid']))
	{
		$station_id = intval($_COOKIE['bo_select_stationid']);
		bo_try_redirect(array('bo_station_id', 'bo_sid'), '&bo_station_id='.$station_id);
	}
	
	return $station_id;
}


function bo_status($status, $const)
{
	if ($const <= 10)
		$status = intval($status/10);

	return $status == $const;
}


function bo_station_init()
{
	$id = (int)$_GET['bo_sid'];
	
	if ($id)
	{
		$tmp = bo_stations('bo_station_id', $id);
		
		//quick&dirty solution :-/
		if ($tmp[$id]['id'])
			$_GET['bo_station_id'] = $tmp[$id]['id'];
		else
			unset($_GET['bo_station_id']);
	}

}

function bo_station2boid($station_id)
{
	$info = bo_station_info($station_id);
	return $info['bo_station_id'];
}

	
function delete_stations($del = array())
{
	//delete the data
	foreach($del as $id)
		BoData::delete_all("%#".$id."#%");
		
	BoDb::query("DELETE FROM ".BO_DB_PREF."stations_stat    WHERE station_id IN (".implode(',', $del).")", false);
	BoDb::query("DELETE FROM ".BO_DB_PREF."stations_strikes WHERE station_id IN (".implode(',', $del).")", false);
	BoDb::query("DELETE FROM ".BO_DB_PREF."densities        WHERE station_id IN (".implode(',', $del).")", false);
	BoDb::query("DELETE FROM ".BO_DB_PREF."stations         WHERE         id IN (".implode(',', $del).")", false);

	return count($del);
}


function bo_purge_deleted_stations($max_time = null)
{
	bo_echod(" ");
	bo_echod("=== Deleting Stations ===");
	
	
	if ($max_time === null)
	{
		$sql = " bo_station_id=0 ";
	}
	else 
	{
		$max_time -= 3600;
		if ($max_time > time())
			return;
			
		$sql = " changed < '".gmdate('Y-m-d H:i:s')."'";
	}
	
	$del = array();
	$res = BoDb::query("SELECT id FROM ".BO_DB_PREF."stations WHERE $sql");
	while ($row = $res->fetch_assoc())
		$del[] = $row['id'];
	
	if (count($del))
		bo_echod("Deleting Stations ".implode(',', $del)."!");
	else
	{
		bo_echod("No Station to delete!");
		return;
	}
	
	$c = delete_stations($del);
	bo_echod("Deleted data of ".$c." stations!");
	
	return;
}


function bo_station_data_valid(&$d)
{
	if ($d['country'] == '-')
		$d['country'] = '';
			
	if (!$d['country'] && !trim($d['city'])) // || bo_status($d['status'], STATUS_BAD_GPS))
	{
		$d['city'] = 'Station '.$d['bo_station_id'];
	}
			
	if ($d['country'] == '')
	{
		//$d['country'] = ' Unknown';
		//return false;
	}
	
	return true;
}			

function bo_round_station_pos($lat, $lon)
{
	$round = (bo_user_get_level() & BO_PERM_SETTINGS) ? 8 : 2;
	return array(round($lat,$round), round($lon,$round));
}

function bo_stations_json()
{
	$S = array();
	$sql = "SELECT bo_station_id id, lat, lon, city, country, status, alt FROM ".BO_DB_PREF."stations WHERE bo_station_id > 0 AND status > 0 AND lat AND lon AND id < ".intval(BO_DELETED_STATION_MIN_ID);
	$res = BoDb::query($sql);
	while($row = $res->fetch_assoc())
	{
		$S[$row['id']] = bo_round_station_pos($row['lat'], $row['lon']);
		$S[$row['id']]['a'] = $row['alt'];
		$S[$row['id']]['c'] = $row['city'];
		$S[$row['id']]['C'] = _BL($row['country']);
		$S[$row['id']]['s'] = $row['status'];
		
	}

	return json_encode($S);
}

?>