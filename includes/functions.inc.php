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
	
	
// Database helper
function bo_db($query = '', $die_on_errors = true)
{
	$connid = BoDb::connect();

	if (!$query)
		return $connid;

	$erg = BoDb::query($query);

	if ($die_on_errors && $erg === false)
		die("<p>Database Query Error:</p><pre>" . htmlspecialchars(BoDb::error()) .
			"</pre> <p>for query</p> <pre>" . htmlspecialchars($query) . "</pre>");

	$qtype = strtolower(substr(trim($query), 0, 6));
	switch ($qtype)
	{
		case 'insert':
			return BoDb::insert_id();

		case 'delete':
		case 'update':
			return BoDb::affected_rows();

		default:
			return $erg;
	}

}



// Load config from database
function bo_get_conf($name, &$changed=0)
{
	$row = bo_db("SELECT data, UNIX_TIMESTAMP(changed) changed FROM ".BO_DB_PREF."conf WHERE name='".BoDb::esc($name)."'")->fetch_object();
	$changed = $row->changed;

	return $row->data;
}

// Save config in database
function bo_set_conf($name, $data)
{
	$name_esc = BoDb::esc($name);
	$data_esc = BoDb::esc($data);

	if ($data === null)
	{
		$sql = "DELETE FROM ".BO_DB_PREF."conf WHERE name='$name_esc'";
		return bo_db($sql);
	}
	
	$sql = "SELECT data, name FROM ".BO_DB_PREF."conf WHERE name='$name_esc'";
	$row = bo_db($sql)->fetch_object();

	if (!$row->name)
		$sql = "INSERT ".BO_DB_PREF."conf SET data='$data_esc', name='$name_esc'";
	elseif ($row->data != $data)
		$sql = "UPDATE ".BO_DB_PREF."conf SET data='$data_esc' WHERE name='$name_esc'";
	else
		$sql = NULL; // no update necessary

	return $sql ? bo_db($sql) : true;
}

// latitude, longitude to distance in meters
function bo_latlon2dist($lat1, $lon1, $lat2 = BO_LAT, $lon2 = BO_LON)
{
	if ($lat1 == $lat2 && $lon1 == $lon2)
		return 0;

	$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2));
	$dist = rad2deg(acos($dist));

	return $dist * 60 * 1.1515 * 1.609344 * 1000;
}


// latitude, longitude to bearing (Rhumb Line!)
function bo_latlon2bearing($lat2, $lon2, $lat1 = BO_LAT, $lon1 = BO_LON)
{
     //difference in longitudinal coordinates
     $dLon = deg2rad($lon2) - deg2rad($lon1);

     //difference in the phi of latitudinal coordinates
     $dPhi = log(tan(deg2rad($lat2) / 2 + pi() / 4) / tan(deg2rad($lat1) / 2 + pi() / 4));

     //we need to recalculate $dLon if it is greater than pi
     if(abs($dLon) > pi()) {
          if($dLon > 0) {
               $dLon = (2 * pi() - $dLon) * -1;
          }
          else {
               $dLon = 2 * pi() + $dLon;
          }
     }

     //return the angle, normalized
	 $beta = (rad2deg(atan2($dLon, $dPhi)) + 360);
	 $beta = fmod($beta, 360);

     return $beta;
}

function bo_bearing2direction($bearing)
{
     $tmp = round($bearing / 22.5);

     switch($tmp)
	 {
          case 1: $direction = "NNE"; break;
          case 2: $direction = "NE";  break;
          case 3: $direction = "ENE"; break;
          case 4: $direction = "E";   break;
          case 5: $direction = "ESE"; break;
          case 6: $direction = "SE";  break;
          case 7: $direction = "SSE"; break;
          case 8: $direction = "S";   break;
          case 9: $direction = "SSW"; break;
          case 10: $direction = "SW"; break;
          case 11: $direction = "WSW"; break;
          case 12: $direction = "W";   break;
          case 13: $direction = "WNW"; break;
          case 14: $direction = "NW";  break;
          case 15: $direction = "NNW"; break;
          default: $direction = "N";
	 }

     return $direction;
}

//return latitude from given position, distance and bearing
function bo_distbearing2latlong($dist, $bearing, $lat = BO_LAT, $lon = BO_LON)
{
	$R       = 6371000;
	$lat     = deg2rad($lat);
	$lon     = deg2rad($lon);
	$bearing = deg2rad($bearing);

	$lat2 = asin( sin($lat) * cos($dist/$R) + cos($lat) * sin($dist/$R) * cos($bearing) );
	$lon2 = $lon + atan2(sin($bearing) * sin($dist/$R) * cos($lat), cos($dist/$R) - sin($lat) * sin($lat2));

	$lat2 = rad2deg($lat2);
	$lon2 = rad2deg($lon2);

	return array($lat2, $lon2);
}

