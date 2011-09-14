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

		$tag = intval(substr($icon,6,1));
		if ($tag >= 1)
		{
			$col = ImageColorAllocate ($im, 0,0,0);
			imageellipse( $im, $c, $c, $c+$tag, $c+$tag, $col );
		}
		
		Imagepng($im, $file);
		ImageDestroy($im);
	}
	
	Header("Content-type: image/png");
	readfile($file);

	exit;
}

//render a map with strike positions and strike-bar-plot
function bo_get_map_image($id=false, $cfg=array(), $return_img=false)
{
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	$archive_maps_enabled = (BO_DISABLE_ARCHIVE !== true && defined('BO_ENABLE_ARCHIVE_MAPS') && BO_ENABLE_ARCHIVE_MAPS)
								|| (bo_user_get_level() & BO_PERM_ARCHIVE);

	if (intval(BO_CACHE_PURGE_MAPS_RAND) > 0 && rand(0, BO_CACHE_PURGE_MAPS_RAND) == 1 && $id !== false)
	{	
		register_shutdown_function('bo_delete_files', BO_DIR.'cache/maps/', intval(BO_CACHE_PURGE_MAPS_HOURS), 3);
	}
	
	global $_BO;

	if ($id === false)
	{
		$id 			= $_GET['map'];
		
		if (preg_match('/[^0-9a-z_]/i', $id))
			exit('Hacking disabled ;-)');
		
		$date 			= $_GET['date'];
		$transparent 	= isset($_GET['transparent']);
		$blank 			= isset($_GET['blank']);
		$region			= $_GET['mark'];
		$strike_id		= intval($_GET['strike_id']);
		
		$cfg = $_BO['mapimg'][$id];
	}
	else
	{
		$date 			= $cfg['date'];
		$transparent 	= $cfg['transparent'];
		$blank 			= $cfg['blank'];
		$region			= $cfg['mark'];
		$strike_id		= $cfg['strike_id'];
		$caching		= $caching && $cfg['caching'];
	}
	
	if (!is_array($cfg) || empty($cfg))
		return;

		
	
	if ($return_img)
	{
		$caching = false;
	}
	else
	{
		session_write_close();
		
		if ($transparent)
			@set_time_limit(5);
		else
			@set_time_limit(10);
		
		if (BO_FORCE_MAP_LANG === true)
			bo_load_locale(BO_LOCALE);
	}
	
	$last_update = bo_get_conf('uptime_strikes_modified');
	
	//Cache file naming
	$cache_file = BO_DIR.'cache/maps/';
	$cache_file .= _BL().'_';
	
	if (BO_CACHE_SUBDIRS === true)
		$cache_file .= $id.'/';
	
	if ($transparent)
		$cache_file .= 'transp_';

	if ($blank)
		$cache_file .= 'blank_';
	
	if ($strike_id)
		$cache_file .= 's'.$strike_id.'_';
	
	if (preg_match('/[0-9a-z]+/i', $region) && isset($_BO['region'][$region]['rect_add']))
		$cache_file .= 'region'.$region.'_';


	$sql_where_id = '';
	
	if ($strike_id)
	{
		//image with only one strike
		
		if (!$archive_maps_enabled)
			bo_image_error('Forbidden!');
		
		$sql_where_id .= " AND id='$strike_id' ";
		
		//no legend
		$cfg['legend'] = array();
		
		$sql = "SELECT time, time_ns FROM ".BO_DB_PREF."strikes s WHERE id='$strike_id' ";
		$res = bo_db($sql);
		$row = $res->fetch_assoc();
		$time_min = $time_max = strtotime($row['time'].' UTC');
		$time_string = date('H:i:s', $time_min).'.'.substr($row['time_ns'], 0, 6);
		
		$file_by_time = true;
		$caching = false;
	}
	else if (preg_match('/^[0-9\-]+$/', $date))
	{
		//the archive images
		
		if (!$archive_maps_enabled)
			bo_image_error('Forbidden!');
		
		$year     = sprintf('%04d', substr($date, 0, 4));
		$month    = sprintf('%02d', substr($date, 4, 2));
		$day      = sprintf('%02d', substr($date, 6, 2));
		$hour     = sprintf('%02d', substr($date, 8, 2));
		$minute   = sprintf('%02d', substr($date, 10, 2));
		$duration = intval(substr($date, 13));

	
		if (!bo_user_get_level() && $duration != $cfg['animation']['range'])
		{
			if ( ($duration > 60 * 24 || ($duration && $duration < 15)) )
				bo_image_error('Time range not allowed!');
			
			//allow only specific settings for guests
			$minute   = floor($minute / 15) * 15;
			$duration = floor($duration / 15) * 15;
		}
		
		if ($duration)
		{
			//When duration/time then use UTC!
			$time_min = strtotime("$year-$month-$day $hour:$minute:00 UTC");
			$time_max = strtotime("$year-$month-$day $hour:$minute:00 +$duration minutes UTC");
		}
		else
		{
			$time_min = strtotime("$year-$month-$day 00:00:00");
			$time_max = strtotime("$year-$month-$day 23:59:59");
		}

		if (BO_CACHE_SUBDIRS === true)
			$cache_file .= gmdate('Ymd', $time_min).'/';
		
		$cache_file .= $id.'_'.gmdate('YmdHi', $time_min).'_'.$duration;
		
		if ($time_max > $last_update)
		{
			$time_max = $last_update;
		}
		else
		{
			$last_update = $time_max + 3600;
		}

		$time_string = date(_BL('_date').' ', $time_min);
		$time_string .= date('H:i', $time_min).' - '.date('H:i', $time_max);
		
		$expire = time() + 3600;
		
		$file_by_time = true;
	}
	else
	{
		//the normal "live" image
		$sql_where_id .= " AND (status>0 OR time > '".gmdate('Y-m-d H:i:s', $last_update - BO_MIN_MINUTES_STRIKE_CONFIRMED * 60)."') ";
		
		$expire = $last_update + 60 * BO_UP_INTVL_STRIKES + 10;
		
		if (isset($cfg['tstart']))
			$time = $cfg['tstart'];
		else
			$time = $last_update;
			
		$time_min = $time - 3600 * $cfg['trange'];
		$time_max = $time;
		
		//$time_string  = date(_BL('_date').' ', $time_min);
		$time_string .= date('H:i', $time_min).' - '.date('H:i', $time_max);
		
		$cache_file .= $id;
		
		$file_by_time = false;
	}

	if ($cfg['date_min'] && strtotime($cfg['date_min']) && $time_min < strtotime($cfg['date_min']))
		bo_image_error('Minimum date is '.$cfg['date_min']);
	
	//find the correct file
	$file = '';
	
	//filename by endtime
	if (($file_by_time || isset($cfg['file_time_search'])) && isset($cfg['file_time']))
	{
		$search_times[] = $time_max;
		
		if (isset($cfg['file_time_search']) && is_array($cfg['file_time_search']))
		{
			$sstep = $cfg['file_time_search'][0];
			$sback = $cfg['file_time_search'][1]; 
			$sforw = $cfg['file_time_search'][2];

			$time_search = floor($time_max / 60 / $sstep) * 60 * $sstep;
			
			$j=0;
			for ($i=$time_search-60*$sback;$i<=$time_search+60*$sforw;$i+=60*$sstep)
			{
				if ($i != $time_search)
					$search_times[(abs($time_search-$i)/60).'.'.$j] = $i;
				$j++;
			}
			
			ksort($search_times);
		}
		
		$found = false;
		foreach($search_times as $stime)
		{
			$replace = array(
				'%Y' => gmdate('Y', $stime),
				'%y' => gmdate('y', $stime),
				'%M' => gmdate('m', $stime),
				'%D' => gmdate('d', $stime),
				'%h' => gmdate('H', $stime),
				'%m' => gmdate('i', $stime)
				);

			$file = strtr($cfg['file_time'], $replace);

			if (file_exists(BO_DIR.'images/'.$file))
			{
				$found = true;
				break;
			}
		}
		
		if (!$found)
		{
			$cache_file .= '_nobg';
			$file = '';
			$expire = time() + 15 * 60;
		}
		else
		{
			$cache_file .= '_'.strtr(basename($file), array('.' => '_')).'_'.filemtime(BO_DIR.'images/'.$file);
		}
	}
	
	if (!$file && $cfg['file'])
		$file = $cfg['file'];
	
	
	//file type
	$extension = strtolower(substr($file, strrpos($file, '.')+1));
	 
	if ($extension == 'jpg' || $extension == 'jpeg')
	{
		$cache_file .= '.jpg';
		$mime = "image/jpeg";
		$use_truecolor = true;
	}
	elseif ($extension == 'gif')
	{
		$cache_file .= '.gif';
		$mime = "image/gif";
		$use_truecolor = BO_IMAGE_USE_TRUECOLOR;
	}
	else // PNG is default
	{
		$cache_file .= '.png';
		$mime = "image/png";
		$use_truecolor = BO_IMAGE_USE_TRUECOLOR;
		$extension = "png";
	}
	
	if ($transparent)
		$extension = "png";
	
	//Headers
	header("Pragma: ");
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_update)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $expire)." GMT");
	header("Cache-Control: public, max-age=".($expire - time()));
	header("Content-Disposition: inline; filename=\"MyBlitzortungStrikeMap.".$extension."\"");

	//Caching
	if ($caching && file_exists($cache_file) && filemtime($cache_file) >= $last_update - intval($cfg['upd_intv'])*60 )
	{
		header("Content-Type: $mime");
		readfile($cache_file);
		exit;
	}

	
	//Preparations
	$latN = $cfg['coord'][0];
	$lonE = $cfg['coord'][1];
	$latS = $cfg['coord'][2];
	$lonW = $cfg['coord'][3];
	$size = $cfg['point_size'];
	$c = $cfg['col'];
	
	//dimensions are set
	if (isset($cfg['dim']))
	{
		$w = $cfg['dim'][0];
		$h = $cfg['dim'][1];
	}
	
	//move img
	if (isset($cfg['dim'][3]) || isset($cfg['dim'][4]))
	{
		$move_x = intval($cfg['dim'][3]);
		$move_y = intval($cfg['dim'][4]);
		
		//reset dimensions (only important when no $file)
		$w -= $move_x;
		$h -= $move_y;
	}

	//dimensions
	if ($transparent && $file)
	{
		list($w, $h) = getimagesize(BO_DIR.'images/'.$file);
		$file = '';
	}
	
	$I = null;
	
	//Dimensions are given, but no file (or transparent)
	if (!$file && $w && $h) 
	{
		if ($use_truecolor === true)
			$I = imagecreatetruecolor($w, $h);
		else
			$I = imagecreate($w, $h);
		
		if ($transparent)
		{
			$back = imagecolorallocate($I, 1, 2, 3);
			imagefilledrectangle($I, 0, 0, $w, $h, $back);
			imagecolortransparent($I, $back);
		}
		elseif ($cfg['dim'][2])
		{
			$back = bo_hex2color($I, $cfg['dim'][2]);
			imagefilledrectangle($I, 0, 0, $w, $h, $back);
		}			

	}
	else if ($file) 	//Filename is given
	{
		if ($transparent) //transpatent image
		{
			list($w, $h) = getimagesize(BO_DIR.'images/'.$file);
			$I = imagecreate($w, $h);
			$blank = imagecolorallocate($I, 1, 2, 3);
			imagefilledrectangle( $I, 0, 0, $w, $h, $blank);
			imagecolortransparent($I, $blank);
		}
		else //normal image
		{
			$I = bo_imagecreatefromfile(BO_DIR.'images/'.$file);
			$w = imagesx($I);
			$h = imagesy($I);
		}
	}

	if (!$I)
		bo_image_error("Image error $w x $h");
	
	//to truecolor, if needed
	if (!$transparent && $use_truecolor === true && imageistruecolor($I) === false) 
	{
		$tmpImage = imagecreatetruecolor($w, $h);
		imagecopy($tmpImage,$I,0,0,0,0,$w,$h);
		imagedestroy($I);
		$I = $tmpImage;
		imagealphablending($I, true);
	}

	
	//image dimensions
	list($x1, $y1) = bo_latlon2projection($cfg['proj'], $latS, $lonW);
	list($x2, $y2) = bo_latlon2projection($cfg['proj'], $latN, $lonE);
	$w_x = $w / ($x2 - $x1);
	$h_y = $h / ($y2 - $y1);

	
	//main strike colors
	$color_tmp = array();
	foreach($c as $i => $rgb)
	{
		if (!is_array($rgb))
		{
			 $rgb = bo_hex2rgb($rgb);
			 $color_tmp[$i] = $rgb;
		}
		
		//alpha doens't work with alpha-channel and transparent background
		if ($transparent)
			$color[$i] = imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);
		else
			$color[$i] = imagecolorallocatealpha($I, $rgb[0], $rgb[1], $rgb[2], $rgb[3]);
			
		$count[$i] = 0;
	}
	
	if (!empty($color_tmp))
		$c = $color_tmp;
	
	//smooth the colors
	if ($cfg['col_smooth'])
	{
		for ($i=0;$i<=$cfg['col_smooth'];$i++)
		{
			list($red, $green, $blue, $alpha) = bo_value2color($i/$cfg['col_smooth'], $c);
			
			if ($transparent)	
				$color_smooth[$i] = imagecolorallocate($I, $red, $green, $blue);
			else
				$color_smooth[$i] = imagecolorallocatealpha($I, $red, $green, $blue, $alpha);
		}
	
	}
	
	
	//for backward compat. read the old point settings (deprecated!)
	if (!isset($cfg['point_style']) && $cfg['point_type'])
		$cfg['point_style'] = array(0 => $cfg['point_type'], 1 => $cfg['point_size']);

		
	//time calculations
	$time_range  = $time_max - $time_min + 59;
	$color_intvl = $time_range / count($c);
	
	
	//get the strikes
	if (!$blank)
	{
	
		//the where clause
		$sql_where = bo_strikes_sqlkey($index_sql, $time_min, $time_max, $latS, $latN, $lonW, $lonE);
	
		$sql = "SELECT time, lat, lon, status
				FROM ".BO_DB_PREF."strikes s
				$index_sql
				WHERE 1 AND
					".($only_own ? " AND part>0 " : "")."
					$sql_where
					$sql_where_id
					".bo_region2sql($region)."
				ORDER BY time ASC";
		$res = bo_db($sql);
		
		
		while ($row = $res->fetch_assoc())
		{
			$strike_time = strtotime($row['time'].' UTC');
			$age = $time_max - $strike_time;
			$color_index = floor($age / $color_intvl);
			

			$count[$color_index]++;

			if (isset($cfg['point_style']))
			{
				list($px, $py) = bo_latlon2projection($cfg['proj'], $row['lat'], $row['lon']);
				$x =      ($px - $x1) * $w_x;
				$y = $h - ($py - $y1) * $h_y;


				if ($cfg['col_smooth'])
					$pcolor = $color_smooth[floor($age / $time_range * $cfg['col_smooth'])];
				else
					$pcolor = $color[$color_index];

				bo_drawpoint($I, $x, $y, $cfg['point_style'], $pcolor, !$transparent);
			}
		}
	}
	
	//default color
	$text_col = imagecolorallocate($I, $cfg['textcolor'][0], $cfg['textcolor'][1], $cfg['textcolor'][2]);

	if (!$transparent)
	{
		//Borders
		if ($cfg['borders'][0] && file_exists(BO_DIR.'images/'.$cfg['borders'][0]))
		{
			$tmpImage = bo_imagecreatefromfile(BO_DIR.'images/'.$cfg['borders'][0]);
			if ($tmpImage)
				imagecopymerge($I, $tmpImage, 0,0, 0,0, $w, $h, $cfg['borders'][1]);
		}
		
		
		//add cities
		bo_add_cities2image($I, $cfg, $w, $h);
		
		//add stations
		bo_add_stations2image($I, $cfg, $w, $h, $strike_id);
		
		//Show station pos
		if ($cfg['show_station'][0])
		{
			$stinfo = bo_station_info();
			
			list($px, $py) = bo_latlon2projection($cfg['proj'], $stinfo['lat'], $stinfo['lon']);
			$x =      ($px - $x1) * $w_x;
			$y = $h - ($py - $y1) * $h_y;
			
			$size = $cfg['show_station'][0];
			
			if (isset($cfg['show_station'][1]))
				$stat_color = imagecolorallocate($I, $cfg['show_station'][1],$cfg['show_station'][2],$cfg['show_station'][3]);
			else
				$stat_color = $text_col;
				
			imageline($I, $x-$size, $y, $x+$size, $y, $stat_color);
			imageline($I, $x, $y-$size, $x, $y+$size, $stat_color);
			
			if ($cfg['show_station'][4])
			{
				$tsize = (int)$cfg['show_station'][4];
				$tsize = $tsize > 4 ? $tsize : 9;
				
				$dx = isset($cfg['show_station'][6]) ? (int)$cfg['show_station'][6] : 2;
				$dy = isset($cfg['show_station'][7]) ? (int)$cfg['show_station'][7] : -12;
				
				bo_imagestring($I, $tsize, $x+$dx, $y+$dy, $stinfo['city'], $stat_color, $cfg['show_station'][5]);
			}
		}
	}
	
	//Show Regions (for developing)
	if ($region && isset($_BO['region'][$region]['rect_add']))
	{
		$rect_col['rect_add'] = imagecolorallocate($I, 0, 255, 0);
		$rect_col['rect_rem'] = imagecolorallocate($I, 255, 100, 0);
		
		foreach(array('rect_add', 'rect_rem') as $rect_type)
		{
			$reg = $_BO['region'][$region][$rect_type];
			
			while ($r = @each($reg))
			{
				$lat1 = $r[1];
				list(,$lon1) = @each($reg);
				list(,$lat2) = @each($reg);
				list(,$lon2) = @each($reg);
				
				list($px, $py) = bo_latlon2projection($cfg['proj'], $lat1, $lon1);
				$rx1 =      ($px - $x1) * $w_x;
				$ry1 = $h - ($py - $y1) * $h_y;

				list($px, $py) = bo_latlon2projection($cfg['proj'], $lat2, $lon2);
				$rx2 =      ($px - $x1) * $w_x;
				$ry2 = $h - ($py - $y1) * $h_y;
				
				imagerectangle($I, $rx1, $ry1, $rx2, $ry2, $rect_col[$rect_type]);
			}
		}
	}

	
	/*** no more calculations with coordinates from here, because image dimensions may change! ***/
	
	//Dimensions where given => copy the image 
	//or image must be resized/moved
	if ($file && $cfg['dim'][0] && $cfg['dim'][1] || ($move_x || $move_y)) 
	{
		$w = $cfg['dim'][0];
		$h = $cfg['dim'][1];
		
		if ($use_truecolor === true)
			$J = imagecreatetruecolor($cfg['dim'][0], $cfg['dim'][1]);
		else
			$J = imagecreate($cfg['dim'][0], $cfg['dim'][1]);

		imagealphablending($J, true);
		
		if ($transparent)
		{
			$back = imagecolorallocate($J, 1, 2, 3);
			imagefilledrectangle( $J, 0, 0, $w, $h, $back);
			imagecolortransparent($J, $back);
		}
		elseif ($cfg['dim'][2])
		{
			$back = bo_hex2color($J, $cfg['dim'][2]);
			imagefilledrectangle( $J, 0, 0, $w, $h, $back);
		}			
		
		imagecopy($J, $I, $move_x, $move_y, 0, 0, imagesx($J), imagesy($J));
		imagedestroy($I);
		$I = $J;
	}
	
	if (!$blank)
	{
		/* LEGEND */
		//lightning legend
		if (isset($cfg['legend']) && is_array($cfg['legend']) && count($cfg['legend']))
		{
			$fontsize = $cfg['legend'][0];
			$cw = $cfg['legend'][1];
			$ch = $cfg['legend'][2];
			$cx = $cfg['legend'][3];
			$cy = $cfg['legend'][4];

			$coLegendWidth = floor($cw / count($color));
			$cx = $w - $cw - $cx;
			$cy = $h - $ch - $cy;
			$legend = true;
		}
		
		//banners
		$extra = _BL('Strikes', true).': '.array_sum($count);
		bo_image_banner_top($I, $w, $h, $cfg, $time_string, $extra);
		bo_image_banner_bottom($I, $w, $h, $cfg, $cw);


		if ($legend)
		{
			$legend_text_drawn = false;

			ksort($count);
			
			foreach($count as $i => $cnt)
			{
				if (max($count))
					$height = $ch * $cnt / max($count);
				else
					$height = 0;

				$px1 = $cx + (count($color)-$i-1) * $coLegendWidth;
				$px2 = $cx + (count($color)-$i) * $coLegendWidth - 1;
				$py1 = $cy + $ch;
				$py2 = $cy + $ch - $height;

				imagefilledrectangle($I, $px1, $py1, $px2, $py2, $color[$i]);

				if (!$legend_text_drawn && $cfg['legend'][0] &&
						(    ($transparent  && $i == count($color)-1)
						  || (!$transparent && $cnt == max($count))
						) 
				   )
				{
				
					if (isset($cfg['legend_font']))
					{
						$fontsize = $cfg['legend_font'][0];
						$tbold = $cfg['legend_font'][1];
						$tcol = $cfg['legend_font'][2];
						$ldx = $cfg['legend_font'][3];
						$ldy = $cfg['legend_font'][4];
					}
					else
						$ldx = -5;
				
					bo_imagestring($I, $fontsize, $px1+$coLegendWidth/2-$fontsize/2+$ldx, $py1 - 4+$ldy, $cnt, $tcol, $tbold, 90);
					$legend_text_drawn = true;
				}

			}

			if ($cfg['legend'][5])
			{
				imagesetthickness($I, 1);
				imageline($I, $cx-1, $cy-1, $cx-1, $cy+$ch, $text_col);
				imageline($I, $cx-1, $cy+$ch, $cx+$cw+2, $cy+$ch, $text_col);
			}
		}

	}
	

	if ($return_img)
	{
		return $I;
	}
	
	BoDb::close();
	
	bo_image_reduce_colors($I, false, $transparent);

	header("Content-Type: $mime");
	if ($caching)
	{
		if (BO_CACHE_SUBDIRS === true)
		{
			$dir = dirname($cache_file);
			if (!file_exists($dir))
				mkdir($dir, 0777, true);
		}

		$ok = bo_imageout($I, $extension, $cache_file);

		if (!$ok)
			bo_image_cache_error($w, $h);
		
		readfile($cache_file);
	}
	else
	{
		bo_imageout($I, $extension);
	}

	exit;
}



