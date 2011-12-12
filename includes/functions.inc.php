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

	$qtype = strtolower(substr(trim($query), 0, 6));
	
	/*
	switch ($qtype)
	{
		case 'insert': 
		case 'delete':
		case 'update':
		case 'replace':
			echo "<p>$query</p>";
			return;
	}
	*/
		
	$erg = BoDb::query($query);

	if ($erg === false)
	{
		if ($die_on_errors !== false)
			echo("<p>Database Query Error:</p><pre>" . htmlspecialchars(BoDb::error()) .
				"</pre> <p>for query</p> <pre>" . htmlspecialchars($query) . "</pre>");
	
		if ($die_on_errors === true)
			die();
	}

	switch ($qtype)
	{
		case 'insert':
			return BoDb::insert_id();

		case 'replace':
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

	return acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2))) * 6371000;
}


function bo_latlon2dist_rhumb($lat1, $lon1, $lat2 = BO_LAT, $lon2 = BO_LON)
{
	$R = 6371000;
	
	$lat1     = deg2rad($lat1);
	$lat2     = deg2rad($lat2);

	$dlat     = deg2rad($lat2 - $lat1);
	$dlon     = abs(deg2rad($lon2 - $lon1));
	
	$dPhi = log(tan($lat2/2 + M_PI/4) / tan($lat1/2 + M_PI/4));
	$q    = $dPhi != 0 ? $dlat / $dPhi : cos($lat1);  // E-W line gives dPhi=0
	
	// if dLon over 180° take shorter rhumb across 180° meridian:
	if ($dlon > M_PI) $dlon = 2*M_PI - $dlon;
	
	$dist = sqrt($dlat*$dlat + $q*$q*$dlon*$dlon) * $R; 

	return $dist;
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

//return latitude from given position, distance and bearing (Rhumb line = constant bearing = not the shortest way)
function bo_distbearing2latlong_rhumb($dist, $bearing, $lat = BO_LAT, $lon = BO_LON)
{
	$lat     = deg2rad($lat);
	$lon     = deg2rad($lon);
	
	$d    = $dist / 6371000;
	$lat2 = $lat + $d * cos($bearing);
	$dLat = $lat2-$lat;
	$dPhi = log(tan($lat2/2 + M_PI/4) / tan($lat/2 + M_PI/4));
	$q    = $dPhi != 0 ? $dLat/$dPhi : cos($lat);  // E-W line gives dPhi=0
	$dLon = $dist * sin($bearing) / $q;
	
	// check for some daft bugger going past the pole, normalise latitude if so
	if (abs($lat2) > M_PI/2) 
		$lat2 = $lat2 > 0 ? M_PI-$lat2 : -(M_PI-$lat2);
	
	$lon2 = ($lon + $dLon + M_PI) % (2 * M_PI) - M_PI;

	
	$lat2 = rad2deg($lat2);
	$lon2 = rad2deg($lon2);
	
	return array($lat2, $lon2);
}


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

	echo '<div id="bo_footer">';
	
	echo '<a href="http://www.blitzortung.org/" target="_blank">';
	echo '<img src="'.bo_bofile_url().'?image=logo" id="bo_copyright_logo">';
	echo '</a>';

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
			if (BO_SHOW_LANG_FLAGS == true && file_exists(BO_DIR.'images/flags/'.$lang.'.png'))
				$a_lang = '<img src="'.bo_bofile_url().'?image=flag_'.$lang.'" class="bo_flag">';
			else
				$a_lang = $lang;
				
			if (trim($lang) == _BL())
				echo ' <strong>'.trim($a_lang).'</strong> ';
			else
				echo ' <a href="'.bo_insert_url('bo_lang', trim($lang)).'">'.trim($a_lang).'</a> ';
				
		}
		
		echo '</div>';
	}
	
	
	echo '<div id="bo_copyright">';
	echo _BL('Lightning data');
	echo ' &copy; 2003-'.date('Y ');
	echo '<a href="http://www.blitzortung.org/" target="_blank">';
	echo 'www.Blitzortung.org';
	echo '</a>';
	echo ' &bull; ';
	echo '<a href="http://'.BO_LINK_HOST.'/" target="_blank" id="mybo_copyright">';
	echo _BL('copyright_footer');
	echo '</a>';
	echo '</div>';

	echo '<div id="bo_copyright_extra">';
	echo _BL('timezone_is').' <strong>'.date('H:i:s').' '._BL(date('T')).'</strong>';
	echo '</div>';

	if (defined('BO_OWN_COPYRIGHT') && trim(BO_OWN_COPYRIGHT))
	{
		echo '<div id="bo_copyright_own">';
		echo _BC(BO_OWN_COPYRIGHT);
		echo '</div>';
	}

	

	echo '</div>';

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

