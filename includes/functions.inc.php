<?php


function bo_insert_url($exclude = array(), $add = null, $absolute = false)
{
	if (!is_array($exclude))
		$exclude = array($exclude);

	if (bo_user_get_id())
		$exclude[] = 'bo_login';

	$exclude_bo = array_search('bo_*', $exclude) !== false;

	$query = '';
	foreach($_GET as $name => $val)
	{
		if (array_search($name, $exclude) !== false 
			|| ($exclude_bo && substr($name,0,3) == 'bo_' && $name != 'bo_page') 
			|| $name == BO_LANG_ARGUMENT)
			continue;

		if ($name == 'bo_page' && !$val)
			continue;
			
		$query .= urlencode($name).(strlen($val) ? '='.urlencode($val) : '').'&';
	}

	if (count($exclude) && $add !== null)
		$query .= $exclude[0].'='.urlencode($add).'&';

	//Always add current language in url if not default (ie nedded for caching)
	if ( (BO_LOCALE != _BL() || BO_LANG_REDIRECT) && array_search(BO_LANG_ARGUMENT, $exclude) === false)
		$query .=  BO_LANG_ARGUMENT.'='._BL().'&';
	
	
	$url = $_SERVER['REQUEST_URI'];
	
	if ($absolute)
	{
		//add the requested file with path
		preg_match('@([^\?]+)(\?|$)@', $url, $r);
		$url = $r[1].'?'.$query;
	}
	else
	{
		//add the requested file
		preg_match('@/([^/\?]+)(\?|$)@', $url, $r);
		$url = $r[1].'?'.$query;
	}
	
	$url = strtr($url, array('&&' => '&', '?&' => '?'));
	$url = preg_replace('/&$/', '', $url);
	$url = preg_replace('/\?$/', '', $url);

	if (!trim($url))
		$url = "?";
	
	return $url;
}



function bo_gpc_prepare($text)
{
	$text = trim($text);
	$text = stripslashes($text);

	if (!defined('BO_UTF8') || !BO_UTF8)
		return utf8_encode($text);
	else
		return $text;
}


//recursive delete function
function bo_delete_files($dir, $min_age=0, $depth=0)
{
	$count = 0;
	$dir .= '/';

	if ($delete_dir_depth === false)
		$delete_dir_depth = 10;

	$files = @scandir($dir);

	if (is_array($files))
	{
		foreach($files as $file)
		{
			if (!is_dir($dir.$file) && substr($file,0,1) != '.' && ($min_age == 0 || @fileatime($dir.$file) < time() - 3600 * $min_age) )
			{
				@unlink($dir.$file);
				$count++;
			}
			else if (is_dir($dir.$file) && substr($file,0,1) != '.' && $depth > 0)
			{
				$count += bo_delete_files($dir.$file, $min_age, $depth-1);
				@rmdir($dir.$file.'/');
			}
		}
	}
	
	return $count;
}


function bo_setcookie($name, $value, $expire = 0, $path = '/')
{
	//don't set cookie on non-cookie domain
	if (!BO_FILE_NOCOOKIE || strpos(BO_FILE_NOCOOKIE, 'http://'.$_SERVER['HTTP_HOST'].'/') === false)
	{
		@setcookie($name, $value, $expire, $path);
	}
}

