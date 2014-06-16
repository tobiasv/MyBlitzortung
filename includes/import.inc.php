<?php

require_once 'classes/FilesDownload.class.php'; 


function bo_update_all($force = false, $only = '')
{
	bo_echod(" ");
	bo_echod("***** Getting lightning data from blitzortung.org *****");
	bo_echod(" ");

	session_write_close();
	ignore_user_abort(true);
	ini_set('default_socket_timeout', BO_SOCKET_TIMEOUT);

	$start_time = time();
	$debug = defined('BO_DEBUG') && BO_DEBUG;
	$is_updating = (int)BoData::get('is_updating');
	$max_time = bo_update_get_timeout();
	bo_getset_timeout($max_time);

	//Check if sth. went wrong on the last update (if older continue)
	if ($is_updating && time() - $is_updating < $max_time*5 + 60 && !($force && $debug))
	{
		bo_echod("Another update is running *** Begin: ".date('Y-m-d H:i:s', $is_updating));
		bo_echod(" ");
		bo_echod("Exiting...");
		return;
	}

	BoData::set('is_updating', time());
	register_shutdown_function('BoData::set', 'is_updating', 0);


	// to avoid to much connections from different stations to blitzortung.org at the same time
	if (!$force && $max_time > 20)
	{
		$max_sleep = BO_UP_MAX_SLEEP;
		$sleep = rand(0,$max_sleep);
		bo_echod("Waiting $sleep seconds, to avoid too high load on Blitzortung servers ...");
		sleep($sleep);
	}


	bo_update_all2($force, $only);

	bo_echod(" ");
	bo_echod("Import finished. Exiting...");
	bo_echod(" ");

	BoData::set('is_updating', 0);

	return;
}


function bo_update_all2($force = false, $only = '')
{

	if (!BoData::get('first_update_time'))
		BoData::set('first_update_time', time());


	//check if we should do an async update
	if (    (BO_UP_INTVL_STRIKES > BO_UP_INTVL_STATIONS && BO_UP_INTVL_STATIONS)
	     || (BO_UP_INTVL_STRIKES > BO_UP_INTVL_RAW)     && BO_UP_INTVL_RAW)
	{
		if (!$force)
			bo_echod("Info: Asynchronous update. No problem, but untestet. To avoid set strike timer < station timer < signal timer (or equal).");

		$async = true;
	}
	else
		$async = false;



	/*** Get the data! ***/


	//Strikes
	if (!$only || $only == 'strikes')
		$strikes_imported = bo_update_strikes($force);


	//Update signals/stations only after strikes where imported
	if ($strikes_imported !== false || $async || $force)
	{
		//Stations
		if (bo_exit_on_timeout()) return;

		if (!$only || $only == 'stations')
			$stations_imported = bo_update_stations($force);


		//Signals
		if (bo_exit_on_timeout()) return;

		if (!$only || $only == 'signals')
			$signals_imported  = bo_update_raw_signals($force);
		
		
		//Assign strikes->signals
		if (bo_exit_on_timeout()) return;
		
		if ($signals_imported && 
			BoData::get('uptime_strikes_modified') - BO_MIN_MINUTES_STRIKE_CONFIRMED*60-60 < BoData::get('uptime_raw'))
		{
			bo_match_strike2raw();
		}


		//Daily statistics
		if (bo_exit_on_timeout()) return;

		if (!$only || $only == 'daily')
			bo_update_daily_stat();


		// Alerts
		if (defined('BO_ALERTS') && BO_ALERTS)
		{
			if (bo_exit_on_timeout()) return;

			if (!$only || $only == 'alerts')
			{
				require_once 'alert.inc.php';
				bo_alert_send();
			}
		}
	}

	if (bo_exit_on_timeout()) return;

	/*** Update strike tracks ***/
	if ($strikes_imported || $force)
	{
		if (!$only || $only == 'tracks')
			bo_update_tracks($force);
	}

	
	/*** Download external pictures/files ***/
	if (!$only || $only == 'download')
		bo_download_external($force);

		
	/*** Purge old data ***/
	if (bo_exit_on_timeout()) return;

	if (!$only || $only == 'purge')
		bo_purge_olddata($only && $force); //only force purge when "only" call


	/*** Densities ***/
	if (bo_exit_on_timeout()) return;

	if (!$only || $only == 'density')
	{
		require_once 'density.inc.php';
		bo_update_densities($force);
	}
		
	/*** File Cache ***/
	if (bo_exit_on_timeout()) return;

	if (!$only || $only == 'cache')
		bo_purge_cache($force);

	
	/*** TABLE STATUS (takes long time with InnoDB) ***/
	if (bo_exit_on_timeout()) return;
	$D = @unserialize(BoData::get('db_table_status'));
	$tables = array('conf', 'raw', 'stations', 'stations_stat', 'stations_strikes', 'strikes', 'station', 'densities', 'cities');
	
	if (time() - $D['last'] > 3600)
	{
		$res = BoDb::query("SHOW TABLE STATUS WHERE Name LIKE '".BO_DB_PREF."%'");
		while($row = $res->fetch_assoc())
		{
			$name = substr($row['Name'], strlen(BO_DB_PREF));

			if (array_search($name, $tables) !== false)
			{
				$D['rows'][$name] = $row['Rows'];
				$D['data'][$name] = $row['Data_length'];
				$D['keys'][$name] = $row['Index_length'];
			}
		}
		
		$D['last'] = time();
		BoData::set('db_table_status', serialize($D));
	}
		
		
	return;
}