function bo_latlon2projection($proj, $lat, $lon)
{
	switch ($proj)
	{
	
		default:
			return bo_latlon2mercator($lat, $lon);
		
		case 'plate':
			return array($lon, $lat);
	
	}
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

	for ($i=0;$i<2;$i++)
	{
		$signal[$i] = (ord(substr($data,$i,1)) - 128) / 128;
		
		//workaround for "best channel setting"
		if ($signal[$i] == 0)
		{
			$channels = 1;
			continue;
		}

		
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
	flush();
	
	$dir .= '/';
	
	if ($delete_dir_depth === false)
		$delete_dir_depth = 10;
		
	$files = @scandir($dir);

	foreach($files as $file)
	{
		if (!is_dir($dir.$file) && substr($file,0,1) != '.' && ($min_age == 0 || @fileatime($dir.$file) < time() - 3600 * $min_age) )
		{
			
			@unlink($dir.$file);
		}
		else if (is_dir($dir.$file) && substr($file,0,1) != '.' && $depth > 0)
		{
			bo_delete_files($dir.$file, $min_age, $depth-1,$delete_dir_depth-1);

			//if ($delete_dir_depth <= 0)
			@rmdir($dir.$file.'/');
		}
	}
}

//loads the needed locales
function bo_load_locale($locale = '')
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
	if ($locale == '')
	{
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
	}
	elseif ($locale !== false)
	{
		if (file_exists($locdir.$locale.'.php'))
			include $locdir.$locale.'.php';
	}
	
	//Send the language
	if (!headers_sent())
		header("Content-Language: $locale");

}