// returns array of all stations with index from database column
function bo_stations($index = 'id', $only = '')
{
	$S = array();

	if ($only)
		$sql = " WHERE $index='".BoDb::esc($only)."' ";

	$sql = "SELECT * FROM ".BO_DB_PREF."stations $sql";
	$res = bo_db($sql);
	while($row = $res->fetch_assoc())
		$S[$row[$index]] = $row;

	return $S;
}

//returns station ID
function bo_station_name2id($name)
{
	$tmp = bo_stations('user', $name);
	return (int)$tmp[$name]['id'];
}

//returns your station_id
function bo_station_id()
{
	static $id = 0;
	
	if (!$id)
		$id = bo_station_name2id(BO_USER);
	
	return $id ? $id : -1;
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
		$tmp = bo_stations('user', BO_USER);
		
		if (defined('BO_STATION_NAME') && BO_STATION_NAME)
			$tmp[BO_USER]['city'] = BO_STATION_NAME;
			
		$ret = $tmp[BO_USER];
	}

	$info[$id] = $ret;
	
	return $info[$id];
}

//insert HTML-hidden tags of actual GET-Request
function bo_insert_html_hidden($exclude = array())
{
	foreach($_GET as $name => $val)
	{
		if (array_search($name, $exclude) !== false)
			continue;

		echo "\n".'<input type="hidden" name="'.htmlentities($name).'" value="'.htmlentities($val).'">';
	}
}

function bo_insert_url($exclude = array(), $add = null)
{
	if (!is_array($exclude))
		$exclude = array($exclude);

	if (bo_user_get_id())
		$exclude[] = 'bo_login';
	
	$exclude_bo = array_search('bo_*', $exclude) !== false;
	
	$query = '';
	foreach($_GET as $name => $val)
	{
		
		if (array_search($name, $exclude) !== false || ($exclude_bo && substr($name,0,3) == 'bo_' && $name != 'bo_page') )
			continue;

		$query .= urlencode($name).(strlen($val) ? '='.urlencode($val) : '').'&';
	}

	if (count($exclude) && $add !== null)
		$query .= $exclude[0].'='.urlencode($add).'&';


	$url = $_SERVER['REQUEST_URI'];
	preg_match('@/([^/\?]+)\?|$@', $url, $r);

	$query = strtr($query, array('&&' => '&'));
	
	return $r[1].'?'.$query;
}


//displays copyright
function bo_copyright_footer()
{

	echo '<div id="bo_copyright">';
	echo '<a href="http://www.blitzortung.org/" target="_blank">';
	echo '<img src="'.BO_FILE.'?image=logo" id="bo_copyright_logo">';
	echo '</a>';
	echo _BL('Lightning data');
	echo ' &copy; 2003-'.date('Y ');
	echo '<a href="http://www.blitzortung.org/" target="_blank">';
	echo 'www.Blitzortung.org';
	echo '</a>';
	echo ' &bull; ';
	echo '<a href="http://'.BO_LINK_HOST.'/" target="_blank" id="mybo_copyright">';
	echo _BL('copyright_footer');
	echo '</a>';
	
	if (defined('BO_OWN_COPYRIGHT') && trim(BO_OWN_COPYRIGHT))
	{
		echo ' &bull; ';
		echo BO_OWN_COPYRIGHT;
	}

	
	echo '</div>';

	echo '<div id="bo_copyright_extra">';
	echo _BL('timezone_is').' <strong>'.date('H:i:s').' '._BL(date('T')).'</strong>';
	echo '</div>';

	if (BO_LOGIN_SHOW === true)
	{
		$file = BO_LOGIN_URL !== false ? BO_LOGIN_URL : BO_FILE;
		$file .= strpos($file, '?') === false ? '?' : '';

		echo '<div id="bo_login_link">';

		if (bo_user_get_name())
		{
			echo '<a href="'.$file.'&bo_login" rel="nofollow">'._BC(bo_user_get_name()).'</a>';
			echo ' (<a href="'.$file.'&bo_logout">'._BL('Logout').'</a>)';
		}
		else
			echo '<a href="'.$file.'&bo_login" rel="nofollow">'._BL('Login').'</a>';

		echo '</div>';


	}
	
	if (BO_SHOW_LANGUAGES === true)
	{
		$languages = explode(',', BO_LANGUAGES);
		
		echo '<div id="bo_lang_links">';

		echo _BL('Languages').': ';
		foreach($languages as $lang)
		{
			if (BO_SHOW_LANG_FLAGS == true)
				$a_lang = '<img src="'.BO_FILE.'?image=flag_'.$lang.'" class="bo_flag">';
			else
				$a_lang = $lang;
				
			if (trim($lang) == _BL())
				echo ' <strong>'.trim($a_lang).'</strong> ';
			else
				echo ' <a href="'.bo_insert_url('bo_lang', trim($lang)).'">'.trim($a_lang).'</a> ';
				
		}
		
		echo '</div>';
	
	}

}