// Get archive data from blitzortung cgi (OUTDATED!)
function bo_get_archive($args='', $bo_login_id=false, $as_array=false)
{
	$file = false;

	if (!$bo_login_id)
	{
		//$bo_login_id = BoData::get('bo_login_id');
		$auto_id = true;
	}

	if ($bo_login_id)
	{
		$url = 'http://www.blitzortung.org/cgi-bin/archiv.cgi?login_string='.$bo_login_id.'&lang=en&'.$args;
		$file = bo_get_file($url, $code, 'archive', $d1, $d2, $as_array);

		if ($file === false)
		{
			bo_echod("ERROR: Couldn't get file from archive! Code: $code");
			bo_update_error('archivedata', 'Download of archive data failed. '.$code);
			return false;
		}

		bo_update_error('archivedata', true);
	}

	if ($file && $as_array)
		$text_line = $file[0];
	elseif ($file)
		$text_line = substr($file, 0, 100);
	else
		$text_line = false;

	//Login seems not successful --> new login ID
	if (!$text_line || strpos($text_line, 'denied') !== false || !$file )
	{

		if ($auto_id)
		{
			$bo_login_id = bo_get_login_str();

			if ($bo_login_id)
			{
				bo_echod("Old Login-Id outdated. Got new Login-Id: ".substr($bo_login_id,0,8)."...");
				$file = bo_get_archive($args,$bo_login_id, $as_array);
				return $file;
			}
			else
			{
				bo_echod("ERROR: Tried to get new Login-Id but it didn't work! Wrong Username/Password? ".substr($bo_login_id,0,8)."...");
				return false;
			}
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
	bo_echod(" ");
	bo_echod("=== Raw-Data ===");
	bo_echod("Not implemented!");
	
	return;
	
	if (!defined('BO_UP_INTVL_RAW') || !BO_UP_INTVL_RAW)
	{
		bo_echod("Disabled!");
		return true;
	}
	
	if (bo_version() < 801)
	{
		bo_echod("Database version too old. Need update...");
		return;
	}

	list($last_update, $auto_force, $update_errs, $last_file_pos, $last_hour, $last_modified) = @unserialize(BoData::get('uptime_raw_data'));

	$count_inserted = 0;
	$count_exists = 0;

	$do_update = time() - $last_update > BO_UP_INTVL_RAW * 60 - 30 || $force || time() < $last_update;

	//Auto force = if there are more files to load (e.g. after installation)
	if (!$do_update && 0 < $auto_force && $auto_force < 30)
	{
		bo_echod("Auto forced update out of order to get the remaining data");
		$do_update = true;
	}

	if ($do_update)
	{
		//set only the important entries to avoid problems if import crashes
		BoData::set('uptime_raw_data', serialize(array(time(), 0, $update_errs+1)));

		$max_signal_back_hours = 22;
		$read_back_seconds = 180;
		$update_err = false;

		// Search last signal
		$sql = "SELECT MAX(time) mtime FROM ".BO_DB_PREF."raw";
		$res = BoDb::query($sql);
		$row = $res->fetch_assoc();
		$last_signal = strtotime($row['mtime'].' UTC');
		$update_time_start = $last_signal - $read_back_seconds;
		$update_time_end   = time();

		if ($update_errs > 10 || (!$last_signal && $last_update) )
		{
			//avoid downloading the same (missing) files again and again -> only (try to) get the latest data
			bo_echod("There were errors during the last import or couldn't find any signal in the database ($update_errs)! Getting only the latest raw-data file.");
			$update_time_start = time() - 3600;
			$last_file_pos = 0;
			$last_hour = false;
		}
		elseif (!$last_signal && !$last_update)
		{
			//First update ever
			bo_echod("No last signal found (maybe first update)! Update begins at 'now -$max_signal_back_hours hours'");
			$update_time_start = time() - 3600 * $max_signal_back_hours;
			$last_file_pos = 0;
			$last_hour = false;
		}
		elseif (	$last_signal > time()
				|| time() - $last_update > $max_signal_back_hours * 3600
				|| time() - $last_signal > $max_signal_back_hours * 3600
				)
		{
			//Last update or last signal was too long ago
			bo_echod("No last signal found or last recieved signal too old! Last time: ".$row['mtime'].". Setting to now -$max_signal_back_hours hours.");
			$update_time_start = time() - 3600 * $max_signal_back_hours;
			$last_file_pos = 0;
			$last_hour = false;
		}


		//anti-duplicate without using unique-db keys
		//Searching for old Signals (to avoid duplicates)
		$old_times = array();
		$date_start = gmdate('Y-m-d H:i:s', $update_time_start);
		$date_end = gmdate('Y-m-d H:i:s', time() + 3600 * 6); //6h to the future to be sure
		$sql = "SELECT id, time, time_ns
				FROM ".BO_DB_PREF."raw
				WHERE time BETWEEN '$date_start' AND '$date_end'";
		$res = BoDb::query($sql);
		while ($row = $res->fetch_assoc())
		{
			$id = $row['id'];
			$old_times[$row['id']] = $row['time'].'.'.$row['time_ns'];
		}


		//which files to download
		$hours = array();
		for ($i=floor($update_time_start/3600); $i<=floor($update_time_end/3600);$i++)
			$hours[] = gmdate('H', $i*3600);


		//Debug output
		$loadcount = BoData::get('upcount_raw');
		BoData::set('upcount_raw', $loadcount+1);
		bo_echod('Last signal: '.date('Y-m-d H:i:s', $last_signal).
				' *** Importing only signals newer than: '.date('Y-m-d H:i:s', $update_time_start).
				' *** Loading '.count($hours).' files'.
				' *** This is update #'.$loadcount);

		//bo_echod("Last hour: $last_hour  Last pos: $last_file_pos");

		$files = 0;
		$lines = 0;
		foreach($hours as $hour)
		{
			$range = 0;
			$bytes = 0;
			$files++;
			$text = " $files. Reading file $hour.log ";
			$url  = bo_access_url().BO_IMPORT_PATH_RAW.trim(BO_USER).'/'.$hour.'.log';
			$modified = $last_modified ? $last_modified : $last_signal;

			//using range-method if parameters known
			if ($files == 1 && $last_hour !== false && $last_hour == $hour && $last_file_pos)
			{
				$range = $last_file_pos;
				$text .= " using range method (starting at $last_file_pos bytes)";
				$last_hour = false; //reset
			}

			// GET THE FILE
			$file = bo_get_file($url, $code, 'raw_data', $range, $modified, true);


			//sth. went wrong -> retry without range
			if ($file === false && intval($code) != 304)
			{
				$text .= 'sth went rong -> getting the file the 2nd time';
				$modified = $last_modified ? $last_modified : $last_signal;

				// GET THE FILE (again)
				$file = bo_get_file($url, $code, 'raw_data', $range, $modified, true);
			}
			elseif ($file !== false && empty($range) && $last_file_pos && $files == 1)
			{
				$text .= " - got whole file ";
			}

			//Check the file
			if ($file === false)
			{
				if (intval($code) == 304)
				{
					bo_echod($text." *** file not modified");
				}
				else
				{
					bo_echod($text." *** couldn't get file $url *** Code: $code");
					$update_err = true;
					$last_file_pos = 0;
				}

				continue;
			}
			else
			{
				//use the range method for the previous hour-file if
				if (!$modified || gmdate('H', $modified-$read_back_seconds) == $hour)
				{
					//save range and modified-time for the next try
					if (isset($range[1]))
						$last_file_pos = $range[1];
					else
						$last_file_pos = strlen(implode('', $file));

					$last_modified = $modified;
					$last_hour = $hour;
				}

				bo_echod($text." *** ".count($file)." Lines ... ");
			}

			//Read the signals
			foreach($file as $line)
			{
				$bytes += strlen($line)+1;

				if (!trim($line))
					continue;

				if (!preg_match('/([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ([0-9]{2}:[0-9]{2}:[0-9]{2})\.([0-9]+) ([-0-9\.]+) ([-0-9\.]+) ([-0-9\.]+) ([0-9]+) ([0-9]+) ([0-9]+) ([0-9]+) ([A-F0-9]+)/', $line, $r))
				{
					bo_echod("Wrong line format: \"$line\"");
					continue;
				}

				$date = $r[1];
				$time = $r[2];
				$time_ns = intval($r[3]);
				$utime = strtotime("$date $time UTC");

				// update strike-data only some seconds *before* the *last signal*
				if ($utime < $update_time_start)
				{
					$count_exists++;
					continue;
				}

				//don't look at existsing signals
				$id = array_search("$date $time.$time_ns", $old_times);
				if ($id)
				{
					$count_exists++;
					continue;
				}

				$lines++;

				$lat = $r[4];
				$lon = $r[5];
				$height = (double)$r[6];
				$channels = $r[7];
				$values = $r[8];
				$bpv = $r[9];
				$ntime = $r[10]; //nanosec per sample
				$data = trim($r[11]);

				//check wether data string fits hex-code
				if (trim($data, "1234567890abcdefABCDEF"))
					$data = '';
				elseif (strlen($data) % 2)
					$data .= '0';

				// signal examinations adn SQL
				$bdata = bo_hex2bin($data);
				$sql_data = bo_examine_signal($bdata, $channels, $ntime);
				$sql_data['time'] = "$date $time";
				$sql_data['time_ns'] = $time_ns;
				$sql_data['lat'] = $lat;
				$sql_data['lon'] = $lon;
				$sql_data['height'] = $height;
				$sql_data['strike_id'] = '0';
				$sql_data['channels'] = $channels;
				$sql_data['ntime'] = $ntime;
				$sql_data['data'] = array($data, 'hex');
							
				BoDb::bulk_insert('raw', $sql_data);
				$count_inserted++;
				
				//Timeout
				if (bo_exit_on_timeout())
				{
					if (count($hours) - $files > 1)
					{
						$auto_force++;
						bo_echod('Auto update will occur during the next import to get the remaining files!');
					}

					//change byte range for partial download
					if ($last_file_pos)
						$last_file_pos -= $bytes;

					$timeout = true;
					break 2;
				}

			}

		}

		if ($update_err)
			$update_errs++;
		else
			$update_errs = 0;
		
		if (!$timeout)
			$auto_force = false;

		//Write stuff
		BoDb::bulk_insert('raw');
			
		//bo_echod("Pos: $last_file_pos Hour: $last_hour");

		BoData::set('uptime_raw_data', serialize(array(time(), $auto_force, $update_errs, $last_file_pos, $last_hour, $last_modified)));

		bo_echod("Lines: $lines *** Files: $files *** New Raw Data: $count_inserted *** Already read: $count_exists");

		if ($channels && $values && $bpv)
		{
			BoData::set('raw_channels', $channels);
			BoData::set('raw_values', $values);
			BoData::set('raw_bitspervalue', $bpv);
			BoData::set('raw_ntime', $ntime);
		}

		//Longtime
		$count = BoData::get('count_raw_signals');
		BoData::set('count_raw_signals', $count + $count_inserted);

		if (!$timeout)
		{
			BoData::set('uptime_raw', time());
			bo_update_status_files('signals');
			bo_match_strike2raw();
			$updated = true;
		}
		else
			$updated = false;

		if ($update_err)
			bo_update_error('rawdata', 'Download of raw data failed.');
		else
			bo_update_error('rawdata', true);
	}
	else
	{
		bo_echod("No update. Last update ".(time() - $last_update)." seconds ago. This is normal and no error message!");
		$updated = false;
	}

	return $updated;
}

// Get new strikes from blitzortung.org
function bo_update_strikes($force = false, $time_start_import = null)
{
	global $_BO;

	$last = BoData::get('uptime_strikes_try');

	bo_echod(" ");
	bo_echod("=== Strikes ===");
	
	if (bo_version() < 801)
	{
		bo_echod("Database version too old. Need update...");
		return;
	}

	if (time() - $last > BO_UP_INTVL_STRIKES * 60 - 30 || $force || time() < $last)
	{
		BoData::set('uptime_strikes_try', time());

		$start_time = time();
		$stations = bo_stations();
		$own_id = bo_station_id();
		$own_ids = bo_stations_own();
		
		$count_inserted = 0;
		$count_exists = 0;
		$old_data = null;
		$dist_data = array();
		$bear_data = array();
		$dist_data_own = array();
		$bear_data_own = array();
		$strikesperstation = array();
		$min_stations_calc = 1000;
		$max_stations_calc = 0;
		$timeout = false;
		$bo2id = array();

		foreach($stations as $stId => $sdata)
		{
			$bo2id[$sdata['bo_station_id']] = $stId;

			if (BO_ENABLE_LONGTIME_ALL === true || isset($own_ids[$sdata['bo_station_id']]))
			{
				$statistic_stations[$stId] = $stId;
				$max_dist_all[$stId] = 0;
				$min_dist_all[$stId] = 9E12;
				$max_dist_own[$stId] = 0;
				$min_dist_own[$stId] = 9E12;
			}
		}
		
		//Extra keys for database
		$keys_enabled = (BO_DB_EXTRA_KEYS === true);
		$key_bytes_time   = $keys_enabled ? intval(BO_DB_EXTRA_KEYS_TIME_BYTES)   : 0;
		$key_bytes_latlon = $keys_enabled ? intval(BO_DB_EXTRA_KEYS_LATLON_BYTES) : 0;
		$key_bytes_time   = 0 < $key_bytes_time   && $key_bytes_time   <= 4 ? $key_bytes_time   : 0;
		$key_bytes_latlon = 0 < $key_bytes_latlon && $key_bytes_latlon <= 4 ? $key_bytes_latlon : 0;

		if ($key_bytes_time)
		{
			$key_time_vals   = pow(2, 8 * $key_bytes_time);
			$key_time_start  = strtotime(BO_DB_EXTRA_KEYS_TIME_START);
			$key_time_div    = (double)BO_DB_EXTRA_KEYS_TIME_DIV_MINUTES;
		}

		if ($key_bytes_latlon)
		{
			$key_latlon_vals = pow(2, 8 * $key_bytes_latlon);
			$key_lat_div     = (double)BO_DB_EXTRA_KEYS_LAT_DIV;
			$key_lon_div     = (double)BO_DB_EXTRA_KEYS_LON_DIV;
		}


		/***** PREPARATIONS BEFORE READING *****/
		$res = BoDb::query("SELECT time, time_ns, id max_id FROM ".BO_DB_PREF."strikes WHERE id=(SELECT MAX(id) FROM ".BO_DB_PREF."strikes)");
		$row = $res->fetch_assoc();
		$strike_last_time = $row['time'] ? strtotime($row['time'].' UTC') : 1;
		$strike_last_time_ns = $row['time_ns'];
		$strike_last_id = $row['max_id'];
		
		if ($strike_last_time >= time() || $strike_last_time <= 0)
			$strike_last_time = 1;

			
		$Files = new FilesDownload('strikes', 10, bo_access_url().BO_IMPORT_PATH_STROKES, $strike_last_time);
	


		/***** SOME MORE PREPARATIONS BEFORE READING *****/
		bo_update_error('strikedata', true); //reset error reporting
		$loadcount = BoData::get('upcount_strikes');
		BoData::set('upcount_strikes', $loadcount+1);
		$last_strikes = unserialize(BoData::get('last_strikes_stations'));
		bo_echod('Last strike: '.date('Y-m-d H:i:s', $strike_last_time).
				' *** Loading '.$Files->FoundFiles.' files from '.gmdate('Y-m-d H:i:s', $Files->TimeStart).' to '.gmdate('Y-m-d H:i:s', $Files->TimeEnd).
				' *** This is update #'.$loadcount);

		while ( ($l = $Files->GetNextLine()) !== false )
		{
			if ($Files->LastMessage)
			{
				bo_echod("  ".$Files->LastMessage);
				$Files->LastMessage = '';
			}
		
			$l = trim($l);
			$D = array();
			$part_stations = array();
			$error = 0;
			$utime = 0;
			
			if (!$l) //empty line
				continue;

			$D['time'] = substr($l, 0, 19);
			$utime = strtotime($D['time'].' UTC');
			
			if (!$utime)
			{
				$error++;
				continue;
			}
			
			$D['time_ns'] = substr($l, 20, 9);
			
			//check if strike is already imported
			if ($utime == $strike_last_time && $D['time_ns'] == $strike_last_time_ns)
			{
				bo_echod("Found latest strike from last import");
				continue;
			}
			else if ($utime < $strike_last_time || ($utime == $strike_last_time && $D['time_ns'] <= $strike_last_time_ns))
			{
				continue;
			}
			
			//Position
			if (preg_match('/pos;([-0-9\.]+);([-0-9\.]+);([-0-9\.]+)/', $l, $r))
			{
				$D['lat'] = $r[1];
				$D['lon'] = $r[2];
				$D['alt'] = $r[3];
			}
			else
				$error++;
			
			//Current
			if (preg_match('/str;([0-9\.]+)/', $l, $r))
				$D['current'] = $r[1];
			else
				$error++;

			//Type
			if (preg_match('/typ;([0-9\.]+)/', $l, $r))
				$D['type'] = $r[1];
			else
				$error++;
				
			//Deviation
			if (preg_match('/dev;([0-9\.]+)/', $l, $r))
			{
				$D['deviation'] = ($r[1]/1E9) * BO_C;
			}
			else
				$error++;

			//Stations
			if (preg_match('/sta;([0-9]+);([0-9]+);([^ ]+)/', $l, $r))
			{
				$D['stations_calc'] = $r[1];
				$D['stations'] = $r[2];
				
				$part_stations = explode(',', $r[3]);
				
				if ( ($pos = array_search(bo_station_id(true), $part_stations)) !== false)
				{
					$D['part'] = 1;
					$D['part_pos'] = $pos+1;
				}
				else
				{
					$D['part'] = 0;
					$D['part_pos'] = 0;
				}
				
			}
			else
				$error++;
			
			$D['status'] = 2; //always 2

			if ($key_bytes_time)
			{
				$D['time_x'] = array('(FLOOR(('.($utime-$key_time_start).')/60/'.$key_time_div.')%'.$key_time_vals.')');
			}

			if ($key_bytes_latlon)
			{
				$D['lat_x'] = array('FLOOR((('.(90+$D['lat']).')%'.$key_lat_div.')/'.$key_lat_div.'*'.$key_latlon_vals.')');
				$D['lon_x'] = array('FLOOR((('.(180+$D['lon']).')%'.$key_lon_div.')/'.$key_lon_div.'*'.$key_latlon_vals.')');
			}
			
			/********* Statistics **********/
			foreach($statistic_stations as $stId)
			{
				$stLat = $stations[$stId]['lat'];
				$stLon = $stations[$stId]['lon'];

				if ($stLat == 0.0 && $stLon == 0.0) //station has no position yet
					continue;

				$dist    = bo_latlon2dist($D['lat'], $D['lon'], $stLat, $stLon);
				$bear    = bo_latlon2bearing($D['lat'], $D['lon'], $stLat, $stLon);
				$bear_id = intval($bear);
				$dist_id = intval($dist / 10 / 1000);
				$max_dist_all[$stId] = max($dist, $max_dist_all[$stId]);
				$min_dist_all[$stId] = min($dist, $min_dist_all[$stId]);
				$bear_data[$stId][$bear_id]++;
				$dist_data[$stId][$dist_id]++;

				//strike detected by station
				if (array_search($stations[$stId]['bo_station_id'], $part_stations) !== false)
				{
					$bear_data_own[$stId][$bear_id]++;
					$dist_data_own[$stId][$dist_id]++;
					$max_dist_own[$stId] = max($dist, $max_dist_own[$stId]);
					$min_dist_own[$stId] = min($dist, $min_dist_own[$stId]);
					$strikesperstation[$stId]++;
				}
				
				if ($stId == $own_id)
				{
					$D['distance'] = $dist;
					$D['bearing'] = $bear;
				}

			}

			

			//auto_increment!!
			$strike_last_id++;
			$D['id'] = $strike_last_id;
			BoDb::bulk_insert('strikes', $D);
			$count_inserted++;
			$id = $strike_last_id;
			$new_strike = true;

			$all_stats = !(defined('BO_STATION_STAT_DISABLE') && BO_STATION_STAT_DISABLE == true);
			
			//Update Strike <-> All Participated Stations
			if ($id && ( count($own_ids) > 1 || $all_stats ) )
			{
				$sql_data = array();
				$st_check = array();
				
				foreach($part_stations as $bo_station_id)
				{
					if (!$all_stats && !$own_ids[$bo_station_id])
						continue;
						
					$stId = $bo2id[$bo_station_id];

					if ($stId && !isset($st_check[$stId]))
					{
						$st_check[$stId] = true; //don't add same station more than 1 time!
						$last_strikes[$stId] = array($utime, $D['time_ns'], $id);
						
						$sql_data['strike_id'] = $id;
						$sql_data['station_id'] = $stId;
						BoDb::bulk_insert('stations_strikes', $sql_data);
					}
				}
			}


			//general statistics
			$min_stations_calc = min($D['stations_calc'], $min_stations_calc);
			$max_stations_calc = max($D['stations_calc'], $max_stations_calc);
			$max_stations = max($max_stations, $D['stations']);

			//Timeout
			if (bo_exit_on_timeout())
			{
				$last_strike_time = $utime;
				$timeout = true;
				break;
			}
		}
	
		if ($Files->LastMessage)
			bo_echod("  ".$Files->LastMessage);

		bo_echod("Lines: ".$Files->NumLines." *** Size: ".round($Files->NumBytes/1024)."kB *** New Strikes: $count_inserted");

		$modified = $Files->NewModified;
		$Files->Close();		
		
		//Insert rest of data
		BoDb::bulk_insert('strikes');
		BoDb::bulk_insert('stations_strikes');


		//General
		BoData::set('last_strikes_stations', serialize($last_strikes));
		$count = BoData::get('longtime_max_participants');
		if ($count < $max_stations)
		{
			BoData::set('longtime_max_participants', $max_stations);
			BoData::set('longtime_max_participants_time', time());
		}

		//strike count per region
		$_BO['region'][] = array(); //dummy for "all"
		$time_start = gmdate('Y-m-d H:i:s', time() - 120 - BO_GRAPH_STAT_STRIKES_NOW_GROUP_MINUTES*60);
		$time_end = gmdate('Y-m-d H:i:s', time() - 120);
		$sql_template = "SELECT MAX(time) mtime, COUNT(id) cnt
			FROM ".BO_DB_PREF."strikes s
			WHERE time BETWEEN '$time_start' AND '$time_end' {where} ";
		$last_strikes_region = unserialize(BoData::get('last_strikes_region'));
		$rate_strikes_region = unserialize(BoData::get('rate_strikes_region'));
		foreach ($_BO['region'] as $reg_id => $d)
		{
			$sql = strtr($sql_template,array('{where}' => bo_region2sql($reg_id)));
			$row = BoDb::query($sql)->fetch_assoc();
			$rate_strikes_region[$reg_id] = $row['cnt'] / BO_GRAPH_STAT_STRIKES_NOW_GROUP_MINUTES;

			if ($row['mtime'])
				$last_strikes_region[$reg_id] = strtotime($row['mtime'].' UTC');
		}
		BoData::set('last_strikes_region', serialize($last_strikes_region));
		BoData::set('rate_strikes_region', serialize($rate_strikes_region));


		//Update Longtime statistics per station for detected strikes
		foreach($strikesperstation as $stId => $count)
		{
			$add = $stId == $own_id ? '' : '#'.$stId.'#';
			
			BoData::update_add('count_strikes_own'.$add, $count);

			$bear_data_tmp = unserialize(BoData::get('longtime_bear_own'.$add));
			if (!$bear_data_tmp['time']) $bear_data_tmp['time'] = time();
			foreach($bear_data_own[$stId] as $bear_id => $bear_count)
				$bear_data_tmp[$bear_id] += $bear_count;
			BoData::set('longtime_bear_own'.$add, serialize($bear_data_tmp));

			$dist_data_tmp = unserialize(BoData::get('longtime_dist_own'.$add));
			if (!$dist_data_tmp['time']) $dist_data_tmp['time'] = time();
			foreach($dist_data_own[$stId] as $dist_id => $dist_count)
				$dist_data_tmp[$dist_id] += $dist_count;
			BoData::set('longtime_dist_own'.$add, serialize($dist_data_tmp));

			$max = BoData::get('longtime_max_dist_own'.$add);
			if ($max < $max_dist_own[$stId])
			{
				BoData::set('longtime_max_dist_own'.$add, $max_dist_own[$stId]);
				BoData::set('longtime_max_dist_own_time'.$add, time());
			}

			$min = BoData::get('longtime_min_dist_own'.$add);
			if (!$min || $min > $min_dist_own[$stId])
			{
				BoData::set('longtime_min_dist_own'.$add, $min_dist_own[$stId]);
				BoData::set('longtime_min_dist_own_time'.$add, time());
			}
		}
		

		//Update Longtime statistics per station for all strikes
		foreach($statistic_stations as $stId)
		{
			//update only if station is active or got some strikes during the update
			if (bo_status($stations[$stId]['status'], STATUS_RUNNING) || $strikesperstation[$stId])
			{
				$add = $stId == $own_id ? '' : '#'.$stId.'#';

				BoData::update_add('count_strikes'.$add, $count_inserted);

				if (isset($dist_data[$stId]))
				{
					$dist_data_tmp = unserialize(BoData::get('longtime_dist'.$add));
					if (!$dist_data_tmp) $dist_data_tmp['time'] = time();
					foreach($dist_data[$stId] as $dist_id => $dist_count)
						$dist_data_tmp[$dist_id] += $dist_count;
					BoData::set('longtime_dist'.$add, serialize($dist_data_tmp));
				}

				if (isset($bear_data[$stId]))
				{
					$bear_data_tmp = unserialize(BoData::get('longtime_bear'.$add));
					if (!$bear_data_tmp) $bear_data_tmp['time'] = time();
					foreach($bear_data[$stId] as $bear_id => $bear_count)
						$bear_data_tmp[$bear_id] += $bear_count;
					BoData::set('longtime_bear'.$add, serialize($bear_data_tmp));
				}

				$max = BoData::get('longtime_max_dist_all'.$add);
				if ($max < $max_dist_all[$stId])
				{
					BoData::set('longtime_max_dist_all'.$add, $max_dist_all[$stId]);
					BoData::set('longtime_max_dist_all_time'.$add, time());
				}

				$min = BoData::get('longtime_min_dist_all'.$add);
				if (!$min || $min > $min_dist_all[$stId])
				{
					BoData::set('longtime_min_dist_all'.$add, $min_dist_all[$stId]);
					BoData::set('longtime_min_dist_all_time'.$add, time());
				}
			}

		}


		if ($timeout)
		{
			bo_echod("TIMEOUT: Continue next time...");
			$updated = false;
			BoData::set('uptime_strikes_modified', $last_strike_time);
		}
		else
		{
			$updated = true;
			BoData::set('uptime_strikes', time());
			BoData::set('uptime_strikes_modified', $modified);
			bo_update_status_files('strikes');
			bo_cache_log('Strike data updated!');


			//Guess Minimum Participants
			if (intval(BO_FIND_MIN_PARTICIPANTS_HOURS))
			{
				$min_hours        = BO_FIND_MIN_PARTICIPANTS_HOURS;
				$see_same         = BO_FIND_MIN_PARTICIPANTS_COUNT;
				$tmp              = unserialize(BoData::get('bo_participants_locating_min'));
				$min_stations_calc = $tmp['value'];

				if (time() - $tmp['time'] > 3600 * BO_FIND_MIN_PARTICIPANTS_HOURS)
				{
					$row = BoDb::query("SELECT MIN(stations) minusers FROM ".BO_DB_PREF."strikes WHERE time>'".gmdate('Y-m-d H:i:s', time() - 3600*$min_hours)."'")->fetch_assoc();

					if ($row['minusers'] >= 3)
					{
						//reset counter if last value differs from new value
						if ($tmp['last'] != $row['minusers'])
							$tmp['count'] = 0;

						//only save the value after some same values
						if ($tmp['count'] >= $see_same)
						{
							$tmp['value'] = $row['minusers'];
							$tmp['count'] = 0;
						}
						else
							$tmp['count']++;

						$tmp['last'] = $row['minusers'];
						$tmp['time'] = time();
						BoData::set('bo_participants_locating_min', serialize($tmp));
					}
				}
			}

			//Maximum Participants
			if (intval(BO_FIND_MAX_PARTICIPANTS_HOURS) && $max_stations_calc >= 3)
			{
				$tmp = unserialize(BoData::get('bo_participants_locating_max'));
				$tmp['value_last'] = max($tmp['value_last'], $max_stations_calc);
				if (time() - $tmp['time'] > 3600 * BO_FIND_MAX_PARTICIPANTS_HOURS && $max_stations_calc >= $min_stations_calc)
				{
					$tmp['time'] = time();
					$tmp['value'] = $tmp['value_last'];
					$tmp['value_last'] = 0; //if max value shrinks!
				}
				BoData::set('bo_participants_locating_max', serialize($tmp));
			}

		}
		
	}
	else
	{
		bo_echod("No update. Last update ".(time() - $last)." seconds ago. This is normal and no error message!");
		$updated = false;
	}

	return $updated;
}



// Update strike_id <-> raw_id
function bo_match_strike2raw()
{
	static $done = false;
	if (!BO_UP_INTVL_RAW || $done)
		return;
	$done = true;
		
	//not really physical values but determined empirical
	$c = BO_STR2SIG_C;
	$fuzz = BO_STR2SIG_FUZZ_SEC ; //minimum fuzzy-seconds
	$dist_fuzz = BO_STR2SIG_FUZZ_SECM; //fuzzy-seconds per meter (seconds)
	$offset_fuzz = BO_STR2SIG_FUZZ_OFFSET;

	$amp_trigger = (BO_TRIGGER_VOLTAGE * BO_STR2SIG_TRIGGER_FACTOR / BO_MAX_VOLTAGE) * 256 / 2;

	//time of last update
	$last_modified = BoData::get('uptime_strikes_modified');
	
	//update only strikes that are "old enough"
	//because younger ones could be updated during the next strike-import
	//and this will overwrite "part" and "raw_id"!
	$maxtime = $last_modified - BO_MIN_MINUTES_STRIKE_CONFIRMED * 60 - 60;
	$mintime = $maxtime - 3600 * 2;
	
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
					AND time BETWEEN '".gmdate('Y-m-d H:i:s', $mintime)."' AND '".gmdate('Y-m-d H:i:s', $maxtime)."'
			ORDER BY part DESC
			LIMIT 10000
			";
	$res = BoDb::query($sql);
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
						AND (strike_id=0 OR strike_id='".$row['id']."')
						AND $nsql
				LIMIT 2";
		$res2 = BoDb::query($sql);
		$num = $res2->num_rows;
		$row2 = $res2->fetch_assoc();

		switch($num)
		{
			case 0:  $n[] = $row['id'];	break; //no raw data found
			default: $m[] = $row['id']; break; //too much lines matched

			case 1:  //exact match

				//bo_echod(round($row2['time_ns'] - $nsearch_from).' '.abs(round($row2['time_ns'] - $nsearch_to)).' '.$row2['time_ns']);

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

				//mark negative --> got strike but not tracked by blitzortung
				if (!($part[$row['id']] & 1))
					$part[$row['id']] = abs($part[$row['id']]) * -1;
				else
					$own_found++;

				break;

		}

		//bo_echod("Strike: ".$row['time'].".".$row['time_ns']." <-> Your Station: ".$row2['time'].".".$row2['time_ns']." Num Rows: ".$num);
		
		
		//Timeout
		if (bo_exit_on_timeout())
		{
			$timeout = true;
			break;
		}
	}

	bo_echod(" ");
	bo_echod("== Assign raw data to strikes ==");
	bo_echod("Max strike time: ".date('Y-m-d H:i:s', $maxtime)." | Strikes: ".(count($u) + count($n) + count($m))." | Own: ".$own_strikes);
	bo_echod("Found unique: ".count($u).
			" | Found own unique: ".$own_found." (Rate ".round($own_strikes ? $own_found / $own_strikes * 100 : 0,1)."%)".
			" | Not found: ".count($n).
			" | Multiple Sig->Str: ".count($m).
			" | Multiple Str->Sig: ".count($d2));

	//Update matched
	foreach($u as $data)
	{
		$strike_id = $data[1];
		$raw_id = $data[0];
		$sql = "UPDATE ".BO_DB_PREF."strikes
				SET
					raw_id='$raw_id',
					polarity='".intval($polarity[$strike_id])."',
					part='".intval($part[$strike_id])."'
				WHERE id='$strike_id'";
		BoDb::query($sql);

		BoDb::query("UPDATE ".BO_DB_PREF."raw SET strike_id='$strike_id' WHERE id='$raw_id'");
	}

	//Update unmatched
	$d = array_merge($n, $m);
	$sql = '';
	foreach($d as $strike_id)
		$sql .= " OR id='$strike_id' ";
	$sql = "UPDATE ".BO_DB_PREF."strikes SET raw_id='0' WHERE 1=0 $sql";
	BoDb::query($sql);

	return true;
}

// Get stations-data and statistics from blitzortung.org
function bo_update_stations($force = false)
{
	$last = BoData::get('uptime_stations_try');

	bo_echod(" ");
	bo_echod("=== Stations ===");

	if (!defined('BO_UP_INTVL_STATIONS') || !BO_UP_INTVL_STATIONS || time() < $last)
	{
		bo_echod("Disabled!");
	}
	
	
	if (bo_version() < 801)
	{
		bo_echod("Database version too old. Need update...");
		return;
	}

	$count_inserted = 0;
	$count_updated = 0;
	$count_noupdate = 0;

	if (time() - $last > BO_UP_INTVL_STATIONS * 60 - 30 || $force)
	{
		BoData::set('uptime_stations_try', time());

		$StData = array();
		$signal_count = 0;
		$time = time();

		//send a last modified header
		if (!$force)
		{
			$last_modified = BoData::get('uptime_stations_modified');
			$modified = $last_modified;
		}
		
		$range = 0;
		$file = bo_get_file(bo_access_url().BO_IMPORT_PATH_STATIONS, $code, 'stations', $range, $modified);


		//wasn't modified
		if ($code == 304)
		{
			if ($last_modified > 0 && time() - $last_modified > 3600 * 2)
			{
				bo_echod("Last modified time too old (Blitzortung.org down?). Setting modified to now!");
				BoData::set('uptime_strikes_modified', time());
			}

			BoData::set('uptime_stations', time());
			bo_echod("File not changed since last download (".date('r', $modified).")");
			return false;
		}


		//set the modified header
		if (intval($modified) <= 0)
			$modified = time() - 180;
		BoData::set('uptime_stations_modified', $modified);


		//ERROR
		if ($file === false)
		{
			bo_update_error('stationdata', 'Download of station data failed. '.$code);
			bo_echod("ERROR: Couldn't get file for stations! Code: $code");
			return false;
		}

		$cache_file  = BO_DIR.BO_CACHE_DIR.'/stations.txt.gz';
		
		if (!file_put_contents($cache_file, $file))
		{
			bo_update_error('stationdata', 'Cannot write cache file');
			bo_echod("ERROR: Cannot write cache file");
			return false;
		}
		
		$tmp = gzfile($cache_file);
		$file = implode('', $tmp);
		$tmp = array();
		
		if (!$file)
		{
			bo_update_error('stationdata', 'Could not uncompress station data. '.$code);
			bo_echod("ERROR: Could not uncompress station data.");
			return false;
		}
		
		$lines = explode("\n", $file);

		//we need the current station data for later usage
		$all_stations = bo_stations('bo_station_id');

		foreach($all_stations as $boid => $d)
			$id2bo[$d['id']] = $boid;

		//check if sth went wrong
		if (count($lines) < 3 || (count($lines) < (count($all_stations)-10) * BO_UP_STATION_DIFFER))
		{
		
			bo_update_error('stationcount', 'Station count differs too much: '.count($all_stations).' in Database / '.count($lines).' lines in stations.txt');
			BoData::set('uptime_stations', time());

			return;
		}


		//Debug output
		$loadcount = BoData::get('upcount_stations');
		BoData::set('upcount_stations', $loadcount+1);
		bo_echod('Last update: '.date('Y-m-d H:i:s', $last).' *** This is update #'.$loadcount);

		//reset error counter
		bo_update_error('stationdata', true);
		bo_update_error('stationcount', true);

		$activebyuser = array();

		foreach($lines as $l)
		{
			$D = array();
			$error = 0;
			$l = trim($l);
			$utime = 0;
			
			if (!$l)
				continue;
			
			//Station-Id
			if (preg_match('/station;([0-9]+)/', $l, $r))
				$D['bo_station_id'] = $r[1];
			else
				$error++;

			//User-Id
			if (preg_match('/user;([0-9]+)/', $l, $r))
				$D['bo_user_id'] = $r[1];
			else
				$error++;

			//MyBlitzortung Status
			if (preg_match('/myblitz;"?([^" ]+)"?/', $l, $r))
				$D['show_mybo'] = $r[1];

			//City
			if (preg_match('/city;"([^"]+)"/', $l, $r))
				$D['city'] = $r[1];

			//Country
			if (preg_match('/country;"([^"]+)"/', $l, $r))
				$D['country'] = $r[1];
			
			//Position
			if (preg_match('/pos;([-0-9\.]+);([-0-9\.]+);([-0-9\.]+)/', $l, $r))
			{
				$D['lat'] = $r[1];
				$D['lon'] = $r[2];
				$D['alt'] = $r[3];
			}
			else
				$error++;
			
			//PCB
			if (preg_match('/board;"?([^ ]+)"?/', $l, $r))
				$D['controller_pcb'] = strtr($r[1], array('"' =>''));
			
			//Status
			if (preg_match('/status;"?([^ ]+)"?/', $l, $r))
				$D['status'] = $r[1];
			
			//Firmware
			if (preg_match('/firmware;"([^"]+)"/', $l, $r))
				$D['firmware'] = $r[1];

			//Comment
			if (preg_match('/comments;"([^"]+)"/', $l, $r))
				$D['comment'] = $r[1];

			//Website
			if (preg_match('/website;"([^"]+)"/', $l, $r))
				$D['url'] = $r[1];
				
			//Signals
			if (preg_match('/signals;"?(([^ ;]+);)?([^ ;]+)"?/', $l, $r))
			{
				if (!trim($r[2]))
				{
					$D['signals'][60] = $r[3];
				}
				else
				{
					$times  = explode(',', $r[2]);
					$values = explode(',', $r[3]);
					
					foreach($times as $i => $t)
						$D['signals'][$t] = $values[$i];
				}
			}

			//Last Signal
			if (preg_match('/last_signal;"([-: 0-9]+)" ?/', $l, $r))
			{
				$D['last_time'] = $r[1];
				//$D['last_time_ns'] = $r[3];
				$utime = strtotime($D['last_time'].' UTC');
			}
			
			
			//Amplifier data
			if (preg_match('/input_board;"?([^ ]+)"?/', $l, $r))
				$D['amp_pcbs'] = $r[1];
			
			if (preg_match('/input_gain;"?([^ ]+)"?/', $l, $r))
				$D['amp_gains'] = $r[1];

			if (preg_match('/input_antenna;"?([^ ]+)"?/', $l, $r))
				$D['amp_antennas'] = $r[1];

			if (preg_match('/input_firmware;"?([^ ]+)"?/', $l, $r))
				$D['amp_firmwares'] = strtr($r[1], array('"' =>'')); //qick&dirty :-/
				
				
				
			if (empty($D) || $error)
			{
				continue;
			}

			$id = $D['bo_station_id'];
			
			$file_truncated = false;
			$D['distance'] = round(bo_latlon2dist($D['lat'], $D['lon']) / 100) * 100;
			
			if ($id <= 0)
			{
				bo_echod("Wrong station Id $id ".$D['country'].'/'.$D['city']);
				continue;
			}
			
			//Set station inactive
			if (time() - $utime > 3600 * 24 * BO_STATION_INACTIVE_DAYS)
				$D['status'] = 0;
			
			//Data for statistics
			$StData[$id] = array(
				'time' => $utime, 
				'lat' => $D['lat'], 
				'lon' => $D['lon'],
				'sig' => $D['signals'][60],
				'status' => $D['status']);

		
			//build query
			$sql = '';
			foreach($D as $name => $val)
			{
				if (is_array($val)) //array ==> statistics
					continue;
					
				$val = stripslashes($val);
					
				if ($val != $all_stations[$id][$name] || $force)
					$sql .= ($sql ? ',' : '').' '.$name."='".BoDb::esc($val)."' ";
			}
			
			if ($sql)
			{
				if (isset($all_stations[$id]))
				{
					$sql = "UPDATE ".BO_DB_PREF."stations SET $sql WHERE bo_station_id='".$id."' AND bo_station_id > 0 LIMIT 1";
					
					BoDb::query($sql);
					$count_updated++;
				}
				else
				{ 
				
					//find new ID
					$row = BoDb::query("SELECT MAX(id) id FROM ".BO_DB_PREF."stations WHERE id < ".intval(BO_DELETED_STATION_MIN_ID))->fetch_assoc();
					$new_id = $row['id']+1;

					$sql .= " , id='$new_id', first_seen='".gmdate('Y-m-d H:i:s')."'";
					BoDb::query("INSERT INTO ".BO_DB_PREF."stations SET $sql", false);
					$count_inserted++;
				}
			}
			else
				$count_noupdate++;
			
			$signal_count += $D['signals'][60];

		}
		
		bo_echod("Stations: ".(count($lines)-2)." *** New Stations: $count_inserted *** Updated: $count_updated *** No Update: $count_noupdate");

		
		
		//set station offline if changed too long ago
		$num = BoDb::query("UPDATE ".BO_DB_PREF."stations SET status=".(STATUS_OFFLINE*10)." WHERE status!=0 AND changed < '".gmdate('Y-m-d H:i:s', time() - 60 * BO_STATION_OFFLINE_MINUTES)."'");
		if ($num)
			bo_echod("Set $num stations offline");

		//deactivate old stations which are not in file
		$num = BoDb::query("UPDATE ".BO_DB_PREF."stations SET status=0 WHERE changed < '".gmdate('Y-m-d H:i:s', time() - 60 * BO_STATION_NOT_PRESENT_MINUTES)."'");
		if ($num)
			bo_echod("Deactivated $num stations");
		
		//Update Statistics
		$datetime      = gmdate('Y-m-d H:i:s', $time);
		$datetime_back = gmdate('Y-m-d H:i:s', $time - 3600);

		$only_own = false;
		$own_stations = bo_stations_own();
		$from_all_stations = !(defined('BO_STATION_STAT_DISABLE') && BO_STATION_STAT_DISABLE == true);
		
		if ($from_all_stations && count($own_stations) == 1)
		{
			$only_own = bo_station_id();
			$sql = "SELECT $only_own sid, COUNT(*) cnt
					FROM ".BO_DB_PREF."strikes
					WHERE time > '$datetime_back' AND part > 0";
			$res = BoDb::query($sql);
		}
		else
		{
			$sql = "SELECT a.station_id sid, COUNT(a.station_id) cnt
					FROM ".BO_DB_PREF."stations_strikes a, ".BO_DB_PREF."strikes b
					WHERE a.strike_id=b.id AND b.time > '$datetime_back'
					GROUP BY a.station_id";		
			$res = BoDb::query($sql, false);
			
			if ($res === false)
			{
				bo_echod("ERROR on query for station count! You have to set SQL_BIG_SELECTS in your MySQL Database or set BO_STATION_STAT_DISABLE to true (recommended)!");
			}
		}
		
		if ($res !== false)
		{
			while ($row = $res->fetch_assoc())
			{
				$boid = $id2bo[intval($row['sid'])];
				$StData[$boid]['strikes'] = $row['cnt'];
			}
		}
		
		$active_stations = 0;
		$active_sig_stations = 0;
		$active_avail_stations = 0;
		$active_nogps = 0;
		$sql_data = array();
		
		foreach($StData as $boid => $data)
		{
			$id = $all_stations[$boid]['id'];
			
			if (!$id)
				continue;
			else if ($only_own && $only_own != $id)
				continue;
			else if (!$from_all_stations && !$own_stations[$boid])
				continue;
				
			
			if ($data['sig'] || $data['strikes'])
			{
				$sql_data['station_id'] = $id;
				$sql_data['time'] = $datetime;
				$sql_data['signalsh'] = intval($data['sig']);
				$sql_data['strikesh'] = intval($data['strikes']);
				BoDb::bulk_insert('stations_stat', $sql_data);
				
				$active_sig_stations++;
			}

			if (bo_status($data['status'], STATUS_RUNNING))
				$active_stations++;

			if (!bo_status($data['status'], STATUS_OFFLINE)) //Station is available (no dummy entry, has sent some data some time ago)
				$active_avail_stations++;

			if (bo_status($data['status'], STATUS_BAD_GPS)) //GPS is unavailable right now
				$active_nogps++;

		}

		//Update whole strike count for dummy station "0"
		$sql = "SELECT COUNT(id) cnt
				FROM ".BO_DB_PREF."strikes
				WHERE time > '$datetime_back'";
		$row = BoDb::query($sql)->fetch_assoc();
		$strike_count = $row['cnt'];

		$sql_data['station_id'] = 0;
		$sql_data['time'] = $datetime;
		$sql_data['signalsh'] = intval($signal_count);
		$sql_data['strikesh'] = intval($strike_count);
		BoDb::bulk_insert('stations_stat', $sql_data);
		BoDb::bulk_insert('stations_stat');
		BoData::set('active_stations_nogps', $active_nogps);


		
		/*** Update Longtime statistics ***/
		//max active stations ever
		$max = BoData::get('longtime_count_max_active_stations');
		if ($active_stations > $max)
		{
			BoData::set('longtime_count_max_active_stations', max($max, $active_stations));
			BoData::set('longtime_count_max_active_stations_time', $time);
		}

		//max active stations (sending signals) ever
		$max = BoData::get('longtime_count_max_active_stations_sig');
		if ($active_sig_stations > $max)
		{
			BoData::set('longtime_count_max_active_stations_sig', max($max, $active_sig_stations));
			BoData::set('longtime_count_max_active_stations_sig_time', $time);
		}

		//max available stations (had sent some signales, no matter when)
		$max = BoData::get('longtime_count_max_avail_stations');
		if ($active_avail_stations > $max)
		{
			BoData::set('longtime_count_max_avail_stations', max($max, $active_avail_stations));
			BoData::set('longtime_count_max_avail_stations_time', $time);
		}

		//max signals/h
		$max = BoData::get('longtime_max_signalsh');
		if ($signal_count > $max)
		{
			BoData::set('longtime_max_signalsh', max($max, $signal_count));
			BoData::set('longtime_max_signalsh_time', $time);
		}

		//max strikes/h
		$max = BoData::get('longtime_max_strikesh');
		if ($strike_count > $max)
		{
			BoData::set('longtime_max_strikesh', max($max, $strike_count));
			BoData::set('longtime_max_strikesh_time', $time);
		}


		/*** Longtime stat. for own Station ***/
		$own_id = bo_station_id();
		$longtime_stations = array();

		if (BO_ENABLE_LONGTIME_ALL === true)
		{
			$longtime_stations = $id2bo;
		}
		else 
		{
			$longtime_stations = array_flip($own_stations);
		}
		
		foreach($longtime_stations as $stId => $boid)
		{
			$add = $stId == $own_id ? '' : '#'.$stId.'#';

			//whole signals count (not exact)
			if ($last > 0 && $StData[$boid]['sig'])
				BoData::update_add('count_raw_signals2'.$add, $StData[$boid]['sig']*($time-$last)/3600);

			//max signals/h (own)
			$new = BoData::update_if_bigger('longtime_max_signalsh_own'.$add, $StData[$boid]['sig']);
			if ($new > 0)
				BoData::set('longtime_max_signalsh_own_time'.$add, $time);

			//max strikes/h (own)
			$new = BoData::update_if_bigger('longtime_max_strikesh_own'.$add, $StData[$boid]['strikes']);
			if ($new > 0)
				BoData::set('longtime_max_strikesh_own_time'.$add, $time);
				
			//Activity/inactivity counter
			$time_interval = $last ? $time - $last : 0;
			if (bo_status($StData[$boid]['status'], STATUS_RUNNING))
			{
				//save first data-time
				if ($stId != $own_id)
				{
					$longtime_time = BoData::get('longtime_station_first_time'.$add);

					if (!$longtime_time)
						BoData::set('longtime_station_first_time'.$add, time());
				}

				BoData::set('station_last_active'.$add, $time);
				BoData::update_add('longtime_station_active_time'.$add, $time_interval);
			}
			else
			{
				BoData::set('station_last_inactive'.$add, $time);
				BoData::update_add('longtime_station_inactive_count'.$add, 1);
				
				//don't update offline counter if station is in test phase (first days)
				if (BoData::get('longtime_station_active_time'.$add) > 3600 * BO_STATISTICS_COUNT_OFFLINE_AFTER_MIN_ONLINE_HOURS)
				{
					BoData::update_add('longtime_station_inactive_time'.$add, $time_interval);
				}
			}


			//Activity/inactivity counter (GPS V-state)
			$time_interval = $last ? $time - $last : 0;
			if (bo_status($StData[$boid]['status'], STATUS_BAD_GPS))
			{
				BoData::set('station_last_nogps'.$add, $time);
				BoData::update_add('longtime_station_nogps_time'.$add, $time_interval);
				BoData::update_add('longtime_station_nogps_count'.$add, 1);
			}

			
			//Station positions for last 24h, every station-update interval (15min)
			if (time() - $StData[$boid]['time'] < 3600 * 2)
			{
				$data = unserialize(BoData::get('station_data24h'.$add));
				if ($data['time'] != $StData[$boid]['time'])
				{
					$time_floor = floor(date('Hi', $StData[$boid]['time']) / BO_UP_INTVL_STATIONS);
					$data[$time_floor] = $StData[$boid];
					BoData::set('station_data24h'.$add, serialize($data));
				}
			}

		}


		BoData::set('uptime_stations', time());


		//send mail to owner if no signals
		if (0 && BO_TRACKER_WARN_ENABLED === true && $own_id > 0)
		{
			$data = unserialize(BoData::get('warn_tracker_offline'));
			$owndata = $StData[ bo_station_id(true) ];
			
			if ($owndata['sig'] == 0)
			{
				if (!isset($data['offline_since']))
					$data['offline_since'] = time();

				if (time() - $data['offline_since'] >= ((double)BO_TRACKER_WARN_AFTER_HOURS * 3600))
				{
					if (time() - $data['last_msg'] >= ((double)BO_TRACKER_WARN_RESEND_HOURS * 3600))
					{
						$data['last_msg'] = time();
						BoData::set('warn_tracker_offline', serialize($data));
						bo_owner_mail('Station OFFLINE', "Your station/tracker didn't send any signals since ".date(_BL('_datetime'),$data['offline_since'] - 3600)." ! If there is lightning near your station, your tracker might be in interference mode. In this case you can ignore this message.");
					}
				}
			}
			else if ($data['last_msg']) //reset
			{
				$data = array();
				bo_owner_mail('Station ONLINE', "Your station/tracker is online again! ".$owndata['sig']." signals in the last 60 minutes.");
			}

			BoData::set('warn_tracker_offline', serialize($data));
		}

		bo_update_status_files('stations');

		$updated = true;
	}
	else
	{
		bo_echod("No update. Last update ".(time() - $last)." seconds ago. This is normal and no error message!");
		$updated = false;
	}

	return $updated;
}

// Daily and longtime strike counts
function bo_update_daily_stat($time = false, $force_renew = false)
{
	global $_BO;

	$ret = false;

	if (bo_version() < 801)
	{
		bo_echod("Database version too old. Need update...");
		return;
	}
	
	//default yesterday
	if (!$time) 
		$time = mktime(date('H')-3,0,0,date("n"),date('j')-1);
	
	$day_id = gmdate('Ymd', $time);
	
	if (!$force_renew)
	{
		$data = unserialize(BoData::get('strikes_'.$day_id));
	}
	

	// Daily Statistics and longtime strike-count
	if (!$data || !is_array($data) || !$data['done'])
	{
		bo_echod(" ");
		bo_echod("=== Updating daily statistics ($day_id) ===");

		$radius = BO_RADIUS_STAT * 1000;
		$day_start = gmdate('Y-m-d H:i:s', strtotime(gmdate('Y-m-d 00:00:00', $time)));
		$day_end =   gmdate('Y-m-d H:i:s', strtotime(gmdate('Y-m-d 23:59:59', $time)));

		$stat_ok = BoData::get('uptime_stations') > $day_end + 60;
		//$raw_ok  = BoData::get('uptime_raw') > $day_end + 60;

		// Strikes SQL template
		$sql_template = "SELECT COUNT(id) cnt FROM ".BO_DB_PREF."strikes s WHERE {where} time BETWEEN '$day_start' AND '$day_end'";

		if (!is_array($data) || $data['status'] < 1)
		{
			/*** Strikes ***/
			//whole strike count
			$row_all = BoDb::query(strtr($sql_template,array('{where}' => '')))->fetch_assoc();
			//own strike count
			$row_own = BoDb::query(strtr($sql_template,array('{where}' => 'part > 0 AND ')))->fetch_assoc();
			//whole strike count (in radius)
			$row_all_rad = BoDb::query(strtr($sql_template,array('{where}' => 'distance < "'.$radius.'" AND ')))->fetch_assoc();
			//own strike count (in radius)
			$row_own_rad = BoDb::query(strtr($sql_template,array('{where}' => 'part > 0 AND distance < "'.$radius.'" AND ')))->fetch_assoc();

			/*** Save the data ***/
			$data = array(	0 => $row_all['cnt'],
							1 => $row_own['cnt'],
							2 => $row_all_rad['cnt'],
							3 => $row_own_rad['cnt']
						);

						
			/*** Longtime statistics ***/
			$D = unserialize(BoData::get('longtime_max_strikes_day_all'));

			if ($D[0] < $row_all['cnt'])
				BoData::set('longtime_max_strikes_day_all', serialize(array($row_all['cnt'], $time)));

			$D = unserialize(BoData::get('longtime_max_strikes_day_all_rad'));
			if ($D[0] < $row_all_rad['cnt'])
				BoData::set('longtime_max_strikes_day_all_rad', serialize(array($row_all_rad['cnt'], $time)));

			$D = unserialize(BoData::get('longtime_max_strikes_day_own'));
			if ($D[0] < $row_own['cnt'])
				BoData::set('longtime_max_strikes_day_own', serialize(array($row_own['cnt'], $time)));

			$D = unserialize(BoData::get('longtime_max_strikes_day_own_rad'));
			if ($D[0] < $row_own_rad['cnt'])
				BoData::set('longtime_max_strikes_day_own_rad', serialize(array($row_own_rad['cnt'], $time)));

			$data['status'] = 1;
		}
		else if ($data['status'] == 1 )
		{
			//strike count per region
			if (isset($_BO['region']) && is_array($_BO['region']))
			{
				foreach ($_BO['region'] as $reg_id => $d)
				{
					//all strikes in a region
					$row = BoDb::query(strtr($sql_template,array('{where}' => '1 '.bo_region2sql($reg_id).' AND ')))->fetch_assoc();
					$strikes_region[$reg_id]['all'] = $row['cnt'];

					//own strikes in a region
					$row = BoDb::query(strtr($sql_template,array('{where}' => 'part>0 '.bo_region2sql($reg_id).' AND ')))->fetch_assoc();
					$strikes_region[$reg_id]['own'] = $row['cnt'];
				}
			}

			$data[7] = $strikes_region;
			$data['status'] = 2;
		}
		else if ($data['status'] == 2 && $stat_ok)
		{
			/*** Stations ***/
			$stations = array();
			
			// available stations
			$row = BoDb::query("SELECT COUNT(*) cnt FROM ".BO_DB_PREF."stations WHERE status != '-'")->fetch_assoc();
			$stations['available'] = $row->cnt;

			// active stations
			$row = BoDb::query("SELECT COUNT(*) cnt FROM ".BO_DB_PREF."stations WHERE status = '".STATUS_RUNNING."'")->fetch_assoc();
			$stations['active'] = $row->cnt;

			$data[10] = $stations;
			$data['status'] = 3;
			$data['done'] = true;
		}

		$sdata = serialize($data);
		BoData::set('strikes_'.$day_id, $sdata);
		bo_echod("Datasets: ".count($data)." Length: ".strlen($sdata)." Status: ".$data['status']);
		$ret = true;
		
		
		
		//Update strike-count per month
		if ($data['done'])
		{
			$month_id = gmdate('Ym', $month_time);
			$last_month_count = BoData::get('strikes_month_'.$month_id);
			
			if (!$last_month_count)
			{
				$data = array();
				$data['daycount'] = 0;

				while($row = BoData::get_all('strikes_'.$month_id.'%'))
				{
					$d = unserialize($row['data']);

					foreach($d as $id => $cnt)
					{
						if (!is_array($cnt))
							$data[$id] += $cnt;
					}

					$data['daycount']++;
				}

				BoData::set('strikes_month_'.$month_id, serialize($data));

				bo_echod("Monthly data '$month_id' updated.");
			}
		}
		
	}

	
	return $ret;
}



function bo_update_tracks($force = false)
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
	$MinStrikeCount = intval(BO_TRACKS_MIN_STRIKE_COUNT);
	$MaxStrikes = intval(BO_TRACKS_MAX_STRIKE_COUNT);

	
	if (!$scantime || !$divisor || !$MaxStrikes)
		return;

	$start_time = time();

	$last = BoData::get('uptime_tracks');

	bo_echod(" ");
	bo_echod("=== Tracks ===");

	if (time() - $last > BO_UP_INTVL_TRACKS * 60 - 30 || $force || time() < $last)
	{
		BoData::set('uptime_tracks', time());
		$time = time();

		//Debug
		//$time = strtotime('2011-06-16 17:00:00 UTC');

		//divide time range
		for ($i=0;$i<$divisor;$i++)
			$stime[] = $time + $scantime * 60 * ($i / $divisor - 1);

		$stime[] = $time;

		for ($i = 0; $i < count($stime)-1; $i++)
		{
			$max = $MaxStrikes;
			$cells[$i] = array();

			$date_start = gmdate('Y-m-d H:i:s', $stime[$i]);
			$date_end   = gmdate('Y-m-d H:i:s', $stime[$i+1]);

			$cells_time[$i] = array('start' => $stime[$i], 'end' => $stime[$i+1]);

			$sql = "SELECT id, time, lat, lon
					FROM ".BO_DB_PREF."strikes s
					WHERE ".bo_times2sql($stime[$i], $stime[$i+2], 's', $max);
			
			if (is_array($max) && $max['divisor'])
				$multiplicator = $max['divisor'];
			else
				$multiplicator = 1;
					
					
			$res = BoDb::query($sql);
			while($row = $res->fetch_assoc())
			{
				//End this ugly calculation to prevent running in php-timeout
				if (bo_exit_on_timeout())
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
					                          'count'   => count($cell['strikes']) * $multiplicator,
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

		if ($force)
		{
			$lines = explode("\n", print_r($data,1));
			foreach($lines as $line)
				bo_echod($line);
		}

		BoData::set('strike_cells', serialize($data));
	}
	else
	{
		bo_echod("Nothing to do.");
	}
}



function bo_update_error($type, $extra = null)
{
	// reset
	if ($extra === true)
	{
		BoData::set('uperror_'.$type, serialize(array()));
		return;
	}

	//Read
	$data = unserialize(BoData::get('uperror_'.$type));

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

		$text = $extra;
		$text .= "\n\nLast error: ".date('Y-m-d H:i:s', $data['last']);
		$text .= "\nFirst error: ".date('Y-m-d H:i:s', $data['first']);
		$text .= "\nError count: ".$data['count'];

		bo_owner_mail(_BL('MyBlitzortung_notags').' - '._BL('Error').': '.$type, $text);
	}

	bo_echod("UPDATE ERROR: [$type] $extra");
	
	//Write
	BoData::set('uperror_'.$type, serialize($data));
}


function bo_update_status_files($type)
{
	if (BO_ENABLE_STATUS_FILES !== true)
		return true;

	$cfile = BO_DIR.'status/status.cfg';

	if (!file_exists($cfile))
		return false;


	$row = BoDb::query("SELECT signalsh, strikesh FROM ".BO_DB_PREF."stations_stat WHERE station_id='".bo_station_id()."' AND time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)")->fetch_assoc();
	$STATION_CURRENT_STRIKES = $row['strikesh'];
	$STATION_CURRENT_SIGNALS = $row['signalsh'];


	$last_strikes_region = unserialize(BoData::get('last_strikes_region'));
	$rate_strikes_region = unserialize(BoData::get('rate_strikes_region'));
	$STRIKE_RATE = _BN($rate_strikes_region[0], 1);
	$LAST_STRIKE = _BDT($last_strikes_region[0]);

	$tmp = @unserialize(BoData::get('last_strikes_stations'));
	$STATION_LAST_STRIKE   = _BDT($tmp[$station_id][0]);

	$act_time = BoData::get('station_last_active');
	$inact_time = BoData::get('station_last_inactive');
	$STATION_LAST_ACTIVE   = _BDT($act_time);
	$STATION_LAST_INACTIVE = _BDT($inact_time);
	$STATION_ACTIVE        = $act_time > $inact_time ? _BL('yes') : _BL('no');

	$IMPORT_LAST_STRIKES   = _BDT(BoData::get('uptime_strikes'));
	$IMPORT_LAST_STATIONS  = _BDT(BoData::get('uptime_stations'));
	$IMPORT_LAST_SIGNALS   = _BDT(BoData::get('uptime_raw'));

	$replace = array(
		'{STATION_CURRENT_STRIKES}'  => $STATION_CURRENT_STRIKES,
		'{STATION_CURRENT_SIGNALS}'  => $STATION_CURRENT_SIGNALS,
		'{STRIKE_RATE}'              => $STRIKE_RATE,
        '{LAST_STRIKE}'              => $LAST_STRIKE,
		'{STATION_ACTIVE}'           => $STATION_ACTIVE,
        '{STATION_LAST_STRIKE}'      => $STATION_LAST_STRIKE,
		'{STATION_LAST_ACTIVE}'      => $STATION_LAST_ACTIVE,
		'{STATION_LAST_INACTIVE}'    => $STATION_LAST_INACTIVE,
		'{IMPORT_LAST_STRIKES}'      => $IMPORT_LAST_STRIKES,
		'{IMPORT_LAST_STATIONS}'     => $IMPORT_LAST_STRIKES,
		'{IMPORT_LAST_SIGNALS}'      => $IMPORT_LAST_STRIKES,

	);

	$config = file_get_contents($cfile);
	$lines = explode("\n", $config);
	chdir(BO_DIR);
	$i = 0;
	foreach($lines as $line)
	{
		$line = trim($line);

		if (substr($line,0,1) == '#')
			continue;

		$d = explode(";", $line);

		if (count($d) != 3)
			continue;

		$cfg_file = trim($d[0]);
		$cfg_dir  = trim($d[1]);
		$cfg_type = strtolower(trim($d[2]));

		if ($cfg_type == $type)
		{
			$content = file_get_contents(BO_DIR.'status/'.$cfg_file);

			if (!$content)
				continue;


			$content = strtr($content, $replace);

			file_put_contents($cfg_dir.'/'.$cfg_file, $content);

			$i++;
		}
	}

	return $i;
}


function bo_readline(&$text = null, $searchlen = 1000)
{
	static $line_nr=0, $pos=0;

	if (!$text)
	{
		$line_nr=0;
		$pos=0;
		return;
	}

	$textpart = substr($text, $pos, $searchlen);
	$newpos = strpos($textpart, "\n");

	if ($newpos === false)
		return false;

	$line = substr($textpart, 0, $newpos);
	$pos += $newpos + 1;
	$line_nr++;

	return $line;

}



function bo_purge_olddata($force = false)
{

	$last = BoData::get('purge_time');
	
	if ($force || (defined('BO_PURGE_MAIN_INTVL') && BO_PURGE_MAIN_INTVL && time() - $last > 3600 * BO_PURGE_MAIN_INTVL))
	{
		bo_echod(" ");
		bo_echod("=== Purging data ===");
		
		//silently purge faulty entries
		BoDb::query("DELETE FROM ".BO_DB_PREF."strikes WHERE time < '2000-01-01 00:00:00'");
		
		if (BO_PURGE_ENABLE === true)	
		{
			BoData::set('purge_time', time());

			$num_strikes = 0;
			$num_stations = 0;
			$num_signals = 0;
			$num_stastr = 0;

			//Raw-Signals, where no strike assigned
			if (defined('BO_PURGE_SIG_NS') && BO_PURGE_SIG_NS)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_SIG_NS * 3600);
				$num = BoDb::query("DELETE FROM ".BO_DB_PREF."raw WHERE time < '$dtime' AND strike_id=0");
				$num_signals += $num;
				bo_echod("Raw signals (with no strikes assigned): $num");
			}


			//All Raw-Signals
			if (defined('BO_PURGE_SIG_ALL') && BO_PURGE_SIG_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_SIG_ALL * 3600);
				$num = BoDb::query("DELETE FROM ".BO_DB_PREF."raw WHERE time < '$dtime'");
				$num_signals += $num;
				bo_echod("Raw signals: $num");
			}

			//Strikes (not participated)
			if (defined('BO_PURGE_STR_NP') && BO_PURGE_STR_NP)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STR_NP * 3600);
				$num1  = BoDb::query("DELETE a,b FROM ".BO_DB_PREF."strikes a, ".BO_DB_PREF."stations_strikes b
						WHERE time < '$dtime' AND a.id=b.strike_id AND part=0");
				$num2  = BoDb::query("DELETE FROM ".BO_DB_PREF."strikes WHERE time < '$dtime' AND part=0"); //to be sure

				$num_stastr += $num1;
				$num_strikes += $num2;

				bo_echod("Strikes (not participated): ".($num1+$num2)."");
			}

			//Strikes (far away)
			if (defined('BO_PURGE_STR_DIST') && BO_PURGE_STR_DIST && defined('BO_PURGE_STR_DIST_KM') && BO_PURGE_STR_DIST_KM)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STR_DIST * 3600);
				$num1  = BoDb::query("DELETE a,b FROM ".BO_DB_PREF."strikes a, ".BO_DB_PREF."stations_strikes b
						WHERE time < '$dtime' AND a.id=b.strike_id AND distance > '".(BO_PURGE_STR_DIST_KM * 1000)."'");
				$num2  = BoDb::query("DELETE FROM ".BO_DB_PREF."strikes WHERE time < '$dtime' AND distance > '".(BO_PURGE_STR_DIST_KM * 1000)."'"); //to be sure

				$num_stastr += $num1;
				$num_strikes += $num2;

				bo_echod("Strikes (over ".BO_PURGE_STR_DIST_KM."km away): ".($num1+$num2)."");
			}

			//All Strikes
			if (defined('BO_PURGE_STR_ALL') && BO_PURGE_STR_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STR_ALL * 3600);
				$num1  = BoDb::query("DELETE a,b FROM ".BO_DB_PREF."strikes a, ".BO_DB_PREF."stations_strikes b
						WHERE time < '$dtime' AND a.id=b.strike_id");
				$num2 = BoDb::query("DELETE FROM ".BO_DB_PREF."strikes WHERE time < '$dtime'"); //to be sure

				$num_stastr += $num1;
				$num_strikes += $num2;

				bo_echod("Strikes: ".($num1+$num2)."");
			}

			//Strike <-> Station table
			if (defined('BO_PURGE_STRSTA_ALL') && BO_PURGE_STRSTA_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STRSTA_ALL * 3600);
				$row = BoDb::query("SELECT id FROM ".BO_DB_PREF."strikes WHERE time=(SELECT MAX(time) id FROM ".BO_DB_PREF."strikes WHERE time < '$dtime')")->fetch_assoc();
				$strId = $row['id'];
				$num = BoDb::query("DELETE FROM ".BO_DB_PREF."stations_strikes WHERE strike_id < '$strId'");
				$num_stastr += $num;
				bo_echod("Strike <-> Station table: $num");
			}

			//Station statistics (not own and whole strike count)
			if (defined('BO_PURGE_STA_OTHER') && BO_PURGE_STA_OTHER)
			{
				$stId = bo_station_id();
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STA_OTHER * 3600);
				$num = BoDb::query("DELETE FROM ".BO_DB_PREF."stations_stat WHERE time < '$dtime' AND station_id != '$stId' AND station_id != 0");
				$num_stations += $num;
				bo_echod("Station statistics (not yours): $num");
			}

			//All station statistics
			if (defined('BO_PURGE_STA_ALL') && BO_PURGE_STA_ALL)
			{
				$dtime = gmdate('Y-m-d H:i:s', time() - BO_PURGE_STA_ALL * 3600);
				$num = BoDb::query("DELETE FROM ".BO_DB_PREF."stations_stat WHERE time < '$dtime'");
				$num_stations += $num;
				bo_echod("Station statistics: $num");
			}

			if (intval(BO_PURGE_OPTIMIZE_TABLES))
			{
				if ($num_strikes > BO_PURGE_OPTIMIZE_TABLES)
				{
					bo_echod("Optimizing strikes table");
					BoDb::query("OPTIMIZE TABLE ".BO_DB_PREF."strikes");
				}

				if ($num_stations > BO_PURGE_OPTIMIZE_TABLES)
				{
					bo_echod("Optimizing stations table");
					BoDb::query("OPTIMIZE TABLE ".BO_DB_PREF."stations_stat");
				}

				if ($num_signals > BO_PURGE_OPTIMIZE_TABLES)
				{
					bo_echod("Optimizing signals table");
					BoDb::query("OPTIMIZE TABLE ".BO_DB_PREF."raw");
				}

				if ($num_stastr > BO_PURGE_OPTIMIZE_TABLES)
				{
					bo_echod("Optimizing strikes-stations table");
					BoDb::query("OPTIMIZE TABLE ".BO_DB_PREF."stations_strikes");
				}
			}

		}
		else
		{
			bo_echod("Purging disabled.");
		}

	}

}