function bo_get_file($url, &$error = '', $type = '', &$range = 0, &$modified = 0, $as_array = false)
{
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
						else if ($response[1] != '200' && $response[1] != '206')
						{
							$err = 2;
							break;
						}
					}
					
					if (preg_match('/Content\-Range: ?bytes ([0-9]+)\-([0-9]+)\/([0-9]+)/', $header, $r))
						$accepted_range = array($r[1], $r[2], $r[3]);

					if (preg_match('/Content\-Length: ?([0-9]+)/', $header, $r))
						$content_length = $r[1];

					if (preg_match('/Last\-Modified:(.+)/', $header, $r))
						$modified = strtotime($r[1]);
			
					$first = false;
				} 
				while (!empty($header) and !feof($fp)); 
					
				//Get the Content
				while (!feof($fp)) 
				{
					$line = fgets($fp);
					$content_size += strlen($line);
					
					if ($as_array)
						$content[] = $line; 
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
		$data = unserialize(bo_get_conf('download_statistics'));
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
		
		bo_set_conf('download_statistics', serialize($data));
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

function bo_latlon2sql($lat1=false, $lat2=false, $lon1=false, $lon2=false)
{
	if ($lat === false)
		return " 1 ";
	
	$sql = " (s.lat BETWEEN '$lat1' AND '$lat2' AND s.lon BETWEEN '$lon1' AND '$lon2') ";
	
	return $sql;
}

function bo_times2sql($time_min = 0, $time_max = 0)
{
	
	$time_min = intval($time_min);
	$time_max = intval($time_max);

	if (!$time_min && !$time_max)
	{
		return " 1 ";
	}
	elseif (!$time_max)
		$time_max = pow(2, 31) - 1;
	
	//date range
	$date_min = gmdate('Y-m-d H:i:s', $time_min);
	$date_max = gmdate('Y-m-d H:i:s', $time_max);

	$sql .= " ( s.time BETWEEN '$date_min' AND '$date_max' ) ";
	
	return $sql;
}

function bo_strikes_sqlkey(&$index_sql, $time_min, $time_max, $lat1=false, $lat2=false, $lon1=false, $lon2=false)
{
	$sql  = " (";
	$sql .= bo_latlon2sql($lat1, $lat2, $lon1, $lon2);
	$sql .= " AND ";
	$sql .= bo_times2sql($time_min, $time_max);
	$sql .= ") ";

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
		
		$sql .= " OR ".bo_latlon2sql($lat2, $lat1, $lon2, $lon1, true);
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
			
			$sql .= " OR ".bo_latlon2sql($lat2, $lat1, $lon2, $lon1, true);

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
function bo_time2freq($d, &$phase=array())
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
	
	$amp = array();
	for ($i=0; $i<=$n/2; $i++) 
	{
		// Calculate the modulus (as length of the hypotenuse)
		$amp[$i] = hypot($d[$i], $im[$i]);
		
		if (!$d[$i])
			$phase[$i] = ($im[$i] < 0 ? -1 : 1) * M_PI / 2;
		else
			$phase[$i] = atan($im[$i] / $d[$i]);
	} 
	
	return $amp; 
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
	
	//dummy signal when returning an empty array
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
	
	
	//fill array with zeros
	for ($i=0;$i<$values;$i++)
	{
		$data['signal_time'][$i] = $i * $utime;
		
		$data['signal'][0][$i] = 0;
		$data['signal'][1][$i] = 0;
		
		$data['signal_raw'][0][$i] = 128;
		$data['signal_raw'][1][$i] = 128;
	}

	
	//channel select
	if ($channels == 1)
	{
		//last byte even ==> channel 1 (A)
		//last byte odd  ==> channel 2 (B)
		$ch = ord(substr($raw,-1)) % 2;
	}
	
	
	//signal string to array
	$ymax = pow(2,$bpv-1);
	for ($i=0;$i<strlen($raw);$i++)
	{
		$byte = ord(substr($raw,$i,1));
		$pos  = floor($i / $channels);
		
		if ($channels == 2)
			$ch = $i%$channels;
		
		$data['signal_raw'][$ch][$pos] = $byte;
		$data['signal'][$ch][$pos] = ($byte - $ymax) / $ymax * BO_MAX_VOLTAGE;	
	}


	//spectrum for each channel
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
				
				if ($sig >= $max)
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
			return imagestringup($I, $size, $x, $y, $text, $color);
		else
			return imagestring($I, $size, $x, $y, $text, $color);
	}
	else
	{
		$h = $angle ? 0 : $size;
		$w = $angle ? $size : 0;
		
		$text = utf8_encode($text);
		
		return bo_imagettftextborder($I, $size, $angle, $x+$w, $y+$h, $color, $font, $text, $bordercolor, $px);
	}
}

function bo_imagestringright($I, $size, $x, $y, $text, $color = false, $bold = false, $angle = 0)
{
	$x -= bo_imagetextwidth($size, $bold, $text);
	return bo_imagestring($I, $size, $x, $y, $text, $color, $bold);
}

function bo_imagestringcenter($I, $size, $x, $y, $text, $color = false, $bold = false, $angle = 0)
{
	$x -= bo_imagetextwidth($size, $bold, $text) / 2;
	return bo_imagestring($I, $size, $x, $y, $text, $color, $bold);
}


//writes text with automatic line brakes into an image
function bo_imagestring_max(&$I, $size, $x, $y, $text, $color, $maxwidth, $bold = false)
{
	$text = strtr($text, array(chr(160) => ' '));
	$line_height = bo_imagetextheight($size, $bold) * 1.2;
	$breaks = explode("\n", $text);
	$blankwidth = bo_imagetextwidth($size, $bold, " ");
	
	foreach($breaks as $text2)
	{
		$width = 0;
		$lines = explode(" ", $text2);
		$x2 = $x;
		
		foreach($lines as $i=>$line)
		{
			$width = bo_imagetextwidth($size, $bold, $line) + $blankwidth;
			
			if ($x2+$width+9 > $x+$maxwidth)
			{
				$y += $line_height;
				$x2 = $x;
			}
			
			bo_imagestring($I, $size, $x2, $y, $line, $color, $bold);
			
			$x2 += $width;
		}
		
		$y += $line_height;
	}
	return $y;
}