// translate text
function _BL($msgid='', $noutf = false)
{
	global $_BL;

	$locale = $_BL['locale'];
	
	if ($msgid === '')
		return $locale;
	
	$msg = $_BL[$locale][$msgid];

	$utf = defined('BO_UTF8') && BO_UTF8 && !$noutf;
	
	if ($msg === false)
	{
		return '';
	}	
	else if (!$msg)
	{
		if (defined('BO_LANG_AUTO_ADD') && BO_LANG_AUTO_ADD)
		{
			bo_add_locale_msgid($locale, $msgid);
			bo_add_locale_msgid('en', $msgid);
		}

		$msg = $_BL['en'][$msgid];
	}

	if (!$msg)
	{
		$msg = $msgid;
	}
	else
	{
		if (strpos($msg, "{STATION}") !== false)
			$msg = strtr($msg, array('{STATION}' => bo_station_city())); //needs a database lookup

		$replace = array(
					'{USER}' => bo_user_get_name(),
					'{MYBO}' => $_BL[$locale]['MyBlitzortung'],
					'{MYBO_NOTAGS}' => $_BL[$locale]['MyBlitzortung_notags'],
					'{MYBO_ORIG}' => $_BL[$locale]['MyBlitzortung_original']
				);
			
		$msg = strtr($msg, $replace);
	}

	if ($utf)
		$msg = utf8_encode($msg);

	return $msg;
}

function _BLN($number, $unit = 'minute')
{

	if ($number == 1)
		return _BL('number_1'.$unit.'');
	else
		return strtr(_BL('number_'.$unit.'s'), array('{NUMBER}' => $number));

}

//charset
function _BC($text, $nospecialchars=false)
{
	if (defined('BO_UTF8') && BO_UTF8)
		return utf8_encode($text);
	else if ($nospecialchars)
		return $text;
	else
		return htmlspecialchars($text);
}

// helper function for developers
function bo_add_locale_msgid($locale, $msgid)
{
	global $_BL;

	$file = BO_DIR.'locales/'.$locale.'.php';

	if (!isset($_BL[$locale][$msgid]) && is_writeable($file))
	{
		$msgid = strtr($msgid, array("'" => "\\'"));
		file_put_contents($file, '$_BL[\''.$locale.'\'][\''.$msgid.'\'] = \'\';'."\n", FILE_APPEND);
		$_BL[$locale][$msgid] = '';
	}

}

function bo_get_tile_dim($x,$y,$zoom)
{
	$tilesZoom = 1 << $zoom;
	$lonW = 360.0 / $tilesZoom;
	$lon = -180 + ($x * $lonW);

	$MtopLat = $y / $tilesZoom;
	$MbottomLat = $MtopLat + 1 / $tilesZoom;

	$lat = (180 / M_PI) * ((2 * atan(exp(M_PI * (1 - (2 * $MbottomLat))))) - (M_PI / 2));
	$lat2 = (180 / M_PI) * ((2 * atan(exp(M_PI * (1 - (2 * $MtopLat))))) - (M_PI / 2));

	return array($lat, $lon, $lat2, $lon+$lonW);
}


function bo_latlon2tile($lat, $lon, $zoom)
{
	list($x, $y) = bo_latlon2mercator($lat, $lon);
	$x += 0.5;
	$y = abs($y-0.5);
	$scale = (1 << ($zoom)) * BO_TILE_SIZE;

	return array((int)($x * $scale), (int)($y * $scale));
}

function bo_latlon2mercator($lat, $lon)
{
	if ($lon > 180)
		$lon -= 360;

	$lon /= 360;
	$lat = asinh(tan(deg2rad($lat)))/M_PI/2;
	return array($lon, $lat);
}