function bo_get_file($url, &$error = '', $type = '', &$range = 0, &$modified = 0, $as_array = false, $depth=0)
{
	//avoid infinite loop on redirections (recursion)
	if ($depth > 5)
		return false;

	if (BO_USE_PHPURLWRAPPER === true)
	{
		$content = file_get_contents($url);
		$content_size = strlen($content);

		if ($as_array)
			$content = explode("\n", $content);
	}
	else
	{
		ini_set("auto_detect_line_endings", "1");

		$err = 0;
		$content_size = 0;
		$content = $as_array ? array() : '';

		$parsedurl = @parse_url($url);
		$host = $parsedurl['host'];
		$user = $parsedurl['user'];
		$pass = $parsedurl['pass'];
		$path = $parsedurl['path'];
		$query = $parsedurl['query'];

		$fp = fsockopen($host, 80, $errno, $errstr);

		if (!$fp)
		{
			$error = "Connect ERROR: $errstr ($errno)<br />\n";
			echo $error;
			$err = 1;
			$content = false;
		}
		else
		{
			// only HTTP1.1 if range request
			// otherwise we could get a chunked response!
			$http_ver = $range > 0 ? "1.1" : "1.0";

			$out =  "GET ".$path."?".$query." HTTP/".$http_ver."\r\n";
			$out .= "Host: ".$host."\r\n";
			$out .= "User-Agent: MyBlitzortung ".BO_VER."\r\n";
			$out .= "Cache-Control: max-age=0\r\n";
			
			if ($user && $pass)
				$out .= "Authorization: Basic ".base64_encode($user.':'.$pass)."\r\n";

			if ($range > 0)
				$out .= "Range: bytes=".intval($range)."-\r\n";

			if ($modified)
				$out .= "If-Modified-Since: ".gmdate("r", $modified)."\r\n";

			$out .= "Connection: Close\r\n\r\n";

			$first = true;
			$response = array();
			$accepted_range = false;
			$content_length = 0;
			$location = '';

			if (fwrite($fp, $out) !== false)
			{
				//Header
				do
				{
					$header = chop(fgets($fp));

					if ($first) //Check the first line (=Response)
					{
						preg_match('/[^ ]+ ([^ ]+) (.+)/', $header, $response);

						if ($response[1] == '304')
						{
							$err = 3;
							break;
						}
						else if ($response[1] != '200' && $response[1] != '206' && $response[1] != '302')
						{
							$err = 2;
							break;
						}
					}

					if (preg_match('/Content\-Range: ?bytes ([0-9]+)\-([0-9]+)\/([0-9]+)/', $header, $r))
						$accepted_range = array($r[1], $r[2], $r[3]);
					elseif (preg_match('/Content\-Length: ?([0-9]+)/', $header, $r))
						$content_length = $r[1];
					elseif (preg_match('/Last\-Modified:(.+)/', $header, $r))
						$modified = strtotime($r[1]);
					elseif (preg_match('/Location:(.+)/', $header, $r))
						$location = trim($r[1]);

					$first = false;
				}
				while (!empty($header) and !feof($fp));


				//It was a redirection!
				if ($response[1] == '302')
				{
					if ($location)
					{
						$url  = 'http://';
						$url .= $user && $pass ? $user.':'.$pass.'@' : '';
						$url .= $host.$path.$location;
						return bo_get_file($url, $error, $type, $range, $modified, $as_array, $depth+1);
					}
					else
						$err = 2;
				}

				//Get the Content
				while (!feof($fp))
				{
					$line = fgets($fp);
					$content_size += strlen($line);

					if ($as_array)
					{
						$line = strtr($line, array("\r" => '', "\n" => ''));
						$content[] = $line;
					}
					else
						$content  .= $line;
				}
			}
			else
			{
				$error = "Send ERROR: $errstr ($errno)<br />\n";
				echo $error;
			}

			fclose($fp);
		}

		if ($err == 2)
		{
			$error = $response[1].' '.$response[2];
			$content = false;
		}
		elseif ($err == 3) //Not Modified
		{
			$error = 304;
			$content = false;
		}
	}


	if ($type)
	{
		$data = unserialize(BoData::get('download_statistics'));
		$data[$type]['count'][$err]++;

		if ($content_size)
		{
			$data[$type]['traffic'] += $content_size;

			$today = date('Ymd');

			if ($today != $data[$type]['traffic_today_date'])
			{
				$data[$type]['traffic_today'] = 0;
				$data[$type]['count_today'] = 0;
			}

			$data[$type]['traffic_today_date'] = $today;
			$data[$type]['traffic_today'] += $content_size;
			$data[$type]['count_today']++;
		}

		if (!$data[$type]['time_first'])
			$data[$type]['time_first'] = time();

		BoData::set_delayed('download_statistics', serialize($data));
	}

	if ($range > 0)
	{
		if ($accepted_range === false)
		{
			$range = array();
			if ($content_length > 0 && $content !== false) // didn't accept range, but sent whole file
			{
				//$range = array(1, $content_length, $content_length);
			}
			else
			{
				$range = array();
				$content = false;
			}
		}
		else
		{
			$range = $accepted_range;
		}
	}


	return $content;
}




