<?php

function bo_times2sql(&$time_min = 0, &$time_max = 0, $table='s', &$auto_reduce=false)
{

	$time_min = intval($time_min);
	$time_max = intval($time_max);

	if (!$time_min && !$time_max)
	{
		return " 1 ";
	}
	elseif (!$time_max)
	{
		$row = BoDb::query("SELECT MAX(time) time FROM ".BO_DB_PREF."strikes")->fetch_assoc();
		$time_max = strtotime($row['time'].' UTC');
	}

	//round?
	//rounded time can be used by calling function due to call by reference
	$time_min = floor($time_min/BO_DB_TIME_ROUND_SECONDS) * BO_DB_TIME_ROUND_SECONDS;
	$time_max = ceil($time_max/BO_DB_TIME_ROUND_SECONDS) * BO_DB_TIME_ROUND_SECONDS;
	
	//date range
	$date_min = gmdate('Y-m-d H:i:s', $time_min);
	$date_max = gmdate('Y-m-d H:i:s', $time_max);
	$sql = " ( $table.time BETWEEN '$date_min' AND '$date_max' ) ";
	
	//find max and min strike id
	//useful for joins (i.e. station_strikes, especially when partitioned)
	//FOREIGN KEY would be better but isn't always avalable
	if (BO_DB_TIME2ID === true && $time_min && $time_max && $time_min > strtotime('2010-01-01'))
	{
		if (BO_DB_TIME_CACHE === true)
		{
			$tmpfile = BO_DIR.BO_CACHE_DIR.'/strcnt/'.$time_min.'_'.$time_max;

			$maxloops = 20;
			while (!file_exists(touch($tmpfile.".lock")) && $maxloops--)
			{
				usleep(500000);
				clearstatcache();
			}
			
			clearstatcache();
			if (!file_exists($tmpfile) || time() - filemtime($tmpfile) > BO_DB_TIME_ROUND_SECONDS)
			{
				touch($tmpfile.".lock");
				$row = BoDb::query("SELECT MAX(id) maxid, MIN(id) minid, COUNT(*) cnt FROM ".BO_DB_PREF."strikes s WHERE $sql")->fetch_assoc();
				@mkdir(BO_DIR.BO_CACHE_DIR.'/strcnt/');
				file_put_contents($tmpfile, serialize($row));
				unlink($tmpfile.".lock");
			}
			else
			{
				$row = unserialize(file_get_contents($tmpfile, serialize($row)));
			}
		}
		else
		{
			$row = BoDb::query("SELECT MAX(id) maxid, MIN(id) minid, COUNT(*) cnt FROM ".BO_DB_PREF."strikes s WHERE $sql")->fetch_assoc();
		}
	
		if (isset($row['maxid']) && isset($row['minid']))
		{
			$sql .= " AND ($table.id BETWEEN ".$row['minid']." AND ".$row['maxid']." ) ";
		}
		
		if ($auto_reduce > 0 && $row['cnt'] > $auto_reduce)
		{
			$div = ceil($row['cnt']/$auto_reduce);
			$sql .= " AND (MOD($table.id,".$div.")=0) ";
			$auto_reduce = array('divisor' => $div, 'count' => $row['cnt']);
		}
	}

	
	//Extra keys for faster search
	//$keys_enabled   = (BO_DB_EXTRA_KEYS === true);
	$key_bytes_time = $keys_enabled ? intval(BO_DB_EXTRA_KEYS_TIME_BYTES)   : 0;
	$key_bytes_time = 0 < $key_bytes_time   && $key_bytes_time   <= 4 ? $key_bytes_time   : 0;

	if ($key_bytes_time)
	{
		$key_time_vals   = pow(2, 8 * $key_bytes_time);
		$key_time_start  = strtotime(BO_DB_EXTRA_KEYS_TIME_START);
		$key_time_div    = (double)BO_DB_EXTRA_KEYS_TIME_DIV_MINUTES;

		if ( ($time_max-$time_min)/60/$key_time_div)
		{
			$time_min_x = fmod(floor(($time_min-$key_time_start)/60/$key_time_div),$key_time_vals);
			$time_max_x = fmod(ceil (($time_max-$key_time_start)/60/$key_time_div),$key_time_vals)+1;

			if ($time_min_x<=$time_max_x)
				$sql .= " AND ( $table.time_x BETWEEN '$time_min_x' AND '$time_max_x' ) ";
			else
				$sql .= " AND ( $table.time_x <= '$time_min_x' OR $table.time_x >= '$time_max_x' ) ";
		}

	}
	

	return $sql;
}

