<?php

require_once 'classes/Data.class.php';

/* For compatibility. */
function bo_db($query = '', $die = true)
{
	return BoDb::query($query, $die);
}

// Load config from database
function bo_get_conf($name, &$changed=0)
{
	return BoData::get($name, $changed);
}

// Save config in database
function bo_set_conf($name, $data)
{
	if ($data === null)
		return BoData::delete($data);
	else
		return BoData::set($name, $data);
}


function bo_db_recreate_strike_keys($quiet = false)
{
	if (!$quiet)
	{
		echo "Updating database structure and keys.\n";
		echo "WARNING: This may take several minutes, hours or even longer!\n";
		echo "Please wait until the page as fully loaded!\n";
		
		if (!isset($_GET['do']))
		{
			echo '<a href="'.bo_insert_url('do', 1).'">Start the update process</a>';
			
			if (BO_DB_PARTITIONING === true)
				echo "\n".'<a href="'.bo_insert_url('do', 1).'&ppos">Start the update process and recreate partition index.</a>';
			
			echo "\n\nThe following commands will be executed:\n\n";
		}
		else
		{
			echo "Executing Database commands:\n";
		}
		flush();
	}
	
	$byte2mysql[1] = 'TINYINT';
	$byte2mysql[2] = 'SMALLINT';
	$byte2mysql[3] = 'MEDIUMINT';
	$byte2mysql[4] = 'INT';
	$maxbytes = 4;
	
	//Get the columns an data types
	$cols = array();
	$res = BoDb::query("SHOW COLUMNS FROM ".BO_DB_PREF."strikes");
	while($row = $res->fetch_assoc())
	{
		$bytes = 0;

		foreach($byte2mysql as $byte => $type)
		{
			if (strpos(strtoupper($row['Type']), $type.'(') !== false)
			{
				$bytes = $byte;
				break;
			}
		}
		
		$cols[$row['Field']] = $bytes;
	}

	
	if (BO_DB_PARTITIONING === true)
	{
		//readd id and lat (lat needed??)
		$sql_alter = array();
		$key_id = BoDb::query("SHOW INDEX FROM `".BO_DB_PREF."strikes` WHERE Key_name='id'")->num_rows;
	
		if (!$key_id)
			$sql_alter[] = ' ADD INDEX(`id`) ';

		//helper column ppos
		if (!isset($cols['ppos']))
		{
			BoData::set('db_partition_update', 1);
			$sql_alter[] = 'ADD `ppos` TINYINT UNSIGNED NOT NULL';
		}
		else if (isset($_GET['ppos']))
		{
			BoData::set('db_partition_update', 1);
		}
		
		if (!empty($sql_alter))
			bo_db_recreate_strike_keys_db('ALTER TABLE '.BO_DB_PREF.'strikes '.implode(', ',$sql_alter), $quiet);

		//create helper column
		if (BoData::get('db_partition_update'))
		{
			$lat_num = 180 / BO_DB_PARTITION_LAT_DIVISOR;
			$lon_num = 360 / BO_DB_PARTITION_LON_DIVISOR;
			
			$row = BoDb::query('SELECT UNIX_TIMESTAMP(MIN(time)) tmin, UNIX_TIMESTAMP(MAX(time)) tmax FROM '.BO_DB_PREF.'strikes')->fetch_assoc();
			$ok = true;
			$t1 = max($row['tmin'], BoData::get('db_partition_update'));
			
			while ($t2 < $row['tmax'] && ($ok || !isset($_GET['do'])) )
			{
				$t2 = $t1 + 3600 * 24 * 7;
				$sql = 'UPDATE `'.BO_DB_PREF.'strikes` '
						.'SET ppos=(( FLOOR( (lat+90) / '.BO_DB_PARTITION_LAT_DIVISOR.') * '.$lon_num.' + FLOOR( (lon+180) / '.BO_DB_PARTITION_LON_DIVISOR.') ) % 256) '
						.'WHERE time BETWEEN FROM_UNIXTIME('.$t1.') AND FROM_UNIXTIME('.$t2.')';
				$ok = bo_db_recreate_strike_keys_db($sql);
				$t1 = $t2;
				
				if ($ok)
					BoData::set('db_partition_update', $t1);
			}
			
			if ($ok)
				BoData::set('db_partition_update', 0);
		}
		
		//create a unique PRIMARY key
		$keys = array();
		$res = BoDb::query("SHOW INDEX FROM `".BO_DB_PREF."strikes` WHERE Key_name='PRIMARY'");
		while($row = $res->fetch_assoc())
		{
			$keys[] = $row;
		}
		
		if ($keys[0]['Column_name'] != 'lat' && $keys[1]['Column_name'] != 'lon' && $keys[2]['Column_name'] != 'time' && $keys[3]['Column_name'] != 'ppos')
		{
			//we need IGNORE in case of duplicate entries
			bo_db_recreate_strike_keys_db('ALTER IGNORE TABLE '.BO_DB_PREF.'strikes DROP PRIMARY KEY, ADD PRIMARY KEY (`lat`, `lon`, `time`, `ppos`)');
		}
		
	
		//check partitions
		$res = BoDb::query("SHOW CREATE TABLE `".BO_DB_PREF."strikes`")->fetch_assoc();
		$create_text = $res['Create Table'];
		
		$num_subpartitions = 0;
		$partitions_exist = array();
		$partition_num = 1;
		$create = false;
		
		if (!strpos($create_text, "PARTITION BY RANGE"))
		{
			//create new
			$create = true;
		}
		else
		{
			//alter existing partitions
			preg_match_all('/PARTITION p([0-9]+)_([0-9]+) VALUES LESS THAN \(([0-9]+)\)/', $create_text, $r, PREG_SET_ORDER);
			$partition_max_time = 0;
			
			foreach ($r as $d)
			{
				$name = 'p'.$d[1].'_'.$d[2];
				$partitions_exist[$name] = $d[3];
				$partition_max_time = max($partition_max_time, $d[3]);
			}

			if (preg_match('/SUBPARTITIONS ([0-9]+)/', $create_text, $r))
				$num_subpartitions = $r[1];
			
			$partition_num = count($partitions_exist) * $num_subpartitions;
			
			echo "\nTable is already partitioned: ".count($partitions_exist)."x".$num_subpartitions."=".$partition_num." existing partitions\n";
			
			/*
			if ($num_subpartitions < BO_DB_PARTITION_SUBPARTITIONS)
			{
				bo_db_recreate_strike_keys_db("ALTER TABLE ".BO_DB_PREF."strikes COALESCE SUBPARTITION ".(BO_DB_PARTITION_SUBPARTITIONS - $num_subpartitions));
			}
			else if ($num_subpartitions > BO_DB_PARTITION_SUBPARTITIONS)
			{
				bo_db_recreate_strike_keys_db("ALTER TABLE ".BO_DB_PREF."strikes ADD PARTITION SUBPARTITIONS ".($num_subpartitions - BO_DB_PARTITION_SUBPARTITIONS));
			}
			*/
			
			if (!$partition_num)
				$create = true;
			
			if (BO_DB_PARTITION_MIN_ROWS > 0)
			{
				if ($res = BoDb::query("SELECT PARTITION_NAME, TABLE_ROWS, DATA_LENGTH FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA='".BO_DB_NAME."' AND TABLE_NAME='".BO_DB_PREF."strikes'", false))
				{
					$num_rows = array();
					while ($row = $res->fetch_assoc())
					{
						$num_rows[ $row['PARTITION_NAME'] ][] = $row['TABLE_ROWS'];
					}
					
					$merge = array();
					$i = 0; 
					foreach($num_rows as $name => $subp)
					{
						//check time
						if ($partitions_exist[$name] > time() - 3600 * 24 * 30 || !$partitions_exist[$name])
							break;
						
						$num = array_sum($subp);
						
						//current partition too large?
						if ($num > BO_DB_PARTITION_MIN_ROWS)
						{
							$i++;
							continue;
						}
						
						$merge[$i][$name] = $num;
						
						//merged partitions too large?		
						if (array_sum($merge[$i]) > BO_DB_PARTITION_MIN_ROWS)
						{
							$i++;
						}
					}
					
					echo " \nMerging ".count($merge).'x'.BO_DB_PARTITION_SUBPARTITIONS." = ".(count($merge) * BO_DB_PARTITION_SUBPARTITIONS)." existing paritions: \n";
					
					foreach($merge as $p)
					{
						if (count($p) > 1)
						{
							$t = 0;
							$name_start = '';
							foreach($p as $name => $num)
							{
								$t = max($t, $partitions_exist[$name]);
								$name_start = $name_start ? $name_start : substr($name, 1, 8);
								$name_end = substr($name, 10, 8);
							}
								
							bo_db_recreate_strike_keys_db("ALTER TABLE ".BO_DB_PREF."strikes\n".
								"REORGANIZE PARTITION ".implode(',', array_flip($p))." INTO (\n".
								"PARTITION p".$name_start."_".$name_end." VALUES LESS THAN (".$t.")\n".
								")");
							
						}
					}
					
				}
			}
		}
		
		$row = BoDb::query("SELECT UNIX_TIMESTAMP(MIN(time)) t FROM ".BO_DB_PREF."strikes WHERE time > '2010-01-01'")->fetch_assoc();
		
		if ($partition_max_time)
		{
			$year = date('Y', $partition_max_time);
			$month = date('m', $partition_max_time);
		}
		else if ($row['t'])
		{
			$year = date('Y', $row['t']);
			$month = date('m', $row['t']);
		}
		else
		{
			$year = date('Y');
			$month = date('m');
		}
		
		$time_max = strtotime("now + ".BO_DB_PARTITION_MONTHS_INADVANCE." month");
		$year_max = date('Y', $time_max);
		$month_max = date('m', $time_max);
		
		if (BO_DB_PARTITION_GROUP_DAYS > 1)
			$group = (int)BO_DB_PARTITION_GROUP_DAYS;
		else
			$group = 31;
		
		$partitions = array();
		$time_last  = $partition_max_time;
		
		while ($year <= $year_max)
		{
			while($month <= 12)
			{
				for ($day = 1; $day < 28; $day += $group)
				{
					$time = gmmktime(0,0,0,$month, $day, $year);
					$name = 'p'.gmdate('Ymd', $time_last).'_'.gmdate('Ymd', $time-1);
					
					if ($partitions_exist[$name] || array_search($time, $partitions_exist) || $time_last >= $time || $time <= 0)
						$partitions = array(); //ignore all previous ones
					else
						$partitions[$name] = array('time_min' => $time_last, 'time_max' => $time);
				
					$time_last = $time;
				}
				
				if ($year == $year_max && $month == $month_max)
					break 2;
				
				$month++;
			}
			
			$month = 1;
			$year++;
		}
		

		if (count($partitions) > 1)
		{
			
			//limit to max. number
			$mtime_min = 0;
			$merged = 0;
			foreach($partitions as $name => $t)
			{
				if ((count($partitions)+1) * BO_DB_PARTITION_SUBPARTITIONS < BO_DB_PARTITION_MAX)
					break;
				
				$mtime_min = $mtime_min ? $mtime_min : $t['time_min'];
				$mtime_max = $t['time_max'];
				unset($partitions[$name]);
				$merged++;
			}
			
			if ($mtime_min)
			{
				$name = 'p'.gmdate('Ymd', $mtime_min).'_'.gmdate('Ymd', $mtime_max-1);
				$partitions[$name] = array('time_min' => $mtime_min, 'time_max' => $mtime_max);
			}
			
			ksort($partitions);
			
			$psql = '';
			foreach($partitions as $name => $d)
			{
				$psql .= "\t\tPARTITION $name VALUES LESS THAN (".$d['time_max']."),\n";
				
			} 
			
			if ($create)
			{
				$psql = "ALTER TABLE `".BO_DB_PREF."strikes`\n".
							"\tPARTITION BY RANGE( UNIX_TIMESTAMP(time) )\n".
							"\tSUBPARTITION BY HASH( ppos ) \n".
							"\tSUBPARTITIONS ".BO_DB_PARTITION_SUBPARTITIONS." ( \n".
							$psql.
							"\tPARTITION pmax VALUES LESS THAN MAXVALUE)";
			}
			else
			{
				$psql = "ALTER TABLE `".BO_DB_PREF."strikes` \n".
							"\tREORGANIZE PARTITION pmax INTO (\n".
							$psql.
							"\tPARTITION pmax VALUES LESS THAN MAXVALUE)";
			}

			echo " \nCreating ".count($partitions).'x'.BO_DB_PARTITION_SUBPARTITIONS." = ".(count($partitions) * BO_DB_PARTITION_SUBPARTITIONS)." new paritions\n";
			
			bo_db_recreate_strike_keys_db($psql);
		}
		

		
	}
	
	
	//Get existing keys
	$keys['timelatlon'] = BoDb::query("SHOW INDEX FROM `".BO_DB_PREF."strikes` WHERE Key_name='timelatlon_index'")->num_rows;
	$keys['latlontime'] = BoDb::query("SHOW INDEX FROM `".BO_DB_PREF."strikes` WHERE Key_name='latlontime_index'")->num_rows;
	$keys['time']       = BoDb::query("SHOW INDEX FROM `".BO_DB_PREF."strikes` WHERE Key_name='time_index'")->num_rows;
	$keys['latlon']     = BoDb::query("SHOW INDEX FROM `".BO_DB_PREF."strikes` WHERE Key_name='latlon_index'")->num_rows;


	//Set the new config
	$keys_enabled = (BO_DB_EXTRA_KEYS === true);
	$bytes_time   = $keys_enabled ? intval(BO_DB_EXTRA_KEYS_TIME_BYTES)   : 0;
	$bytes_latlon = $keys_enabled ? intval(BO_DB_EXTRA_KEYS_LATLON_BYTES) : 0;
	$bytes_time   = 0 < $bytes_time   && $bytes_time   <= $maxbytes ? $bytes_time   : 0;
	$bytes_latlon = 0 < $bytes_latlon && $bytes_latlon <= $maxbytes ? $bytes_latlon : 0;
	$mercator     = BO_DB_STRIKES_MERCATOR === true;
	
	$sql_alter = array();
	
	//1. Remove keys if needed
	if ($keys['latlontime'] && (!$bytes_time || !$bytes_latlon))
		$sql_alter[] = 'DROP INDEX `latlontime_index`';

	if ($keys['timelatlon'])
		$sql_alter[] = 'DROP INDEX `timelatlon_index`';

	if ($keys['time'])
		$sql_alter[] = 'DROP INDEX `time_index`';

	if ($keys['latlon'] && (!$bytes_latlon || ($bytes_time && $bytes_latlon)))
		$sql_alter[] = 'DROP INDEX `latlon_index`';

	//2. Remove columns if needed
	if (isset($cols['time_x']) && !$bytes_time)
		$sql_alter[] = 'DROP `time_x`';
	
	if (isset($cols['lat_x']) && !$bytes_latlon)
		$sql_alter[] = 'DROP `lat_x`';
	
	if (isset($cols['lon_x']) && !$bytes_latlon)
		$sql_alter[] = 'DROP `lon_x`';

	if (isset($cols['lat_merc']) && !$mercator)
		$sql_alter[] = 'DROP `lat_merc`';

	if (isset($cols['lon_merc']) && !$mercator)
		$sql_alter[] = 'DROP `lon_merc`';
	

	if (!empty($sql_alter))
		bo_db_recreate_strike_keys_db('ALTER TABLE '.BO_DB_PREF.'strikes '.implode(', ',$sql_alter), $quiet);

		
	$sql_alter = array();
	
	//3. Add/change columns if needed
	if (!isset($cols['time_x']) && $bytes_time)
	{
		BoData::set('db_keys_update', 1);
		$sql_alter[] = 'ADD `time_x` '.$byte2mysql[$bytes_time].' UNSIGNED NOT NULL';
	}
	else if (isset($cols['time_x']) && $bytes_time && $cols['time_x'] != $bytes_time)
	{
		BoData::set('db_keys_update', 1);
		$sql_alter[] = 'CHANGE `time_x` `time_x` '.$byte2mysql[$bytes_time].' UNSIGNED NOT NULL';
	}
		
	if (!isset($cols['lat_x']) && $bytes_latlon)
	{
		BoData::set('db_keys_update', 1);
		$sql_alter[] = 'ADD `lat_x` '.$byte2mysql[$bytes_latlon].' UNSIGNED NOT NULL';
	}
	else if (isset($cols['lat_x']) && $bytes_latlon && $cols['lat_x'] != $bytes_latlon)
	{
		BoData::set('db_keys_update', 1);
		$sql_alter[] = 'CHANGE `lat_x` `lat_x` '.$byte2mysql[$bytes_latlon].' UNSIGNED NOT NULL';
	}

	if (!isset($cols['lon_x']) && $bytes_latlon)
	{
		BoData::set('db_keys_update', 1);
		$sql_alter[] = 'ADD `lon_x` '.$byte2mysql[$bytes_latlon].' UNSIGNED NOT NULL';
	}
	else if (isset($cols['lon_x']) && $bytes_latlon && $cols['lon_x'] != $bytes_latlon)
	{
		BoData::set('db_keys_update', 1);
		$sql_alter[] = 'CHANGE `lon_x` `lon_x` '.$byte2mysql[$bytes_latlon].' UNSIGNED NOT NULL';
	}

	if (!isset($cols['lat_merc']) && $mercator)
	{
		BoData::set('db_mercator_update', 1);
		$sql_alter[] = 'ADD `lat_merc` INT UNSIGNED NOT NULL';
	}

	if (!isset($cols['lon_merc']) && $mercator)
	{
		BoData::set('db_mercator_update', 1);
		$sql_alter[] = 'ADD `lon_merc` INT UNSIGNED NOT NULL';
	}
	
	if (!empty($sql_alter))
		bo_db_recreate_strike_keys_db('ALTER TABLE '.BO_DB_PREF.'strikes '.implode(', ',$sql_alter), $quiet);

	
	
	//4. Add keys if needed
	$sql_alter = array();
	if (!$keys['latlontime'] && $bytes_time && $bytes_latlon)
	{
		$sql_alter[] = 'ADD INDEX `latlontime_index` (`lat_x`,`lon_x`,`time_x`)';
	}
	else if ( !$bytes_time || !$bytes_latlon )
	{
		if (!$keys['latlon'] && $bytes_latlon)
			$sql_alter[] = 'ADD INDEX `latlon_index` (`lat_x`,`lon_x`)';
	}
		
	if (!empty($sql_alter))
		bo_db_recreate_strike_keys_db('ALTER TABLE '.BO_DB_PREF.'strikes '.implode(', ',$sql_alter), $quiet);

		
	//5. update values
	list($t1, $t2, $p1, $p2) = unserialize(BoData::get('db_keys_settings'));
	
	if ( BoData::get('db_keys_update') == 1
			|| $t1 != BO_DB_EXTRA_KEYS_TIME_START
			|| $t2 != BO_DB_EXTRA_KEYS_TIME_DIV_MINUTES
			|| $p1 != BO_DB_EXTRA_KEYS_LAT_DIV
			|| $p2 != BO_DB_EXTRA_KEYS_LON_DIV
		)
	{
		$ok = true;
		
		if ($bytes_time)
		{
			$time_vals   = pow(2, 8 * $bytes_time);
			$time_start  = strtotime(BO_DB_EXTRA_KEYS_TIME_START);
			$time_div    = (double)BO_DB_EXTRA_KEYS_TIME_DIV_MINUTES;
			$sql = 'UPDATE `'.BO_DB_PREF.'strikes` SET time_x=(FLOOR((UNIX_TIMESTAMP(time)-'.$time_start.')/60/'.$time_div.')%'.$time_vals.')';
			$ok = bo_db_recreate_strike_keys_db($sql, $quiet) >= 0;
		}
		
		if ($ok && $bytes_latlon)
		{
			$latlon_vals = pow(2, 8 * $bytes_latlon);
			$lat_div     = (double)BO_DB_EXTRA_KEYS_LAT_DIV;
			$lon_div     = (double)BO_DB_EXTRA_KEYS_LON_DIV;
			
			$sql = 'UPDATE `'.BO_DB_PREF.'strikes` SET lat_x=FLOOR(((90+lat)%'.$lat_div.')/'.$lat_div.'*'.$latlon_vals.'), lon_x=FLOOR(((180+lon)%'.$lon_div.')/'.$lon_div.'*'.$latlon_vals.')';
			$ok = bo_db_recreate_strike_keys_db($sql, $quiet) >= 0;
		}
		
		if ($ok)
		{
			BoData::set('db_keys_update', 0);
			BoData::set('db_keys_settings', 
					serialize(array(BO_DB_EXTRA_KEYS_TIME_START, 
									BO_DB_EXTRA_KEYS_TIME_DIV_MINUTES, 
									BO_DB_EXTRA_KEYS_LAT_DIV, 
									BO_DB_EXTRA_KEYS_LON_DIV)));
		}
	}

	if ( BoData::get('db_mercator_update') )
	{
		$sql = 'UPDATE `'.BO_DB_PREF.'strikes` SET '.
				'lat_merc='.bo_sql_lat2tiley('lat', BO_DB_STRIKES_MERCATOR_SCALE, false).', '.
				'lon_merc='.bo_sql_lon2tilex('lon', BO_DB_STRIKES_MERCATOR_SCALE, false).
				'WHERE lat_merc=0 AND lon_merc=0';
		
		$ok = bo_db_recreate_strike_keys_db($sql, $quiet) >= 0;
		
		if ($ok)
			BoData::set('db_mercator_update', 0);
	}

	
	if (!$quiet)
		echo "\n\nFinished! ";
	
	return;
}

function bo_db_recreate_strike_keys_db($sql, $quiet = false)
{
	if (!$quiet)
		echo "\n * $sql";
	
	flush();
	
	if (!isset($_GET['do']))
		return false;
	
	$ok = BoDb::query($sql, false);

	if (!$quiet)
		echo " | "._BL("Result").": "._BL($ok !== false ? '<b>OK</b>' : '<b>FAIL</b>');
	
	flush();

	return $ok;
}

?>