function bo_owner_mail($subject, $text)
{
	$mail = bo_user_get_mail(1);
	$ret = false;

	if ($mail)
	{
		$ret = bo_mail($mail, $subject, $text);
	}

	if (!$ret)
		bo_echod("ERROR: Could not send mail to '$mail'!");
	
	return $ret;
}


function bo_mail($mail, $subject = '', $text = '', $headers = '', $from = '')
{
	if (!trim($from))
	{
		if (BO_EMAIL_FROM)
		{
			$from = BO_EMAIL_FROM;
		}
		else
		{
			//create a pseudo address
			$from = _BL('MyBlitzortung_notags').' <noreply@'.$_SERVER['HTTP_HOST'].'>';
		}
	}

	$from    = trim($from);
	$headers = trim($headers);
	$mail    = trim($mail);
	
	if (BO_EMAIL_SMTP !== true)
	{
		if ($headers && $from)
			$from = "From: $from\n";
		elseif ($from)
			$from = "From: $from";
		
		$ok = mail($mail, $subject, $text, $from.$headers);
	}
	else
	{
		if (preg_match("/^(.*)[ ]+\<(.*)\>/", $from, $r))
		{
			$mail_from = $r[2];
			$mail_from_name = $r[1];
		}
		else
		{
			$mail_from = $from;
			$mail_from_name = '';
		}
		
		require_once('phpmailer/class.phpmailer.php');

		$PHPMailer = new PHPMailer();
		$PHPMailer->IsSMTP();
		$PHPMailer->Host       = BO_EMAIL_SMTP_SERVER;
		$PHPMailer->Port       = BO_EMAIL_SMTP_PORT;
		$PHPMailer->Subject    = $subject;
		$PHPMailer->Body       = $text;
		//$PHPMailer->SMTPDebug  = 2; 
		
		$PHPMailer->SetFrom($mail_from, $mail_from_name);
		$PHPMailer->AddAddress($mail);
		
		
		if (BO_EMAIL_SMTP_USERNAME && BO_EMAIL_SMTP_PASSWORD)
		{
			$PHPMailer->SMTPAuth   = true;
			$PHPMailer->SMTPSecure = BO_EMAIL_SMTP_SECURE;
			$PHPMailer->Username   = BO_EMAIL_SMTP_USERNAME;
			$PHPMailer->Password   = BO_EMAIL_SMTP_PASSWORD;
		}

		$ok = $PHPMailer->Send();

	}
	
	return $ok;
}