function bo_strike2polarity($data, $bearing)
{
	static $cache=0, $antbear=array();

	if (!$data)
		return null;

	if ($cache == 0)
	{
		$antbear[0] = bo_get_conf('antenna1_bearing_elec');
		$antbear[1] = bo_get_conf('antenna2_bearing_elec');
		$cache = 1;
	}

	$channels = bo_get_conf('raw_channels');
	
	$ant_arc = 80;

	for ($i=0;$i<$channels;$i++)
	{
		$signal[$i] = (ord(substr($data,$i,1)) - 128) / 128;

		if (abs($signal[$i]) > 0.03)
			$sig_pol[$i] = $signal[$i] > 0 ? 1 : -1;
		else
			$sig_pol[$i] = null;

		$bearing = $antbear[$i] <= 360 ? $bearing : $bearing+360;

		if ($antbear[$i]-$ant_arc < $bearing && $bearing < $antbear[$i]+$ant_arc)
			$ant_side[$i] = 1;
		else if ( ($antbear[$i]-$ant_arc+180 < $bearing && $bearing < $antbear[$i]+$ant_arc+180) || ($antbear[$i]-$ant_arc-180 < $bearing && $bearing < $antbear[$i]+80-180))
			$ant_side[$i] = -1;
		else
			$ant_side[$i] = null;

		if ($sig_pol[$i] && $ant_side[$i])
			$strike_pol[$i] = $sig_pol[$i] * $ant_side[$i];
		else
			$strike_pol[$i] = null;

	}

	if ($channels == 1)
		$strike_pol[1] = $strike_pol[0];
	
	if (!$strike_pol[0] && !$strike_pol[1])
		$polarity = null;
	else if ($strike_pol[0] && $strike_pol[1] && $strike_pol[0] != $strike_pol[1])
		$polarity = null;
	else if ($strike_pol[0])
		$polarity = $strike_pol[0];
	else if ($strike_pol[1])
		$polarity = $strike_pol[1];

	//echo '<li>'.$bearing.' * '.$antbear[0].'/'.$antbear[1].' * '.$sig_pol[0].'/'.$sig_pol[1].' * '.$ant_side[0].'/'.$ant_side[1].' * '.$strike_pol[0].'/'.$strike_pol[1].' ==> '.$polarity.'</li>';

	return $polarity;
}

function bo_show_all()
{
	$page = $_GET['bo_page'] ? $_GET['bo_page'] : 'map';

	switch($page)
	{
		default:
		case 'map': 		bo_show_map(); break;
		case 'archive': 	bo_show_archive(); break;
		case 'statistics': 	bo_show_statistics(); break;
		case 'info': 		bo_show_info(); break;
		case 'login': 		bo_show_login(); break;
	}

}

function bo_show_menu()
{
	$page = $_GET['bo_page'] ? $_GET['bo_page'] : 'map';

	echo '<ul id="bo_mainmenu">';
	echo '<li><a href="'.bo_insert_url(array('bo_page', 'bo_*'), 'map').'"        id="bo_mainmenu_map"  class="bo_mainmenu'.($page == 'map' ? '_active' : '').'">'._BL('main_menu_map').'</a></li>';
	
	if (BO_DISABLE_ARCHIVE !== true)
		echo '<li><a href="'.bo_insert_url(array('bo_page', 'bo_*'), 'archive').'"    id="bo_mainmenu_arch" class="bo_mainmenu'.($page == 'archive' ? '_active' : '').'">'._BL('main_menu_archive').'</a></li>';
	
	echo '<li><a href="'.bo_insert_url(array('bo_page', 'bo_*'), 'statistics').'" id="bo_mainmenu_stat" class="bo_mainmenu'.($page == 'statistics' ? '_active' : '').'">'._BL('main_menu_statistics').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_page', 'bo_*'), 'info').'"       id="bo_mainmenu_info" class="bo_mainmenu'.($page == 'info' ? '_active' : '').'">'._BL('main_menu_info').'</a></li>';

	if (bo_user_get_id())
		echo '<li><a href="'.bo_insert_url(array('bo_page', 'bo_*'), 'login').'"       id="bo_mainmenu_info" class="bo_mainmenu'.($page == 'login' ? '_active' : '').'">'._BL('main_menu_login').'</a></li>';
	
	echo '</ul>';

}

function bo_get_title()
{
	$page = $_GET['bo_page'] ? $_GET['bo_page'] : 'map';

	switch($page)
	{
		default:
		case 'map': $title = _BL('main_title_map'); break;
		case 'archive': $title = _BL('main_title_archive'); break;
		case 'statistics': $title = _BL('main_title_statistics'); break;
		case 'info': $title = _BL('main_title_info'); break;
		case 'login': $title = _BL('main_title_login'); break;
	}

	return $title;
}

