<?php


// returns array of all stations with index from database column
function bo_stations($index = 'id', $only = '', $under_constr = true)
{
	$S = array();

	$sql .= '';

	if ($only)
		$sql .= " AND $index='".BoDb::esc($only)."' ";

	if (!$under_constr)
		$sql .= " AND last_time != '1970-01-01 00:00:00' ";

	$sql = "SELECT * FROM ".BO_DB_PREF."stations WHERE 1 $sql AND id < ".intval(BO_DELETED_STATION_MIN_ID);
	$res = BoDb::query($sql);
	while($row = $res->fetch_assoc())
		$S[$row[$index]] = $row;

	return $S;
}

//returns your station_id
function bo_station_id($ret_bo = false)
{
	static $id = -1;
	
	if ($id != -1)
		return $id;
	
	if (BO_NO_DEFAULT_STATION === true)
		return -1; // -1 ==> does not interfer with station statistic table (0 = all stations)

	if (BO_STATION_ID > 0)
		$bo_id = (int)BO_STATION_ID;
	else if (BoData::get('bo_station_id'))
		$bo_id = BoData::get('bo_station_id');
	
	if ($bo_id)
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

function bo_get_station_list(&$style_class = array())
{
	$stations = bo_stations();
	$opts = array();
	foreach($stations as $id => $d)
	{
		if ($d['lat'] == 0.0 && $d['lon'] == 0.0 && time() - strtotime($d['last_time'].' UTC') > 1800)
			continue;

		if ($d['country'] == '-')
			$d['country'] = '';
			
		if (!$d['country'] && !trim($d['city'])) // || bo_status($d['status'], STATUS_BAD_GPS))
		{
			$d['city'] = 'Station '.$d['bo_station_id'];
		}

			
		if ($d['country'] == '')
			$d['country'] = ' Unknown';
			
		$opts[$id] = _BL($d['country']).': '._BC($d['city']);
		
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
		
		//Redirect, so that URL matches to content (for caching!)
		if (empty($_POST) && !headers_sent())
		{
			$url = bo_insert_url('bo_station_id', $station_id, true);
			header("Location: http://".$_SERVER['HTTP_HOST'].$url);
			exit;
		}
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
		$_GET['bo_station_id'] = $tmp[$id]['id'];
	}

}

function bo_station2boid($station_id)
{
	$info = bo_station_info($station_id);
	return $info['bo_station_id'];
}

?>