function bo_output_kml()
{
	global $_BO;

	$type = intval($_GET['kml']);

	$host = $_SERVER["SERVER_NAME"] ? $_SERVER["SERVER_NAME"] : $_SERVER["HTTP_HOST"];
	$p = parse_url($_SERVER["REQUEST_URI"]);
	$url = 'http://'.$host.$p['path'];

	header("Content-type: application/vnd.google-earth.kml+xml");
	header("Content-Disposition: attachment; filename=\"MyBlitzortung.kml\"");

	echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
	echo '<kml xmlns="http://www.opengis.net/kml/2.2">'."\n";

	switch($type)
	{

		case 1:

			echo "<Folder>\n";
			echo "<name>"._BL($d['name'], false, BO_CONFIG_IS_UTF8)."</name>\n";
			echo "<description></description>\n";
			echo "<visibility>0</visibility>\n";
			echo "<refreshVisibility>0</refreshVisibility>\n";

			foreach($_BO['mapimg'] as $id => $d)
			{
				if (!$d['kml'])
					continue;

				$imgurl = $url."?map=".$id."&amp;".BO_LANG_ARGUMENT."="._BL();

				if (!$d['file'])
					$imgurl .= "&amp;transparent";

				echo "<GroundOverlay>\n";
				echo "<name>"._BL($d['name'], false, BO_CONFIG_IS_UTF8)."</name>\n";
				echo "<description></description>\n";
				echo "<Icon>\n";
				echo "<href>".$imgurl."</href>\n";
				echo "</Icon>\n";
				echo "<LatLonBox>\n";
				echo "<north>".$d['coord'][0]."</north>\n";
				echo "<south>".$d['coord'][2]."</south>\n";
				echo "<east>".$d['coord'][1]."</east>\n";
				echo "<west>".$d['coord'][3]."</west>\n";
				echo "<rotation>0</rotation>\n";
				echo "<visibility>0</visibility>\n";
				echo "<refreshVisibility>0</refreshVisibility>\n";
				echo "</LatLonBox>\n";
				echo "</GroundOverlay>\n";


			}

			echo "</Folder>\n";

			break;

		default:


			echo "<NetworkLink>\n";
			echo "<name>"._BL('MyBlitzortung_notags')."</name>\n";
			echo "<visibility>0</visibility>\n";
			echo "<open>0</open>\n";
			echo "<description></description>\n";
			echo "<refreshVisibility>0</refreshVisibility>\n";
			echo "<flyToView>0</flyToView>\n";
			echo "<Link>\n";
			echo "  <href>".$url."?kml=1&amp;".BO_LANG_ARGUMENT."="._BL()."</href>\n";
			echo "</Link>\n";
			echo "</NetworkLink>\n";

	}

	echo '</kml>';

	exit;
}

function bo_session_close($force = false)
{
	$c = intval(BO_SESSION_CLOSE);

	if (!$c)
		return;

	if ($c == 2 || ($c == 1 && $force))
		@session_write_close();

}

function bo_bofile_url()
{
	if (!bo_user_get_id() && defined('BO_FILE_NOCOOKIE') && BO_FILE_NOCOOKIE)
		return BO_FILE_NOCOOKIE;
	else
		return BO_FILE;
}

function bo_participants_locating_min()
{
	static $value=false;

	if ($value === false && intval(BO_FIND_MIN_PARTICIPANTS_HOURS))
	{
		$tmp = unserialize(BoData::get('bo_participants_locating_min'));
		$value = intval($tmp['value']);
	}

	if (!$value)
		$value = BO_MIN_PARTICIPANTS;

	return intval($value);
}

function bo_participants_locating_max()
{
	static $value=false;

	if ($value === false && intval(BO_FIND_MAX_PARTICIPANTS_HOURS))
	{
		$tmp = unserialize(BoData::get('bo_participants_locating_max'));
		$value = intval($tmp['value']);
	}

	if (!$value)
		$value = BO_MAX_PARTICIPANTS;

	return intval($value);
}



function bo_echod($text = '')
{
	static $start = 0;
	
	if (!$start)
	{
		$start = microtime(true);
	}
	
	if (!isset($_GET['quiet']))
	{
		echo date('Y-m-d H:i:s', $start);
		printf(" +%5dms | %s\n", (microtime(true)-$start)*1000, $text);
		flush();
	}
}

function bo_dprint($text = '')
{
	if (defined('BO_DEBUG') && BO_DEBUG)
	{
		echo $text;
	}
}


