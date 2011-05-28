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



// returns png-image for map-marker
function bo_icon($icon)
{
	$dir = BO_DIR."cache/icons/";
	$file = $dir.$icon.'.png';

	$s = 11;

	if (!file_exists($file))
	{
		$c = floor($s/2);

		$im = ImageCreate($s, $s);
		$bg = imagecolorallocate($im, 255, 255, 255);
		$trans = imagecolortransparent($im, $bg);
		imagefill($im,0,0,$trans);

		$col = ImageColorAllocate ($im, hexdec(substr($icon,0,2)), hexdec(substr($icon,2,2)), hexdec(substr($icon,4,2)));
		imagefilledellipse( $im, $c, $c, $c+2, $c+2, $col );

		$tag = substr($icon,6,1);
		if ($tag == 1)
		{
			$col = ImageColorAllocate ($im, 0,0,0);
			imageellipse( $im, $c, $c, $c+2, $c+2, $col );
		}
		
		Imagepng($im, $file);
		ImageDestroy($im);
	}
	
	Header("Content-type: image/png");
	readfile($file);

	exit;
}

function bo_tile()
{
	set_time_limit(3);

	global $_BO;

	session_write_close();
	register_shutdown_function('bo_purge_images', 24, BO_DIR.'cache/tiles/');

	$x = intval($_GET['x']);
	$y = intval($_GET['y']);
	$zoom = intval($_GET['zoom']);
	$type = intval($_GET['type']);
	$only_own = intval($_GET['own']);
	$only_info = isset($_GET['info']);

	$time = time();

	//get config
	$cfg = $_BO['mapcfg'][$type];
	$time_start = $time - 60 * $cfg['tstart'];
	$time_range = $cfg['trange'];
	$update_interval = $cfg['upd_intv'];
	$c = $cfg['col'];

	if (!$time_start)
		exit;

	//calculate some time information
	$time_min = mktime(date('H', $time_start), ceil(date('i', $time_start) / $update_interval) * $update_interval, 0, date('m', $time_start), date('d', $time_start), date('Y', $time_start));
	$time_max = $time_min + 60 * $time_range + 59;
	$cur_minute  = intval(intval(date('i')) / $update_interval);
	$mod_time = mktime(date('H'), $cur_minute * $update_interval , 0);
	$exp_time = $mod_time + 60 * $update_interval + 59;
	$age      = $exp_time - time();

	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	
	//Headers
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $mod_time)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $exp_time)." GMT");

	if ($caching)
	{
		header("Pragma: ");
		header("Cache-Control: public, max-age=".$age);
	}
	else
	{
		header("Pragma: no-cache");
		header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
	}

	//send only the info image (colors, time)
	if ($only_info)
	{
		$w = 80;
		$h = 25;
		$col_height = 10;

		$I = imagecreate($w, $h);
		$col = imagecolorallocate($I, 50, 50, 50);
		imagefill($I, 0, 0, $col);

		$col_width = $w / count($c);
		foreach($c as $i => $rgb)
		{
			$color[$i] = imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);
			imagefilledrectangle($I, (count($c)-$i-1)*$col_width, 0, (count($c)-$i)*$col_width, $col_height, $color[$i]);
		}

		$time_max = min(bo_get_conf('uptime_strikes'), $time_max);
		$col = imagecolorallocate($I, 255,255,255);
		imagestring($I, 2, 1, $col_height + 1, date('H:i', $time_min).' - '.date('H:i', $time_max), $col);

		
		header("Content-Type: image/png");
		imagepng($I);
		exit;
	}


	//Caching
	$dir = BO_DIR.'cache/tiles/';
	$filename = 'tile_'.$type.'_'.$x.'x'.$y.'_'.$zoom.'_'.$only_own.'_'.(bo_user_get_level() ? 1 : 0).'.png';
	$file = $dir.$filename;

	if (file_exists($file) && $caching)
	{
		$filetime = filemtime($file);
		$file_minute = intval(intval(date('i', $filetime)) / $update_interval);

		if ($cur_minute == $file_minute && time() - $filetime < $update_interval * 60 )
		{
			header("Content-Type: image/png");
			readfile($file);
			exit;
		}
	}

	$date_min = gmdate('Y-m-d H:i:s', $time_min);
	$date_max = gmdate('Y-m-d H:i:s', $time_max);

	list($lat1, $lon1, $lat2, $lon2) = bo_get_tile_dim($x, $y, $zoom);
	//max. Distance
	$radius = $_BO['radius'] * 1000;

	if ($radius)
	{
		if ($zoom < BO_MAX_ZOOM_LIMIT) //set max. distance to 0 (no limit) for small zoom levels
		{
			$radius = 0;
		}
		else
		{
			list($min_lat, $min_lon) = bo_distbearing2latlong($radius * sqrt(2), 225);
			list($max_lat, $max_lon) = bo_distbearing2latlong($radius * sqrt(2), 45);

			//return text if outside of radius
			if ( 	($lat1 > $max_lat && $lat2 > $max_lat) ||
					($lat1 < $min_lat && $lat2 < $min_lat) ||
					($lon1 > $max_lon && $lon2 > $max_lon) ||
					($lon1 < $min_lon && $lon2 < $min_lon)
				)
			{

				if (!file_exists($dir.'na.png') || !$caching)
				{
					$I = imagecreate(BO_TILE_SIZE, BO_TILE_SIZE);

					$blank = imagecolorallocate($I, 255, 255, 255);
					$textcol = imagecolorallocate($I, 70, 70, 70);
					$box_bg  = imagecolorallocate($I, 210, 210, 255);
					$box_line  = imagecolorallocate($I, 255, 255, 255);

					imagefilledrectangle( $I, 0, 0, imagesx($I), imagesy($I), $blank);

					$text = _BL('tile not available', true);
					$lines = explode("\n", $text);
					$height = (count($lines));
					$width = 0;
					foreach($lines as $line)
						$width = max(strlen($line), $width);

					imagefilledrectangle( $I, 25, 115, 30+$width*9.5, 127+$height*12, $box_bg);
					imagerectangle( $I, 25, 115, 30+$width*9.5, 127+$height*12, $box_line);

					foreach($lines as $i=>$line)
						imagestring($I, 5, 30, 120+$i*12, $line, $textcol);

					imagecolortransparent($I, $blank);
					
					if (!$caching)
					{
						header("Content-Type: image/png");
						imagepng($I);
						exit;
					}
					
					imagepng($I, $dir.'na.png');

				}

				header("Content-Type: image/png");
				readfile($dir.'na.png');

				exit;
			}

		}
	}

	$zoom_show_deviation = defined('BO_MAP_STRIKE_SHOW_DEVIATION_ZOOM') ? intval(BO_MAP_STRIKE_SHOW_DEVIATION_ZOOM) : 12;
	
	//add some space around
	if ($zoom >= $zoom_show_deviation)
		$space = 2;
	else
		$space = 0.05;

	$lat1 -= ($lat2-$lat1) * $space;
	$lon1 -= ($lon2-$lon1) * $space;
	$lat2 += ($lat2-$lat1) * $space;
	$lon2 += ($lon2-$lon1) * $space;

	//color handling
	$color_intvl = ($time_max - $time_min) / count($c);

	$points = array();
	$deviation = array();
	$sql = "SELECT id, time, lat, lon, deviation, polarity
			FROM ".BO_DB_PREF."strikes
			USE INDEX (time_dist)
			WHERE 1
				".($radius ? "AND distance < $radius" : "")."
				".($only_own ? " AND part=1 " : "")."
				AND NOT (lat < $lat1 OR lat > $lat2 OR lon < $lon1 OR lon > $lon2)
				AND time BETWEEN '$date_min' AND '$date_max'
			-- ORDER BY time ASC";
	$erg = bo_db($sql);
	while ($row = $erg->fetch_assoc())
	{
		list($px, $py) = bo_latlon2tile($row['lat'], $row['lon'], $zoom);

		if ($zoom >= $zoom_show_deviation)
		{
			 list($dlat, $dlon) = bo_distbearing2latlong($row['deviation'], 0, $row['lat'], $row['lon']);
			 list($dx, $dy)     = bo_latlon2tile($dlat, $dlon, $zoom);
			 $deviation[]		= $py - $dy;
		}

		$px -= (BO_TILE_SIZE * $x);
		$py -= (BO_TILE_SIZE * $y);

		$strike_time = strtotime($row['time'].' UTC');
		$col = floor(($time_max - $strike_time) / $color_intvl);

		$points[] = array($px, $py, $col, $row['polarity']);
	}

	//no points --> blank tile
	if (count($points) == 0)
	{
		$img = file_get_contents(BO_DIR.'images/blank_tile.png');
		file_put_contents($file, $img);
		
		header("Content-Type: image/png");
		echo $img;
		exit;
	}


	//create Image
	$I = imagecreate(BO_TILE_SIZE, BO_TILE_SIZE);
	$blank = imagecolorallocate($I, 0, 0, 0);
	imagefilledrectangle( $I, 0, 0, imagesx($I), imagesy($I), $blank);


	foreach($c as $i => $rgb)
		$color[$i] = imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);

	if ($zoom >= BO_MAP_STRIKE_SHOW_CIRCLE_ZOOM)
	{
		$cradius = floor((4+0.5*$zoom)/2)*2-1;
	}
	else if ($zoom >= BO_EXPERIMENTAL_POLARITY_ZOOM && BO_EXPERIMENTAL_POLARITY_CHECK === true)
	{
		$style = 2; //with polarity
		$cradius = 5;
	}
	else
	{
		$cradius = 5;
		$style = 1;
	}


	$white = imagecolorallocate($I, 255, 255, 255);
	foreach($points as $i => $p)
	{
		switch($style)
		{
			case 1: // plot a "+"
				$s = 3;
				imagesetthickness($I, 2);
				imageline($I, $p[0]-$s, $p[1], $p[0]+$s-1, $p[1], $color[$p[2]]);
				imageline($I, $p[0], $p[1]-$s, $p[0], $p[1]+$s-1, $color[$p[2]]);
				break;

			case 2:
				$s = 3;

				if ($p[3] == null) //plot circle (no polarity known)
				{
					imagesetthickness($I, 1);
					imagefilledellipse($I, $p[0], $p[1], $cradius, $cradius, $color[$p[2]]);
				}
				else //plot "+" or "-"
				{
					imagesetthickness($I, 2);
					imageline($I, $p[0]-$s, $p[1], $p[0]+$s-1, $p[1], $color[$p[2]]);
					if ($p[3] > 0)
						imageline($I, $p[0], $p[1]-$s, $p[0], $p[1]+$s-1, $color[$p[2]]);
				}

				break;

			default: // plot circle
				imagesetthickness($I, 1);
				imagefilledellipse($I, $p[0], $p[1], $cradius, $cradius, $color[$p[2]]);

				if ($p[3] != null && BO_EXPERIMENTAL_POLARITY_CHECK == true)
				{
					imagesetthickness($I, 1);
					$s = intval($cradius / 2);
					imageline($I, $p[0]-$s+1, $p[1], $p[0]+$s-1, $p[1], $white);
					if ($p[3] > 0)
						imageline($I, $p[0], $p[1]-$s+1, $p[0], $p[1]+$s-1, $white);
				}

				break;
		}

		if ($zoom >= $zoom_show_deviation)
		{
			imagesetthickness($I, 1);
			imageellipse($I, $p[0], $p[1], $deviation[$i], $deviation[$i], $color[$p[2]]);
		}
	}



	/*
	$col = imagecolorallocate($I, 70, 70, 70);
	imagestring($I, 5, 10, 10 + $type * 18, date('Y-m-d H:i:s'), $col);
	imagerectangle( $I, 0, 0, imagesx($I), imagesy($I), $col);
	*/

	imagecolortransparent($I, $blank);

	header("Content-Type: image/png");
	if ($caching)
	{
		imagepng($I, $file);
		readfile($file);
	}
	else
		imagepng($I);
		
	imagedestroy($I);

	exit;
}