function bo_getset_timeout($set_max_time = 60)
{
	static $max_time = 0, $start_time = 0;
	static $last_time = 0;
	
	if (!$start_time)
		$start_time = time();
	
	
	
	if ($set_max_time && !$max_time)
	{
		$max_time = $set_max_time;
		return false;
	}

	//check duration between last calls and substract it from max allowed time
	if ($last_time)
		$duration = time() - $last_time;
	else
		$duration = 0;
		
	if ($max_time > 0 && time() - $start_time > $max_time - $duration)
	{
		return true;
	}
	else
	{
		$last_time = time();
		return false;
	}
}

function bo_exit_on_timeout()
{
	static $ptext = false;

	if (bo_getset_timeout())
	{
		if ($ptext == false)
			bo_echod("TIMEOUT! We will continue the next time.");

		$ptext = true;

		return true;
	}
	else
	{
		return false;
	}
}



function bo_update_get_timeout()
{
	$overall_timeout = intval(BO_UP_MAX_TIME);
	if (!$overall_timeout)
		$overall_timeout = 55;

	$exec_timeout = intval(ini_get('max_execution_time'));
	$max_time = $exec_timeout - 10;

	if ($debug || !$exec_timeout)
		$max_time = 300;
	else if ($max_time < 20)  //give it a try
		$max_time = 45;
	else
		$max_time = $overall_timeout;

	@set_time_limit($max_time+10);

	//recheck the new timeout
	$max_time = intval(ini_get('max_execution_time'));

	if ($max_time < 15)
		$max_time = 20;
	else if ($max_time > $overall_timeout)
		$max_time = $overall_timeout;

	bo_echod( "Information: PHP Execution timeout is ".$exec_timeout.'s '.
				($exec_timeout && $exec_timeout < 25 ? ' - Not good :(' : ' --> Fine :)').
				' *** Setting MyBlitzortung timeout to: '.$max_time."s");

	return $max_time;
}




