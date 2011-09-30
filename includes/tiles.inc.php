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

define("BO_WAIT_SIM_TILE_CREATION", false); //waits when another tile is created (for testing)

function bo_tile()
{
	@set_time_limit(5);

	global $_BO;

	bo_session_close();
	
	if (intval(BO_CACHE_PURGE_TILES_RAND) > 0 && rand(0, BO_CACHE_PURGE_TILES_RAND) == 1)
	{
		register_shutdown_function('bo_delete_files', BO_DIR.'cache/tiles/', BO_CACHE_PURGE_TILES_HOURS, 5);
	}
	
	$x = intval($_GET['x']);
	$y = intval($_GET['y']);
	$zoom = intval($_GET['zoom']);
	$only_own = intval($_GET['own']);
	$only_info = isset($_GET['info']);
	$type = intval($_GET['type']);
	
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	$time = time();

	$cfg = $_BO['mapcfg'][$type];
	
	//manual time select
	if (isset($_GET['from']) && isset($_GET['to']))
	{
		if (!$only_info && BO_MAP_MANUAL_TIME_ENABLE !== true && !(bo_user_get_level() & BO_PERM_NOLIMIT))
		{
			bo_tile_message('tile_time_range_na_err', 'range_na', $caching);
			exit;
		}
		
		$time_manual_from = strtotime($_GET['from']);
		$time_manual_to = strtotime($_GET['to']);

		// set "current" time
		$time = $time_manual_to;
		
		// add config
		$cfg['trange'] = ($time_manual_to - $time_manual_from - 59) / 60; 
		$cfg['upd_intv'] = 1;
		$cfg['tstart'] = ($time_manual_to - $time_manual_from) / 60;

		if (!$only_info && $time_manual_to < $time_manual_from)
		{
			bo_tile_message('tile_wrong_time_range_err', 'range_wrong', $caching);
			exit;
		}
		else if (!$only_info && intval(BO_MAP_MANUAL_TIME_MAX_HOURS) < $cfg['trange']/60 && bo_user_get_id() != 1)
		{
			bo_tile_message('tile_maximum_time_range_err', 'range_max', $caching, array('{HOURS}' => intval(BO_MAP_MANUAL_TIME_MAX_HOURS)));
			exit;
		}
		
		//disable caching
		$caching = false;
	}
	else
	{
		$time = bo_get_conf('uptime_strikes_modified');
		$time_manual_from = false;
	}

	
	//get config
	if (isset($_GET['count'])) // display strike count
	{
		$type = 0;
		$time_range = 0;
		$time_start = 0;
		$update_interval = 0;
		
		$time_min = array();
		$time_max = array();
		$update_interval = array();
		
		if ($time_manual_from)
			$count_types[0] = -1;
		else
			$count_types = explode(',',$_GET['count']);
			
		foreach($count_types as $i)
		{
			$type += pow(2, $i);
			
			if (!$time_manual_from)
				$cfg = $_BO['mapcfg'][$i];
			
			if (!is_array($cfg) || !$cfg['upd_intv'])
				continue;
			
			$time_start = $time - 60 * $cfg['tstart'];
			
			$update_intervals[$i] = $cfg['upd_intv'];
			$times_min[$i]        = mktime(date('H', $time_start), ceil(date('i', $time_start) / $cfg['upd_intv']) * $cfg['upd_intv'], 0, date('m', $time_start), date('d', $time_start), date('Y', $time_start));
			$times_max[$i]        = $times_min[$i] + 60 * $cfg['trange'] + 59;
		}
		
		$update_interval = count($update_intervals) ? min($update_intervals) : 0;
		$time_min        = count($times_min) ? min($times_min) : 0;
		$time_max        = count($times_max) ? max($times_max) : 0;
		
		if ($_GET['stat'] == 2)
			$type = 'count_stat_'.$type;
		else
			$type = 'count_'.$type;
	}
	else //normal strike display
	{
		$time_start = $time - 60 * $cfg['tstart'];
		$time_range = $cfg['trange'];
		$update_interval = $cfg['upd_intv'];
		$c = $cfg['col'];
		$time_min   = mktime(date('H', $time_start), ceil(date('i', $time_start) / $update_interval) * $update_interval, 0, date('m', $time_start), date('d', $time_start), date('Y', $time_start));
		$time_max   = $time_min + 60 * $time_range + 59;
	}
	
	if (!$time_start || !$time_min || !$time_max)
		bo_tile_output();

	//calculate some time information
	$cur_minute = intval(intval(date('i')) / $update_interval);
	$mod_time   = mktime(date('H'), $cur_minute * $update_interval , 0);
	$exp_time   = $mod_time + 60 * $update_interval + 59;
	$age        = $exp_time - time();

	
	//Headers
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $mod_time)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $exp_time)." GMT");
	header("Content-Disposition: inline; filename=\"MyBlitzortungTile.png\"");
	
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

	//send only the info/color-legend image (colors, time)
	if ($only_info)
	{
		bo_load_locale();
		
		$time_max = min(bo_get_conf('uptime_strikes_modified'), $time_max);
		$show_date = $time_manual_from || ($time_max-$time_min) > 3600 * 12 ? true : false;
		
		$fh = imagefontheight(BO_MAP_LEGEND_FONTSIZE);
		$w = BO_MAP_LEGEND_WIDTH;
		$h = BO_MAP_LEGEND_HEIGHT + $fh * ($show_date ? 2 : 1)+1;
		

		$I = imagecreate($w, $h);
		$col = imagecolorallocate($I, 50, 50, 50);
		imagefill($I, 0, 0, $col);

		$coLegendWidth = $w / count($c);
		foreach($c as $i => $rgb)
		{
			$color[$i] = imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);
			imagefilledrectangle($I, (count($c)-$i-1)*$coLegendWidth, 0, (count($c)-$i)*$coLegendWidth, BO_MAP_LEGEND_HEIGHT, $color[$i]);
		}

		
		$col = imagecolorallocate($I, 255,255,255);
		
		if ($show_date)
		{
			imagestring($I, BO_MAP_LEGEND_FONTSIZE, 2, BO_MAP_LEGEND_HEIGHT+1,  '  '.date(_BL('_dateshort').' H:i', $time_min), $col);
			imagestring($I, BO_MAP_LEGEND_FONTSIZE, 2, BO_MAP_LEGEND_HEIGHT+1 + $fh, '- '.date(_BL('_dateshort').' H:i', $time_max), $col);
		}
		else
			imagestring($I, BO_MAP_LEGEND_FONTSIZE, 2, BO_MAP_LEGEND_HEIGHT+1, date('H:i', $time_min).' - '.date('H:i', $time_max), $col);

		
		header("Content-Type: image/png");
		imagepng($I);
		exit;
	}


	//Caching
	$dir = BO_DIR.'cache/tiles/';
	$filename = $type.'_'.$zoom.'_'.$x.'x'.$y.'-'.$only_own.'-'.(bo_user_get_level() ? 1 : 0).'.png';
	
	if (BO_CACHE_SUBDIRS === true)
		$filename = strtr($filename, array('_' => '/'));

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

	list($lat1, $lon1, $lat2, $lon2) = bo_get_tile_dim($x, $y, $zoom);
	
	//Check if zoom or position is in limit
	$radius = $_BO['radius'] * 1000; //max. Distance
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

				$text = _BL('tile not available', true);
				bo_tile_message($text, 'na', $caching);
				exit;
			}

		}
	}

	if (BO_WAIT_SIM_TILE_CREATION)
	{
		//to avoid too much parallel sql queries
		usleep(rand(0,100) * 1000);
		$maxwait = 5000000;
		$wait_start = microtime(true);
		while( (int)($is_creating = bo_get_conf('is_creating_tile')) && microtime(true) - $wait_start < $maxwait)
		{
			usleep(rand(200,500) * 1000);
		}
		
		bo_set_conf('is_creating_tile', $is_creating+1);
	}
	
	//Display only strike count
	if (isset($_GET['count'])) 
	{
		$sql_where = '';
		foreach($count_types as $i)
		{
			$date_min = gmdate('Y-m-d H:i:s', $times_min[$i]);
			$date_max = gmdate('Y-m-d H:i:s', $times_max[$i]);
			
			$sql_where_time .= " OR s.time BETWEEN '$date_min' AND '$date_max' ";
		}
		
		$strike_count = 0;
		$whole_strike_count = 0;
		
		//the where clause
		$sql_where = bo_strikes_sqlkey($index_sql, min($times_min), max($times_max), $lat1, $lat2, $lon1, $lon2);

		
		if ($_GET['stat'] == 2)
		{
			$stations = bo_stations();
			$stations_count = array();
			
			$sql = "SELECT ss.station_id sid, COUNT(s.time) cnt 
				FROM ".BO_DB_PREF."stations_strikes ss
				JOIN ".BO_DB_PREF."strikes s $index_sql
					ON s.id=ss.strike_id
				WHERE 1
					".($radius ? "AND s.distance < $radius" : "")."
					AND (0 $sql_where_time)
					AND $sql_where
					".($only_own ? " AND part>0 " : "")."
				GROUP BY sid
				";
			$erg = bo_db($sql);
			while ($row = $erg->fetch_assoc())
			{
				$stations_count[$row['sid']] = $row['cnt'];
			}
		}
		
		$sql = "SELECT COUNT(time) cnt ".($only_own ? ", part>0 participated " : "")."
			FROM ".BO_DB_PREF."strikes s $index_sql
			WHERE 1
				".($radius ? "AND distance < $radius" : "")."
				AND (0 $sql_where_time)
				AND $sql_where
				".($only_own ? " GROUP BY participated " : "")."
			";
		$erg = bo_db($sql);
		while ($row = $erg->fetch_assoc())
		{
			if ($only_own)
			{
				if ($row['participated'])
					$strike_count += $row['cnt'];
				
				$whole_strike_count += $row['cnt'];
			}
			else
				$strike_count += $row['cnt'];
		}
		
		BoDb::close();
		bo_session_close(true);
		
		//create tile image
		$I = imagecreate(BO_TILE_SIZE, BO_TILE_SIZE);
		imagealphablending($I, true); 
		imagesavealpha($I, true);

		$blank = imagecolorallocatealpha($I, 255, 255, 255, 127);
		imagefilledrectangle($I, 0, 0, BO_TILE_SIZE, BO_TILE_SIZE, $blank);
	
	
		//border
		$col = imagecolorallocatealpha($I, 100,100,100,50);
		imagerectangle( $I, 0, 0, BO_TILE_SIZE-1, BO_TILE_SIZE-1, $col);
		
		//number
		$textsize = BO_MAP_COUNT_FONTSIZE;
		$bold = BO_MAP_COUNT_FONTBOLD;
		$twidth = bo_imagetextwidth($textsize, $bold, $strike_count);
		$theight = bo_imagetextheight($textsize, $bold, $strike_count);
		$white = imagecolorallocatealpha($I, 255,255,255,0);
		imagefilledrectangle( $I, 0, 0, $twidth+5, $theight+2, $col);
		bo_imagestring($I, $textsize, 2, 2, $strike_count, $white, $bold);
		
		if ($only_own && intval($whole_strike_count))
		{
			$ratio = round($strike_count / $whole_strike_count * 100).'%';
			$twidth = bo_imagetextwidth($textsize, false, $ratio);
			imagefilledrectangle( $I, 0, $theight+3, $twidth+6, 2*$theight+4, $col);
			bo_imagestring($I, $textsize, 2, $theight+4, $ratio, $white, $bold);
		}
		
		//Stations
		if ($_GET['stat'] == 2)
		{
			arsort($stations_count);
			$theight = bo_imagetextheight($textsize-1, $bold, $strike_count);
			$i = 0;
			foreach($stations_count as $sid => $cnt)
			{
				$i++;
				
				$text = round($cnt / $strike_count * 100).'% ';
				$text .= trim($stations[$sid]['city']);

				$twidth = bo_imagetextwidth($textsize-1, $bold, $text);
				
				imagefilledrectangle($I, 0, ($theight+3)*$i, $twidth+10, ($theight+3)*($i+1), $col);
				bo_imagestring($I, $textsize-1, 2, ($theight+3)*$i+3, $text, $white, false);
				
				if ($i >= BO_MAP_COUNT_STATIONS)
					break;
			}
		}
		
		if (BO_WAIT_SIM_TILE_CREATION)
			bo_set_conf('is_creating_tile', 0);
			
		bo_tile_output($file, $caching, $I);
		
		exit;
	}
	
	
	/****** The main part: Get the data and display the strikes ******/
	
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

	//the where clause
	$sql_where = bo_strikes_sqlkey($index_sql, $time_min, $time_max, $lat1, $lat2, $lon1, $lon2);
	
	//get the data!
	$points = array();
	$deviation = array();
	$sql = "SELECT s.id id, s.time time, s.lat lat, s.lon lon, s.deviation deviation, s.polarity polarity
			FROM ".BO_DB_PREF."strikes s
			$index_sql
			WHERE 1
				".($radius ? "AND distance < $radius" : "")."
				".($only_own ? " AND part>0 " : "")."
				AND $sql_where
			ORDER BY time ASC";
	$erg = bo_db($sql);
	
	//Max. strikes per tile
	$num = $erg->num_rows;
	$max = intval(BO_MAP_MAX_STRIKES_PER_TILE);
	
	while ($row = $erg->fetch_assoc())
	{
		//Max. strikes per tile handling
		//This random thing is quick&easy but needs no further strike calculation (position/time/color)
		//Problem: tile borders
		if ($max && $num > $max)
		{
			if (rand(0, $num) > $max)
				continue;
		}
		
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
	
	if (BO_WAIT_SIM_TILE_CREATION)
		bo_set_conf('is_creating_tile', 0);
	
	//no points --> blank tile
	if (count($points) == 0)
	{
		bo_tile_output($file, $caching);
	}


	//create Image
	$I = imagecreate(BO_TILE_SIZE, BO_TILE_SIZE);
	$blank = imagecolorallocate($I, 0, 0, 0);
	$white = imagecolorallocate($I, 255, 255, 255);
	imagefilledrectangle( $I, 0, 0, BO_TILE_SIZE, BO_TILE_SIZE, $blank);

	foreach($c as $i => $rgb)
		$color[$i] = imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);

	
	if ($zoom >= BO_MAP_STRIKE_SHOW_CIRCLE_ZOOM) //circle (grows with zoom)
	{
		$s = floor((BO_MAP_STRIKE_CIRCLE_SIZE+BO_MAP_STRIKE_CIRCLE_GROW*$zoom)/2)*2-1;
	}
	else if ($zoom >= BO_EXPERIMENTAL_POLARITY_ZOOM && BO_EXPERIMENTAL_POLARITY_CHECK === true)
	{
		$s = BO_MAP_STRIKE_POLARITY_SIZE;
		$style = 2; //with polarity
	}
	else
	{
		$s = BO_MAP_STRIKE_SIZE;
		$style = 1;
	}

	foreach($points as $i => $p)
	{
		switch($style)
		{
			case 1: // plot a "+"
				
				imagesetthickness($I, 2);
				imageline($I, $p[0]-$s, $p[1], $p[0]+$s-1, $p[1], $color[$p[2]]);
				imageline($I, $p[0], $p[1]-$s, $p[0], $p[1]+$s-1, $color[$p[2]]);
				break;

			case 2:
				if (!$p[3]) //plot circle (no polarity known)
				{
					imagesetthickness($I, 1);
					imagefilledellipse($I, $p[0], $p[1], $s, $s, $color[$p[2]]);
				}
				else //plot "+" or "-"
				{
					$t = $s - 2;
					imagesetthickness($I, 2);
					imageline($I, $p[0]-$t, $p[1], $p[0]+$t-1, $p[1], $color[$p[2]]);
					if ($p[3] > 0)
						imageline($I, $p[0], $p[1]-$t, $p[0], $p[1]+$t-1, $color[$p[2]]);
				}

				break;

			default: // plot circle
				imagesetthickness($I, 1);
				imagefilledellipse($I, $p[0], $p[1], $s, $s, $color[$p[2]]);

				if ($p[3] && BO_EXPERIMENTAL_POLARITY_CHECK == true)
				{
					$t = intval($s / 2);
					imageline($I, $p[0]-$t+1, $p[1], $p[0]+$t-1, $p[1], $white);
					if ($p[3] > 0)
						imageline($I, $p[0], $p[1]-$t+1, $p[0], $p[1]+$t-1, $white);
				}

				break;
		}

		if ($zoom >= $zoom_show_deviation)
		{
			imagesetthickness($I, 1);
			imageellipse($I, $p[0], $p[1], $deviation[$i], $deviation[$i], $color[$p[2]]);
		}
	}

	imagecolortransparent($I, $blank);
	bo_tile_output($file, $caching, $I);
}