function bo_gpc_prepare($text)
{
	$text = trim($text);
	$text = stripslashes($text);
	
	if (defined('BO_UTF8') && BO_UTF8)
		return utf8_decode($text);
	else
		return $text;

}


//recursive delete function
function bo_delete_files($dir, $min_age=0, $depth=0, $delete_dir_depth=false)
{
	$dir .= '/';
	
	if ($delete_dir_depth === false)
		$delete_dir_depth = 10;
		
	$files = @scandir($dir);

	foreach($files as $file)
	{
		if (!is_dir($dir.$file) && ($min_age == 0 || @fileatime($dir.$file) < time() - 3600 * $min_age) )
		{
			@unlink($dir.$file);
		}
		else if (is_dir($dir.$file) && $file != '.' && $file != '..' && $depth > 0)
		{
			bo_delete_files($dir.$file.'/', $min_age, $depth-1,$delete_dir_depth-1);

			if ($delete_dir_depth <= 0)
				@rmdir($dir.$file.'/');
		}
	}
}

//loads the needed locales
function bo_load_locale()
{
	global $_BL;
	$locdir = BO_DIR.'locales/';
	
	if (BO_LOCALE2 && file_exists($locdir.BO_LOCALE2.'.php')) // 2nd locale -> include first
	{
		include $locdir.BO_LOCALE2.'.php';
	}
	
	if (file_exists($locdir.BO_LOCALE.'.php')) //main locale -> overwrites 2nd
	{
		include $locdir.BO_LOCALE.'.php';
	}
	elseif (BO_LOCALE2 != 'en')
	{
		include $locdir.'en.php';
	}

	if (file_exists($locdir.'own.php')) //own translation (language independent)
		include $locdir.'own.php';
	
	
	//individual locale for user (link, session, cookie)
	
	$locale = '';
	if (isset($_GET['bo_lang']) && preg_match('/^[a-zA-Z]{2}$/', $_GET['bo_lang']))
	{
		$locale = strtolower($_GET['bo_lang']);
		$_SESSION['bo_locale'] = $locale;
		@setcookie("bo_locale", $locale, time()+3600*24*365*10,'/');
	}
	else if (isset($_SESSION['bo_locale']) && preg_match('/^[a-zA-Z]{2}$/', $_SESSION['bo_locale']))
	{
		$locale = $_SESSION['bo_locale'];
	}
	else if (isset($_COOKIE['bo_locale']) && preg_match('/^[a-zA-Z]{2}$/', $_COOKIE['bo_locale']))
	{
		$locale = $_COOKIE['bo_locale'];
	}

	if ($locale && file_exists($locdir.$locale.'.php') && $locale != BO_LOCALE)
	{
		include $locdir.$locale.'.php';

		if (file_exists($locdir.'own.php')) //include this 2nd time (must overwrite the manual specified language!)
			include $locdir.'own.php';
	}

	//Send the language
	if (!headers_sent())
		header("Content-Language: $locale");

}

function bo_get_file($url, &$error = '', $type = '')
{
	if (BO_USE_PHPURLWRAPPER === true)
	{
		return file_get_contents($url);
	}
	
	$parsedurl = @parse_url($url); 
	$host = $parsedurl['host'];
	$user = $parsedurl['user'];
	$pass = $parsedurl['pass'];
	$path = $parsedurl['path'];
	$query = $parsedurl['query'];
	
	$fp = fsockopen($host, 80, $errno, $errstr);
	
	$err = 0;
	
	if (!$fp)
	{
		$error = "Connect ERROR: $errstr ($errno)<br />\n";
		echo $error;
		$err = 1;
	}
	else
	{
		$out =  "GET ".$path."?".$query." HTTP/1.1\r\n";
		$out .= "Host: ".$host."\r\n";
		$out .= "User-Agent: MyBlitzortung ".BO_VER."\r\n";
		
		if ($user && $pass)
			$out .= "Authorization: Basic ".base64_encode($user.':'.$pass)."\r\n";
		
		$out .= "Connection: Close\r\n\r\n";
		
		fwrite($fp, $out);		 
		$content = ''; 
		$first = true;
		$response = array();
		
		//Header
		do 
		{ 
			$header = chop(fgets($fp)); 
			
			if ($first)
			{
				preg_match('/[^ ]+ ([^ ]+) (.+)/', $header, $response);
				
				if ($response[1] != '200')
				{
					$err = 2;
					break;
				}
			}
			
			$first = false;
		} 
		while (!empty($header) and !feof($fp)); 
			
		//Get the Content
		while (!feof($fp)) { 
			$content .= fgets($fp); 
		} 
		
		fclose($fp);
	}

	if ($err == 2)
	{
		$error = $response[1].' '.$response[2];
		$content = false;
	}
	
	if ($type)
	{
		$data = unserialize(bo_get_conf('download_'.$type));
		$data['count'][$err]++;
		
		if ($content)
			$data['traffic'] += strlen($content);
		
		bo_set_conf('download_'.$type, serialize($data));
	}
	
	return $content; 	 
}