function bo_download_external($force = false)
{
	global $_BO;


	if (!isset($_BO['download']) || count($_BO['download']) == 0)
		return;

	bo_echod(" ");
	bo_echod("=== File Downloads ===");

	$data = unserialize(BoData::get('uptime_ext_downloads'));

	if (time() - $data['last_update'] > BO_UP_INTVL_DOWNLOADS * 60 - 30 || $force || time() < $data['last_update'])
	{
		//pre-save time to avoid errors when downloads hang...
		$data['last_update'] = time();
		BoData::set('uptime_ext_downloads', serialize($data));
	
	
		
		foreach($_BO['download'] as $name => $d)
		{
			//use a short id
			$id = substr(md5($id), 0, 10);
			
			bo_echod(" - $name [$id] / Last download: ".date('Y-m-d H:i:s', $data['data'][$id]['last']).' / Last modified: '.date('Y-m-d H:i:s', $data['data'][$id]['modified']));
			
			//Download if min minute is gone and last update was interval-time before
			if (!$force)
			{
				if (isset($d['after_minute']) && (int)date('i') < $d['after_minute'])
				{
					bo_echod("    -> No download, minute has to be greater than ".$d['after_minute']);
					continue;
				}

				if (isset($data['data'][$id]['last']) && time() - $data['data'][$id]['last'] < $d['interval'] * 60)
				{
					bo_echod("    -> Needs no download, last download too close (interval ".$d['interval']."min)");
					continue;
				}
				
				if (isset($d['modified_interval']) && time() - $data['data'][$id]['modified'] < $d['modified_interval'] * 60)
				{
					bo_echod("    -> Needs no download, last modified time too close (interval ".$d['modified_interval'].")");
					continue;
				}

			}
			clearstatcache();
			
			if (!$d['time_floor'])
				$d['time_floor'] = 1;
			
			//Replace date/time modifiers of the remote url/file
			$d['url'] = bo_insert_date_string($d['url'], floor((time()+$d['time_add_remote']*60)/$d['time_floor']/60) * $d['time_floor']*60);
			$data['data'][$id]['last'] = time();
			$modified = $force ? 0 : $data['data'][$id]['modified'];
			
			//Download!
			if (substr($d['url'],0,3) == 'ftp')
			{
				//quick&dirty solution, php ftp function would be nicer
				//Todo: Download a whole directory
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $d['url']);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_FILETIME, true);
				$file_content = curl_exec($curl);
				
				if ($file_content !== false)
				{
					$modified = curl_getinfo($curl, CURLINFO_FILETIME);
				}
				
				curl_close($curl);
			}
			else
			{
				
				$range = 0;
				$file_content = bo_get_file($d['url'], $code, 'download_'.$id, $range, $modified);
			}
			
			if ($file_content === false)
			{
				if ($code == 304)
					bo_echod("    -> File not modified, no download. ");
				else
					bo_echod("    -> Error: Download failed (URL: ".$d['url']." Code: $code)!");
				
				continue;
			}
			else
			{
			
				if ($modified)
					$data['data'][$id]['modified'] = $modified;
				else
					$data['data'][$id]['modified'] = time();

				
				$file_time = $data['data'][$id]['modified'] + $d['time_add']*60;
				
				if (isset($d['time_floor']) && (int)$d['time_floor'])
					$file_time = floor($file_time/$d['time_floor']/60) * $d['time_floor']*60;
				
				if (isset($d['store_db']) && $d['store_db'])
				{
					$db_name = 'download_';
					$db_name .= bo_insert_date_string($d['store_db'], $file_time);
					$ok = BoData::set($db_name, $file_content, true);

					if (!$ok)
					{
						bo_echod("    -> Error: Writing data in Db ($ok)!");
						continue;
					}
				}
				else
				{
					//File/Directory handling
					$dir = BO_DIR.'/'.$d['dir'].'/';
					$file = bo_insert_date_string($d['file'], $file_time);
					$dir = $dir.dirname($file);
					$file = $dir.'/'.basename($file);
					
					if (!file_exists($file))
						@mkdir($dir, 0777, true);
					
					if (file_exists($file) && is_dir($file))
					{
						bo_echod("    -> Error: Filename is a directory!");
						continue;
					}
					elseif (file_exists($file) && !$d['overwrite'])
					{
						bo_echod("    -> File exists! Overwrite disabled!");
						continue;
					}
					elseif(file_exists($file) && !is_writeable($file))
					{
						bo_echod("    -> Error: Directory/file is not writeable!");
						continue;
					}
					elseif(!file_exists($file) && !is_writeable($dir))
					{
						bo_echod("    -> Error: Directory/file is not writeable!");
						continue;
					}
					
					//Save it
					$put = file_put_contents($file, $file_content);
					
					if (!$put)
					{
						bo_echod("    -> Error: Writing to file!");
						continue;
					}
				}
				
				$kbytes = round(strlen($file_content) / 1024, 1);
				bo_echod("    -> Success: Saved $kbytes kB, File last modified: ".gmdate('Y-m-d H:i:s', $modified).' UTC');
			}
			
		}

		
		
		$data['last_update'] = time();
		BoData::set('uptime_ext_downloads', serialize($data));

	}
	else
	{
		bo_echod("No update. Last update ".(time() - $data['last_update'])." seconds ago. This is normal and no error message!");
	}
	
	return;
	
}