//get gif animation
function bo_get_map_image_ani()
{	
	global $_BO;
	include 'gifencoder/GIFEncoder.class.php';

	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);	
	$dir = BO_DIR.'cache/maps/';
	$id = intval($_GET['animation']);
	$cfg = $_BO['mapimg'][$id];
	
	if (!is_array($cfg) || empty($cfg))
		return;

	if (!$cfg['gif_animation_enable'])
		bo_image_error('Animation disabled!');
	
	if (BO_FORCE_MAP_LANG === true)
		bo_load_locale(BO_LOCALE);

	session_write_close();
	@set_time_limit(20);
		
	$cfg_ani = $cfg['gif_animation'];
	$cache_file = $dir._BL().'_ani_'.$id.'.gif';	
	$last_update = bo_get_conf('uptime_strikes_modified');

	//Caching
	if ($caching && file_exists($cache_file) && filemtime($cache_file) >= $last_update)
	{
		header("Content-Type: image/gif");
		readfile($cache_file);
		exit;
	}

	$time_start = $last_update - $cfg_ani['minutes'] * 60;
	$cfg_single = $cfg;
	
	if (!$cfg_ani['range'])
		$cfg_ani['range'] = $cfg_ani['minutes'] / $cfg_ani['count'];
	
	$cfg_single['trange'] = $cfg_ani['range'] / 60;
	
	if (isset($cfg_ani['colors']))
		$cfg_single['col'] = $cfg_ani['colors'];

	if (isset($cfg_ani['legend']))
		$cfg_single['legend'] = $cfg_ani['legend'];
		
	$frames = array();
	$framed = array();
	
	for ($i=1;$i<=$cfg_ani['count'];$i++)
	{
		$cfg_single['tstart'] = $time_start + $cfg_ani['minutes'] * 60 * $i / $cfg_ani['count'];
		$file = $dir._BL().'_gifani_'.$id.'_'.$i.'.gif';
		
		$I = bo_get_map_image($id, $cfg_single, true);
		imagegif($I, $file);
		
		$framed[] = $i == $cfg_ani['count'] ? $cfg_ani['delay_end'] : $cfg_ani['delay'];
		$frames[] = $file;
	}
	
	$loops = 0;
	$disposal = 2;

	
	$gif = new GIFEncoder($frames, $framed, $loops, $disposal, 0, 0, 0, "url"); 
	

	header('Content-type: image/gif'); 
	if ($caching)
	{
		file_put_contents($cache_file, $gif->GetAnimation());
		readfile($cache_file);
	}
	else
	{
		echo $gif->GetAnimation(); 
	}
	
	return;
}