function bo_latlon2sql($lat1, $lat2, $lon1, $lon2, $with_indexed_values = false)
{
	$sql = " (lat BETWEEN '$lat2' AND '$lat1' AND lon BETWEEN '$lon2' AND '$lon1' ";
	
	if ($with_indexed_values)
	{
		$lat2min = floor($lat2);
		$lat1max = ceil($lat1);
		$lon2min = floor($lon2/180 * 128);
		$lon1max = ceil($lon1/180 * 128);

		$sql .= " AND lat2 BETWEEN '$lat2min' AND '$lat1max' AND lon2 BETWEEN '$lon2min' AND '$lon1max' ";
	}

	$sql .= ") ";
	
	return $sql;
}

function bo_times2sql($time_min, $time_max, $with_indexed_values = false)
{
	$time_min = intval($time_min);
	$time_max = intval($time_max);

	//date range
	$date_min = gmdate('Y-m-d H:i:s', $time_min);
	$date_max = gmdate('Y-m-d H:i:s', $time_max);

	$sql .= " ( time BETWEEN '$date_min' AND '$date_max' ";
	
	if ($with_indexed_values)
	{
		$sql .= " AND time_key BETWEEN $time_min/(60*5) AND $time_max/(60*5) ";
	}

	$sql .= ") ";
	
	return $sql;
}

function bo_strikes_sqlkey(&$index_sql, $time_min, $time_max, $lat1, $lat2, $lon1, $lon2)
{
	
	$sql  = " (";
	$sql .= bo_latlon2sql($lat1, $lat2, $lon1, $lon2, true);
	$sql .= " AND ";
	$sql .= bo_times2sql($time_min, $time_max, true);
	$sql .= ") ";
	
	$index_sql = " FORCE INDEX (time_latlon) ";
	
	return $sql;
}

function bo_region2sql($region)
{
	global $_BO;
	
	if (!isset($_BO['region'][$region]['rect_add']))
		return '';
	
	$reg = $_BO['region'][$region]['rect_add'];
	$sql .= ' AND ( 0 ';
	
	while ($r = @each($reg))
	{
		$lat1 = $r[1];
		list(,$lon1) = @each($reg);
		list(,$lat2) = @each($reg);
		list(,$lon2) = @each($reg);
		
		$sql .= " OR ".bo_latlon2sql($lat1, $lat2, $lon1, $lon2, true);
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
			
			$sql .= " OR ".bo_latlon2sql($lat1, $lat2, $lon1, $lon2, true);

		}
		
		$sql .= ' ) ';
	}
	
	return $sql;
}


	
// FFT
// Fast Fourier Trasforn according "Numerical Recipies"
// bo_fft($sign, $ar, $ai)
// $sign = -1 for inverse FFT, 1 otherwise 
// $ar = array of real parts
// $ar = array of imaginary parts
// count($ar) must be power of 2
function bo_fft($sign, &$ar, &$ai)
{
	$n = count($ar);
	
	// n must be positive and power of 2: 2^n & (2^n-1) == 0
	if (($n<2) or ($n & ($n-1)))
	  return false;
	
	$scale = sqrt(1.0/$n);
	
	for ($i=$j=0; $i<$n; ++$i) {
		if ($j>=$i) {
			$tempr = $ar[$j]*$scale;           
			$tempi = $ai[$j]*$scale;           
			$ar[$j] = $ar[$i]*$scale;
			$ai[$j] = $ai[$i]*$scale;
			$ar[$i] = $tempr;
			$ai[$i] = $tempi;
		}
		$m = $n >> 1;
		while ($m>=1 && $j>=$m) {
			$j -= $m;
			$m /= 2;
		}
		$j += $m;
	}
	
	for ($mmax=1,$istep=2*$mmax; $mmax<$n; $mmax=$istep,$istep=2*$mmax) 
	{
		$delta = $sign*pi()/$mmax;
		for ($m=0; $m<$mmax; ++$m) 
		{
			$w = $m*$delta;             
			$wr = cos($w);
			$wi = sin($w);
			for ($i=$m; $i<$n; $i+=$istep) 
			{
				$j = $i+$mmax;
				$tr = $wr*$ar[$j]-$wi*$ai[$j];
				$ti = $wr*$ai[$j]+$wi*$ar[$j];
				$ar[$j] = $ar[$i]-$tr;
				$ai[$j] = $ai[$i]-$ti;
				$ar[$i] += $tr;
				$ai[$i] += $ti;
			}
		}
		$mmax = $istep;
	}
	
	return true;
}