//from hex to binary
function bo_hex2bin($data)
{
	
	$bdata = '';
	for ($j=0;$j < strlen($data);$j+=2)
	{
		$bdata .= chr(hexdec(substr($data,$j,2)));
	}
	
	return $bdata;
}


function bo_insert_date_string($text, $time = null)
{
	if ($time === null)
		$time = time();

	$replace = array(
		'%Y' => gmdate('Y', $time),
		'%y' => gmdate('y', $time),
		'%M' => gmdate('m', $time),
		'%D' => gmdate('d', $time),
		'%h' => gmdate('H', $time),
		'%m' => gmdate('i', $time)
		);

	$text = strtr($text, $replace);
		
	return $text;
}


function bo_get_regions($bo_station_id = false)
{
	global $_BO;

	$regions[0] = array();
	$regions[1] = array();
	
	if (isset($_BO['region']) && is_array($_BO['region']))
	{
		foreach ($_BO['region'] as $reg_id => $d)
		{
			if ($d['visible'] && isset($d['rect_add']))
			{
				$regions[0][$reg_id] = _BS($d['name'], false, BO_CONFIG_IS_UTF8);
				$regions[1]['-'.$reg_id] = _BL('outside of').' '._BS($d['name'], false, BO_CONFIG_IS_UTF8);
			}
		}

		asort($regions[0]);
		asort($regions[1]);
	}

	//Distances
	if ($bo_station_id !== false)
	{
		$dists = explode(',', BO_DISTANCES_REGION);
		$name = bo_station_city($bo_station_id);
		
		if (count($dists) && $name)
		{
			foreach($dists as $dist)
			{
				$dist = intval($dist);
				if ($dist > 0)
				{
					$regions[0]['dist'.$dist] = _BL('max.').' '._BK($dist).' '._BL('to station').' '._BC($name);
					$regions[1]['-dist'.$dist] = _BL('min.').' '._BK($dist).' '._BL('to station').' '._BC($name);
				}
			}
		}
	}
	
	return $regions;

}

function bo_get_select_region($region, $bo_station_id = false, $show_exclude = true)
{
	$regions = bo_get_regions($bo_station_id);
	$ret = '';
	
	if (count($regions[0]) > 1)
	{
		$ret .= '<select name="bo_region" onchange="submit();" id="bo_stat_strikes_select_now" class="bo_select_region">';
		$ret .= '<option value="">'._BL('No limit').'</option>';
		
		$ret .= '<optgroup label="'._BL('Show only region').'">';
		foreach($regions[0] as $i => $y)
			$ret .= '<option value="'.$i.'" '.($i === $region ? 'selected' : '').'>'.$y.'</option>';
		$ret .= '</optgroup>';

		if ($show_exclude && count($regions[1]) > 1)
		{
			$ret .= '<optgroup label="'._BL('Exclude region').'">';
			foreach($regions[1] as $i => $y)
				$ret .= '<option value="'.$i.'" '.($i === $region ? 'selected' : '').'>'.$y.'</option>';
			$ret .= '</optgroup>';
		}
		
		$ret .= '</select>';
	}

	return $ret;
}

function bo_region2name($region, $bo_station_id = false)
{
	$regions = bo_get_regions($bo_station_id);
	$regions = array_merge($regions[0], $regions[1]);
	
	return $regions[$region];
}



