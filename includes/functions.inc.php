<?php


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

// latitude, longitude to bearing
function bo_latlon2bearing($lat1, $lon1, $lat2 = BO_LAT, $lon2 = BO_LON)
{
	if ($lat1 == $lat2 && $lon1 == $lon2)
		return false;
		
	$dlon = deg2rad($lon1-$lon2);
	$lat1 = deg2rad($lat1);
	$lat2 = deg2rad($lat2);
	
	$bear = atan2(sin($dlon)*cos($lat2), cos($lat1)*sin($lat2) - sin($lat1)*cos($lat2)*cos($dlon) );
	$bear = -rad2deg($bear) + 180;
	$bear = fmod($bear, 360);
	
	return $bear;
}


// latitude, longitude to bearing (Rhumb Line!)
function bo_latlon2bearing_rhumb($lat2, $lon2, $lat1 = BO_LAT, $lon1 = BO_LON)
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
	 $bear = rad2deg(atan2($dLon, $dPhi)) + 360;
	 $bear = fmod($bear, 360);

     return $bear;
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

	$lon2 = fmod($lon + $dLon + M_PI, 2 * M_PI) - M_PI;

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
	$res = BoDb::query($sql);
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
	if (BO_NO_DEFAULT_STATION === true)
		return -1; // -1 ==> does not interfer with station statistic table (0 = all stations)

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
		if (BO_NO_DEFAULT_STATION === true)
			return false;

		$tmp = bo_stations('user', BO_USER);

		if (defined('BO_STATION_NAME') && BO_STATION_NAME)
			$tmp[BO_USER]['city'] = BO_STATION_NAME;

		$ret = $tmp[BO_USER];
	}

	$info[$id] = $ret;

	return $info[$id];
}


function bo_get_station_list(&$style_class = array())
{
	$stations = bo_stations();
	$opts = array();
	foreach($stations as $id => $d)
	{
		if (!$d['country'] || !$d['city'] || $d['status'] == '-')
			continue;

		$opts[$id] = _BL($d['country']).': '._BC($d['city']);
		
		$style_class[$id] = 'bo_select_station';
		
		if ($d['status'] == 'A')
			$style_class[$id] .= '_active';
		elseif ($d['status'] == 'O')
			$style_class[$id] .= '_offline';
		elseif ($d['status'] == 'V')
			$style_class[$id] .= '_nogps';
	}

	asort($opts);

	return $opts;
}