if (!function_exists('hypot')) 
{
	function hypot($x, $y) 
	{
	  return sqrt($x*$x + $y*$y);
	}
}

// transform from time domine to frequency domine using FFT
function bo_time2freq($d)
{
	$n = count($d);

	// check if n is a power of 2: 2^n & (2^n-1) == 0
	if ($n & ($n-1)) 
	{
	  // eval the minimum power of 2 >= $n
	  $p = pow(2, ceil(log($n, 2)));
	  $d += array_fill($n, $p-$n, 0);
	  $n = $p;
	}  

	$im = array_fill(0, $n, 0);

	bo_fft(1, $d, $im);
	
	for ($i=0; $i<=$n/2; $i++) 
	{
	  // Calculate the modulus (as length of the hypotenuse)
	  $h[$i] = hypot($d[$i], $im[$i]);
	} 
	
	return $h; 
}

//Raw hexadecimal signal to array
function raw2array($raw = false, $calc_spec = false)
{
	static $channels = -1, $bpv = -1, $values = -1, $utime = -1;
	
	if ($channels == -1 && $bpv == -1 && $utime == -1 && $values == -1)
	{
		$channels = bo_get_conf('raw_channels');
		$bpv      = bo_get_conf('raw_bitspervalue');
		$utime    = bo_get_conf('raw_ntime') / 1000;
		$values   = bo_get_conf('raw_values');
	}

	if (!$channels || !$bpv || !$utime || !$values)
		return false;
	
	//dummy signal
	if ($raw === false)
	{
		$calc_spec = true;
		$raw = str_repeat(chr(0), $values * $channels);
	}
	
	$data = array();
	$data['signal_raw'] = array();
	$data['signal'] = array();
	$data['spec'] = array();
	$data['spec_freq'] = array();
	
	$ymax = pow(2,$bpv-1);
	
	for ($i=0;$i<strlen($raw);$i++)
	{
		$byte = ord(substr($raw,$i,1));
		$data['signal_raw'][$i%$channels][] = $byte;
		$data['signal'][$i%$channels][] = ($byte - $ymax) / $ymax * BO_MAX_VOLTAGE;	
	}

	foreach($data['signal'][0] as $i => $dummy)
	{
		$data['signal_time'][$i] = $i * $utime;
	}

	if ($calc_spec)
	{
		foreach($data['signal'] as $channel => $d)
		{
			$data['spec'][$channel] = bo_time2freq($d);
		}
		
		foreach ($data['spec'][0] as $i => $dummy)
		{
			$data['spec_freq'][$i] = round($i / ($values * $utime) * 1000);
		}
	}
	
	return $data;
}

		
function bo_examine_signal($data, &$amp = array(), &$amp_max = array(), &$freq = array(), &$freq_amp = array())
{
	$sig_data = raw2array($data, true);
	
	$amp = array(0,0);
	$amp_max = array(0,0);
	$freq = array(0,0);
	$freq_amp = array(0,0);
	
	if ($sig_data)
	{
		foreach($sig_data['signal_raw'] as $channel => $dummy)
		{
			//amplitude of first value
			$amp[$channel] = $sig_data['signal_raw'][$channel][0];
			
			//max. amplitude
			$max = 0;
			foreach($sig_data['signal_raw'][$channel] as $signal)
			{
				$sig = abs($signal - 128);
				
				if ($sig > $max)
				{
					$max = $sig;
					$amp_max[$channel] = $signal;
				}
			}
			
			//main frequency
			$max = 0;
			$freq_id_max = 0;
			foreach($sig_data['spec'][$channel] as $freq_id => $famp)
			{
				if ($freq_id > 0 && $max < $famp)
				{
					$max = $famp;
					$freq[$channel] = $sig_data['spec_freq'][$freq_id];
				}
			}
			
			$freq_amp[$channel] = $max * 100;
		}
	}
	
	$sql = "amp1='$amp[0]', amp2='$amp[1]', 
			amp1_max='$amp_max[0]', amp2_max='$amp_max[1]', 
			freq1='$freq[0]', freq2='$freq[1]', 
			freq1_amp='$freq_amp[0]', freq2_amp='$freq_amp[1]' ";

	return $sql;
}	