function bo_error_handler($errno, $errstr, $errfile, $errline)
{
	
	// This error code is not included in error_reporting
    if (!(error_reporting() & $errno)) 
        return;
	
	$exit = false;
	
	switch ($errno) 
	{
		case E_ERROR:
		case E_USER_ERROR:
			$exit = true;
			$type = "ERROR";
			break;

		case E_WARNING:
		case E_USER_WARNING:
			$type = "WARNING";
			break;

		case E_NOTICE:
		case E_USER_NOTICE:
			$type = "Notice";
			break;

		default:
			$type = "Unknown ERROR";
			break;
    }
	
	$text  = "$type ";
	$text .= "on line $errline ";
	$text .= "in file $errfile:\t";
	//$text .= "[$errno]\t";
	$text .= " *** $errstr *** \t";
	$text .= "MB: ".BO_VER."\t";
	$text .= "PHP ".PHP_VERSION." (".PHP_OS.")\t";
	$text .= "URL: ".$_SERVER['REQUEST_URI'];
	$text .= "\n";
	
	if (defined('BO_PHP_ERROR_LOG') && BO_PHP_ERROR_LOG)
	{
		$date = gmdate('Y-m-d H:i:s');
		$ok = @file_put_contents(BO_PHP_ERROR_LOG, $date." | ".$text, FILE_APPEND);
	}
	
	if ($exit)
	{
		@header('HTTP/1.1 500 Internal Server Error');
		
		if (BO_DEBUG === true || php_sapi_name() === 'cli')
			echo $text;
			
		echo "<p>Oops! An error occured. Please try again later.<br />\n";
		exit();
	}
	
	// Don't execute PHP internal error handler
    return true;
}

function bo_cache_log($text, $end = false)
{
	if (BO_CACHE_LOG !== true)
		return;
		
	static $start = true, $lines = "", $msec_start = 0;

	if ($end)
	{
		file_put_contents(BO_DIR.BO_CACHE_DIR.'/cache.log', $lines."---\n", FILE_APPEND);
		return;
	}
	
	$msec = microtime(true) * 1000;
	
	if ($start)
	{
		$start = false;
		register_shutdown_function('bo_cache_log', '', true);
		$msec_start = $msec;
	}

	$lines .= date('Y-m-d H:i:s').sprintf(" | +%4dms | %1d | %80s | ", $msec - $msec_start, BoDb::$dbh === null ? 0 : 1, $_SERVER['QUERY_STRING'])." $text\n";
}