function bo_tile_tracks()
{
	global $_BO;
	bo_session_close();

	if (!intval(BO_TRACKS_SCANTIME)) //disabled
		exit;
	
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	$x = intval($_GET['x']);
	$y = intval($_GET['y']);
	$zoom = intval($_GET['zoom']);

	$file = BO_DIR.'cache/tiles/tracks_'.$zoom.'_'.$x.'x'.$y.'.png';
	
	if (BO_CACHE_SUBDIRS === true)
		$file = strtr($file, array('_' => '/'));

	if (file_exists($file) && $caching)
	{
		if (file_exists($file) && filemtime($file) + intval(BO_UP_INTVL_TRACKS) > time())
		{
			header("Content-Type: image/png");
			readfile($file);
			exit;
		}
	}
	
	//create Image
	$I = imagecreate(BO_TILE_SIZE, BO_TILE_SIZE);
	$blank = imagecolorallocatealpha($I, 255, 255, 255, 127);
	imagefill($I, 0, 0, $blank);

	imagesavealpha($I, true);
	imagealphablending($I, false);
	
	

	if ($zoom >= BO_TRACKS_MAP_ZOOM_MIN && $zoom <= BO_TRACKS_MAP_ZOOM_MAX)
	{
		$data = unserialize(gzinflate(bo_get_conf('strike_cells')));
		
		if (is_array($data['cells']))
		{
			$size = 10 + (pow(1.5,$zoom-BO_TRACKS_MAP_ZOOM_MIN+8));
			
			if ($size >= 67)
			{
				$rsizex = 30;
				$rsizey = 27;
				$textsize = 3;
				$size = 70;
			}
			else
			{
				$rsizex = 20;
				$rsizey = 17;
				$textsize = 1;
			}
			
			$linecolor = imagecolorallocatealpha($I, 50, 150, 50, 0 );
			$textcolor = imagecolorallocatealpha($I, 0, 0, 0, 0 );
			$rectcolorfill = imagecolorallocatealpha($I, 230, 230, 230, 10 );
			$rectcolorline = imagecolorallocatealpha($I, 50, 100, 50, 0 );

			//forecast style
			$col1 = imagecolorallocatealpha($I, 0, 155, 0, 127);
			$col2 = imagecolorallocatealpha($I, 0, 155, 0, 0);
			$style = array($col1, $col1, $col1, $col1, $col2, $col2, $col2, $col2);
			imagesetstyle($I, $style);
			$forecastcolorfill = imagecolorallocatealpha($I, 160, 255, 160, 20 );
			
			$count = count($data['cells']) - 1;
			for ($i=0; $i<=$count; $i++)
				$color[$i] = imagecolorallocatealpha($I, 
															100 - 50*($i/$count), 
															255, 
															250 - 150*($i/$count), 
															0);
		
			foreach($data['cells'] as $i => $cells)
			{
				if ($i == 0 && BO_TRACKS_SHOW_UNTRACKED === false)
					continue;
				
				$time_range = $data['cells_time'][$i]['end'] - $data['cells_time'][$i]['start'];

				
				foreach($data['cells'][$i] as $cellid => $cell)
				{
					if (!isset($cell['dist']) && BO_TRACKS_SHOW_UNTRACKED === false)
						continue;
					
					if ($cell['count'] < intval(BO_TRACKS_MAP_MIN_STRIKES_DISPLAY))
						continue;
					
					list($px, $py) = bo_latlon2tile($cell['lat'], $cell['lon'], $zoom);
					$px -= (BO_TILE_SIZE * $x);
					$py -= (BO_TILE_SIZE * $y);
					
					if ($px/BO_TILE_SIZE > 4 || $px/BO_TILE_SIZE < -4 || $py/BO_TILE_SIZE > 4 || $py/BO_TILE_SIZE < -4)
						continue;
					
					$circle_drawn = false;
					
					if (isset($cell['dist']))
					{
						foreach($cell['dist'] as $did => $dist)
						{
							//old cell
							$old = $cell['old'][$did];
							$oldcount = $data['cells'][$i-1][$old]['count'];
							
							if ($oldcount < intval(BO_TRACKS_MAP_MIN_STRIKES_DISPLAY))
								continue 2;

							//$distance to specified time range
							$dist = $cell['dist'][$did] / $time_range * 60 * BO_TRACKS_MAP_TIME_FORCAST;
							
							list($lat, $lon) = bo_distbearing2latlong($dist, $cell['bear'][$did], $cell['lat'], $cell['lon']);
							list($px2, $py2) = bo_latlon2tile($lat, $lon, $zoom);
							$px2 -= (BO_TILE_SIZE * $x);
							$py2 -= (BO_TILE_SIZE * $y);

							imageline($I, $px, $py, $px2, $py2, $linecolor);
							imagefilledellipse($I, $px2, $py2, $size/1.2, $size/1.2, $forecastcolorfill);
							imageellipse($I, $px2, $py2, $size/1.2, $size/1.2, IMG_COLOR_STYLED);
							
							
							
							//show info data (speed...)
							if ($zoom >= BO_TRACKS_MAP_ZOOM_INFO)
							{
								imagestring($I, $textsize, $px2-8, $py2-8, '+'.intval(BO_TRACKS_MAP_TIME_FORCAST), $textcolor);
								imagestring($I, $textsize, $px2-8, $py2+2, 'min', $textcolor);
								
								$strikechange = round(($cell['count'] - $oldcount) / $oldcount * 100);
								if ($strikechange > 0)
									$strikechange = '+'.$strikechange;
								
								$strikepermin = number_format($cell['count'] / $time_range * 60, 1, _BL('.'), _BL(','));

								//speed
								$speed = $cell['dist'][$did] / $time_range * 3.6;
								
								//Position
								$pxr = $px;
								$pyr = $py;
								
								//imagefilledrectangle($I, $pxr - $rsizex, $pyr - $rsizey, $pxr + $rsizex, $pyr + $rsizey, $rectcolorfill);
								//imagerectangle($I, $pxr - $rsizex, $pyr - $rsizey, $pxr + $rsizex, $pyr + $rsizey, $rectcolorline);
								
								imagefilledellipse($I, $px, $py, $size, $size, $color[$i]);
								imageellipse($I, $px, $py, $size+1, $size+1, $linecolor);
								$circle_drawn = true;
								
								$height = imagefontheight($textsize)+1;
								
								$pxr += 2;
								$pyr += 6;
								
								//Speed
								imagestring($I, $textsize, $pxr - $rsizex, $pyr - $rsizey, round($speed).'km/h', $textcolor);
								$pyr += $height;
								
								//Strikes
								//Doesn't make too much sense, because the scantime isn't displayed
								//imagestring($I, $textsize, $pxr - $rsizex, $pyr - $rsizey, $cell['count'], $textcolor);
								//$pyr += $height;
								
								//Strikes per minute
								imagestring($I, $textsize, $pxr - $rsizex, $pyr - $rsizey, $strikepermin.'/min', $textcolor);
								$pyr += $height;
								
								//Strike count change
								imagestring($I, $textsize, $pxr - $rsizex, $pyr - $rsizey, $strikechange.'%', $textcolor);
							}
							
							break; //currently only the first dataset
						}
					}

					if (!$circle_drawn)
					{
						imagefilledellipse($I, $px, $py, $size, $size, $color[$i]);
						imageellipse($I, $px, $py, $size+1, $size+1, $linecolor);
					}
				}
			}
		}
	}
	
	bo_tile_output($file, $caching, $I);
}