function bo_purge_cache($force = false)
{
	$whole_count = 0;
	
	//Purge Maps
	if (intval(BO_CACHE_PURGE_MAPS_RAND) > 0 && rand(0, BO_CACHE_PURGE_MAPS_RAND) == 1)
	{	
		bo_echod("=== Cache Purge: Maps ===");
		$count = bo_delete_files(BO_DIR.BO_CACHE_DIR.'/maps/', intval(BO_CACHE_PURGE_MAPS_HOURS), 8);
		bo_echod(" -> Deleted $count files");
		$whole_count += $count;
	}

	
	//Purge Tiles
	if (intval(BO_CACHE_PURGE_TILES_RAND) > 0 && rand(0, BO_CACHE_PURGE_TILES_RAND) == 1)
	{
		bo_echod("=== Cache Purge: Tiles ===");
		$count = bo_delete_files(BO_DIR.BO_CACHE_DIR.'/tiles/', BO_CACHE_PURGE_TILES_HOURS, 8);
		bo_echod(" -> Deleted $count files");
		$whole_count += $count;
	}

	
	//Purge Densities
	if (intval(BO_CACHE_PURGE_DENS_RAND) > 0 && rand(0, BO_CACHE_PURGE_DENS_RAND) == 1)
	{
		bo_echod("=== Cache Purge: Densities ===");
		
		if (BO_CACHE_SUBDIRS === true)
			$count = bo_delete_files(BO_DIR.BO_CACHE_DIR.'/densitymap', intval(BO_CACHE_PURGE_DENS_HOURS), 8);
		else
			$count = bo_delete_files(BO_DIR.BO_CACHE_DIR, intval(BO_CACHE_PURGE_DENS_HOURS), 0);
		
		bo_echod(" -> Deleted $count files");
		$whole_count += $count;
	}
	
	
	//Purge other files
	if (intval(BO_CACHE_PURGE_OTHER_RAND) > 0 && rand(0, BO_CACHE_PURGE_OTHER_RAND) == 1)
	{
		bo_echod("=== Cache Purge: Other Files ===");
		$count = bo_delete_files(BO_DIR.BO_CACHE_DIR, intval(BO_CACHE_PURGE_OTHER_HOURS), 0);
		
		if (BO_CACHE_SUBDIRS === true)
		{
			bo_echod(" * Signals");
			$count = bo_delete_files(BO_DIR.BO_CACHE_DIR.'/signals', intval(BO_CACHE_PURGE_OTHER_HOURS), 8);
		}
		
		bo_echod(" * Icons");
		$count += bo_delete_files(BO_DIR.BO_CACHE_DIR.'/icons', intval(BO_CACHE_PURGE_OTHER_HOURS), 8);

		bo_echod(" * Graphs");
		$count += bo_delete_files(BO_DIR.BO_CACHE_DIR.'/graphs', intval(BO_CACHE_PURGE_OTHER_HOURS), 8);
		
		bo_echod(" -> Deleted $count files");
		$whole_count += $count;
	}

	return $whole_count;
	
}



?>