function bo_output_cache_file($cache_file, $mod_time = 0)
{
	static $output_disabled = false;
	
	if (!$output_disabled)
	{
		if (!file_exists($cache_file))
		{
			header("X-MyBlitzortung: no-cache-file", false);
			header("Pragma: no-cache");
			header("Cache-Control: no-cache");
			bo_cache_log("Out - no-cache-file");
			return false;
		}
		
		//don't output anything the next time the function get called
		$output_disabled = true;
		
		if (function_exists('apache_request_headers') && $mod_time !== false)
		{
			$request = apache_request_headers();
			$ifmod = $request['If-Modified-Since'] ? strtotime($request['If-Modified-Since']) : false;
			
			if (!$mod_time)
			{
				clearstatcache();
				$mod_time = filemtime($cache_file);
			}
				
			if ($mod_time - $ifmod <= 1)
			{
				header("X-MyBlitzortung: not-modified", false);
				header("HTTP/1.1 304 Not Modified");
				bo_cache_log("Out - not-modified!");
				return;
			}
				
		}
		
		if ($mod_time === false)
		{
			header("X-MyBlitzortung: new-file", false);
			bo_cache_log("Out - new-file");			
		}
		else
		{
			header("X-MyBlitzortung: from-cache", false);
			bo_cache_log("Out - from-cache");
		}
			
		bo_readfile_mime($cache_file);
	}
	else
	{
		bo_cache_log("New file created");
	}
	
	if (BO_CACHE_WAIT_SAME_FILE > 0)
	{
		$isfile = $cache_file.'.is_creating';
		@unlink($isfile);
		clearstatcache();
	}
}


	
function bo_output_cachefile_if_exists($cache_file, $last_update, $update_interval, $allow_old = true)
{	
	
	bo_cache_log("Check - $cache_file");
	
	if (file_exists($cache_file))
	{
		$file_expired_sec = $last_update - @filemtime($cache_file);
		
		$deliver_old = $allow_old && (BO_CACHE_CREATE_NEW_DELIVER_OLD > 0) 
						&& ($file_expired_sec <= $update_interval * BO_CACHE_CREATE_NEW_DELIVER_OLD);

		bo_cache_log("Check - Data now: ".date('Y-m-d H:i:s', $last_update));
						
		//Delete files that are to new
		if (filemtime($cache_file) - 300 > time())
		{
			@unlink($cache_file);
			clearstatcache();
			bo_cache_log("Check - Cache file deleted, was to new!");
		}
		
		bo_cache_log("Check - Filedate: ".date('Y-m-d H:i:s', @filemtime($cache_file)));
		bo_cache_log("Check - Sec expired $file_expired_sec - Intvl: $update_interval s");
	}
	else
	{
		bo_cache_log("Check - Doesn't exist");
	}

	//if same file is created for a parallel client
	//and delivering outdated files isn't possible
	//then wait some time
	if (BO_CACHE_WAIT_SAME_FILE > 0 && !$deliver_old)
	{
		$isfile = $cache_file.'.is_creating';
		$start = microtime(true);
		$force_load_old_file = false;
		clearstatcache();
		
		//if file is currently created by another process -> wait
		while (file_exists($isfile) && time() - filemtime($isfile) < 30)
		{
			if (microtime(true) - $start > BO_CACHE_WAIT_SAME_FILE * 1e-6)
			{
				//file didn't appear, load old one instead
				$force_load_old_file = true;
				bo_cache_log("Check - Waited, forced old file");
				break;
			}
			
			usleep(rand(10, 300) * 1000);
			clearstatcache();
		}
	}
	
	//Cache-File is ok
	if (file_exists($cache_file) && filesize($cache_file) > 0)
	{
		
		$is_new = $file_expired_sec < $update_interval;
		$is_old = $file_expired_sec < $update_interval * BO_CACHE_WAIT_SAME_FILE_OLD;
		
		//if file is new 
		//OR file is not too old
		if ($is_new || ($is_old && $force_load_old_file))
		{
			bo_cache_log("Check - Output cache file and exit");
			bo_output_cache_file($cache_file);
			exit;
		}

		
		//deliver cached file (if not too old) and after that create new file
		if ($deliver_old)
		{
			//Delivering old file, which is still new enough
			//but we need to send "outdated" headers
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime($cache_file) - $update_interval)." GMT");
			header("Expires: ".gmdate("D, d M Y H:i:s", time() + 10)." GMT");
			header("Cache-Control: public, max-age=10");
			header("X-MyBlitzortung: delivered-outdated", false);
			
			bo_cache_log("Check - Deliver old cache file, creating new one");
			
			bo_output_cache_file($cache_file);
			ignore_user_abort(true);
			session_write_close();
		}

		
		//Since here, the cachefile was too old
		//The last chance is to check the last update, 
		//maybe no redraw is needed
		//if then, only one database query needed
		$last_update_real = BoData::get('uptime_strikes');
		if (filemtime($cache_file) > $last_update_real && BO_CACHE_MOD_UPDATE_DIVISOR)
		{
			bo_cache_log("Check - Output old cache file, no new data");
			touch($cache_file, time() + $update_interval / BO_CACHE_MOD_UPDATE_DIVISOR);
			bo_output_cache_file($cache_file);
			exit;
		}
		

	}
	
	if (BO_CACHE_WAIT_SAME_FILE > 0 && $isfile)
	{
		//Nothing found
		//mark "file is currently under construction"
		@mkdir(dirname($cache_file), 0777, true);
		if (touch($isfile))
			register_shutdown_function('unlink_quiet', $isfile);
		ignore_user_abort(true);
	}
}

function unlink_quiet($f)
{
	@unlink($f);
}

function extension2mime($extension)
{
	if ($extension == 'jpg' || $extension == 'jpeg')
		$mime = "image/jpeg";
	elseif ($extension == 'gif')
		$mime = "image/gif";
	elseif ($extension == 'png')
		$mime = "image/png";

	return $mime;
}