function bo_strikes_sqlkey(&$index_sql, &$time_min, &$time_max, $lat1=false, $lat2=false, $lon1=false, $lon2=false, &$auto_reduce=false)
{
	//$index_sql = " IGNORE INDEX (...) ";
	
	$sql  = " (";
	$sql .= bo_latlon2sql($lat1, $lat2, $lon1, $lon2);
	$sql .= " AND ";
	$sql .= bo_times2sql($time_min, $time_max, 's', $auto_reduce);
	$sql .= ") ";

	return $sql;
}

function bo_region2sql($region, $station_id = 0)
{
	global $_BO;

	$region = trim($region);
	
	//Exclude?
	if (substr($region,0,1) == '-')
	{
		$exclude = true;
		$region = substr($region,1);
	}
	
	//Distance
	if (substr($region,0,4) == 'dist')
	{
		$dist = intval(substr($region, 4)) * 1000;
		
		if (!$station_id)
			$station_id = bo_station_id();

		$data = bo_station_info($station_id);
		$lat = $data['lat'];
		$lon = $data['lon'];
		
		if ($station_id == bo_station_id() && $station_id > 0)
		{
			$sql .= " s.distance <= '$dist' ";
		
		}
		else
		{
			$sql .= bo_sql_latlon2dist($lat, $lon, 's.lat', 's.lon')." <= '$dist' ";
		}
	}
	else
	{
	
		if (!isset($_BO['region'][$region]['rect_add']))
			return '';

		$reg = $_BO['region'][$region]['rect_add'];
		$sql .= ' ( 0 ';

		while ($r = @each($reg))
		{
			$lat1 = $r[1];
			list(,$lon1) = @each($reg);
			list(,$lat2) = @each($reg);
			list(,$lon2) = @each($reg);

			$sql .= " OR ".bo_latlon2sql($lat2, $lat1, $lon2, $lon1);
		}

		$sql .= ' ) ';

		if (isset($_BO['region'][$region]['rect_rem']))
		{
			$reg = $_BO['region'][$region]['rect_rem'];
			$sql .= ' AND NOT ( 0 ';

			while ($r = @each($reg))
			{
				$lat1 = $r[1];
				list(,$lon1) = @each($reg);
				list(,$lat2) = @each($reg);
				list(,$lon2) = @each($reg);

				$sql .= " OR ".bo_latlon2sql($lat2, $lat1, $lon2, $lon1);

			}

			$sql .= ' ) ';
		}
	}
	
	if ($sql)
		$sql = ($exclude ? ' AND NOT ' : ' AND ').$sql;
	
	return $sql;
}


