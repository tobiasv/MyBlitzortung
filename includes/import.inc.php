<?php



function bo_access_url()
{
	$path = sprintf(BO_IMPORT_PATH, trim(BO_REGION));
	return sprintf('http://%s:%s@%s/%s', trim(BO_USER), trim(BO_PASS), trim(BO_IMPORT_SERVER), $path);
}

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
	$is_updating = (int)bo_get_conf('is_updating');
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

	bo_set_conf('is_updating', time());
	register_shutdown_function('bo_set_conf', 'is_updating', 0);


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

	bo_set_conf('is_updating', 0);

	return;
}


function bo_update_all2($force = false, $only = '')
{

	if (!bo_get_conf('first_update_time'))
		bo_set_conf('first_update_time', time());


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


		//Daily statistics
		if (bo_exit_on_timeout()) return;

		if (!$only || $only == 'daily')
			bo_update_daily_stat();


		// Alerts
		if (defined('BO_ALERTS') && BO_ALERTS)
		{
			if (bo_exit_on_timeout()) return;

			if (!$only || $only == 'alerts')
				bo_alert_send();
		}
	}

	if (bo_exit_on_timeout()) return;

	/*** Update strike tracks ***/
	if ($strikes_imported)
	{
		if (!$only || $only == 'tracks')
			bo_update_tracks($force);
	}
	/*** Update MyBlitzortung stations ***/
	else if (!$strikes_imported && !$stations_imported && !$signals_imported)
	{
		if (!$only || $only == 'mbstations')
			bo_my_station_autoupdate($force);
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
		bo_update_densities($force);

		
	/*** File Cache ***/
	if (bo_exit_on_timeout()) return;

	if (!$only || $only == 'cache')
		bo_purge_cache($force);

		
	return;
}

// Login to blitzortung.org an return login-string
function bo_get_login_str()
{
	$fail = unserialize(bo_get_conf('login_string_fail'));

	if (isset($fail['last']) && isset($fail['count']))
	{
		if ($fail['count'] > 5 && time() - $fail['last'] < 3600)
		{
			bo_echod("ERROR: Login to Blitzortung failed to often in the last hour!");
			return false;
		}
		else
		{
			$fail = array();
			bo_set_conf('login_string_fail', serialize($fail));
		}
	}

	$file = bo_get_file('http://www.blitzortung.org/Webpages/index.php?page=3&username='.BO_USER.'&password='.BO_PASS.'&region='.BO_REGION, $code, 'login');

	if (preg_match('/login_string=([A-Z0-9]+)/', $file, $r))
	{
		bo_update_error('archivelogin', true);
		$bo_login_id = $r[1];
		bo_set_conf('bo_login_id', $bo_login_id);
		return $bo_login_id;
	}
	else
	{
		//sth. got wrong
		$fail['count'] = $fail['count'] + 1;
		$fail['last']  = time();
		bo_set_conf('login_string_fail', serialize($fail));

		if ($file === false)
		{
			bo_echod("ERROR: Couldn't get file to login! Code: $code");
			bo_update_error('archivelogin', "Login to archive failed. Couldn't get file. $code");
		}
		else
		{
			bo_echod("ERROR: Couldn't find login-id! Code: $code");
			bo_update_error('archivelogin', "Login to archive failed. Couldn't find login-id. $code");
		}

		return false;
	}
}