//automaticaly purge old tiles
function bo_purge_images($min_age, $dir)
{
	if (rand(0, 1000) == 1)
	{
		$files = scandir($dir);

		foreach($files as $file)
		{
			if (!is_dir($dir.$file) && fileatime($dir.$file) < time() - 3600 * $min_age)
				@unlink($dir.$file);
		}
	}

}

//render a map with strike positions and strike-bar-plot
function bo_get_map_image()
{
	session_write_close();
	set_time_limit(10);
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	
	
	register_shutdown_function('bo_purge_images', 96, BO_DIR.'cache/maps/');
	
	global $_BO;

	$id 			= intval($_GET['map']);
	$date 			= $_GET['date'];
	$transparent 	= isset($_GET['transparent']);
	$blank 			= isset($_GET['blank']);
	
	$cfg = $_BO['mapimg'][$id];
	if (!is_array($cfg))
		exit;

	//show the image without strikes
	if ($blank)
	{
		$file = $cfg['file'];
		
		$mod_time = filemtime(BO_DIR.'images/'.$file);
		$exp_time = time() + 3600 * 24 * 7;
		$age      = $exp_time - time();

		header("Content-Type: image/png");
		header("Pragma: ");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $mod_time)." GMT");
		header("Expires: ".gmdate("D, d M Y H:i:s", $exp_time)." GMT");
		header("Cache-Control: public, max-age=".$age);
		
		readfile(BO_DIR.'images/'.$file);
		exit;
	}

	$cache_file = BO_DIR.'cache/maps/'.$cache_file;
	
	if ($transparent)
		$cache_file .= 'transp_';
	

		
	$last_update = bo_get_conf('uptime_strikes');
	
	if (preg_match('/^[0-9\-]+$/', $date))
	{
		$archive_maps_enabled = (defined('BO_ENABLE_ARCHIVE_MAPS') && BO_ENABLE_ARCHIVE_MAPS) || (bo_user_get_level() & BO_PERM_ARCHIVE);
		
		if (!$archive_maps_enabled)
			exit('Forbidden!');
		
		$year = substr($date, 0, 4);
		$month = substr($date, 4, 2);
		$day = substr($date, 6, 2);

		$hour = substr($date, 8, 2);
		$minute = substr($date, 10, 2);
		$duration = intval(substr($date, 13));

		if (!bo_user_get_level())
		{
			if ( ($duration > 60 * 24 || ($duration && $duration < 60)) )
				exit;
			
			//allow only specific settings for guests
			$minute   = floor($minute / 15) * 15;
			$duration = floor($duration / 15) * 15;
		}
		
		if ($duration)
		{
			$time_min = strtotime("$year-$month-$day $hour:$minute:00");
			$time_max = strtotime("$year-$month-$day $hour:$minute:00 +$duration minutes");
		}
		else
		{
			$time_min = strtotime("$year-$month-$day 00:00:00");
			$time_max = strtotime("$year-$month-$day 23:59:59");
		}
		
		if ($time_max > $last_update)
			$time_max = $last_update;
		else
			$last_update = $time_max + 3600;
			
		$expire = time() + 3600;
		
		
		$time_string = date('d.m.Y H:i - ', $time_min).date('H:i', $time_max);
		
		$cache_file .= $id.'_'.$date.'.png';
	}
	else
	{
		$expire = $last_update + 60 * BO_UP_INTVL_STRIKES + 10;
		$time = time();
		$time_min = $time - 3600 * $cfg['trange'];
		$time_max = $time;
		$time_string = date('H:i', $time_min).' - '.date('H:i', $time_max);
		
		$cache_file .= $id.'.png';
	}

	$date_min = gmdate('Y-m-d H:i:s', $time_min);
	$date_max = gmdate('Y-m-d H:i:s', $time_max);
	
	
	header("Pragma: ");
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_update)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $expire)." GMT");
	header("Cache-Control: public, max-age=".($expire - time()));

	//Caching
	if ($caching && file_exists($cache_file) && filemtime($cache_file) >= $last_update)
	{
		header("Content-Type: image/png");
		readfile($cache_file);
		exit;
	}

	$file = $cfg['file'];
	$latN = $cfg['coord'][0];
	$lonE = $cfg['coord'][1];
	$latS = $cfg['coord'][2];
	$lonW = $cfg['coord'][3];
	$c = $cfg['col'];
	$size = $cfg['point_size'];

	if ($cfg['dim'][0] && $cfg['dim'][1])
	{
		$w = $cfg['dim'][0];
		$h = $cfg['dim'][1];
		$I = imagecreate($w, $h);
	}

	if ($file)
	{
		if ($transparent)
		{
			if (!$w || !$h)
				list($w, $h) = getimagesize(BO_DIR.'images/'.$file);
			
			$I = imagecreate($w, $h);
			$blank = imagecolorallocate($I, 0, 0, 0);
			imagefilledrectangle( $I, 0, 0, $w, $h, $blank);
			imagecolortransparent($I, $blank);
		}
		else if ($w && $h)
		{
			$J = imagecreatefrompng(BO_DIR.'images/'.$file);
			imagecopy($I, $J, 0, 0, 0, 0, imagesx($J), imagesy($J));
			imagedestroy($J);
		}
		else
		{
			$I = imagecreatefrompng(BO_DIR.'images/'.$file);
			$w = imagesx($I);
			$h = imagesy($I);
		}
	}

	list($x1, $y1) = bo_latlon2mercator($latS, $lonW);
	list($x2, $y2) = bo_latlon2mercator($latN, $lonE);
	$w_x = $w / ($x2 - $x1);
	$h_y = $h / ($y2 - $y1);

	foreach($c as $i => $rgb)
	{
		$color[$i] = imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);
		$count[$i] = 0;
	}

	$color_intvl = ($time_max - $time_min) / count($c);
	$sql = "SELECT id, time, lat, lon
			FROM ".BO_DB_PREF."strikes
			USE INDEX (time_dist)
			WHERE 1
				".($only_own ? " AND part=1 " : "")."
				AND NOT (lat < '$latS' OR lat > '$latN' OR lon < '$lonW' OR lon > '$lonE')
				AND time BETWEEN '$date_min' AND '$date_max'
			-- ORDER BY time ASC";
	$erg = bo_db($sql);
	while ($row = $erg->fetch_assoc())
	{
		$strike_time = strtotime($row['time'].' UTC');
		$col = floor(($time_max - $strike_time) / $color_intvl);
		$count[$col]++;

		if ($cfg['point_type'])
		{
			list($px, $py) = bo_latlon2mercator($row['lat'], $row['lon']);
			$x =      ($px - $x1) * $w_x;
			$y = $h - ($py - $y1) * $h_y;

			if ($cfg['point_type'] == 1)
			{
				imagefilledellipse($I, $x, $y, $size, $size, $color[$col]);
			}
			else if ($cfg['point_type'] == 2)
			{
				imageline($I, $x-$size, $y, $x+$size, $y, $color[$col]);
				imageline($I, $x, $y-$size, $x, $y+$size, $color[$col]);
			}
		}
	}

	//Date/Time/Strikes
	$fontsize = $w / 100;
	$time_max = min($last_update, $time_max);
	$time_max = intval($time_max / 60 / BO_UP_INTVL_STRIKES) * 60 * BO_UP_INTVL_STRIKES;
	$time_min = intval($time_min / 60 / BO_UP_INTVL_STRIKES) * 60 * BO_UP_INTVL_STRIKES;
	$text_col = imagecolorallocate($I, $cfg['textcolor'][0], $cfg['textcolor'][1], $cfg['textcolor'][2]);
	imagestring($I, $fontsize, 1, 1, $time_string, $text_col);

	//Strikes
	$text = _BL('Strikes', true).': '.array_sum($count);
	$fw = imagefontwidth($fontsize) * strlen($text);
	imagestring($I, $fontsize, $w - $fw - 1, 1, $text, $text_col);


	//Copyright
	imagestring($I, $fontsize, 1, $h - 9 - $fontsize, '(c) blitzortung.org', $text_col);

	//lightning legend
	if (isset($cfg['legend']) && is_array($cfg['legend']) && count($cfg['legend']))
	{
		$cw = $cfg['legend'][1];
		$ch = $cfg['legend'][2];
		$cx = $cfg['legend'][3];
		$cy = $cfg['legend'][4];

		$col_width = $cw / count($color);
		$cx = $w - $cw - $cx;
		$cy = $h - $ch - $cy;

		foreach($count as $i => $cnt)
		{
			if (max($count))
				$height = $ch * $cnt / max($count);
			else
				$height = 0;

			$px1 = $cx + (count($color)-$i-1) * $col_width;
			$px2 = $cx + (count($color)-$i) * $col_width - 1;
			$py1 = $cy + $ch;
			$py2 = $cy + $ch - $height;

			imagefilledrectangle($I, $px1, $py1, $px2, $py2, $color[$i]);

			if ($i == count($color)-1 && $cfg['legend'][0])
			{
				imagestringup($I, $cfg['legend'][0], $px1+1, $py1 - 4, $cnt, $text_col);
			}

		}

		if ($cfg['legend'][5])
		{
			imageline($I, $cx, $cy-1, $cx, $cy+$ch, $text_col);
			imageline($I, $cx, $cy+$ch, $cx+$cw+2, $cy+$ch, $text_col);
		}
	}



	header("Content-Type: image/png");
	if ($caching)
	{
		imagepng($I, $cache_file);
		readfile($cache_file);
	}
	else
		imagepng($I);

	exit;
}