function bo_latlon2sql($lat1=false, $lat2=false, $lon1=false, $lon2=false)
{
	if ($lat1 === false || $lat2 === false || $lon1 === false || $lon2 === false)
		return " 1 ";

	$sql = " (s.lat BETWEEN '".round($lat1, 6)."' AND '".round($lat2, 6)."' AND s.lon BETWEEN '".round($lon1, 6)."' AND '".round($lon2, 6)."') ";


	//Extra keys for faster search (esp. tiles ans strike search)
	//$keys_enabled = (BO_DB_EXTRA_KEYS === true);
	$key_bytes_latlon = $keys_enabled ? intval(BO_DB_EXTRA_KEYS_LATLON_BYTES) : 0;
	$key_bytes_latlon = 0 < $key_bytes_latlon && $key_bytes_latlon <= 4 ? $key_bytes_latlon : 0;

	if ($key_bytes_latlon)
	{
		$key_latlon_vals = pow(2, 8 * $key_bytes_latlon);
		$key_lat_div     = (double)BO_DB_EXTRA_KEYS_LAT_DIV;
		$key_lon_div     = (double)BO_DB_EXTRA_KEYS_LON_DIV;

		//only use key when it makes sense
		if (abs($lat1-$lat2) < $key_lat_div)
		{
			$lat1_x = floor(fmod(90+$lat1,$key_lat_div)/$key_lat_div*$key_latlon_vals);
			$lat2_x = ceil (fmod(90+$lat2,$key_lat_div)/$key_lat_div*$key_latlon_vals);

			if ($lat1_x <= $lat2_x)
				$sql .= " AND (s.lat_x BETWEEN '$lat1_x' AND '$lat2_x')";
			else
				$sql .= " AND (s.lat_x <= '$lat2_x' OR '$lat1_x' <= s.lat_x)";
		}

		//only use key when it makes sense
		if (abs($lon1-$lon2) < $key_lon_div)
		{
			$lon1_x = floor(fmod(180+$lon1,$key_lon_div)/$key_lon_div*$key_latlon_vals);
			$lon2_x = ceil (fmod(180+$lon2,$key_lon_div)/$key_lon_div*$key_latlon_vals);

			if ($lon1_x <= $lon2_x)
				$sql .= " AND (s.lon_x BETWEEN '$lon1_x' AND '$lon2_x')";
			else
				$sql .= " AND (s.lon_x <= '$lon2_x' OR '$lon1_x' <= s.lon_x)";

		}
	}
	
	//Partition pruning by lon-hash
	if (BO_DB_PARTITIONING === true && BO_DB_PARTITIONING_USE == true)
	{
		if ($lon1 < $lon2)
		{
			$lon_start = $lon1;
			$lon_end   = $lon2;
		}
		else 
		{
			$lon_start = $lon1;
			$lon_end   = ($lon2+360);
		}
		$ppos = array();
		
		for ($lon = $lon_start; $lon <= $lon_end + BO_DB_PARTITION_LON_DIVISOR; $lon += BO_DB_PARTITION_LON_DIVISOR)
		{
			for ($lat = $lat1; $lat <= $lat2 + BO_DB_PARTITION_LAT_DIVISOR; $lat += BO_DB_PARTITION_LAT_DIVISOR)
			{
				$id = ( floor( ($lat+90) / BO_DB_PARTITION_LAT_DIVISOR) * (360 / BO_DB_PARTITION_LON_DIVISOR) + floor( ($lon+180)%360 / BO_DB_PARTITION_LON_DIVISOR) ) % 256;
				$ppos[$id] = $id;
			}
		}
		
		ksort($ppos); //just looks better
		if (count($ppos))
			$sql .= "\n AND ppos IN (".implode(',', $ppos).")\n";
	}

	return $sql;
}

function bo_sql_latlon2dist($lat1, $lon1, $lat_name='lat', $lon_name='lon')
{
	if (!trim($lat_name) || !trim($lon_name))
		return ' 0 ';
	
	if ($lat1 == 0.0 && $lon1 == 0.0)
		return ' 0 ';
	
	$sql = "ACOS(SIN(RADIANS($lat1)) * SIN(RADIANS($lat_name)) + COS(RADIANS($lat1)) * COS(RADIANS($lat_name)) * COS(RADIANS($lon1 - $lon_name))) * 6371000";

	return " ($sql) ";
}

function bo_sql_lat2tiley($name, $zoom, $cache = true)
{
	if (BO_DB_STRIKES_MERCATOR !== true || !$cache)
	{
		$scale = (1 << $zoom) * 256;

		if (is_numeric($name))
		{
			$y = round(abs(log(tan(M_PI/4+deg2rad($name)/2))/M_PI/2 - 0.5) * $scale);
		}
		else
		{
			$lat_mercator = "(LOG(TAN(PI()/4+RADIANS($name)/2))/PI()/2) ";
			$y = "ROUND(ABS($lat_mercator-0.5)*$scale)";
		}
	}
	else
	{
		$scale = (1 << (BO_DB_STRIKES_MERCATOR_SCALE-$zoom));
		$y = "ROUND(lat_merc/$scale)";
	}

	return $y;
}

function bo_sql_lon2tilex($name, $zoom, $cache = true)
{
	if (BO_DB_STRIKES_MERCATOR !== true || !$cache)
	{
		$scale = (1 << $zoom) * 256;
		
		if (is_numeric($name))
		{
			$x = round(($name/360+0.5)*$scale);
		}
		else
		{
			$lon_mercator = "($name/360)";
			$x = "ROUND(($lon_mercator+0.5)*$scale)";
		}
	}
	else
	{
		$scale = (1 << (BO_DB_STRIKES_MERCATOR_SCALE-$zoom));
		$x = "ROUND(lon_merc / $scale)";
	}
	
	return $x;
}

?>