function bo_tile_output($file='', $caching=false, &$I=null)
{
	BoDb::close();
	bo_session_close(true);
	
	if ($caching && BO_CACHE_SUBDIRS === true)
	{
		$dir = dirname($file);
		if (!file_exists($dir))
			@mkdir($dir, 0777, true);
	}


	if ($I === null)
	{
		$img = file_get_contents(BO_DIR.'images/blank_tile.png');
		$ok = @file_put_contents($file, $img);
		
		if (!$ok && $caching)
			bo_image_cache_error(BO_TILE_SIZE, BO_TILE_SIZE);
		
		header("Content-Type: image/png");
		echo $img;
		exit;
	}
		
	header("Content-Type: image/png");
	if ($caching)
	{
		$ok = @imagepng($I, $file);
		
		if (!$ok)
			bo_image_cache_error(BO_TILE_SIZE, BO_TILE_SIZE);
		
		readfile($file);
	}
	else
		imagepng($I);
		
	imagedestroy($I);

	exit;
}


function bo_tile_message($text, $type, $caching=false, $replace = array())
{
	$dir = BO_DIR.'cache/tiles/';
	
	$file = $dir.$type.'_'._BL().'.png';
	
	if (!file_exists($file) || !$caching)
	{
		bo_load_locale();
		$text = strtr(_BL($text, true), $replace);
		
		$I = imagecreate(BO_TILE_SIZE, BO_TILE_SIZE);

		$blank = imagecolorallocate($I, 255, 255, 255);
		$textcol = imagecolorallocate($I, 70, 70, 70);
		$box_bg  = imagecolorallocate($I, 210, 210, 255);
		$box_line  = imagecolorallocate($I, 255, 255, 255);

		imagefilledrectangle( $I, 0, 0, BO_TILE_SIZE, BO_TILE_SIZE, $blank);
		
		$text = strtr($text, array('\n' => "\n"));
		
		$lines = explode("\n", $text);
		$height = (count($lines));
		$width = 0;
		foreach($lines as $line)
			$width = max(strlen($line), $width);
		
		$fwidth  = imagefontwidth(BO_MAP_NA_FONTSIZE);
		$fheight = imagefontheight(BO_MAP_NA_FONTSIZE);

		imagefilledrectangle( $I, 25, 115, 35+$width*$fwidth, 127+$height*$fheight, $box_bg);
		imagerectangle( $I, 25, 115, 35+$width*$fwidth, 127+$height*$fheight, $box_line);

		foreach($lines as $i=>$line)
			imagestring($I, BO_MAP_NA_FONTSIZE, 30, 120+$i*$fheight, $line, $textcol);

		imagecolortransparent($I, $blank);
		
		if (!$caching)
		{
			header("Content-Type: image/png");
			imagepng($I);
			exit;
		}
		
		$ok = @imagepng($I, $file);
		
		if (!$ok)
			bo_image_cache_error(BO_TILE_SIZE, BO_TILE_SIZE);

	}

	header("Content-Type: image/png");
	readfile($file);


}

?>