function bo_get_stations_html_select($station_id)
{
	$opts = bo_get_station_list($style_class);

	$text = '<select name="bo_station_id" onchange="submit()">';
	$text .= '<option></option>';
	foreach($opts as $id => $name)
	{
		$text .= '<option value="'.$id.'" '.($id == $station_id ? 'selected' : '').' class="'.$style_class[$id].'">';
		$text .= $name;
		$text .= '</option>';
	}
	$text .= '</select>';

	return $text;
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

	if (_BL('copyright_extra'))
		echo ' &bull; '._BL('copyright_extra');

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
		
		//Try to find some known words in short strings, i.e. country names
		if (strlen($msg) < 200 && strlen($msg) > 5)
		{
			$words = preg_split("@[,;:/\(\)\<\> ]@", $msg, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

			if (count($words) > 1)
			{
				$offset_diff = 0;
				foreach ($words as $d)
				{
					$word   = $d[0];
					$offset = $d[1];
					$len    = strlen(trim($word));
					
					if ($len > 2)
					{
						$word_new = _BL($word, true);
						$len_new  = strlen($word_new);
						
						if ($word_new && $word_new != $word)
						{
							$msg_new  = substr($msg, 0, $offset + $offset_diff);
							$msg_new .= $word_new;
							$msg_new .= substr($msg, $offset + $offset_diff + $len);
							
							$offset_diff = $len_new - $len;
							
							$msg = $msg_new;
							//$msg = strtr($msg, array($word => $word_new));
						}
					}
				}
			}
		}
		
		
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

function bo_get_tile_dim($x,$y,$zoom, $size=BO_TILE_SIZE)
{
	$tilesZoom = ((1 << $zoom) * 256 / $size);
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
	$scale = (1 << $zoom) * 256;

	return array((int)($x * $scale), (int)($y * $scale));
}

function bo_latlon2mercator($lat, $lon)
{
	if ($lon > 180)
		$lon -= 360;

	$lon /= 360;
	$lat = log(tan(M_PI/4 + deg2rad($lat)/2))/M_PI/2;
	return array($lon, $lat);
}


function bo_latlon2geos($lat, $lon)
{

	//  REFERENCE:                                            
	//  [1] LRIT/HRIT Global Specification                     
	//      (CGMS 03, Issue 2.6, 12.08.1999)                  
	//      for the parameters used in the program.

	if ($lon > 180)
		$lon -= 360;

	$SUB_LON     = 0.0;        /* longitude of sub-satellite point in radiant */
	$R_POL       = 6356.5838;  /* radius from Earth centre to pol             */
	$R_EQ        =  6378.169;  /* radius from Earth centre to equator         */
	$SAT_HEIGHT  = 42164.0;    /* distance from Earth centre to satellite     */
		
	$lat = deg2rad($lat);
	$lon = deg2rad($lon);
	
	/* calculate the geocentric latitude from the          */
	/* geograhpic one using equations on page 24, Ref. [1] */
	$c_lat = atan( 0.993243 * tan($lat) );
	
	/* using c_lat calculate the length form the Earth */
	/* centre to the surface of the Earth ellipsoid    */
	/* equations on page 23, Ref. [1]                  */
	$re = $R_POL / sqrt( (1.0 - 0.00675701 * cos($c_lat) * cos($c_lat) ) );
	
	/* calculate the forward projection using equations on */
	/* page 24, Ref. [1]                                   */
	$rl = $re; 
	$r1 = $SAT_HEIGHT - $rl * cos($c_lat) * cos($lon - $SUB_LON);
	$r2 = -$rl *  cos($c_lat) * sin($lon - $SUB_LON);
	$r3 = $rl * sin($c_lat);
	$rn = sqrt( $r1*$r1 + $r2*$r2 +$r3*$r3 );
	

	/* check for visibility, whether the point on the Earth given by the */
	/* latitude/longitude pair is visible from the satellte or not. This */ 
	/* is given by the dot product between the vectors of:               */
	/* 1) the point to the spacecraft,			               */
	/* 2) the point to the centre of the Earth.			       */
	/* If the dot product is positive the point is visible otherwise it  */
	/* is invisible.						       */
	$dotprod = $r1*($rl * cos($c_lat) * cos($lon - $SUB_LON)) - $r2*$r2 - $r3*$r3*(pow(($R_EQ/$R_POL),2));

	if ($dotprod <= 0 )
		return false;
	
	/* the forward projection is x and y */
	$x = atan(-$r2/$r1);
	$y = asin(-$r3/$rn);
	
	return array($x, $y);
}




function bo_sql_latlon2dist($lat1, $lon1, $lat_name='lat', $lon_name)
{
	if ($lat1 == $lat2 && $lon1 == $lon2)
		return 0;

	$sql = "ACOS(SIN(RADIANS($lat1)) * SIN(RADIANS($lat_name)) + COS(RADIANS($lat1)) * COS(RADIANS($lat_name)) * COS(RADIANS($lon1 - $lon_name))) * 6371000";

	return " ($sql) ";
}

function bo_sql_lat2tiley($name, $zoom)
{
	$scale = (1 << $zoom) * 256;
	$lat_mercator = " (  LOG(TAN( PI()/4 + RADIANS($name)/2 )) / PI() / 2 ) ";
	$y = " ROUND( ABS( $lat_mercator - 0.5 ) * $scale ) ";

	return $y;
}

function bo_sql_lon2tilex($name, $zoom)
{
	$scale = (1 << $zoom) * 256;
	$lon_mercator = " ( $name / 360 ) ";
	$x = " ROUND( ($lon_mercator+0.5) * $scale ) ";

	return $x;
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
			$_SESSION['bo_locale'] = $_COOKIE['bo_locale'];
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
	if ($lat1 === false || $lat2 === false || $lon1 === false || $lon2 === false)
		return " 1 ";

	$sql = " (s.lat BETWEEN '$lat1' AND '$lat2' AND s.lon BETWEEN '$lon1' AND '$lon2') ";


	//Extra keys for faster search (esp. tiles ans strike search)
	$keys_enabled = (BO_DB_EXTRA_KEYS === true);
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


	return $sql;
}

function bo_times2sql($time_min = 0, $time_max = 0, $table='s')
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

	//date range
	$date_min = gmdate('Y-m-d H:i:s', $time_min);
	$date_max = gmdate('Y-m-d H:i:s', $time_max);
	$sql = " ( $table.time BETWEEN '$date_min' AND '$date_max' ) ";


	//Extra keys for faster search
	$keys_enabled   = (BO_DB_EXTRA_KEYS === true);
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

function bo_strikes_sqlkey(&$index_sql, $time_min, $time_max, $lat1=false, $lat2=false, $lon1=false, $lon2=false)
{
	$sql  = " (";
	$sql .= bo_latlon2sql($lat1, $lat2, $lon1, $lon2);
	$sql .= " AND ";
	$sql .= bo_times2sql($time_min, $time_max);
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
	}
	
	if ($sql)
		$sql = ($exclude ? ' AND NOT ' : ' AND ').$sql;
	
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
function bo_raw2array($raw = false, $calc_spec = false, $channels = -1, $ntime = -1)
{
	static $std_channels = -1, $std_bpv = -1, $std_values = -1, $std_ntime = -1;

	//load default values
	if ($std_channels == -1 && $std_bpv == -1 && $std_ntime == -1 && $std_values == -1)
	{
		$std_channels = bo_get_conf('raw_channels');
		$std_bpv      = bo_get_conf('raw_bitspervalue');
		$std_ntime    = bo_get_conf('raw_ntime');
		$std_values   = bo_get_conf('raw_values');
	}

	$channels =  $channels > 0 ? $channels : $std_channels;
	$bpv      =  $bpv > 0      ? $bpv      : $std_bpv;
	$utime    = ($ntime > 0	  ? $ntime    : $std_ntime) / 1000;


	if ($channels <= 0 || $bpv <= 0 || $utime <= 0)
		return false;


	if ($raw === false)
	{
		//dummy signal when returning an empty array out of standard value count
		$calc_spec = true;
		$values = $std_values;
		$raw = str_repeat(chr(0), $std_values * $channels);
	}
	else
	{
		//calculate count of values out of current raw-data
		// -> no need to save this variable
		$values = strlen($raw) / $channels;
	}


	$data = array();
	$data['signal_raw'] = array();
	$data['signal'] = array();
	$data['spec'] = array();
	$data['spec'][0] = array();
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


function bo_examine_signal($data, $channels=0, $ntime=0, &$amp = array(), &$amp_max = array(), &$freq = array(), &$freq_amp = array())
{
	$sig_data = bo_raw2array($data, true, $channels, $ntime);

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
	if (!isset($_GET['quiet']))
	{
		echo date('Y-m-d H:i:s | ');
		echo $text;
		echo "\n";
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

// min/max zoom limits for dynamic map
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


//error output
function bo_image_error($text, $w=400, $h=300, $size=2)
{
	$I = imagecreate($w, $h);
	imagefill($I, 0, 0, imagecolorallocate($I, 255, 150, 150));
	$black = imagecolorallocate($I, 0, 0, 0);
	bo_imagestring($I, $size, 10, $h/2-25, $text, $black, $w-20);
	imagerectangle($I, 0,0,$w-1,$h-1,$black);
	
	Header("Content-type: image/png");
	Imagepng($I);
	exit;
}

function bo_image_cache_error($w=400, $h=300)
{
	bo_image_error('Creating image failed! Please check if your cache-dirs are writeable!', $w, $h, 3);
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
				$regions[0][$reg_id] = _BL($d['name']);
				$regions[1]['-'.$reg_id] = _BL('outside of').' '._BL($d['name']);
			}
		}

		ksort($regions[0]);
		ksort($regions[1]);
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
					$regions[0]['dist'.$dist] = _BL('max.').' '.$dist.'km '._BL('to station').' '._BC($name);
					$regions[1]['-dist'.$dist] = _BL('min.').' '.$dist.'km '._BL('to station').' '._BC($name);
				}
			}
		}
	}
	
	return $regions;

}

function bo_show_select_region($region, $bo_station_id = false, $show_exclude = true)
{
	$regions = bo_get_regions($bo_station_id);
	
	if (count($regions[0]) > 1)
	{
		echo '<select name="bo_region" onchange="submit();" id="bo_stat_strikes_select_now" class="bo_select_region">';
		echo '<option value="">'._BL('No limit').'</option>';
		
		echo '<optgroup label="'._BL('Show only region').'">';
		foreach($regions[0] as $i => $y)
			echo '<option value="'.$i.'" '.($i === $region ? 'selected' : '').'>'.$y.'</option>';
		echo '</optgroup>';

		if ($show_exclude && count($regions[1]) > 1)
		{
			echo '<optgroup label="'._BL('Exclude region').'">';
			foreach($regions[1] as $i => $y)
				echo '<option value="'.$i.'" '.($i === $region ? 'selected' : '').'>'.$y.'</option>';
			echo '</optgroup>';
		}
		
		echo '</select>';
	}


}

function bo_region2name($region, $bo_station_id = false)
{
	$regions = bo_get_regions($bo_station_id);
	$regions = array_merge($regions[0], $regions[1]);
	
	return $regions[$region];
}


?>