//render a map with strike positions and strike-bar-plot
function bo_get_density_image()
{
	$densities_enabled = defined('BO_CALC_DENSITIES') && BO_CALC_DENSITIES
							&& ((defined('BO_ENABLE_DENSITIES') && BO_ENABLE_DENSITIES) || (bo_user_get_level() & BO_PERM_ARCHIVE))
							&& BO_DISABLE_ARCHIVE !== true;

	if (!$densities_enabled)
		bo_image_error('Forbidden');

	if (intval(BO_CACHE_PURGE_DENS_RAND) > 0 && rand(0, BO_CACHE_PURGE_DENS_RAND) == 1)
	{
		if (BO_CACHE_SUBDIRS === true)
			register_shutdown_function('bo_delete_files', BO_DIR.'cache/densitymap', intval(BO_CACHE_PURGE_DENS_HOURS), 3);
		else
			register_shutdown_function('bo_delete_files', BO_DIR.'cache', intval(BO_CACHE_PURGE_DENS_HOURS), 0);
	}
	
	if (BO_FORCE_MAP_LANG === true)
		bo_load_locale(BO_LOCALE);

	
	$year = intval($_GET['bo_year']);
	$month = intval($_GET['bo_month']);
	$map_id = intval($_GET['map']);
	$station_id = intval($_GET['id']);
	$ratio = isset($_GET['ratio']) && $station_id;
	
	
	@set_time_limit(30);
	session_write_close();
	
	
	global $_BO;

	//Image settings
	$cfg = $_BO['mapimg'][$map_id];
	if (!is_array($cfg) || !$cfg['density'])
		bo_image_error('Missing image data!');

	$min_block_size = max($cfg['density_blocksize'], intval($_GET['bo_blocksize']), 1);	
	
	
	//Caching
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	$cache_file = BO_DIR.'cache/';
	
	if (BO_CACHE_SUBDIRS === true)
		$cache_file .= 'densitymap/'.$map_id.'/';
	else
		$cache_file .= 'densitymap_'.$map_id.'_';
		
	$cache_file .= _BL().'_'.sprintf('station%d_%d_b%d_%04d%02d', $station_id, $ratio ? 1 : 0, $min_block_size, $year, $month);

	
	//file format
	$file = $cfg['file'];
	$extension = strtolower(substr($file, strrpos($file, '.')+1));
	 
	if ($extension == 'jpg' || $extension == 'jpeg')
	{
		$cache_file .= '.jpg';
		$mime = "image/jpeg";
	}
	elseif ($extension == 'gif')
	{
		$cache_file .= '.gif';
		$mime = "image/gif";
	}
	else // PNG is default
	{
		$cache_file .= '.png';
		$mime = "image/png";
		$extension = "png";
	}
	
	
	//todo: needs adjustments
	$last_update = strtotime('today +  4 hours');
	$expire      = strtotime('today + 28 hours');
	
	
	//Headers
	header("Pragma: ");
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_update)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $expire)." GMT");
	header("Cache-Control: public, max-age=".($expire - time()));
	header("Content-Disposition: inline; filename=\"MyBlitzortungDensity.".$extension."\"");

	
	//Cache - First cache try
	if ($caching && file_exists($cache_file) && filemtime($cache_file) >= $last_update)
	{
		header("Content-Type: $mime");
		readfile($cache_file);
		exit;
	}
	
	
	//Image: Size, colors	
	$PicLatN = $cfg['coord'][0];
	$PicLonE = $cfg['coord'][1];
	$PicLatS = $cfg['coord'][2];
	$PicLonW = $cfg['coord'][3];
	$colors = is_array($cfg['density_colors']) ? $cfg['density_colors'] : $_BO['tpl_density_colors'];

	$tmpImage = bo_imagecreatefromfile(BO_DIR.'images/'.$file);
	$w = imagesx($tmpImage);
	$h = imagesy($tmpImage);

	
	//Legend
	$LegendWidth = 150;
	$ColorBarWidth  = 10;
	$ColorBarHeight = $h - 70;
	$ColorBarX = $w + 10;
	$ColorBarY = 50;
	$ColorBarStep = 15;
	
	
	//create new trucolor image (always needed because of legend!)
	$I = imagecreatetruecolor($w+$LegendWidth, $h);
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

	
	//Legend
	$color = imagecolorallocatealpha($I, 100, 100, 100, 0);
	imagefilledrectangle($I, $w, 0, $w+$LegendWidth, $h, $color);
	
	list($x1, $y1) = bo_latlon2projection($cfg['proj'], $PicLatS, $PicLonW);
	list($x2, $y2) = bo_latlon2projection($cfg['proj'], $PicLatN, $PicLonE);
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
					date_start, date_end, status,
					UNIX_TIMESTAMP(changed) changed
					FROM ".BO_DB_PREF."densities 
					WHERE 1 
						AND status >= 1 
						AND date_start = '$date_start'
						AND date_end   <= '$date_end'
						AND 
							(station_id = $station_id
							".($ratio ? " OR station_id=0 " : "")."
							)
						AND lat_max >= '$PicLatN'
						AND lon_max >= '$PicLonE'
						AND lat_min <= '$PicLatS'
						AND lon_min <= '$PicLonW'
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
	$type = $row['type'];
	$max_real_count = $info['max']; //max strike count for area elements
	
	//dates 
	if ($info['date_start_real'])
		$date_start = $row['date_start_real'];
	else
		$date_start = $row['date_start'];
	
	$date_end = $row['date_end'];
	$time_string = date(_BL('_date'), strtotime($row['date_start'])).' - '.date(_BL('_date'), strtotime($row['date_end']));
	$last_changed = $row['changed'];
	
	//coordinates
	$DensLat       = $row['lat_min'];
	$DensLon       = $row['lon_min'];
	$DensLat_end   = $row['lat_max'];
	$DensLon_end   = $row['lon_max'];
	$length    = $row['length'];
	$area      = pow($length, 2);
	$distance = $length * sqrt(2) * 1000;
	
	//SECOND DATABASE CALL
	if ($ratio)
	{
		$row_own = $res->fetch_assoc();
		if ($station_id != $row_own['station_id'] || $date_end != $row_own['date_end'] || $type != $row_own['type'])
		{
			$exit_msg = _BL('Not enough data available!', true);	
		}
		else
		{
			$OWN_DATA = gzinflate($row_own['data']);
			$info = unserialize($row_own['info']);
			$max_real_own_count = $info['max']; //max strike count
			$last_changed = max($row['changed'], $last_changed);
		}
	}
	
	unset($row['data']);
	unset($row_own['data']);

	if ($station_id)
	{
		$stinfo = bo_station_info($station_id);
		list($px, $py) = bo_latlon2projection($cfg['proj'], $stinfo['lat'], $stinfo['lon']);
		$StX =      ($px - $x1) * $w_x;
		$StY = $h - ($py - $y1) * $h_y;
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
	
	
	//Cache - Second cache try
	if ($caching && file_exists($cache_file) && filemtime($cache_file) >= $last_changed)
	{
		header("Content-Type: $mime");
		readfile($cache_file);
		exit;
	}

	
	
	//pointer on current part of string
	$string_pos = 0;
	
	$STRIKE_COUNT = array();
	$VAL_COUNT = array();
	$strike_count = 0;
	$strike_count_own = 0;
	$max_count_block = 0;
	$max_count_pos = 0;
	$last_y = $h;
	
	BoDb::close();
	
	while ($DensLat < $DensLat_end)
	{
		
		//density: difference to current lat/lon
		list($dlat, $dlon) = bo_distbearing2latlong($distance, 45, $DensLat, $DensLon);
		$dlat -= $DensLat;
		$dlon -= $DensLon;
		
		// check if latitude lies in picture
		if ($DensLat + $dlat >= $PicLatS)
		{
			//select correct data segment from data string
			$lon_start_pos  = floor(($PicLonW-$DensLon)/$dlon) * 2 * $bps;
			$lon_string_len = floor(($PicLonE-$DensLon)/$dlon) * 2 * $bps - $lon_start_pos;
		
			$lon_data = substr($DATA, $string_pos + $lon_start_pos, $lon_string_len);
			
			//image coordinates (left side of image, height is current latitude)
			list($px, $py) = bo_latlon2projection($cfg['proj'], $DensLat, $PicLonE);
			$y  = $h - ($py - $y1) * $h_y; //image y
			$ay = round(($y / $min_block_size)); //block number y
			$dx = $dlon / ($PicLonE - $PicLonW) * $w; //delta x
			
			if ($ratio)
			{
				$lon_data_own = substr($OWN_DATA, $string_pos + $lon_start_pos, $lon_string_len);
			}
			
			//get the data!
			for($j=0; $j<$lon_string_len/2/$bps; $j++)
			{
				//image x
				$x = $j * $dx;
				
				//x coordinates to picture "block-numbers"
				$ax = round(($x / $min_block_size));
				$pos_id = $ax+$ay*$w;

				//number of calculated values in block
				if (!$ratio)
				{
					//sum up for density, because strike count is an absolute value and we need the mean value of a block
					$VAL_COUNT[$pos_id]++;
				}

				//strikes per square kilometer
				$value = hexdec(substr($lon_data, $j * 2 * $bps, 2 * $bps));
				
				if (!intval($value))
					continue;

				$strike_count += $value;
					
				if ($ratio)
				{
					//sum up here, so $value == 0 doesn't affect the calculation (ratio is a relative value)
					$VAL_COUNT[$pos_id]++;
					$own_value = hexdec(substr($lon_data_own, $j * 2 * $bps, 2 * $bps));
					$strike_count_own += $own_value;
					$value = $own_value / $value;
				}

				//Save to Data array
				$STRIKE_COUNT[$pos_id] += $value;
			}
		}

		$string_pos += (floor(($DensLon_end-$DensLon)/$dlon)+2) * 2 * $bps;
		
		// stop if picture is full
		if ($DensLat > $PicLatN)
			break;
			
		$DensLat += $dlat;
	}

	if ($ratio)
	{
		$max_count_block = 1; //always 100%
	}
	else
	{
		//find max strikes per block
		foreach($STRIKE_COUNT as $pos_id => $value)
		{
			if ($STRIKE_COUNT[$pos_id]/$VAL_COUNT[$pos_id] > $max_count_block)
			{
				$max_count_pos = $pos_id;
				$max_count_block = $STRIKE_COUNT[$pos_id]/$VAL_COUNT[$pos_id];
			}
		}
	}
	
	if ($max_count_block)
	{
		foreach($STRIKE_COUNT as $pos_id => $value)
		{
			$x = ($pos_id % $w);
			$y = ($pos_id-$x) / $w;

			//mean value of a block
			$value /= $VAL_COUNT[$pos_id];
			
			if (!$ratio)
			{
				//strike count to relative count 0 to 1 for colors
				$value /= $max_count_block;
			}
			
			$x *= $min_block_size;
			$y *= $min_block_size;
			
			list($red, $green, $blue, $alpha) = bo_value2color($value, $colors);
			$color = imagecolorallocatealpha($I, $red, $green, $blue, $alpha);
			imagefilledrectangle($I, $x, $y, $x+$min_block_size-1, $y+$min_block_size-1, $color);

		}
	}

	//Borders
	if ($cfg['borders'][0] && file_exists(BO_DIR.'images/'.$cfg['borders'][0]))
	{
		$tmpImage = bo_imagecreatefromfile(BO_DIR.'images/'.$cfg['borders'][0]);
		if ($tmpImage)
			imagecopymerge($I, $tmpImage, 0,0, 0,0, $w, $h, $cfg['borders'][1]);
	}
	
	//add cities
	bo_add_cities2image($I, $cfg, $w, $h);

	//Antennas
	if ($ratio && $station_id == bo_station_id() && isset($info['antennas']) && is_array($info['antennas']['bearing']))
	{
		$col1 = imagecolorallocatealpha($I, 255, 255, 255, 127);
		$col2 = imagecolorallocatealpha($I, 255, 255, 255, 30);
		$style = array($col1, $col1, $col1, $col1, $col2, $col2, $col2, $col2);
		imagesetstyle($I, $style);
		
		$size = 0.3 * ($w + $h) / 2;
		
		foreach($info['antennas']['bearing'] as $bear)
		{
			list($lat, $lon) = bo_distbearing2latlong(100000, $bear, $stinfo['lat'], $stinfo['lon']);
			list($px, $py) = bo_latlon2projection($cfg['proj'], $lat, $lon);
			$ant_x =      ($px - $x1) * $w_x - $StX;
			$ant_y = $h - ($py - $y1) * $h_y - $StY;
			
			$ant_xn = $ant_x / sqrt(pow($ant_x,2) + pow($ant_y,2)) * $size;
			$ant_yn = $ant_y / sqrt(pow($ant_x,2) + pow($ant_y,2)) * $size;
			
			imageline($I, $StX, $StY, $StX + $ant_xn *  1, $StY + $ant_yn *  1, IMG_COLOR_STYLED);
			imageline($I, $StX, $StY, $StX + $ant_xn * -1, $StY + $ant_yn * -1, IMG_COLOR_STYLED);
		}
	}

	
	//Legend (again!)
	$color = imagecolorallocatealpha($I, 100, 100, 100, 0);
	imagefilledrectangle($I, $w, 0, $w+$LegendWidth, $h, $color);

	//Legend: Text
	$PosX = $w + 5;
	$PosY = 10;
	$MarginX = 8;

	$size_title = 8;
	$size_text = 9;
	$size_title_legend = 12;
	
	//Station name
	if ($station_id)
	{
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Station', true).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, $stinfo['city'], $text_col, $LegendWidth, true);
		$PosY += 10;
	}
	
	//Strike count
	$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Strikes', true).':', $text_col, $LegendWidth);
	$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, $strike_count, $text_col, $LegendWidth, true);
	$PosY += 10;
	
	
	if ($ratio && intval($strike_count))
	{
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, strtr(_BL('densities_strikes_station', true), array('{STATION_CITY}' => $stinfo['city'])).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, $strike_count_own, $text_col, $LegendWidth, true);
		$PosY += 10;
	
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Mean strike ratio', true).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, number_format($strike_count_own / $strike_count * 100, 1, _BL('.'), _BL(',')).'%', $text_col, $LegendWidth, true);
		$PosY += 25;
	}
	else
		$PosY += 15;
	
	/*
	//Area elements (calculation)
	$length_text = number_format($length, 1, _BL('.'), _BL(','));
	$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL("Calculation basis are elements with area", true).':', $text_col, $LegendWidth);
	$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, " ".$length_text.'km x '.$length_text.'km', $text_col, $LegendWidth);
	$PosY += 10;

	if (!$ratio && $area)
	{
		$max_real_density = $max_real_count / $area;
		
		//Strike density
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Maximum strike density calculated', true).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, " ".number_format($max_real_density, 1, _BL('.'), _BL(',')).'/km^2', $text_col, $LegendWidth);
		$PosY += 10;
	}
	
	$PosY += 15;
	*/
	
	if (!$ratio)
	{
		//Max. density per block
		$max_density = $max_count_block / $area;
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Maximum mean strike density displayed', true).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, number_format($max_density, 3, _BL('.'), _BL(',')).'/km²', $text_col, $LegendWidth, true);
		$PosY += 15;
	}
	
	$PosY += 10;
	$PosY = bo_imagestring_max($I, $size_title_legend, $PosX, $PosY, _BL('Legend', true), $text_col, $LegendWidth);
	if ($ratio)
		$PosY = bo_imagestring_max($I, $size_title, $PosX+$MarginX, $PosY, '('._BL('Strike ratio', true).')', $text_col, $LegendWidth);
	else
		$PosY = bo_imagestring_max($I, $size_title, $PosX+$MarginX, $PosY, '('._BL('Strikes per square kilometer', true).')', $text_col, $LegendWidth);
	
	if ($PosY + 15 > $ColorBarY)
	{
		$ColorBarHeight -= $PosY+15 - $ColorBarY;
		$ColorBarY = $PosY+15;
	}
	
	//Legend: Colorbar
	for ($i=$ColorBarY; $i<= $ColorBarHeight+$ColorBarY; $i += $ColorBarStep)
	{
		$value = 1-($i-$ColorBarY)/$ColorBarHeight;
		
		list($red, $green, $blue, $alpha) = bo_value2color($value, $colors);
		$color = imagecolorallocatealpha($I, $red, $green, $blue, $alpha);	
		imagefilledrectangle($I, $ColorBarX, $i, $ColorBarX+$ColorBarWidth, $i+$ColorBarStep-1, $color);
	}
	
	//Legend: Colorbar Text
	if ($ratio)
	{
		$max_ratio = $max_count_block;
		$text_top = number_format($max_ratio*100, 1, _BL('.'), _BL(',')).'%';
		$text_middle = '50%';
		$text_bottom = '0%';
	}
	else
	{
		$max_density = $max_count_block / $area;
		$text_top = number_format($max_density, 3, _BL('.'), _BL(','));
		$text_middle = number_format($max_density/2, 3, _BL('.'), _BL(','));
		$text_bottom = '0';
	}
	
	imagestring($I, 3, $ColorBarX+$ColorBarWidth+6, $ColorBarY+3, $text_top, $text_col);
	imagestring($I, 3, $ColorBarX+$ColorBarWidth+6, $ColorBarY-8+$ColorBarHeight/2, $text_middle, $text_col);
	imagestring($I, 3, $ColorBarX+$ColorBarWidth+6, $ColorBarY-5+$ColorBarHeight, $text_bottom, $text_col);
	
	
	//Station Name
	$extra_text = '';
	if ($station_id)
	{
		imagestring($I, $fontsize, 1, 1 + $fontsize * 3, $text, $text_col);
		
		$size = 6;
		$color = imagecolorallocate($I, 255,255,255);
		imageline($I, $StX-$size, $StY, $StX+$size, $StY, $color);
		imageline($I, $StX, $StY-$size, $StX, $StY+$size, $color);
		
	}

	//Banner
	$extra_text = _BL($ratio ? 'Strike ratio' : 'Strike density', true);

	bo_image_banner_top($I, $w, $h, $cfg, $time_string, $extra_text);
	bo_image_banner_bottom($I, $w, $h, $cfg, 0);
	
	


	bo_image_reduce_colors($I, true);

	header("Content-Type: $mime");
	if ($caching)
	{
		if (BO_CACHE_SUBDIRS === true)
		{
			$dir = dirname($cache_file);
			if (!file_exists($dir))
				mkdir($dir, 0777, true);
		}

		$ok = @bo_imageout($I, $extension, $cache_file);
		
		if (!$ok)
			bo_image_cache_error($w, $h);

		readfile($cache_file);
	}
	else
		bo_imageout($I, $extension);

	exit;
	
}