function bo_imagetextheight($size, $bold = false, $string = false)
{
	if ($size <= 5)
	{
		return imagefontheight($size);
	}
	
	$font = bo_imagestring_font($size, $bold);

	$string = $string === false ? 'Ag' : $string;
	
	if (BO_FONT_USE_FREETYPE2)
		$tmp = imageftbbox($size, 0, $font, $string);
	else
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

	if (BO_FONT_USE_FREETYPE2)
		$tmp = imageftbbox($size, 0, $font, $string);
	else
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
				$bg = bo_imagefttext($I, $size, $angle, $c1, $c2, $bordercolor, $font, $text);
	}
 
   return bo_imagefttext($I, $size, $angle, $x, $y, $textcolor, $font, $text);
}

function bo_imagefttext(&$I, $size, $angle, $c1, $c2, $bordercolor, $font, $text)
{
	if (BO_FONT_USE_FREETYPE2)
		return imagefttext($I, $size, $angle, $c1, $c2, $bordercolor, $font, $text);
	else
		return imagettftext($I, $size, $angle, $c1, $c2, $bordercolor, $font, $text);
}

function bo_hex2color(&$I, $str, $use_alpha = true)
{
	$rgb = bo_hex2rgb($str);

	if (count($rgb) == 4 && imageistruecolor($I) && $use_alpha)
		return imagecolorallocatealpha($I, $rgb[0], $rgb[1], $rgb[2], $rgb[3]);
	else
	{
		$col = imagecolorexact($I, $rgb[0], $rgb[1], $rgb[2]);
		
		if ($col === -1)
			return imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);
		else
			return $col;
	}
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
		else
			$rgb[3] = 0;
    } 
	elseif (strlen($hexStr) == 6 || strlen($hexStr) == 8) 
	{
        $rgb[0] = hexdec(substr($hexStr, 0, 2));
        $rgb[1] = hexdec(substr($hexStr, 2, 2));
        $rgb[2] = hexdec(substr($hexStr, 4, 2));
		
		if (strlen($hexStr) == 8)
			$rgb[3] = hexdec(substr($hexStr, 6, 2)) / 2;
		else
			$rgb[3] = 0;
    }
	
    return $rgb;
}


function bo_owner_mail($subject, $text)
{
	$mail = bo_user_get_mail(1);
	$ret = false;
	
	if ($mail)
	{
		$ret = mail($mail, $subject, $text, "From: MyBlitzortung");
		echo '<p>Sent E-Mail to '.$mail.':</p><p>'.$subject.'</p><pre>'.$text.'</pre>';
	}
	
	return $ret;
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
			echo "<name>"._BL($d['name'])."</name>\n";
			echo "<description></description>\n";
			echo "<visibility>0</visibility>\n";
			echo "<refreshVisibility>0</refreshVisibility>\n";
			
			foreach($_BO['mapimg'] as $id => $d)
			{
				if (!$d['kml'])
					continue;
				
				$imgurl = $url."?map=".$id."&amp;bo_lang="._BL();
				
				if (!$d['file'])
					$imgurl .= "&amp;transparent";
				
				echo "<GroundOverlay>\n";
				echo "<name>"._BL($d['name'])."</name>\n";
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
			echo "  <href>".$url."?kml=1&amp;bo_lang="._BL()."</href>\n";
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
		$tmp = unserialize(bo_get_conf('bo_participants_locating_min'));
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
		$tmp = unserialize(bo_get_conf('bo_participants_locating_max'));
		$value = intval($tmp['value']);
	}
	
	if (!$value)
		$value = BO_MAX_PARTICIPANTS;
	
	return intval($value);
}



function bo_echod($text = '')
{
	echo date('Y-m-d H:i:s | ');
	echo $text;
	echo "\n";
	flush();
}



?>