// Get archive data from blitzortung cgi (OUTDATED!)
function bo_get_archive($args='', $bo_login_id=false, $as_array=false)
{
	$file = false;

	if (!$bo_login_id)
	{
		//$bo_login_id = bo_get_conf('bo_login_id');
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

	if (!defined('BO_UP_INTVL_RAW') || !BO_UP_INTVL_RAW)
	{
		bo_echod("Disabled!");
		return true;
	}

	list($last_update, $auto_force, $update_errs, $last_file_pos, $last_hour, $last_modified) = @unserialize(bo_get_conf('uptime_raw_data'));

	$count_inserted = 0;
	$count_exists = 0;
	$count_updated = 0;

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
		bo_set_conf('uptime_raw_data', serialize(array(time(), 0, $update_errs+1)));

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
		$loadcount = bo_get_conf('upcount_raw');
		bo_set_conf('upcount_raw', $loadcount+1);
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

				$sql = "	time='$date $time',
							time_ns='$time_ns',
							lat='$lat',lon='$lon',
							height='$height',
							strike_id='0',
							channels='$channels',
							ntime='$ntime',
							data=x'$data'
							";

				// signal examinations
				$bdata = bo_hex2bin($data);
				$sql .= ",".bo_examine_signal($bdata, $channels, $ntime);

				if ($id)
				{
					$sql = "UPDATE ".BO_DB_PREF."raw SET $sql WHERE id='$id'";
					BoDb::query($sql, 1);
					$count_updated++;
				}
				else
				{
					$sql = "INSERT INTO ".BO_DB_PREF."raw SET $sql";
					BoDb::query($sql, 1);
					$count_inserted++;
				}

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

		if (!$timeout)
			$auto_force = false;

		//bo_echod("Pos: $last_file_pos Hour: $last_hour");

		bo_set_conf('uptime_raw_data', serialize(array(time(), $auto_force, $update_errs, $last_file_pos, $last_hour, $last_modified)));

		bo_echod("Lines: $lines *** Files: $files *** New Raw Data: $count_inserted *** Updated: $count_updated *** Already read: $count_exists");

		if ($channels && $values && $bpv)
		{
			bo_set_conf('raw_channels', $channels);
			bo_set_conf('raw_values', $values);
			bo_set_conf('raw_bitspervalue', $bpv);
			bo_set_conf('raw_ntime', $ntime);
		}

		//Longtime
		$count = bo_get_conf('count_raw_signals');
		bo_set_conf('count_raw_signals', $count + $count_inserted);

		if (!$timeout)
		{
			bo_set_conf('uptime_raw', time());
			bo_match_strike2raw();
			bo_update_status_files('signals');
			$updated = true;
		}
		else
			$updated = false;

		if ($update_err)
			bo_update_error('rawdata', 'Download of raw data failed. '.$code);
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
function bo_update_strikes($force = false)
{
	global $_BO;

	$last = bo_get_conf('uptime_strikes_try');

	bo_echod(" ");
	bo_echod("=== Strikes ===");

	if (time() - $last > BO_UP_INTVL_STRIKES * 60 - 30 || $force || time() < $last)
	{
		bo_set_conf('uptime_strikes_try', time());

		$start_time = time();
		$stations = bo_stations();
		$own_id = bo_station_id();

		$count_updated = 0;
		$count_inserted = 0;
		$count_exists = 0;
		$old_data = null;
		$dist_data = array();
		$bear_data = array();
		$dist_data_own = array();
		$bear_data_own = array();
		$strikesperstation = array();
		$min_participants = 1000;
		$max_participants = 0;
		$timeout = false;
		$longtime_stations[$own_id] = $own_id;
		$max_dist_all[$own_id] = 0;
		$min_dist_all[$own_id] = 9E12;
		$max_dist_own[$own_id] = 0;
		$min_dist_own[$own_id] = 9E12;
		$user2id = array();

		foreach($stations as $stId => $sdata)
		{
			$user2id[$sdata['user']] = $stId;

			if (BO_ENABLE_LONGTIME_ALL === true)
			{
				$longtime_stations[$stId] = $stId;
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
		$res = BoDb::query("SELECT MAX(time) mtime FROM ".BO_DB_PREF."strikes");
		$row = $res->fetch_assoc();
		$last_strike = strtotime($row['mtime'].' UTC');

		if ($last_strike > time())
			$last_strike = time() - 3600 * 24;
		else if ($last_strike <= 0 || !$last_strike)
			$last_strike = strtotime('2000-01-01');

		$last_modified = bo_get_conf('uptime_strikes_modified');

		if ($last_modified)
			$time_update = $last_modified - BO_MIN_MINUTES_STRIKE_CONFIRMED * 60;
		else
			$time_update = $last_strike - BO_MIN_MINUTES_STRIKE_CONFIRMED * 60;



		/***** PARTIAL DOWNLOAD OF STRIKEDATA *****/
		//estimate the size of the participants.txt before none-imported strikes
		$date_file_begins = strtotime('now -2 hours');
		$sql = "SELECT COUNT(*) cnt_lines, SUM(users) sum_users
				FROM ".BO_DB_PREF."strikes
				WHERE 1
					AND time BETWEEN '".gmdate('Y-m-d H:i:s', $date_file_begins)."' AND '".gmdate('Y-m-d H:i:s', $time_update)."'
					AND status>0
				";
		$res = BoDb::query($sql);
		$row = $res->fetch_assoc();
		$calc_range = $row['cnt_lines'] * 69 + $row['sum_users'] * 9;
		//$calc_range = $calc_range * 0.98; //some margin to be sure

		$range = $calc_range;

		//adjust range
		$tmp = unserialize(bo_get_conf('import_strike_filelength'));

		// use old file size if range got wrong the last time
		if (is_array($tmp) && !empty($tmp))
		{
			list($oldsize, $oldtime, $oldrange) = $tmp;
			$time_diff = (time() - $oldtime) / 60;

			if ( $time_diff < 11 && $range > $oldsize && ($oldrange > $oldsize * 0.98 || !$oldrange) )
			{
				bo_echod("Calculated range was $calc_range bytes");
				$range = $oldsize * 0.90;
			}
		}

		if ($range < 5000)
			$range = 0;

		$sent_range = $range;

		//send a last modified header
		$modified = $last_modified; //!!

		//get the file
		$file = bo_get_file(bo_access_url().BO_IMPORT_PATH_PARTICIPANTS, $code, 'strikes', $range, $modified, true);

		//check the date of the 2nd line (1st may be truncated!)
		if ($file === false)
		{
			if ($code == 304) //wasn't modified
			{
				if ($last_modified > 0 && time() - $last_modified > 3600 * 2)
				{
					bo_echod("Last modified time too old (Blitzortung.org down?). Setting modified to now!");
					bo_set_conf('uptime_strikes_modified', time());
				}
				
				bo_set_conf('uptime_strikes', time());
				bo_echod("File not changed since last download (".date('r', $modified).")");
				return false;
			}

			$first_strike_file = 0;
		}
		elseif (!empty($range))
		{
			//range request was successful...
			unset($file[0]); //1st line is always chunked
			$first_strike_file = strtotime(substr($file[1],0,19).' UTC');
		}



		if ($file !== false && empty($range))
		{
			$filesize = strlen(implode('', $file));
			bo_echod("Partial download didn't work, got whole file instead (sent range $sent_range got $filesize bytes)");
			bo_set_conf('import_strike_filelength', serialize(array($filesize, time(), 0)));
		}
		else if ($file === false || $first_strike_file > $last_strike)
		{
			/***** COMPLETE DOWNLOAD OF STRIKEDATA *****/
			$modified = $last_modified; //!!
			$drange = 0;
			$file = bo_get_file(bo_access_url().BO_IMPORT_PATH_PARTICIPANTS, $code, 'strikes', $drange, $modified, true);

			if ($file === false)
			{
				bo_echod("Partial download AND fallback to normal download didn't work!");
				return false;
			}

			$filesize = strlen(implode('', $file));
			bo_echod("Using partial download FAILED (Range $sent_range)! Fallback to normal download ($filesize bytes).");

			if ($code == 304) //wasn't modified
			{
				bo_set_conf('uptime_strikes', time());
				bo_echod("File not changed since last download (".date('r', $modified).")");
				return false;
			}


			if ($first_strike_file > 0)
				bo_echod("Problem: Partial file begins with strike ".date('Y-m-d H:i:s', $first_strike_file)." which is newer than last strike from database (size: ".($range[1]-$range[0]+1).").");
			elseif ($code)
				bo_echod("Errorcode: $code");

			if ($file === false)
			{
				bo_update_error('strikedata', 'Download of strike data failed. '.$code);
				bo_echod("ERROR: Couldn't get file for strikes! Code: $code");
				return false;
			}
		}
		else
		{
			bo_echod("Using partial download! Beginning with strike ".date('Y-m-d H:i:s', $first_strike_file).". Bytes read ".$range[0]."-".$range[1]." (".($range[1]-$range[0]+1).") from ".$range[2].". ".(intval($range[2]) ? "This saved ".round($range[0] / $range[2] * 100)."% traffic." : ""));
			bo_set_conf('import_strike_filelength', serialize(array($range[2], time(), $calc_range)));
		}

		//set the modified header
		if (intval($modified) <= 0)
			$modified = time() - 180;
		bo_set_conf('uptime_strikes_modified', $modified);

		/***** SOME MORE PREPARATIONS BEFORE READING *****/
		bo_update_error('strikedata', true); //reset error reporting
		$loadcount = bo_get_conf('upcount_strikes');
		bo_set_conf('upcount_strikes', $loadcount+1);
		$last_strikes = unserialize(bo_get_conf('last_strikes_stations'));
		bo_echod('Last strike: '.date('Y-m-d H:i:s', $last_strike).
				' *** Importing only strikes newer than: '.date('Y-m-d H:i:s', $time_update).
				' *** This is update #'.$loadcount);


		foreach($file as $l)
		{
			if (!$l)
				continue;

			if (!preg_match('/([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ([0-9]{2}:[0-9]{2}:[0-9]{2})\.([0-9]+) ([-0-9\.]+) ([-0-9\.]+) ([0-9\.]+)k?A?.* ([0-9]+)m? ([0-9]+) (.*)/', $l, $r))
			{
				bo_echod("Wrong line format: \"$l\"");
				continue;
			}

			$date = $r[1];
			$time = $r[2];

			$utime = strtotime("$date $time UTC");

			// update strike-data only some seconds *before* the *last strike in Database*
			if ($utime < $time_update)
			{
				$count_exists++;
				continue;
			}

			//get older strike data to avoid duplicates
			if ($old_data === null)
			{
				$old_data = array();

				//get the range out of the time of the first line
				$date_start = gmdate('Y-m-d H:i:s', $time_update - 10); //a small margin to be sure
				$date_end = gmdate('Y-m-d H:i:s', $utime + 3600 * 3); //3h to the future to be sure

				//Searching for old Strikes (to avoid duplicates)
				//ToDo: fuzzy search for lat/lon AND time
				$sql = "SELECT id, part, time, time_ns, lat, lon, users
						FROM ".BO_DB_PREF."strikes
						WHERE time BETWEEN '$date_start' AND '$date_end'
						ORDER BY time";
				$res = BoDb::query($sql);
				while ($row = $res->fetch_assoc())
				{
					$id = $row['id'];
					$old_data[$id]['t'] = strtotime($row['time'].' UTC');
					$old_data[$id]['n'] = $row['time_ns'];
					$old_data[$id]['loc'] = array($row['lat'], $row['lon']);
					$old_data[$id]['users'] = $row['users'];
					$old_data[$id]['part'] = $row['part'];
				}
			}


			//The data for the strike
			$time_ns = intval($r[3]);
			$lat = $r[4];
			$lon = $r[5];
			$cur = $r[6];
			$deviation = $r[7];
			$participants_calc = $r[8];
			$participants = explode(' ', $r[9]);
			$users = count($participants);
			$part = strpos($r[9], BO_USER) !== false ? 1 : 0;
			$dist = bo_latlon2dist($lat, $lon);
			$bear = bo_latlon2bearing($lat, $lon);


			//sometimes two strikes with same time one after another --> ignore 2nd one
			if ("$time.$time_ns" === $time_last_strike)
				continue;

			$time_last_strike = "$time.$time_ns";

			//search for older entries of the same strike
			$id = false;
			$ids_found = array();
			$nreftime = 0;

			if ($utime < $last_strike + 2)
			{
				$search_from  = $utime;
				$search_to    = $utime;
				$nsearch_from = ($time_ns - BO_UP_STRIKES_FUZZY_NSEC) * 1E-9;
				$nsearch_to   = ($time_ns + BO_UP_STRIKES_FUZZY_NSEC) * 1E-9;

				if ($nsearch_from < 0) { $nsearch_from++; $search_from--; }
				else if ($nsearch_from > 1) { $nsearch_from--; $search_from++; }

				if ($nsearch_to < 0) { $nsearch_to++; $search_to--; }
				else if ($nsearch_to > 1) { $nsearch_to--; $search_to++; }


				foreach($old_data as $oldid => $d)
				{
					//remove entries, which are too old
					if ($utime - $d['t'] >= 2)
					{
						unset($old_data[$i]);
						continue;
					}

					//couldn't find any previous strikes to update
					else if ($d['t'] - $utime >= 2)
					{
						break;
					}

					//found exactly the same strike
					elseif ($d['t'] == $utime && $d['n'] == $time_ns)
					{
						unset($old_data[$i]); //remove it from the search array!
						unset($ids_found); //only update the strike with the same time
						$ids_found[] = $oldid;
						break; //end search!
					}

					//FUZZY-SEARCH -> found seconds match
					else if ($search_from <= $d['t'] && $d['t'] <= $search_to)
					{
						$nreftime = $d['n'] * 1E-9;
						$is_old_strike = false;

						//search for nseconds match
						if ($nsearch_from > $nsearch_to && ($nsearch_from <= $nreftime || $nreftime <= $nsearch_to) )
						{
							$is_old_strike = true;
						}
						elseif ($nsearch_from <= $nsearch_to && $nsearch_from <= $nreftime && $nreftime <= $nsearch_to)
						{
							$is_old_strike = true;
						}

						if ($is_old_strike)
						{
							//was strike in the same area?
							$old_dist = bo_latlon2dist($lat, $lon, $d['loc'][0], $d['loc'][1]);

							//could be a new one if participant count differs too much
							if ($old_dist < BO_UP_STRIKES_FUZZY_KM * 1000) // && $users * 0.9 <= $d['users'] && $d['users'] <= $users * 1.5)
							{
								$ids_found[] = $oldid;
								unset($old_data[$i]);
							}
						}

					}

				}

				if (!empty($ids_found))
				{
					$id = $ids_found[0];
				}
			}


			$sql = "
				time='$date $time',
				time_ns='$time_ns',
				lat='$lat',lon='$lon',
				distance='$dist',
				bearing='$bear',
				deviation='$deviation',
				current='$cur',
				users='$users',
				part='$part',
				raw_id=NULL
				";


			if ($key_bytes_time)
				$sql .= ', time_x=(FLOOR(('.($utime-$key_time_start).')/60/'.$key_time_div.')%'.$key_time_vals.')';


			if ($key_bytes_latlon)
				$sql .= ', lat_x=FLOOR((('.(90+$lat).')%'.$key_lat_div.')/'.$key_lat_div.'*'.$key_latlon_vals.')
						 , lon_x=FLOOR((('.(180+$lon).')%'.$key_lon_div.')/'.$key_lon_div.'*'.$key_latlon_vals.')';


			if (!$id) //new strike
			{
				if ($modified - $utime > BO_MIN_MINUTES_STRIKE_CONFIRMED * 60)
					$sql .= " , status=2 ";
				else
					$sql .= " , status=0 ";

				$id = BoDb::query("INSERT INTO ".BO_DB_PREF."strikes SET $sql", false);
				$count_inserted++;

				$new_strike = true;
			}
			else //update
			{
				if ($modified - $utime > BO_MIN_MINUTES_STRIKE_CONFIRMED * 60)
					$sql .= " , status=2 ";
				else
					$sql .= " , status=1 ";

				BoDb::query("UPDATE ".BO_DB_PREF."strikes SET $sql WHERE id='$id'");
				$count_updated++;
				$new_strike = false;
			}


			//Update Strike <-> All Participated Stations
			if ($id && !(defined('BO_STATION_STAT_DISABLE') && BO_STATION_STAT_DISABLE == true) )
			{
				$sql = '';
				foreach($participants as $user)
				{
					$stId = $user2id[$user];
					$last_strikes[$stId] = array($utime, $time_ns, $id);
					$sql .= ($sql ? ',' : '')." ('$id', '$stId') ";
				}

				if ($sql)
				{
					$sql = "REPLACE INTO ".BO_DB_PREF."stations_strikes (strike_id, station_id) VALUES $sql";
					BoDb::query($sql);
				}
			}


			//general statistics
			$min_participants = min($participants_calc, $min_participants);
			$max_participants = max($participants_calc, $max_participants);
			$max_users = max($max_users, $users);


			//statistics relative to station(s)
			if ($new_strike)
			{

				// *** Own station *** //

				$bear_id      = intval($bear);
				$dist_id      = intval($dist / 10 / 1000);

				//All strikes
				$max_dist_all[$own_id] = max($dist, $max_dist_all[$own_id]);
				$min_dist_all[$own_id] = min($dist, $min_dist_all[$own_id]);

				//Long-Time Statistics (all strikes relative to own station)
				$bear_data[$own_id][$bear_id]++;
				$dist_data[$own_id][$dist_id]++;

				//Own Long-Time Statistics for self-detected strike
				if ($part)
				{
					$bear_data_own[$own_id][$bear_id]++;
					$dist_data_own[$own_id][$dist_id]++;
					$max_dist_own[$own_id] = max($dist, $max_dist_own[$own_id]);
					$min_dist_own[$own_id] = min($dist, $min_dist_own[$own_id]);
					$last_strikes[$own_id] = array($utime, $time_ns, $id);
					$strikesperstation[$own_id]++;
				}


				// *** other stations *** //
				if (BO_ENABLE_LONGTIME_ALL === true)
				{
					foreach($stations as $stId => $sdata)
					{
						$stLat = $sdata['lat'];
						$stLon = $sdata['lon'];

						if ($stLat == 0.0 && $stLon == 0.0) //station has no position yet
							continue;

						$dist_other    = bo_latlon2dist($lat, $lon, $stLat, $stLon);
						$bear_other    = bo_latlon2bearing($lat, $lon, $stLat, $stLon);
						$bear_id_other = intval($bear_other);
						$dist_id_other = intval($dist_other / 10 / 1000);
						$max_dist_all[$stId] = max($dist_other, $max_dist_all[$stId]);
						$min_dist_all[$stId] = min($dist_other, $min_dist_all[$stId]);
						$bear_data[$stId][$bear_id_other]++;
						$dist_data[$stId][$dist_id_other]++;


						//strike detected by station
						if (array_search($sdata['user'], $participants) !== false)
						{
							$bear_data_own[$stId][$bear_id_other]++;
							$dist_data_own[$stId][$dist_id_other]++;
							$max_dist_own[$stId] = max($dist_other, $max_dist_own[$stId]);
							$min_dist_own[$stId] = min($dist_other, $min_dist_own[$stId]);
							$strikesperstation[$stId]++;
						}
					}

				}

			}

			//Timeout
			if (bo_exit_on_timeout())
			{
				$timeout = true;
				break;
			}
		}

		bo_echod("Lines: ".count($file)." *** New Strikes: $count_inserted *** Updated: $count_updated *** Already read: $count_exists");

		//General
		bo_set_conf('last_strikes_stations', serialize($last_strikes));
		$count = bo_get_conf('longtime_max_participants');
		if ($count < $max_users)
		{
			bo_set_conf('longtime_max_participants', $max_users);
			bo_set_conf('longtime_max_participants_time', time());
		}

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
			$row = BoDb::query($sql)->fetch_assoc();
			$rate_strikes_region[$reg_id] = $row['cnt'] / BO_GRAPH_STAT_STRIKES_NOW_GROUP_MINUTES;

			if ($row['mtime'])
				$last_strikes_region[$reg_id] = strtotime($row['mtime'].' UTC');
		}
		bo_set_conf('last_strikes_region', serialize($last_strikes_region));
		bo_set_conf('rate_strikes_region', serialize($rate_strikes_region));


		//Update Longtime statistics per station for detected strikes
		foreach($strikesperstation as $stId => $count)
		{
			$add = $stId == $own_id ? '' : '#'.$stId.'#';

			$oldcount = bo_get_conf('count_strikes_own'.$add);
			bo_set_conf('count_strikes_own'.$add, $oldcount + $count);

			$bear_data_tmp = unserialize(bo_get_conf('longtime_bear_own'.$add));
			if (!$bear_data_tmp['time']) $bear_data_tmp['time'] = time();
			foreach($bear_data_own[$stId] as $bear_id => $bear_count)
				$bear_data_tmp[$bear_id] += $bear_count;
			bo_set_conf('longtime_bear_own'.$add, serialize($bear_data_tmp));

			$dist_data_tmp = unserialize(bo_get_conf('longtime_dist_own'.$add));
			if (!$dist_data_tmp['time']) $dist_data_tmp['time'] = time();
			foreach($dist_data_own[$stId] as $dist_id => $dist_count)
				$dist_data_tmp[$dist_id] += $dist_count;
			bo_set_conf('longtime_dist_own'.$add, serialize($dist_data_tmp));

			$max = bo_get_conf('longtime_max_dist_own'.$add);
			if ($max < $max_dist_own[$stId])
			{
				bo_set_conf('longtime_max_dist_own'.$add, $max_dist_own[$stId]);
				bo_set_conf('longtime_max_dist_own_time'.$add, time());
			}

			$min = bo_get_conf('longtime_min_dist_own'.$add);
			if (!$min || $min > $min_dist_own[$stId])
			{
				bo_set_conf('longtime_min_dist_own'.$add, $min_dist_own[$stId]);
				bo_set_conf('longtime_min_dist_own_time'.$add, time());
			}

		}


		//Update Longtime statistics per station for all strikes
		foreach($longtime_stations as $stId)
		{
			//update only if station is active or got some strikes during the update
			if ($stations[$stId]['status'] == 'A' || $strikesperstation[$stId])
			{

				$add = $stId == $own_id ? '' : '#'.$stId.'#';

				$count = bo_get_conf('count_strikes'.$add);
				bo_set_conf('count_strikes'.$add, $count + $count_inserted);

				if (isset($dist_data[$stId]))
				{
					$dist_data_tmp = unserialize(bo_get_conf('longtime_dist'.$add));
					if (!$dist_data_tmp) $dist_data_tmp['time'] = time();
					foreach($dist_data[$stId] as $dist_id => $dist_count)
						$dist_data_tmp[$dist_id] += $dist_count;
					bo_set_conf('longtime_dist'.$add, serialize($dist_data_tmp));
				}

				if (isset($bear_data[$stId]))
				{
					$bear_data_tmp = unserialize(bo_get_conf('longtime_bear'.$add));
					if (!$bear_data_tmp) $bear_data_tmp['time'] = time();
					foreach($bear_data[$stId] as $bear_id => $bear_count)
						$bear_data_tmp[$bear_id] += $bear_count;
					bo_set_conf('longtime_bear'.$add, serialize($bear_data_tmp));
				}

				$max = bo_get_conf('longtime_max_dist_all'.$add);
				if ($max < $max_dist_all[$stId])
				{
					bo_set_conf('longtime_max_dist_all'.$add, $max_dist_all[$stId]);
					bo_set_conf('longtime_max_dist_all_time'.$add, time());
				}

				$min = bo_get_conf('longtime_min_dist_all'.$add);
				if (!$min || $min > $min_dist_all[$stId])
				{
					bo_set_conf('longtime_min_dist_all'.$add, $min_dist_all[$stId]);
					bo_set_conf('longtime_min_dist_all_time'.$add, time());
				}
			}

		}


		if ($timeout)
		{
			$updated = false;
		}
		else
		{
			$updated = true;
			bo_set_conf('uptime_strikes', time());
			bo_update_status_files('strikes');
		}

		//Guess Minimum Participants
		if (intval(BO_FIND_MIN_PARTICIPANTS_HOURS))
		{
			$min_hours        = BO_FIND_MIN_PARTICIPANTS_HOURS;
			$see_same         = BO_FIND_MIN_PARTICIPANTS_COUNT;
			$tmp              = unserialize(bo_get_conf('bo_participants_locating_min'));
			$min_participants = $tmp['value'];

			if (time() - $tmp['time'] > 3600 * BO_FIND_MIN_PARTICIPANTS_HOURS)
			{
				$row = BoDb::query("SELECT MIN(users) minusers FROM ".BO_DB_PREF."strikes WHERE time>'".gmdate('Y-m-d H:i:s', time() - 3600*$min_hours)."'")->fetch_assoc();

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
					bo_set_conf('bo_participants_locating_min', serialize($tmp));
				}
			}
		}

		//Maximum Participants
		if (intval(BO_FIND_MAX_PARTICIPANTS_HOURS) && $max_participants >= 3)
		{
			$tmp = unserialize(bo_get_conf('bo_participants_locating_max'));
			$tmp['value_last'] = max($tmp['value_last'], $max_participants);
			if (time() - $tmp['time'] > 3600 * BO_FIND_MAX_PARTICIPANTS_HOURS && $max_participants >= $min_participants)
			{
				$tmp['time'] = time();
				$tmp['value'] = $tmp['value_last'];
				$tmp['value_last'] = 0; //if max value shrinks!
			}
			bo_set_conf('bo_participants_locating_max', serialize($tmp));
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
	//not really physical values but determined empirical
	$c = BO_STR2SIG_C;
	$fuzz = BO_STR2SIG_FUZZ_SEC ; //minimum fuzzy-seconds
	$dist_fuzz = BO_STR2SIG_FUZZ_SECM; //fuzzy-seconds per meter (seconds)
	$offset_fuzz = BO_STR2SIG_FUZZ_OFFSET;

	$amp_trigger = (BO_TRIGGER_VOLTAGE * BO_STR2SIG_TRIGGER_FACTOR / BO_MAX_VOLTAGE) * 256 / 2;

	//time of last update
	$last_modified = bo_get_conf('uptime_strikes_modified');
	
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

				//experimental polarity checking
				if (BO_EXPERIMENTAL_POLARITY_CHECK === true
					&& ( !intval(BO_EXPERIMENTAL_POLARITY_MAX_DIST)
                          || intval(BO_EXPERIMENTAL_POLARITY_MAX_DIST) * 1000 > $row['distance']
					   )
					)
				{
					$polarity[$row['id']] = bo_strike2polarity($row2['data'], $row['bearing']);

					if ($polarity[$row['id']] === null)
						$polarity[$row['id']] = "NULL";
				}

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
	$last = bo_get_conf('uptime_stations_try');

	bo_echod(" ");
	bo_echod("=== Stations ===");

	if (!defined('BO_UP_INTVL_STATIONS') || !BO_UP_INTVL_STATIONS || time() < $last)
	{
		bo_echod("Disabled!");
	}

	$count_inserted = 0;
	$count_updated = 0;

	if (time() - $last > BO_UP_INTVL_STATIONS * 60 - 30 || $force)
	{
		bo_set_conf('uptime_stations_try', time());

		$StData = array();
		$signal_count = 0;
		$time = time();

		//send a last modified header
		$last_modified = bo_get_conf('uptime_stations_modified');
		$modified = $last_modified;
		$range = 0;
		$file = bo_get_file(bo_access_url().BO_IMPORT_PATH_STATIONS, $code, 'stations', $range, $modified);


		//wasn't modified
		if ($code == 304)
		{
			if ($last_modified > 0 && time() - $last_modified > 3600 * 2)
			{
				bo_echod("Last modified time too old (Blitzortung.org down?). Setting modified to now!");
				bo_set_conf('uptime_strikes_modified', time());
			}

			bo_set_conf('uptime_stations', time());
			bo_echod("File not changed since last download (".date('r', $modified).")");
			return false;
		}


		//set the modified header
		if (intval($modified) <= 0)
			$modified = time() - 180;
		bo_set_conf('uptime_stations_modified', $modified);


		//ERROR
		if ($file === false)
		{
			bo_update_error('stationdata', 'Download of station data failed. '.$code);
			bo_echod("ERROR: Couldn't get file for stations! Code: $code");
			return false;
		}


		$lines = explode("\n", $file);

		//we need the current station data for later usage
		$all_stations = bo_stations();

		//check if sth went wrong
		if ($lines < count($all_stations) * BO_UP_STATION_DIFFER)
		{
			bo_update_error('stationcount', 'Station count differs too much: '.count($all_stations).'Database / '.$lines.' stations.txt');
			bo_set_conf('uptime_stations', time());

			return;
		}


		//Debug output
		$loadcount = bo_get_conf('upcount_stations');
		bo_set_conf('upcount_stations', $loadcount+1);
		bo_echod('Last update: '.date('Y-m-d H:i:s', $last).' *** This is update #'.$loadcount);

		//reset error counter
		bo_update_error('stationdata', true);
		bo_update_error('stationcount', true);

		$activebyuser = array();

		foreach($lines as $l)
		{
			$cols = explode(" ", $l);
			$stId = intval($cols[0]);

			if (!$l || !$stId)
				continue;

			if (!$stId || count($cols) < 10)
			{
				bo_echod("Wrong line format: \"$l\"");
				continue;
			}


			$stUser 	= $cols[1];
			$stCity 	= strtr(html_entity_decode($cols[3]), array(chr(160) => ' '));
			$stCountry 	= strtr(html_entity_decode($cols[4]), array(chr(160) => ' '));
			$stLat	 	= $cols[5];
			$stLon 		= $cols[6];
			$stTime 	= substr($cols[7], 0, 10).' '.substr($cols[7], 16, 8);
			$stTimeMs 	= substr($cols[7], 25);
			$stStatus 	= $cols[8];
			$stDist 	= bo_latlon2dist($stLat, $stLon);
			$stTracker	= strtr(html_entity_decode($cols[9]), array(chr(160) => ' '));
			$stSignals	= (int)$cols[10];
			$stTimeU    = strtotime($stTime.' UTC');


			//station has been active by user (~ a year ago)
			if (time() - $stTimeU < 3600 * 24 * 366)
				$activebyuser[$stUser] = array('id' => $stId, 'sig' => $stSignals, 'lat' => $stLat, 'lon' => $stLon);

			//mark Offline?
			if ($stStatus != '-' && 
					(  
						   time() - $stTimeU > (double)BO_STATION_OFFLINE_MINUTES * 60
						|| $stTimeU - time() > 3600 * 24
					)
				)
			{
				$stStatus = 'O';  //Special offline status
			}

			//Data for statistics
			$StData[$stId] = array('time' => $stTimeU, 'lat' => $stLat, 'lon' => $stLon);
			$StData[$stId]['sig'] = $stSignals;
			$StData[$stId]['status'] = $stStatus;

			$sql = " 	id='$stId',
						user='$stUser',
						city='$stCity',
						country='$stCountry',
						lat='$stLat',
						lon='$stLon',
						distance='$stDist',
						last_time='$stTime',
						last_time_ns='$stTimeMs',
						status='$stStatus',
						tracker='$stTracker'
						";

			$sql = strtr($sql, array('\null' => ''));

			//user rename ==> station owner/city changed ==> new station ==> delete old data
			if (isset($all_stations[$stId])
					&& $all_stations[$stId]['user'] != $stUser
					&& trim($all_stations[$stId]['user'])
					&& trim($stUser)
				)
			{
				if (bo_delete_station($stId))
					unset($all_stations[$stId]);
			}

			if (isset($all_stations[$stId]))
			{
				BoDb::query("UPDATE ".BO_DB_PREF."stations SET $sql WHERE id='$stId'");
				$count_updated++;
			}
			else
			{
				$sql .= " , first_seen='".gmdate('Y-m-d H:i:s')."'";
				BoDb::query("INSERT INTO ".BO_DB_PREF."stations SET $sql", false);
				$count_inserted++;
			}

			$signal_count += $StData[$stId]['sig'];

		}

		bo_echod("Stations: ".(count($lines)-2)." *** New Stations: $count_inserted *** Updated: $count_updated");

		//Check wether stations still exists
		foreach($all_stations as $id => $d)
		{
			//station was deleted in stations.txt :(
			if (!isset($StData[$id]) && time() - strtotime($d['last_time']) > 24*3600*7)
			{
				bo_delete_station($id);
			}
		}


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
			$cdata_tmp = array();

			foreach($activebyuser as $user => $d)
			{
				if (!isset($data[$user]) && $d['sig'] && $d['lat'] && $d['lon']) //no old entry but new signals and gps fixed
				{
					// mark as NEW STATION
					$data[$user] = time();

					//construction time if mybo installation is not too new ;-)
					$changed = strtotime($all_stations[$d['id']]['changed']);
					if ($changed - bo_get_conf('first_update_time') > 3600 * 24);
						$cdata_tmp[$user] = $time - $changed;

					$new = true;

					bo_echod("Found NEW station: $user *** Construction time: ".round($cdata_tmp[$user]/3600/24)."days");
				}
			}

			if ($new)
			{
				bo_set_conf('stations_new_date', serialize($data));

				//extra data
				$cdata = unserialize(bo_get_conf('stations_new_data'));
				if (!is_array($cdata))
					$cdata = $cdata_tmp;
				else
					$cdata = array_merge($cdata, $cdata_tmp);

				bo_set_conf('stations_new_data', serialize($cdata));
			}
		}


		//Update Statistics
		$datetime      = gmdate('Y-m-d H:i:s', $time);
		$datetime_back = gmdate('Y-m-d H:i:s', $time - 3600);

		$only_own = false;
		if ((defined('BO_STATION_STAT_DISABLE') && BO_STATION_STAT_DISABLE == true))
			 $only_own = bo_station_id();


		$sql = "SELECT a.station_id sid, COUNT(a.station_id) cnt
				FROM ".BO_DB_PREF."stations_strikes a, ".BO_DB_PREF."strikes b
				WHERE a.strike_id=b.id AND b.time > '$datetime_back'
					".($only_own ? " AND a.station_id='".$only_own."'" : "")."
				GROUP BY a.station_id";
		$res = BoDb::query($sql);
		while ($row = $res->fetch_assoc())
			$StData[intval($row['sid'])]['strikes'] = $row['cnt'];

		$active_stations = 0;
		$active_sig_stations = 0;
		$active_avail_stations = 0;
		$active_nogps = 0;
		$stat_sql = '';
		
		foreach($StData as $id => $data)
		{
			if ($only_own && $only_own != $id)
				continue;

			if ($id && ($data['sig'] || $data['strikes']))
			{
				$stat_sql .= ($stat_sql ? ',' : '');
				$stat_sql .= "('$id', '$datetime', '".intval($data['sig'])."', '".intval($data['strikes'])."')";
			}

			if ($data['status'] == 'A') //GPS is/was active
				$active_stations++;

			if ($data['status'] != '-') //Station is available (no dummy entry, has sent some data some time ago)
				$active_avail_stations++;

			if ($data['status'] == 'V') //GPS is unavailable right now
				$active_nogps++;

			if ($data['sig']) //Station is sending (really active)
				$active_sig_stations++;
		}

		if ($stat_sql)
			BoDb::query("INSERT INTO ".BO_DB_PREF."stations_stat (station_id, time, signalsh, strikesh) VALUES $stat_sql");
		
		bo_set_conf('active_stations_nogps', $active_nogps);

		//Update whole strike count for dummy station "0"
		$sql = "SELECT COUNT(id) cnt
				FROM ".BO_DB_PREF."strikes
				WHERE time > '$datetime_back'";
		$row = BoDb::query($sql)->fetch_assoc();
		$strike_count = $row['cnt'];
		BoDb::query("INSERT INTO ".BO_DB_PREF."stations_stat
				SET station_id='0', time='$datetime',
					signalsh='".intval($signal_count)."', strikesh='".intval($strike_count)."'");


		/*** Update Longtime statistics ***/
		//max active stations ever
		$max = bo_get_conf('longtime_count_max_active_stations');
		if ($active_stations > $max)
		{
			bo_set_conf('longtime_count_max_active_stations', max($max, $active_stations));
			bo_set_conf('longtime_count_max_active_stations_time', $time);
		}

		//max active stations (sending signals) ever
		$max = bo_get_conf('longtime_count_max_active_stations_sig');
		if ($active_sig_stations > $max)
		{
			bo_set_conf('longtime_count_max_active_stations_sig', max($max, $active_sig_stations));
			bo_set_conf('longtime_count_max_active_stations_sig_time', $time);
		}

		//max available stations (had sent some signales, no matter when)
		$max = bo_get_conf('longtime_count_max_avail_stations');
		if ($active_avail_stations > $max)
		{
			bo_set_conf('longtime_count_max_avail_stations', max($max, $active_avail_stations));
			bo_set_conf('longtime_count_max_avail_stations_time', $time);
		}

		//max signals/h
		$max = bo_get_conf('longtime_max_signalsh');
		if ($signal_count > $max)
		{
			bo_set_conf('longtime_max_signalsh', max($max, $signal_count));
			bo_set_conf('longtime_max_signalsh_time', $time);
		}

		//max strikes/h
		$max = bo_get_conf('longtime_max_strikesh');
		if ($strike_count > $max)
		{
			bo_set_conf('longtime_max_strikesh', max($max, $strike_count));
			bo_set_conf('longtime_max_strikesh_time', $time);
		}


		/*** Longtime stat. for own Station ***/
		$own_id = bo_station_id();
		$longtime_stations = array();

		if (BO_ENABLE_LONGTIME_ALL === true)
			$longtime_stations = $all_stations;
		else
			$longtime_stations[$own_id] = $own_id;

		foreach($longtime_stations as $stId => $dummy)
		{
			$add = $stId == $own_id ? '' : '#'.$stId.'#';

			//whole signals count (not exact)
			if ($last > 0 && $StData[$stId]['sig'])
			{
				$count = bo_get_conf('count_raw_signals2'.$add);
				bo_set_conf('count_raw_signals2'.$add, $count + ($StData[$stId]['sig']*($time-$last)/3600));
			}

			//max signals/h (own)
			$max = bo_get_conf('longtime_max_signalsh_own'.$add);
			if ($StData[$stId]['sig'] > $max)
			{
				bo_set_conf('longtime_max_signalsh_own'.$add, max($max, $StData[$stId]['sig']));
				bo_set_conf('longtime_max_signalsh_own_time'.$add, $time);
			}

			//max strikes/h (own)
			$max = bo_get_conf('longtime_max_strikesh_own'.$add);
			if ($StData[$stId]['strikes'] > $max)
			{
				bo_set_conf('longtime_max_strikesh_own'.$add, max($max, $StData[$stId]['strikes']));
				bo_set_conf('longtime_max_strikesh_own_time'.$add, $time);
			}

			//Activity/inactivity counter
			$time_interval = $last ? $time - $last : 0;
			if ($StData[$stId]['status'] == 'A')
			{

				//save first data-time
				if ($stId != $own_id)
				{
					$longtime_time = bo_get_conf('longtime_station_first_time'.$add);

					if (!$longtime_time)
						bo_set_conf('longtime_station_first_time'.$add, time());
				}

				bo_set_conf('station_last_active'.$add, $time);
				$time_interval += bo_get_conf('longtime_station_active_time'.$add);
				bo_set_conf('longtime_station_active_time'.$add, $time_interval);
			}
			else
			{
				bo_set_conf('station_last_inactive'.$add, $time);
				
				//don't update offline counter if station is in test phase (first days)
				if (bo_get_conf('longtime_station_active_time'.$add) > 3600 * BO_STATISTICS_COUNT_OFFLINE_AFTER_MIN_ONLINE_HOURS)
				{
					$time_interval += bo_get_conf('longtime_station_inactive_time'.$add);
					bo_set_conf('longtime_station_inactive_time'.$add, $time_interval);
				}
				
				$cnt = bo_get_conf('longtime_station_inactive_count'.$add);
				bo_set_conf('longtime_station_inactive_count'.$add, $cnt);
			}


			//Activity/inactivity counter (GPS V-state)
			$time_interval = $last ? $time - $last : 0;
			if ($StData[$stId]['status'] == 'V')
			{
				bo_set_conf('station_last_nogps'.$add, $time);
				$time_interval += bo_get_conf('longtime_station_nogps_time'.$add);
				bo_set_conf('longtime_station_nogps_time'.$add, $time_interval);
				$cnt = bo_get_conf('longtime_station_nogps_count'.$add);
				bo_set_conf('longtime_station_nogps_count'.$add, $cnt);
			}

			//Station positions for last 24h, every station-update interval (15min)
			if (time() - $StData[$stId]['time'] < 3600 * 2)
			{
				$data = unserialize(bo_get_conf('station_data24h'.$add));
				if ($data['time'] != $StData[$stId]['time'])
				{
					//add height to own station
					if ($stId == $own_id)
					{
						$sql = "SELECT height
								FROM ".BO_DB_PREF."raw
								WHERE time=(SELECT MAX(time) FROM ".BO_DB_PREF."raw)
								LIMIT 1";
						$row = BoDb::query($sql)->fetch_assoc();
						if (isset($row['height']))
							$StData[$stId]['height'] = $row['height'];
					}
					
					$time_floor = floor(date('Hi', $StData[$stId]['time']) / BO_UP_INTVL_STATIONS);
					$data[$time_floor] = $StData[$stId];
					bo_set_conf('station_data24h'.$add, serialize($data));
				}
			}

		}


		bo_set_conf('uptime_stations', time());


		//send mail to owner if no signals
		if (BO_TRACKER_WARN_ENABLED === true && $own_id > 0)
		{
			$data = unserialize(bo_get_conf('warn_tracker_offline'));

			if ($StData[$own_id]['sig'] == 0)
			{
				if (!isset($data['offline_since']))
					$data['offline_since'] = time();

				if (time() - $data['offline_since'] >= ((double)BO_TRACKER_WARN_AFTER_HOURS * 3600))
				{
					if (time() - $data['last_msg'] >= ((double)BO_TRACKER_WARN_RESEND_HOURS * 3600))
					{
						$data['last_msg'] = time();
						bo_set_conf('warn_tracker_offline', serialize($data));
						bo_owner_mail('Station OFFLINE', "Your station/tracker didn't send any signals since ".date(_BL('_datetime'),$data['offline_since'] - 3600)." ! If there is lightning near your station, your tracker might be in interference mode. In this case you can ignore this message.");
					}
				}
			}
			else if ($data['last_msg']) //reset
			{
				$data = array();
				bo_owner_mail('Station ONLINE', "Your station/tracker is online again! ".$StData[$own_id]['sig']." signals in the last 60 minutes.");
			}

			bo_set_conf('warn_tracker_offline', serialize($data));
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
function bo_update_daily_stat()
{
	global $_BO;

	$ret = false;

	//Update strike-count per day
	$ytime = mktime(date('H')-3,0,0,date("n"),date('j')-1);
	$day_id = gmdate('Ymd', $ytime);
	$data = unserialize(bo_get_conf('strikes_'.$day_id));

	// Daily Statistics and longtime strike-count
	if (!$data || !is_array($data) || !$data['done'])
	{
		bo_echod(" ");
		bo_echod("=== Updating daily statistics ===");

		$radius = BO_RADIUS_STAT * 1000;
		$yesterday_start = gmdate('Y-m-d H:i:s', strtotime(gmdate('Y-m-d 00:00:00', $ytime)));
		$yesterday_end =   gmdate('Y-m-d H:i:s', strtotime(gmdate('Y-m-d 23:59:59', $ytime)));

		$stat_ok = bo_get_conf('uptime_stations') > $yesterday_end + 60;
		$raw_ok  = bo_get_conf('uptime_raw') > $yesterday_end + 60;

		// Strikes SQL template
		$sql_template = "SELECT COUNT(id) cnt FROM ".BO_DB_PREF."strikes s WHERE {where} time BETWEEN '$yesterday_start' AND '$yesterday_end'";

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

			$data['status'] = 1;
			bo_set_conf('strikes_'.$day_id, serialize($data));
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
			bo_set_conf('strikes_'.$day_id, serialize($data));
		}
		else if ($data['status'] == 2 && $stat_ok)
		{
			/*** Stations ***/
			$stations = array();
			// available stations
			$row = BoDb::query("SELECT COUNT(*) cnt FROM ".BO_DB_PREF."stations WHERE status != '-'")->fetch_assoc();
			$stations['available'] = $row->cnt;

			// active stations
			$row = BoDb::query("SELECT COUNT(*) cnt FROM ".BO_DB_PREF."stations WHERE status = 'A'")->fetch_assoc();
			$stations['active'] = $row->cnt;

			$data[10] = $stations;
			$data['status'] = 3;
			bo_set_conf('strikes_'.$day_id, serialize($data));

		}
		else if ($data['status'] == 3 && $raw_ok)
		{

			/*** Signals ***/
			//own exact value
			$signals_exact = BoDb::query("SELECT COUNT(id) cnt FROM ".BO_DB_PREF."raw WHERE time BETWEEN '$yesterday_start' AND '$yesterday_end'")->fetch_assoc();

			//all from station statistics
			$sql = "SELECT SUM(signalsh) cnt, COUNT(DISTINCT time) entries, station_id=".bo_station_id()." own
							FROM ".BO_DB_PREF."stations_stat
							WHERE time BETWEEN '$yesterday_start' AND '$yesterday_end' AND station_id != 0
							GROUP BY own, HOUR(time)";
			$res = BoDb::query($sql);
			while ($row = $res->fetch_assoc())
			{
				if (intval($row['entries']))
					$signals[(int)$row['own']] += $row['cnt'] / $row['entries'];
			}

			$data[4] = $signals_exact['cnt'];
			$data[5] = round($signals[0]);
			$data[6] = round($signals[1]);

			$data['status'] = 4;
			bo_set_conf('strikes_'.$day_id, serialize($data));
		}
		else if ($data['status'] == 4 && $raw_ok)
		{
			$start_time = time();
			//$channels = bo_get_conf('raw_channels');
			$max_lines = 10000;
			$limit = intval($data['raw_limit']);

			bo_echod("Analyzing signals: Start at $limit");

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
			$sql ="SELECT data, amp1, amp2, amp1_max, amp2_max,
							freq1, freq2, freq1_amp, freq2_amp,
							channels, ntime
					FROM ".BO_DB_PREF."raw
					WHERE time BETWEEN '$yesterday_start' AND '$yesterday_end'
					LIMIT $limit,$max_lines";
			$res = BoDb::query($sql);
			while ($row = $res->fetch_assoc())
			{
				if (bo_exit_on_timeout()) break;

				$d = bo_raw2array($row['data'], true, $row['channels'], $row['ntime']);

				if ($row['amp1_max'])
				{
					//count of first amplitudes
					$amps['count'][round($row['amp1'] / 10)][0]++;

					//count of max amplitudes
					$amps['count_max'][round($row['amp1_max'] / 10)][0]++;

					$freq1_index = round($row['freq1'] / 10);

					//count of main frequency
					$freqs['count'][$freq1_index][0]++;

					//amp sum of main frequency
					$freqs['sum_main'][$freq1_index][0] += $row['freq1_amp'];
				}

				if ($row['amp2_max'])
				{
					//count of first amplitudes
					$amps['count'][round($row['amp2'] / 10)][1]++;

					//count of max amplitudes
					$amps['count_max'][round($row['amp2_max'] / 10)][1]++;

					$freq2_index = round($row['freq2'] / 10);

					//count of main frequency
					$freqs['count'][$freq2_index][1]++;

					//amp sum of main frequency
					$freqs['sum_main'][$freq2_index][1] += $row['freq2_amp'];
				}

				//spectrum analyzation
				for ($i=0;$i<2;$i++)
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
				$data['done'] = true;
			}
			else
			{
				$data['raw_limit'] = $limit + $rows;
			}

			bo_set_conf('strikes_'.$day_id, serialize($data));
		}

		bo_echod("Datasets: ".count($data)."");


		/*** Longtime statistics ***/
		$D = unserialize(bo_get_conf('longtime_max_strikes_day_all'));
		if ($D[0] < $row_all['cnt'])
			bo_set_conf('longtime_max_strikes_day_all', serialize(array($row_all['cnt'], $ytime)));

		$D = unserialize(bo_get_conf('longtime_max_strikes_day_all_rad'));
		if ($D[0] < $row_all_rad['cnt'])
			bo_set_conf('longtime_max_strikes_day_all_rad', serialize(array($row_all_rad['cnt'], $ytime)));

		$D = unserialize(bo_get_conf('longtime_max_strikes_day_own'));
		if ($D[0] < $row_own['cnt'])
			bo_set_conf('longtime_max_strikes_day_own', serialize(array($row_own['cnt'], $ytime)));

		$D = unserialize(bo_get_conf('longtime_max_strikes_day_own_rad'));
		if ($D[0] < $row_own_rad['cnt'])
			bo_set_conf('longtime_max_strikes_day_own_rad', serialize(array($row_own_rad['cnt'], $ytime)));

		$D = unserialize(bo_get_conf('longtime_max_signals_day_own'));
		if ($D[0] < $signals_exact['cnt'])
			bo_set_conf('longtime_max_signals_day_own', serialize(array($signals_exact['cnt'], $ytime)));

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
		$res = BoDb::query($sql);
		while($row = $res->fetch_assoc())
		{
			$d = unserialize($row['data']);

			foreach($d as $id => $cnt)
			{
				if (!is_array($cnt))
					$data[$id] += $cnt;
			}

			$data['daycount']++;
		}

		bo_set_conf('strikes_month_'.$month_id, serialize($data));

		$ret = true;
	}

	return $ret;
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
				bo_echod('Error: Own URL is empty!');

		}

	}

}

function bo_my_station_update($url, $force_bo_login = false)
{
	bo_echod('=== '._BL('Linking with other MyBlitzortung stations').' ===');

	if (!$force_bo_login)
	{
		$authid = bo_get_conf('mybo_authid');

		if ($authid)
		{
			bo_echod(_BL('Using auth ID').' *'.$authid.'*');
		}
	}

	if (!$authid)
	{
		bo_echod(_BL('Getting Login string'));
		$login_id = bo_get_login_str();
		bo_echod(_BL('Login string is').': *'.$login_id.'*');
	}

	$ret = false;

	if (!$authid && !$login_id)
	{
		bo_echod(_BL('Couldnt get login id').'!');
	}
	else
	{
		bo_echod("== "._BL('Requesting data')." ==");
		bo_echod(_BL('Connecting to ').' *'.BO_LINK_HOST.'*');

		$request = 'id='.bo_station_id().'&login='.$login_id.'&username='.BO_USER.'&region='.BO_REGION.'&authid='.$authid.'&url='.urlencode($url).'&lat='.((double)BO_LAT).'&lon='.((double)BO_LON.'&rad='.(double)BO_RADIUS.'&zoom='.(double)BO_MAX_ZOOM_LIMIT);
		$data_url = 'http://'.BO_LINK_HOST.BO_LINK_URL.'?mybo_link&'.$request;

		$content = bo_get_file($data_url, $error, 'mybo_stations');

		$R = unserialize($content);

		if (!$R || !is_array($R))
		{
			bo_echod(_BL('Error talking to the server. Please try again later.').($error ? ' Code: *'.$error.'*' : ''));
		}
		else
		{
			switch($R['status'])
			{
				case 'auth_fail':

					bo_echod(_BL('Authentication failure'));

					if ($authid && $force_bo_login === false)
					{
						bo_echod('Fallback to Blitzortung login!');
						return bo_my_station_update($url,true);
					}

					break;

				case 'content_error':
					bo_echod(_BL('Cannot access your website').'!');
					break;

				case 'request_fail':
					bo_echod(_BL('Failure in Request URL: ').'*'._BC($data_url).'*');
					break;

				case 'rad_limit': case 'zoom_limit':
					bo_echod(_BL('You exceeded max. radius or zoom limit. Please change your settings in config.php!'));
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
							bo_echod(_BL('Auth ID is').': *'.$R['authid'].'*');

						bo_echod("== "._BL('Received urls').' ('.count($urls).') ==');
						ksort($urls);
						foreach($urls as $id => $st_url)
						{
							bo_echod(' - '.$id.': '._BC($st_url));
						}

						bo_echod(" ");
						bo_echod(_BL('DONE'));

						$ret = true;
					}
					else
					{
						bo_echod(_BL('Cannot read url data').'!');
					}

					break;
			}
		}
	}

	//logout
	if ($login_id)
	{
		bo_echod(_BL('Logging out from Blitzortung.org'));
		$file = bo_get_file('http://www.blitzortung.org/Webpages/index.php?page=3&login_string='.$login.'&logout=1', $code, 'logout');

		if (!$file)
			bo_echod("ERROR: Couldn't get file! Code: *$code*");
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
	$MinStrikeCount = 3;

	if (!$scantime || !$divisor)
		return;

	$start_time = time();

	$last = bo_get_conf('uptime_tracks');

	bo_echod(" ");
	bo_echod("=== Tracks ===");

	if (time() - $last > BO_UP_INTVL_TRACKS * 60 - 30 || $force || time() < $last)
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

		if ($force)
		{
			$lines = explode("\n", print_r($data,1));
			foreach($lines as $line)
				bo_echod($line);
		}

		bo_set_conf('strike_cells', gzdeflate(serialize($data)));
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

		$text = $extra;
		$text .= "\n\nLast error: ".date('Y-m-d H:i:s', $data['last']);
		$text .= "\nFirst error: ".date('Y-m-d H:i:s', $data['first']);
		$text .= "\nError count: ".$data['count'];

		bo_owner_mail(_BL('MyBlitzortung_notags').' - '._BL('Error').': '.$type, $text);
	}

	//Write
	bo_set_conf('uperror_'.$type, serialize($data));
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


	$last_strikes_region = unserialize(bo_get_conf('last_strikes_region'));
	$rate_strikes_region = unserialize(bo_get_conf('rate_strikes_region'));
	$STRIKE_RATE = _BN($rate_strikes_region[0], 1);
	$LAST_STRIKE = _BDT($last_strikes_region[0]);

	$tmp = @unserialize(bo_get_conf('last_strikes_stations'));
	$STATION_LAST_STRIKE   = _BDT($tmp[$station_id][0]);

	$act_time = bo_get_conf('station_last_active');
	$inact_time = bo_get_conf('station_last_inactive');
	$STATION_LAST_ACTIVE   = _BDT($act_time);
	$STATION_LAST_INACTIVE = _BDT($inact_time);
	$STATION_ACTIVE        = $act_time > $inact_time ? _BL('yes') : _BL('no');

	$IMPORT_LAST_STRIKES   = _BDT(bo_get_conf('uptime_strikes'));
	$IMPORT_LAST_STATIONS  = _BDT(bo_get_conf('uptime_stations'));
	$IMPORT_LAST_SIGNALS   = _BDT(bo_get_conf('uptime_raw'));

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

	if (BO_PURGE_ENABLE === true)
	{
		bo_echod(" ");
		bo_echod("=== Purging data ===");
		$last = bo_get_conf('purge_time');

		if ($force || (defined('BO_PURGE_MAIN_INTVL') && BO_PURGE_MAIN_INTVL && time() - $last > 3600 * BO_PURGE_MAIN_INTVL))
		{
			bo_set_conf('purge_time', time());

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
				$row = BoDb::query("SELECT MAX(id) id FROM ".BO_DB_PREF."strikes WHERE time < '$dtime'")->fetch_assoc();
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
			bo_echod('Purged nothing.');
		}

	}
	else
	{
		//bo_echod("Purging disabled.");
	}
}


function bo_getset_timeout($set_max_time = 60)
{
	static $max_time = 0, $start_time = 0;

	if (!$start_time)
		$start_time = time();

	if ($set_max_time && !$max_time)
	{
		$max_time = $set_max_time;
		return false;
	}

	if ($max_time > 0 && time() - $start_time > $max_time)
		return true;
	else
		return false;
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

	if ($debug)
		$max_time = 300;
	else if ($max_time < 20)  //give it a try
		$max_time = 50;
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
				($exec_timeout < 25 ? ' - Not good :(' : ' --> Fine :)').
				' *** Setting MyBlitzortung timeout to: '.$max_time."s");

	return $max_time;
}



function bo_delete_station($id = 0)
{
	$id = intval($id);

	if ($id > 0)
	{
		//get station info
		$station_info = bo_station_info($id);
		if ($station_info['id'] != $id)
			return false;

		//find new ID
		$row = BoDb::query("SELECT MAX(id) id FROM ".BO_DB_PREF."stations WHERE id >= ".intval(BO_DELETED_STATION_MIN_ID))->fetch_assoc();
		$new_id = $row['id']+1;
		if ($new_id < intval(BO_DELETED_STATION_MIN_ID))
			$new_id = intval(BO_DELETED_STATION_MIN_ID);

		//save old data in extra variable
		$deleted_stations = unserialize(bo_get_conf('stations_deleted'));
		$deleted_stations[] = $station_info;
		bo_set_conf('stations_deleted', serialize($deleted_stations));

		
		//delete data from "new_id" -> otherwise update would not work it sth did wrong before
		BoDb::query("DELETE FROM ".BO_DB_PREF."conf             WHERE name LIKE '%#".$new_id."#%'");
		BoDb::query("DELETE FROM ".BO_DB_PREF."stations_stat    WHERE station_id='$new_id'");
		BoDb::query("DELETE FROM ".BO_DB_PREF."stations_strikes WHERE station_id='$new_id'");
		BoDb::query("DELETE FROM ".BO_DB_PREF."densities        WHERE station_id='$new_id'");
		BoDb::query("DELETE FROM ".BO_DB_PREF."stations         WHERE id='$new_id'");

		
		//reassign IDs
		BoDb::query("UPDATE ".BO_DB_PREF."conf SET name=REPLACE(name, '#".$id."#', '#".$new_id."#') WHERE name LIKE '%#".$id."#%'", false);
		BoDb::query("UPDATE ".BO_DB_PREF."stations_stat    SET station_id='$new_id' WHERE station_id='$id'", false);
		BoDb::query("UPDATE ".BO_DB_PREF."stations_strikes SET station_id='$new_id' WHERE station_id='$id'", false);
		BoDb::query("UPDATE ".BO_DB_PREF."densities        SET station_id='$new_id' WHERE station_id='$id'", false);

		$last_strikes = unserialize(bo_get_conf('last_strikes_stations'));
		$last_strikes[$new_id] = $last_strikes[$id];
		unset($last_strikes[$id]);
		bo_set_conf('last_strikes_stations', serialize($last_strikes));

		//last not least the station itself (last update, if former queries fail)
		BoDb::query("UPDATE ".BO_DB_PREF."stations         SET id='$new_id', status='-' WHERE id='$id'");

		bo_echod("Deleted old station $id, reassigned new ID $new_id");

		return true;
	}

	return false;
}

function bo_download_external($force = false)
{
	global $_BO;


	if (!isset($_BO['download']) || count($_BO['download']) == 0)
		return;

	bo_echod(" ");
	bo_echod("=== File Downloads ===");

	$data = unserialize(bo_get_conf('uptime_ext_downloads'));

	if (time() - $data['last_update'] > BO_UP_INTVL_DOWNLOADS * 60 - 30 || $force || time() < $data['last_update'])
	{
		//pre-save time to avoid errors when downloads hang...
		$data['last_update'] = time();
		bo_set_conf('uptime_ext_downloads', serialize($data));
	
	
		
		foreach($_BO['download'] as $name => $d)
		{
			//use a short id
			$id = substr(md5($id), 0, 10);
			
			bo_echod(" - $name [$id] / Last download: ".date('Y-m-d H:i:s', $data['data'][$id]['last']).' / Last modified: '.date('Y-m-d H:i:s', $data['data'][$id]['modified']));
			
			//Download if min minute is gone and last update was interval-time before
			if (!$force && ((int)date('i') < $d['after_minute'] || time() - $data['data'][$id]['last'] < $d['interval'] * 60))
			{
				bo_echod("    -> Needs no download");
				continue;
			}

			clearstatcache();
			
			//Download!
			$modified = $force ? 0 : $data['data'][$id]['modified'];
			$range = 0;
			$data['data'][$id]['last'] = time();
			$file_content = bo_get_file($d['url'], $code, 'download_'.$id, $range, $modified);
			
			if ($file_content === false)
			{
				if ($code == 304)
					bo_echod("    -> File not modified, no download. ");
				else
					bo_echod("    -> Error: Download failed (Code: $code)!");
				
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
				
				$kbytes = round(strlen($file_content) / 1024, 1);
				
				bo_echod("    -> Success: Saved $kbytes kB");
			}
			
		}

		
		
		$data['last_update'] = time();
		bo_set_conf('uptime_ext_downloads', serialize($data));

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