function bo_image_banner_top($I, $w, $h, $cfg, $time_string = null, $extra = null, $copy = true)
{
	//default color
	$text_col = imagecolorallocate($I, $cfg['textcolor'][0], $cfg['textcolor'][1], $cfg['textcolor'][2]);

	$tdy = 0;
	if (isset($cfg['top_style']))
	{
		imagefilledrectangle($I, 0,0, $w-1, $cfg['top_style'][0], bo_hex2color($I, $cfg['top_style'][2]));
		$tdy = $cfg['top_style'][1];
		
		if ($cfg['top_style'][3])
		{
			imagesetthickness($I, $cfg['top_style'][3]);
			imageline($I, 0,$cfg['top_style'][0], $w,$cfg['top_style'][0], bo_hex2color($I, $cfg['top_style'][4]));
		}
	}
	
	if (isset($cfg['top_font']))
	{
		$fontsize = $cfg['top_font'][0];
		$tbold = $cfg['top_font'][1];
		$tcol = $cfg['top_font'][2];
	}
	else //for old template style
	{
		$fontsize = $cfg['textsize'] ? $cfg['textsize'] : $w / 80;
		$tbold = true;
		$tcol = $text_col;
	}
	
	//Date/Time/Strikes
	if ($time_string !== null)
		bo_imagestring($I, $fontsize, 2, 2+$tdy, $time_string, $tcol, $tbold);

	//Strikes
	if ($extra !== null)
		bo_imagestringright($I, $fontsize, $w - 2, 2+$tdy, $extra, $tcol, $tbold);
	
	//Own Copyright
	if (defined('BO_OWN_COPYRIGHT') && $copy)
	{
		$copy_width = bo_imagetextwidth($fontsize, $tbold, BO_OWN_COPYRIGHT);
		$info_text_width = bo_imagetextwidth($fontsize, $tbold, $time_string.'         '.$strike_text);
		
		if ($w - $info_text_width > $copy_width)
		{
			$copy_pos = $w / 2 - $copy_width / 2;
			bo_imagestring($I, $fontsize, $copy_pos, 2+$tdy, BO_OWN_COPYRIGHT, $tcol, $tbold);
		}
	}
}