function bo_imagestring(&$I, $size, $x, $y, $text, $tcolor = false, $bold = false, $angle = 0, $bordercolor = false, $px = 0)
{
	$font = bo_imagestring_font($size, $bold);
	
	if (is_string($tcolor))
	{
		$color = bo_hex2color($I, $tcolor);
	}
	elseif (is_array($tcolor))
	{
		$color = bo_hex2color($I, $tcolor[0]);
		$bordercolor = bo_hex2color($I, $tcolor[2]);
		$px = $tcolor[1];
	}
	else
		$color = $tcolor;

	if ($size <= 5)
	{
		if ($angle == 90)
			imagestringup($I, $size, $x, $y, $text, $color);
		else
			imagestring($I, $size, $x, $y, $text, $color);
	}
	else
	{
		$h = $angle ? 0 : $size;
		$w = $angle ? $size : 0;
		
		$text = utf8_encode($text);
		
		bo_imagettftextborder($I, $size, $angle, $x+$w, $y+$h, $color, $font, $text, $bordercolor, $px);
	}
}

function bo_imagestringright($I, $size, $x, $y, $text, $color = false, $bold = false, $angle = 0)
{
	$x -= bo_imagetextwidth($size, $bold, $text);
	return bo_imagestring($I, $size, $x, $y, $text, $color, $bold);
}


function bo_imagetextheight($size, $bold = false, $string = false)
{
	if ($size <= 5)
	{
		return imagefontheight($size);
	}
	
	$font = bo_imagestring_font($size, $bold);

	$string = $string === false ? 'Ag' : $string;
	$tmp = imagettfbbox($size, 0, $font, $string);
	$height = $tmp[1] - $tmp[5];
	
	return $height;
}

function bo_imagetextwidth($size, $bold = false, $string = false)
{
	if ($size <= 5)
	{
		return imagefontwidth($size) * strlen($string);
	}
	
	$font = bo_imagestring_font($size, $bold);
	
	$string = $string === false ? 'A' : $string;
	$tmp = imagettfbbox($size, 0, $font, $string);
	$width = $tmp[2] - $tmp[0];
	
	return $width;
}


function bo_imagestring_font(&$size, &$type)
{
	if ($type === true) // bold
		$font = BO_DIR.BO_FONT_TTF_BOLD;
	else if ((int)$type && $type == 1)
		$font = BO_DIR.BO_FONT_TTF_MONO;
	else
		$font = BO_DIR.BO_FONT_TTF_NORMAL;

	
	return $font;

}


function bo_imagettftextborder(&$I, $size, $angle, $x, $y, &$textcolor, $font, $text, $bordercolor = false, $px = 0)
{
	if ($px)
	{
		for($c1 = ($x-abs($px)); $c1 <= ($x+abs($px)); $c1++)
			for($c2 = ($y-abs($px)); $c2 <= ($y+abs($px)); $c2++)
				$bg = imagettftext($I, $size, $angle, $c1, $c2, $bordercolor, $font, $text);
	}
 
   return imagettftext($I, $size, $angle, $x, $y, $textcolor, $font, $text);
}

function bo_hex2color(&$I, $str)
{
	$rgb = bo_hex2rgb($str);

	if (count($rgb) == 4 && imageistruecolor($I))
		return imagecolorallocatealpha($I, $rgb[0], $rgb[1], $rgb[2], $rgb[3]);
	else
		return imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);
}

function bo_hex2rgb($str) 
{
    $hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $str);
    $rgb = array();
	
	if (strlen($hexStr) == 3 || strlen($hexStr) == 4) 
	{
        $rgb[0] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
        $rgb[1] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
        $rgb[2] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
		
		if (strlen($hexStr) == 4)
			$rgb[3] = hexdec(str_repeat(substr($hexStr, 3, 1), 2)) / 2;
    } 
	elseif (strlen($hexStr) == 6 || strlen($hexStr) == 8) 
	{
        $rgb[0] = hexdec(substr($hexStr, 0, 2));
        $rgb[1] = hexdec(substr($hexStr, 2, 2));
        $rgb[2] = hexdec(substr($hexStr, 4, 2));
		
		if (strlen($hexStr) == 8)
			$rgb[3] = hexdec(substr($hexStr, 6, 2)) / 2;
    }
	
    return $rgb;
}


?>