function bo_readfile_mime($file)
{
	$extension = strtolower(substr($file, strrpos($file, '.')+1));
	$mime = extension2mime($extension);
	bo_cache_log("Read - $mime");
	header("Content-Type: $mime");
	readfile($file);
	flush();
}



function bo_str_max($str, $max = 35)
{
	if (strlen($str) > $max)
		return substr($str,0,$max-20).($max > 30 ? '...'.substr($str,-8) : '');
	else
		return $str;
}




function bo_access_url($server = BO_IMPORT_SERVER, $path = BO_IMPORT_PATH)
{
	$path = sprintf($path, trim(BO_REGION));
	return sprintf('http://%s:%s@%s/%s', trim(BO_USER), trim(BO_PASS), trim($server), $path);
}

function bo_hours($h)
{
	$minute = ($h - floor($h)) * 60;
	$minute = $minute < 10 ? "0$minute" : $minute;
	$hour   = floor($h) < 10 ? "0".floor($h) : floor($h);
	
	return "$hour:$minute";
}





function bo_version()
{
	return bo_version2number(BoData::get('version'));
}

function bo_version2number($version)
{
	preg_match('/([0-9]+)(\.([0-9]+)(\.([0-9]+))?)?([a-z])?/', $version, $r);
	$num = $r[1] * 10000 + $r[3] * 100 + $r[5];
	
	if ($r[6])
		$num += (abs(ord($r[6]) - ord('a')) + 1) * 0.01;
	
	return $num;
}

// min/max zoom limits for dynamic map
// NOTE: will be used by tiles too!
function bo_get_zoom_limits()
{

	//allow all zoom levels on logged in users with access rights	
	if ((bo_user_get_level() & BO_PERM_NOLIMIT)) 
	{
		$max_zoom = defined('BO_MAX_ZOOM_IN_USER') ? intval(BO_MAX_ZOOM_IN_USER) : 999;
		$min_zoom = defined('BO_MIN_ZOOM_OUT_USER') ? intval(BO_MIN_ZOOM_OUT_USER) : 0;
	}
	else
	{
		$max_zoom = defined('BO_MAX_ZOOM_IN') ? intval(BO_MAX_ZOOM_IN) : 999;
		$min_zoom = defined('BO_MIN_ZOOM_OUT') ? intval(BO_MIN_ZOOM_OUT) : 0;
	}
	
	$min_zoom = max($min_zoom, round(BO_TILE_SIZE/256)-1);
	
	return array($min_zoom, $max_zoom);
}

	

function bo_get_latest_strike_calc_time($refresh_interval, $type = '')
{
	$time = 0;
	$cache_fast = BO_CACHE_FAST && (BO_CACHE_FAST == $type || !$type);

	if ($cache_fast)
	{
		$time = floor(time() / $refresh_interval) * $refresh_interval;
	}
	else
	{
		$row = BoDb::query("SELECT MAX(time) mtime FROM ".BO_DB_PREF."strikes s")->fetch_assoc();
		$time = strtotime($row['mtime'].' UTC');
		
		if (time() - $time > BO_LATEST_STRIKE_TIME_CALC * 60)
		{
			$time = bo_get_last_import_time($refresh_interval, $type);
		}
	}
	
	return $time;
}


function bo_get_last_import_time($refresh_interval, $type = '')
{
	$time = 0;
	$cache_fast = BO_CACHE_FAST && (BO_CACHE_FAST == $type || !$type);
	
	if (!$cache_fast)
	{
		$time = BoData::get('uptime_strikes_modified');
		
		if (time() - $time > 60 * 15) //if import is failing (i.e. no strokes)
			$time = 0;
	}
	
	if ($time == 0)
		$time = floor(time() / $refresh_interval) * $refresh_interval - 60;

	return $time;
}


?>