function bo_image_banner_bottom($I, $w, $h, $cfg, $legend_width = 0, $copy = false)
{
	//default color
	$text_col = imagecolorallocate($I, $cfg['textcolor'][0], $cfg['textcolor'][1], $cfg['textcolor'][2]);

	$tdy = 0;
	
	if (isset($cfg['top_font']))
	{
		$fontsize = $cfg['top_font'][0];
		$tbold = $cfg['top_font'][1];
		$tcol = $cfg['top_font'][2];
	}
	else //for old template style
	{
		$fontsize = $cfg['textsize'] ? $cfg['textsize'] : $w / 80;
		$tbold = true;
		$tcol = $text_col;
	}

	if (isset($cfg['bottom_font']))
	{
		$fontsize = $cfg['bottom_font'][0];
		$tbold = $cfg['bottom_font'][1];
		$tcol = $cfg['bottom_font'][2];
	}
	

	/* BOTTOM LINE */
	if (isset($cfg['bottom_style']))
	{
		imagefilledrectangle($I, 0,$h, $w, $h-$cfg['bottom_style'][0], bo_hex2color($I, $cfg['bottom_style'][2]));
		$tdy = $cfg['bottom_style'][1];
		
		if ($cfg['bottom_style'][3])
		{
			imagesetthickness($I, $cfg['bottom_style'][3]);
			imageline($I, 0,$h-$cfg['bottom_style'][0], $w,$h-$cfg['bottom_style'][0], bo_hex2color($I, $cfg['bottom_style'][4]));
		}
	}
	

	$tdy = bo_imagetextheight($fontsize);		
	
	//Copyright
	$text = _BL('Lightning data from Blitzortung.org', true);
	$bo_width = bo_imagetextwidth($fontsize, $tbold, $text);
	if ($bo_width > $w - $legend_width - 5)
		$text = _BL('Blitzortung.org', true);
	
	if ($cfg['image_footer'])
		$text .= ' '.$cfg['image_footer'];
	
	bo_imagestring($I, $fontsize, 4, $h - $tdy, $text, $tcol, $tbold);

	//Own copyright
	if (defined('BO_OWN_COPYRIGHT') && $copy)
	{
		$bo_width2 = bo_imagetextwidth($fontsize, $tbold, BO_OWN_COPYRIGHT);
		$bo_pos2 = $bo_width + $fontsize * 5;
		
		if ($bo_width2+$bo_pos2 < $w - $legend_width - 5)
			bo_imagestring($I, $fontsize, $bo_pos2, $h - $tdy, BO_OWN_COPYRIGHT, $tcol, $tbold);
	}
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

//get an image from /images directory
//we need this for easy integration of MyBlitzortung in other projects
function bo_get_image($img)
{
	switch($img)
	{
		case 'bt':
			$file = 'blank_tile.png';
			break;

		case 'logo':
			$file = 'blitzortung_logo.jpg';
			break;

		case 'wait':
			$file = 'wait.gif';
			break;
		
		default: //default image
		case 'my':
			$file = 'myblitzortung.png';
			break;
		
	}

	if (preg_match('/^flag_([a-zA-Z]{2})$/', $img, $r))
	{
		$file = 'flags/'.$r[1].'.png';
	}

	$ext = strtr(substr($file, -3), array('jpg' => 'jpeg'));

	$file = BO_DIR.'images/'.$file;

	if (!file_exists($file))
		exit;
	
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

// for density images: value (from 0 to 1) to color
function bo_value2color($value, &$colors)
{
	$color_count = count($colors)-1;
	
	if ($value > 1) //this shouldn't happen!
	{
		$red = $green = $blue = 255;
		$alpha = 0;
	}
	else if ($value == 1)
	{
		$red   = $colors[$color_count][0];
		$green = $colors[$color_count][1];
		$blue  = $colors[$color_count][2];
		$alpha = $colors[$color_count][3];
	}
	else
	{
		$color_index = floor($value * ($color_count));
		$color_pos   = $value * ($color_count) - floor($value * ($color_count)); //find "position" between the two colors
		
		$col1 = $colors[$color_index];
		$col2 = $colors[$color_index+1];
		
		$red   = $col1[0] + ($col2[0] - $col1[0]) * $color_pos;
		$green = $col1[1] + ($col2[1] - $col1[1]) * $color_pos;
		$blue  = $col1[2] + ($col2[2] - $col1[2]) * $color_pos;
		$alpha = $col1[3] + ($col2[3] - $col1[3]) * $color_pos;
	}
	
	return array($red, $green, $blue, $alpha);
}


function bo_image_reduce_colors(&$I, $density_map=false, $transparent=false)
{
	if ($density_map)
		$colors = intval(BO_IMAGE_PALETTE_COLORS_DENSITIES);
	else
		$colors = intval(BO_IMAGE_PALETTE_COLORS_MAPS);
	
	
	if ($colors)
	{
		//colorstotal works only for palette images
		$total = imagecolorstotal($I);
		if ($total && $total <= 256)
			return;

		
		if ($colors)
		{
			$auto = true;
			$width = imagesx($I);
			$height = imagesy($I);

			if (BO_IMAGE_PALETTE_AUTO)
			{
				$Itmp = ImageCreateTrueColor($width, $height);
				
				if ($transparent)
				{
					$back = imagecolorallocate($Itmp, 1, 2, 3);
					imagefilledrectangle($Itmp, 0, 0, $width, $height, $back);
					imagecolortransparent($Itmp, $back);
				}

				ImageCopy($Itmp, $I, 0, 0, 0, 0, $width, $height);
			}
			
			//reduce colors: imagecolormatch doesn't exist in some PHP-GD modules (i.e. Ubuntu)
			if (function_exists('imagecolormatch'))
			{
				$colors_handle = ImageCreateTrueColor($width, $height);
				ImageCopyMerge($colors_handle, $I, 0, 0, 0, 0, $width, $height, 100 );
				ImageTrueColorToPalette($I, false, $colors);
				ImageColorMatch($colors_handle, $I);
				ImageDestroy($colors_handle);
			}
			else
			{
				imagetruecolortopalette($I, false, $colors);
			}

			if (BO_IMAGE_PALETTE_AUTO)
			{
				if (imagecolorstotal($I) == 256) //too much colors ==> back to truecolor
				{
					imagedestroy($I);
					$I = $Itmp;
				}
				else
				{
					imagedestroy($Itmp);
				}
			}

		}
	}
}


function bo_add_cities2image($I, $cfg, $w, $h)
{
	if (!isset($cfg['cities']) || !is_array($cfg['cities']))
		return;
	
	$sql_types = '';
	foreach($cfg['cities'] as $type => $data)
	{
		if (!$data['point'][0])
			continue;
		
		$sql_types .= " OR type='$type' ";
	}
	
	$latN = $cfg['coord'][0];
	$lonE = $cfg['coord'][1];
	$latS = $cfg['coord'][2];
	$lonW = $cfg['coord'][3];
	
	list($x1, $y1) = bo_latlon2projection($cfg['proj'], $latS, $lonW);
	list($x2, $y2) = bo_latlon2projection($cfg['proj'], $latN, $lonE);
	$w_x = $w / ($x2 - $x1);
	$h_y = $h / ($y2 - $y1);

	$sql = "SELECT id, name, lat, lon, type
			FROM ".BO_DB_PREF."cities
			WHERE 1
				AND NOT (lat < '$latS' OR lat > '$latN' OR lon < '$lonW' OR lon > '$lonE')
				AND (0 $sql_types)
			ORDER BY type ASC";
	$erg = bo_db($sql);
	while ($row = $erg->fetch_assoc())
	{
		list($px, $py) = bo_latlon2projection($cfg['proj'], $row['lat'], $row['lon']);
		$x =      ($px - $x1) * $w_x;
		$y = $h - ($py - $y1) * $h_y;

		$c = $cfg['cities'][$row['type']];
	
		if ($c['font'][0])
		{
			if ($c['font'][3] < 0)
				$font_x = $x - bo_imagetextwidth($c['font'][3], $c['font'][0], $c['font'][1]) + $c['font'][3];
			else
				$font_x = $x + $c['font'][3];

			$font_y = $y + $c['font'][4];
		
			bo_imagestring($I, $c['font'][0], $font_x, $font_y, $row['name'], $c['font'][2], $c['font'][1]);
		}
		
		bo_drawpoint($I, $x, $y, $c['point']);	
	
	}
	
}


function bo_add_stations2image($I, $cfg, $w, $h, $strike_id = 0)
{
	global $_BO;
	
	if (!$strike_id && (!isset($cfg['stations']) || empty($cfg['stations'])))
		return;
	
	$latN = $cfg['coord'][0];
	$lonE = $cfg['coord'][1];
	$latS = $cfg['coord'][2];
	$lonW = $cfg['coord'][3];
	
	list($x1, $y1) = bo_latlon2projection($cfg['proj'], $latS, $lonW);
	list($x2, $y2) = bo_latlon2projection($cfg['proj'], $latN, $lonE);
	$w_x = $w / ($x2 - $x1);
	$h_y = $h / ($y2 - $y1);

	$stations = bo_stations();

	if ($strike_id)
	{
	
		$sql = "SELECT lat, lon
				FROM ".BO_DB_PREF."strikes
				WHERE id='$strike_id'";
		$erg = bo_db($sql);
		$row = $erg->fetch_assoc();
		list($px, $py) = bo_latlon2projection($cfg['proj'], $row['lat'], $row['lon']);
		$strike_x =      ($px - $x1) * $w_x;
		$strike_y = $h - ($py - $y1) * $h_y;
	
		$sql = "SELECT ss.station_id id
				FROM ".BO_DB_PREF."stations_strikes ss
				WHERE ss.strike_id='$strike_id'
				";
		$erg = bo_db($sql);
		while ($row = $erg->fetch_assoc())
		{
			$stations[$row['id']]['part'] = 1;
		}
		
		$tmp = $cfg['stations'][0];
		unset($cfg['stations']);
		if (0 && !is_array($tmp))
			$cfg['stations'][0] = $tmp;
		else
			$cfg['stations'][0] = $_BO['points'][BO_ARCHIVE_STR_DETAILS_DEFAULT_POINT];
	}
	
	foreach($stations as $id => $d)
	{
		$type = $d['status'];
		$lon = $d['lon'];
		$lat = $d['lat'];
		
		if ( !isset($cfg['stations'][$type]) && !isset($cfg['stations'][0]) )
			continue;
		
		if (!$strike_id && ($lat > $latN || $lat < $latS || $lon > $lonE || $lon < $lonW))
			continue;
		
		if (isset($cfg['stations'][$type]))
			$c = $cfg['stations'][$type];
		else
			$c = $cfg['stations'][0];
		
		list($px, $py) = bo_latlon2projection($cfg['proj'], round($d['lat'],2), round($d['lon'],2));
		$x =      ($px - $x1) * $w_x;
		$y = $h - ($py - $y1) * $h_y;

		if ($c['font'][0])
		{
			if ($c['font'][3] < 0)
				$font_x = $x - bo_imagetextwidth($c['font'][3], $c['font'][0], $c['font'][1]) + $c['font'][3];
			else
				$font_x = $x + $c['font'][3];

			$font_y = $y + $c['font'][4];
		
			bo_imagestring($I, $c['font'][0], $font_x, $font_y, $d['city'], $c['font'][2], $c['font'][1]);
		}
		
		bo_drawpoint($I, $x, $y, $c['point']);
		
		if ($strike_id && $d['part'])
		{
			imageline($I, $strike_x, $strike_y, $x, $y, bo_hex2color($I, BO_ARCHIVE_STR_DETAILS_LINECOLOR));
		}
	
	}
	
}


function bo_drawpoint($I, $x, $y, &$style, $color = null, $use_alpha = true, $strikedata = null)
{
	if ($color == null && $style[2]) //fillcolor
		$color = bo_hex2color($I, $style[2], $use_alpha);

	$bordercolor = null;
		
	if ($style[3]) 
	{
		$bordercolor = bo_hex2color($I, $style[4], $use_alpha);
		imagesetthickness($I, $style[3]);
	}

	$s = $style[1]; //size
		
		
	switch ($style[0])
	{
		case 1: //Circle
			
			if ($s == 1)
			{
				imagesetpixel($I, $x, $y, $color);
			}
			else if ($s == 2)
			{
				imagerectangle($I, $x, $y, $x+1, $y+1, $color);
			}
			else
			{
				imagefilledellipse($I, $x, $y, $s, $s, $color);
			}
			
			if ($bordercolor !== null)
				imageellipse($I, $x, $y, $s+1, $s+1, $bordercolor);
				
			break;
		
		
		case 2: //Plus
		
			$s /= 2;
			$x = (int)$x;
			$y = (int)$y;
			
			if ($bordercolor !== null)
			{
				imagesetthickness($I, $style[3]+2);
				imageline($I, $x-$s-1, $y, $x+$s+1, $y, $bordercolor);
				imageline($I, $x, $y-$s-1, $x, $y+$s+1, $bordercolor);
			}
			
			if ($style[3])
				imagesetthickness($I, $style[3]);
				
			imageline($I, $x-$s, $y, $x+$s, $y, $color);
			imageline($I, $x, $y-$s, $x, $y+$s, $color);
			
	
			break;
		
		
		case 3: // Square
		
			$s /= 2;
			
			if ($style[2])
				imagefilledrectangle($I, $x-$s, $y-$s, $x+$s, $y+$s, $color);
			
			if ($bordercolor !== null)
				imagerectangle($I, $x-$s-1, $y-$s-1, $x+$s+1, $y+$s+1, $bordercolor);
				
			break;
		
		case 10: // Station sign *g*
		
			imageline($I, $x-$s*0.6, $y+$s*0.9, $x+$s*0.6, $y+$s*0.9, $color);
			imageline($I, $x, $y-$s, $x, $y+$s*0.9, $color);
			
			imagefilledellipse($I, $x, $y-$s, $s-1, $s-1, $color);
			
			imagearc($I, $x-$s, $y-$s, $s*4, $s*3, -30, +30, $bordercolor);
			imagearc($I, $x+$s, $y-$s, $s*4, $s*3, -30+180, +30+180, $bordercolor);
			
			break;


			
		case 20: // Strike sign
		
			$points = array(
					$x-$s*0.3, $y+$s*0.1, 
					$x-$s*0.1, $y+$s*0.1,
					$x-$s*0.3, $y+$s,
					$x+$s*0.4, $y-$s*0.1, 
					$x+$s*0.1, $y-$s*0.1,
					$x+$s*0.7, $y-$s, 
					$x+$s*0.1, $y-$s,
					$x-$s*0.3, $y+$s*0.1);

			if ($style[2])					
				imagefilledpolygon($I, $points, count($points)/2, $color);
			
			if ($bordercolor !== null)
				imagepolygon($I, $points, count($points)/2, $bordercolor);
			
			
			break;
			
		
		default:
		
			if (function_exists($style[0]))
				call_user_func($style[0], $I, $x, $y, $color, $style, $strikedata);
				
			break;
			
	}

}


function bo_imagecreatefromfile($file)
{
	$extension = strtolower(substr($file, strrpos($file, '.')+1));
	
	if ($extension == 'jpg' || $extension == 'jpeg')
		$I = imagecreatefromjpeg($file);
	elseif ($extension == 'gif')
		$I = imagecreatefromgif($file);
	else // PNG is default
		$I = imagecreatefrompng($file);
	
	if ($I === false)
		bo_image_error("Couldn't open image file!");
	
	return $I;
}

function bo_imageout($I, $extension = 'png', $file = null, $quality = BO_IMAGE_JPEG_QUALITY)
{
	$extension = strtr($extension, array('.' => ''));
	
	if ($extension == 'png')
		$ret = imagepng($I, $file, BO_IMAGE_PNG_COMPRESSION, BO_IMAGE_PNG_FILTERS);
	else if ($extension == 'gif')
		$ret = imagegif($I, $file);
	else if ($extension == 'jpeg')
		$ret = imagejpeg($I, $file, $quality);
	else if (imageistruecolor($I) === false)
		$ret = imagepng($I, $file, BO_IMAGE_PNG_COMPRESSION, BO_IMAGE_PNG_FILTERS);
	else
		$ret = imagejpeg($I, $file, $quality);
		
	return $ret;
}

?>