//render a map with strike positions and strike-bar-plot
function bo_get_density_image()
{
	$densities_enabled = defined('BO_CALC_DENSITIES') && BO_CALC_DENSITIES
							&& ((defined('BO_ENABLE_DENSITIES') && BO_ENABLE_DENSITIES) || (bo_user_get_level() & BO_PERM_ARCHIVE));
	
	if (!$densities_enabled)
		exit('Forbidden');

	$year = intval($_GET['bo_year']);
	$month = intval($_GET['bo_month']);
	$map_id = intval($_GET['map']);
	$station_id = intval($_GET['id']);
	$ratio = isset($_GET['ratio']);
	
	set_time_limit(30);
	
	global $_BO;

	//Image settings
	$cfg = $_BO['mapimg'][$map_id];
	if (!is_array($cfg) || !$cfg['density'])
		exit('Missing image data!');

	//todo: needs adjustments
	$last_update = strtotime('today - 12 hours');
	$expire = time() + 3600 * 24;
		
	header("Pragma: ");
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_update)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $expire)." GMT");
	header("Cache-Control: public, max-age=".($expire - time()));

	//Caching
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true) && BO_LOCALE == _BL();
	$cache_file = BO_DIR.'cache/density_map'.sprintf('%d_station%d_%d_%04d%02d.png', $map_id, $station_id, $ratio ? 1 : 0, $year, $month);
	if ($caching && file_exists($cache_file) && filemtime($cache_file) >= $last_update)
	{
		header("Content-Type: image/png");
		readfile($cache_file);
		exit;
	}
		
	$file = $cfg['file'];
	$latN = $cfg['coord'][0];
	$lonE = $cfg['coord'][1];
	$latS = $cfg['coord'][2];
	$lonW = $cfg['coord'][3];
	$min_block_size = max($cfg['density_blocksize'], intval($_GET['blocksize']), 1);
	$colors = is_array($cfg['density_colors']) ? $cfg['density_colors'] : $_BO['tpl_density_colors'];

	$tmpImage = imagecreatefrompng(BO_DIR.'images/'.$file);
	$w = imagesx($tmpImage);
	$h = imagesy($tmpImage);
	
	$I = imagecreatetruecolor($w, $h);
	imagecopy($I,$tmpImage,0,0,0,0,$w,$h);
	imagedestroy($tmpImage);
	imagealphablending($I, true);
	$text_col = imagecolorallocate($I, $cfg['textcolor'][0], $cfg['textcolor'][1], $cfg['textcolor'][2]);
	$fontsize = $w / 100;	
	
	if ($cfg['density_darken'])
	{
		$color = imagecolorallocatealpha($I, 0, 0, 0, (1 - $cfg['density_darken'] / 100) * 127);
		imagefilledrectangle($I, 0,0, $w, $h, $color);
	}
	
	list($x1, $y1) = bo_latlon2mercator($latS, $lonW);
	list($x2, $y2) = bo_latlon2mercator($latN, $lonE);
	$w_x = $w / ($x2 - $x1);
	$h_y = $h / ($y2 - $y1);

	if ($month)
	{
		$date_start = "$year-$month-01";
		$date_end   = date('Y-m-d', mktime(0,0,0,$month+1,0,$year));
	}
	else
	{
		$date_start = "$year-01-01";
		$date_end   = "$year-12-31";	
	}
	
	//find density to image
	$sql = "SELECT 	id, station_id, type, info, data, 
					lat_max, lon_max, lat_min, lon_min, length,
					date_start, date_end
					FROM ".BO_DB_PREF."densities 
					WHERE 1 
						AND status >= 1 
						AND date_start = '$date_start'
						AND date_end   <= '$date_end'
						AND 
							(station_id = $station_id
							".($ratio ? " OR station_id=0 " : "")."
							)
						AND lat_max >= '$latN'
						AND lon_max >= '$lonE'
						AND lat_min <= '$latS'
						AND lon_min <= '$lonW'
					ORDER BY length ASC, date_end DESC, station_id ASC
					LIMIT 2
						";
	$res = bo_db($sql);
	$row = $res->fetch_assoc();

	
	$exit_msg = '';
	if (!$row['id'])
		$exit_msg = _BL('No data available!', true);
	
	//Data and info
	$DATA = gzinflate($row['data']);
	$info = unserialize($row['info']);
	$bps = $info['bps'];
	
	//dates 
	$date_end = $row['date_end'];
	$time_string = $row['date_start'].' - '.$row['date_end'];
	
	//coordinates
	$lat       = $row['lat_min'];
	$lon       = $row['lon_min'];
	$lat_end   = $row['lat_max'];
	$lon_end   = $row['lon_max'];
	$length    = $row['length'];
	$area      = pow($length, 2);

	if ($ratio)
	{
		$row = $res->fetch_assoc();
		if ($station_id != $row['station_id'] || $date_end != $row['date_end'])
			$exit_msg = _BL('Not enough data available!', true);	

		$OWN_DATA = gzinflate($row['data']);
	}
	
	// Exit if not enough data
	if ($exit_msg)
	{
		$fw = imagefontwidth($fontsize) * strlen($exit_msg);
		imagestring($I, $fontsize, $w/2 - $fw/2 - 1, $h / 2, $exit_msg, $text_col);
		header("Content-Type: image/png");
		imagepng($I);
		exit;
	}
	
	
	//max strike count
	$max_density = $info['max'] / $area;

	//pointer on current part of string
	$string_pos = 0;
	
	$lonW = $lon;
	$lonE = $lon_end;
	$lonN = $lat_end;
	$lonS = $lat;
	
	$PIC_DATA = array();
	$strike_count = 0;
	
	$last_y = $h;
	while ($lat < $lat_end)
	{
		//density: difference to current lat/lon
		list($dlat, $dlon) = bo_distbearing2latlong($length * sqrt(2) * 1000, 45, $lat, $lon);
		$dlat -= $lat;
		$dlon -= $lon;
	
		// check if latitude lies in picture
		if ($lat + $dlat >= $latS)
		{
			//select correct data segment from data string
			$lon_start_pos  = floor(($lonW-$lon)/$dlon) * 2 * $bps;
			$lon_string_len = floor(($lonE-$lon)/$dlon) * 2 * $bps - $lon_start_pos;
		
			$lon_data = substr($DATA, $string_pos + $lon_start_pos, $lon_string_len);
			
			if ($ratio)
				$lon_data_own = substr($OWN_DATA, $string_pos + $lon_start_pos, $lon_string_len);
			
			//image coordinates (left side of image, height is current latitude)
			list($px, $py) = bo_latlon2mercator($lat, $lonE);
			$y  = $h - ($py - $y1) * $h_y; //image y
			$dx = $dlon / ($lonE - $lonW) * $w;
			
			//get the data!
			for($j=0; $j<$lon_string_len/2/$bps; $j++)
			{
				//strikes per square kilometer
				$value = hexdec(substr($lon_data, $j * 2 * $bps, 2 * $bps));
				
				if (!intval($value))
					continue;

				if ($ratio)
					$value = hexdec(substr($lon_data_own, $j * 2 * $bps, 2 * $bps)) / $value;
					
				//color
				$oldcolor = $colors[0];
				
				//image x
				$x = $j * $dx;
				
				//x,y coordinates to picture "blocks"
				$ax = round(($x / $min_block_size));
				$ay = round(($y / $min_block_size));
				
				//Data array
				$PIC_DATA[$ax+$ay*$w] += $value;
			}
		}

		$string_pos += (floor(($lon_end-$lon)/$dlon)+2) * 2 * $bps;
		$lat += $dlat;
		
		// stop if picture is full
		if ($lat > $latN)
			break;
	
	}

	$max = !empty($PIC_DATA) ? max($PIC_DATA) : 0;

	if ($max)
	{
		$color_count = count($colors)-1;
		foreach($PIC_DATA as $no => $value)
		{
			$value = $value / $max; //to relative count

			$x = ($no % $w);
			$y = ($no-$x) / $w;

			$x *= $min_block_size;
			$y *= $min_block_size;
			
			$oldcolor = $colors[0];
			foreach($colors as $color_index => $c)
			{
				$color_value = ($color_index) / $color_count;
				
				if ($value <= $color_value)
				{
					//find "position" between the two colors
					$color_pos = $value * $color_count - $color_value * $color_count + 1;
					
					$red   = $oldcolor[0] + ($c[0] - $oldcolor[0]) * $color_pos;
					$green = $oldcolor[1] + ($c[1] - $oldcolor[1]) * $color_pos;
					$blue  = $oldcolor[2] + ($c[2] - $oldcolor[2]) * $color_pos;
					$alpha = $oldcolor[3] + ($c[3] - $oldcolor[3]) * $color_pos;
					break;
				}
				
				$oldcolor = $c;
			}

			$color = imagecolorallocatealpha($I, $red, $green, $blue, $alpha);
			imagefilledrectangle($I, $x, $y, $x+$min_block_size-1, $y+$min_block_size-1, $color);

		}
	}

	//Date/Time
	imagestring($I, $fontsize, 1, 1, $time_string, $text_col);

	//Station Name
	if ($station_id)
	{
		$stinfo = bo_station_info($station_id);
		$text = $stinfo['city'];
		
		$text .= $ratio ? ' ('._BL('Strike ratio').')' : '';
		
		imagestring($I, $fontsize, 1, 1 + $fontsize * 3, _BL('Station', true).': '.$text, $text_col);
		
		list($px, $py) = bo_latlon2mercator($stinfo['lat'], $stinfo['lon']);
		$x =      ($px - $x1) * $w_x;
		$y = $h - ($py - $y1) * $h_y;
		
		$size = 6;
		$color = imagecolorallocate($I, 255,255,255);
		imageline($I, $x-$size, $y, $x+$size, $y, $color);
		imageline($I, $x, $y-$size, $x, $y+$size, $color);

	}
	else
	{
		//Strike density
		$text = _BL('Max strikes density', true).': '.number_format($max_density, 1, _BL('.'), _BL(',')).'/km^2';
		$fw = imagefontwidth($fontsize) * strlen($text);
		imagestring($I, $fontsize, $w - $fw - 1, 1, $text, $text_col);

		//Strike density
		$text = _BL('Strikes', true).': '.array_sum($PIC_DATA).'';
		$fw = imagefontwidth($fontsize) * strlen($text);
		imagestring($I, $fontsize, $w - $fw - 1, 1 + $fontsize * 3, $text, $text_col);
		
	}
	
	//Copyright
	imagestring($I, $fontsize, 1, $h - 9 - $fontsize, '(c) blitzortung.org', $text_col);

	
	header("Content-Type: image/png");
	if ($caching)
	{
		imagepng($I, $cache_file);
		readfile($cache_file);
	}
	else
		imagepng($I);

	exit;
	
}

//get an image from /images directory
//we need this for easy integration of MyBlitzortung in other projects
function bo_get_image($img)
{

	switch($img)
	{
		case 'logo':
		default:
			$file = 'blitzortung_logo.jpg';
			break;

		case 'my':
		default:
			$file = 'myblitzortung.png';
			break;
	}

	$ext = strtr(substr($file, -3), array('jpg' => 'jpeg'));

	$file = BO_DIR.'images/'.$file;

	$mod_time = filemtime($file);
	$exp_time = time() + 3600 * 24 * 7;
	$age      = $exp_time - time();

	header("Content-Type: image/".$ext);
	header("Pragma: ");
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $mod_time)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $exp_time)." GMT");
	header("Cache-Control: public, max-age=".$age);

	readfile($file);
	exit;
}


?>