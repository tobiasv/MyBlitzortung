<?php


function bo_tile()
{
	@set_time_limit(BO_TILE_CREATION_TIMEOUT);

	global $_BO;

	bo_session_close();
	
	$x            = intval($_GET['x']);
	$y            = intval($_GET['y']);
	$zoom         = intval($_GET['zoom']);
	$station_info_id = intval($_GET['sid']);
	$only_station = isset($_GET['os']);
	$only_info    = isset($_GET['info']);
	$show_count   = isset($_GET['count']);
	$type         = intval($_GET['type']);
	
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	$time = time();
	$cfg = $_BO['mapcfg'][$type];
	
	if ($show_count)
		$tile_size = BO_TILE_SIZE_COUNT;
	else
		$tile_size = BO_TILE_SIZE;

	list($min_zoom, $max_zoom) = bo_get_zoom_limits();
	
	if ($station_info_id && $only_station)
		$station_id = $station_info_id;
	else
		$station_id = false;
	
	if (!$only_info && ($zoom < $min_zoom || $zoom > $max_zoom))
	{
		bo_tile_message('tile_zoom_not_allowed', 'zoom_na', $caching, array(), $tile_size);
		exit;
	}
	
	if ( $station_id > 0 && $station_id != bo_station_id() && (!(bo_user_get_level() & BO_PERM_NOLIMIT) || BO_MAP_STATION_SELECT !== true) )
	{
		bo_tile_message('tile_station_not_allowed', 'station_na', $caching, array(), $tile_size);
		exit;
	}
	
	//manual time select
	if (isset($_GET['from']) && isset($_GET['to']))
	{
		if (!$only_info && BO_MAP_MANUAL_TIME_ENABLE !== true && !(bo_user_get_level() & BO_PERM_NOLIMIT))
		{
			bo_tile_message('tile_time_range_na_err', 'range_na', $caching, array(), $tile_size);
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
			bo_tile_message('tile_wrong_time_range_err', 'range_wrong', $caching, array(), $tile_size);
			exit;
		}
		else if (!$only_info && intval(BO_MAP_MANUAL_TIME_MAX_HOURS) < $cfg['trange']/60 && bo_user_get_id() != 1)
		{
			bo_tile_message('tile_maximum_time_range_err', 'range_max', $caching, array('{HOURS}' => intval(BO_MAP_MANUAL_TIME_MAX_HOURS)), $tile_size);
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
	if ($show_count) // display strike count
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
				$ccfg = $_BO['mapcfg'][$i];
			else
				$ccfg = $cfg;

			if (!is_array($ccfg) || !$ccfg['upd_intv'])
				continue;
			
			$time_start = $time - 60 * $ccfg['tstart'];
			
			$update_intervals[$i] = $ccfg['upd_intv'];
			$times_min[$i]        = mktime(date('H', $time_start), ceil(date('i', $time_start) / $ccfg['upd_intv']) * $ccfg['upd_intv'], 0, date('m', $time_start), date('d', $time_start), date('Y', $time_start));
			$times_max[$i]        = $times_min[$i] + 60 * $ccfg['trange'] + 59;
		}
		
		$update_interval = count($update_intervals) ? min($update_intervals) : 0;
		$time_min        = count($times_min) ? min($times_min) : 0;
		$time_max        = count($times_max) ? max($times_max) : 0;
		
		if ($_GET['stat'] == 2)
			$type = 'count_stat_'.($only_station ? 'only_' : '').'_'.$station_info_id.$type;
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
	
	if (time() - $exp_time < 10)
		$exp_time = time() + 60;
	
	//Headers
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $mod_time)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $exp_time)." GMT");
	header("Content-Disposition: inline; filename=\"MyBlitzortungTile.png\"");
	
	if ($caching)
	{
		header("Pragma: ");
		header("Cache-Control: public, max-age=".($exp_time - time()));
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
	$filename = $type.'_'.$zoom.'_'.$station_id.'_'.$x.'x'.$y.'-'.(bo_user_get_level() ? 1 : 0).'.png';
	
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
			bo_output_cache_file($file, $mod_time);
			exit;
		}
	}

	list($lat1, $lon1, $lat2, $lon2) = bo_get_tile_dim($x, $y, $zoom, $tile_size);
	
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
				bo_tile_message($text, 'na', $caching, array(), $tile_size);
				exit;
			}

		}
	}

	if (BO_TILE_CREATION_SIM_WAIT)
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
	if ($show_count) 
	{
		$strike_count = 0;
		$whole_strike_count = 0;
		
		//Build ugly SQL Query
		$sql_join  = '';
		$sql_where = ' ( 0';
		foreach($count_types as $i)
		{
			$date_min = gmdate('Y-m-d H:i:s', $times_min[$i]);
			$date_max = gmdate('Y-m-d H:i:s', $times_max[$i]);
			
			$sql_where .= " OR s.time BETWEEN '$date_min' AND '$date_max' ";
		}
		$sql_where .= ') ';
		
		//the where clause
		$sql_where .= ' AND '.bo_strikes_sqlkey($index_sql, min($times_min), max($times_max), $lat1, $lat2, $lon1, $lon2);
		$sql_where .= $radius ? " AND s.distance < $radius " : "";

		
		if ($station_id && $station_id == bo_station_id())
		{
			$sql_where2 = " AND s.part>0 ";
			$sql_participated = " s.part>0 ";
		}
		elseif ($station_id > 0)
		{
			$sql_where2  = "";
			$sql_join1   = " LEFT OUTER JOIN ".BO_DB_PREF."stations_strikes ss2 ON s.id=ss2.strike_id AND ss2.station_id='".$station_id."'";
			$sql_join2   = "            JOIN ".BO_DB_PREF."stations_strikes ss2 ON s.id=ss2.strike_id AND ss2.station_id='".$station_id."'";
			$sql_participated = " ss2.strike_id IS NOT NULL ";
		}
		else
		{
			$sql_where2 = "";
			$sql_participated = " 0 ";
		}
		
		//get count of all other stations
		if ($_GET['stat'] == 2)
		{
			$stations = bo_stations();
			$stations_count = array();
			
			if ($station_info_id)
				$stations_count[$station_info_id] = 0;
			
			$sql = "SELECT ss.station_id sid, COUNT(s.time) cnt 
					FROM ".BO_DB_PREF."stations_strikes ss 
					JOIN ".BO_DB_PREF."strikes s $index_sql ON s.id=ss.strike_id
					$sql_join2
					WHERE $sql_where $sql_where2
					GROUP BY sid
					";
			$erg = BoDb::query($sql);
			while ($row = $erg->fetch_assoc())
			{
				$stations_count[$row['sid']] = $row['cnt'];
			}
		}
		
		//all strike count and participated count
	 	$sql = "SELECT COUNT(s.time) cnt, $sql_participated participated 
				FROM ".BO_DB_PREF."strikes s $index_sql $sql_join1
				WHERE $sql_where 
				GROUP BY participated
				";
		$erg = BoDb::query($sql);
		while ($row = $erg->fetch_assoc())
		{
			if ($station_id)
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
		$I = imagecreate($tile_size, $tile_size);
		imagealphablending($I, true); 
		imagesavealpha($I, true);

		$blank = imagecolorallocatealpha($I, 255, 255, 255, 127);
		imagefilledrectangle($I, 0, 0, $tile_size, $tile_size, $blank);
	
	
		//border
		$col = imagecolorallocatealpha($I, 100,100,100,50);
		imagerectangle( $I, 0, 0, $tile_size-1, $tile_size-1, $col);
		
		//number
		$textsize = BO_MAP_COUNT_FONTSIZE;
		$bold = BO_MAP_COUNT_FONTBOLD;
		
		$text = $strike_count;
		if ($station_id > 0 && intval($whole_strike_count))
		{
			$ratio = $strike_count / $whole_strike_count * 100;
			$text .= ' / '.number_format($ratio, 1, _BL('.'), _BL(',')).'%';
		}
		
		$twidth = bo_imagetextwidth($textsize, $bold, $text)+3;
		$theight = bo_imagetextheight($textsize, $bold, $text)+2;
		$white = imagecolorallocatealpha($I, 255,255,255,0);
		$color2   = imagecolorallocatealpha($I, 255,220,170,0);
		imagefilledrectangle( $I, 0, 0, $twidth, $theight, $col);
		bo_imagestring($I, $textsize, 2, 2, $text, $white, $bold);
		
		
		//Stations
		if ($_GET['stat'] == 2)
		{
			if ($strike_count > 0)
			{
				arsort($stations_count);
				$theight-=1;
				$selected_station_displayed = $station_info_id ? false : true;
				
				$i = 0;
				$j = 0;
				foreach($stations_count as $sid => $cnt)
				{
					$j++;

					if ($j > BO_MAP_COUNT_STATIONS)
					{
						if ($selected_station_displayed)
							break;
						elseif ($station_info_id == $sid)
							$selected_station_displayed = true;
						else
							continue;
					}
					
					$i++;
					
					$text = round($cnt / $strike_count * 100).'% ';
					$text .= trim($stations[$sid]['city']);
					
					if ($sid == $station_info_id)
						$text .= " ($cnt)";
					
					$twidth = bo_imagetextwidth($textsize-1, $bold, $text);
					
					imagefilledrectangle($I, 0, $theight*$i, $twidth+10, $theight*($i+1), $col);
					bo_imagestring($I, $textsize-1, 2, $theight*$i+3, $text, $sid == $station_info_id ? $color2 : $white, false);
					

					
				}
			}
		}
		
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

	/*** Build SQL Query ***/
	$sql_join = "";
	
	/* WHERE */
	$sql_where  = bo_strikes_sqlkey($index_sql, $time_min, $time_max, $lat1, $lat2, $lon1, $lon2);
	$sql_where .= $radius ? "AND distance < $radius" : "";
	
	/* Station Filter */
	if ($station_id && $station_id == bo_station_id())
	{
		$sql_where .= " AND s.part>0 ";
	}
	elseif ($station_id > 0)
	{
		$sql_join   = " JOIN ".BO_DB_PREF."stations_strikes ss ON s.id=ss.strike_id AND ss.station_id='".$station_id."'";
	}
	
	
	//strike grouping
	//no grouping for deviation-circle
	if ($zoom >= $zoom_show_deviation) 
	{
		$sql_select = " s.time mtime, s.lat lat, s.lon lon  ";
		$grouping   = false;
	}
	//Calculate tile coordinates in SQL and group them 
	else
	{
		$sql_select = " MAX(s.time) mtime, 
						".bo_sql_lat2tiley('s.lat', $zoom)." y, 
						".bo_sql_lon2tilex('s.lon', $zoom)." x  ";
		$grouping   = true;
	}
	
	//get the data!
	$points = array();
	$deviation = array();
	$sql = "SELECT s.id id, s.deviation deviation, s.polarity polarity,
				$sql_select	
			FROM ".BO_DB_PREF."strikes s $index_sql $sql_join
			WHERE $sql_where
			".($grouping ? " GROUP BY x, y" : "")."
			ORDER BY mtime ASC";
	$erg = BoDb::query($sql);
	
	//Max. strikes per tile
	$num = $erg->num_rows;
	$max = intval(BO_MAP_MAX_STRIKES_PER_TILE);
	
	
	//no points --> blank tile
	if ($num == 0)
	{
		bo_tile_output($file, $caching);
	}

	//create Image
	$I = imagecreate($tile_size, $tile_size);
	$blank = imagecolorallocate($I, 0, 0, 0);
	$white = imagecolorallocate($I, 255, 255, 255);
	imagefilledrectangle( $I, 0, 0, $tile_size, $tile_size, $blank);

	//prepare "brushes" and styles
	foreach($c as $i => $rgb)
		$color[$i] = imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);

	if ($zoom >= BO_MAP_STRIKE_SHOW_CIRCLE_ZOOM) //circle (grows with zoom)
	{
		$s = BO_MAP_STRIKE_CIRCLE_SIZE + round(pow(2,($zoom-BO_MAP_STRIKE_SHOW_CIRCLE_ZOOM)*BO_MAP_STRIKE_CIRCLE_GROW));
		$style = 0;
	}
	else if ($zoom >= BO_EXPERIMENTAL_POLARITY_ZOOM && BO_EXPERIMENTAL_POLARITY_CHECK === true)
	{
		$s1 = BO_MAP_STRIKE_POLARITY_SIZE;
		$s  = BO_MAP_STRIKE_POLARITY_SIZE_UNKNOWN;
		$style = 2; //with polarity
	}
	else
	{
		$s = BO_MAP_STRIKE_SIZE;
		$style = 1;
	}

	
	// get the data and paint tile
	while ($row = $erg->fetch_assoc())
	{
		//Max. strikes per tile handling
		//This random thing is quick&easy 
		//but needs no further strike calculation (position/time/color)
		//Problem: tile borders
		if ($max && $num > $max)
		{
			if (rand(0, $num) > $max)
				continue;
		}

		if ($grouping)
		{
			$px = $row['x'];
			$py = $row['y'];
		}
		else
		{
			list($px, $py)     = bo_latlon2tile($row['lat'], $row['lon'], $zoom);
			
			if ($zoom >= $zoom_show_deviation)
			{
				list($dlat, $dlon) = bo_distbearing2latlong($row['deviation'], 0, $row['lat'], $row['lon']);
				list($dx, $dy)     = bo_latlon2tile($dlat, $dlon, $zoom);
				$deviation  	   = $py - $dy;
			}
		}

		$px -= ($tile_size * $x);
		$py -= ($tile_size * $y);
		
		$strike_time = strtotime($row['mtime'].' UTC');
		$col = $color[floor(($time_max - $strike_time) / $color_intvl)];
		
		
		//imagefilledarc draws much nicer circles, but has a bug in older php versions
		//https://bugs.php.net/bug.php?id=43547
		//imagefilledarc: not nice when size is even
		if (!($s%2) || $s >= 8 || BO_NICE_CIRCLES == 0 || (BO_NICE_CIRCLES == 2 && $py >= $tile_size-$s+3))
			$nice_circles = false;
		else
			$nice_circles = true;
		
		
		//paint!
		switch($style)
		{
			case 1: // plot a "+"
				
				imagesetthickness($I, 2);
				imageline($I, $px-$s, $py, $px+$s-1, $py, $col);
				imageline($I, $px, $py-$s, $px, $py+$s-1, $col);
				break;

			case 2:
				if (!$row['polarity']) //plot circle (no polarity known)
				{
					imagesetthickness($I, 1);
					
					if ($nice_circles)
						imagefilledarc($I, $px, $py, $s, $s, 0, 360, $col, IMG_ARC_PIE);
					else
						imagefilledellipse($I, $px, $py, $s, $s, $col);
		
						
				}
				else //plot "+" or "-"
				{
					$t = $s1 - 2;
					imagesetthickness($I, 2);
					imageline($I, $px-$t, $py, $px+$t-1, $py, $col);
					if ($row['polarity'] > 0)
						imageline($I, $px, $py-$t, $px, $py+$t-1, $col);
				}

				break;

			default: // plot circle
				imagesetthickness($I, 1);
				
				if ($nice_circles)
					imagefilledarc($I, $px, $py, $s, $s, 0, 360, $col, IMG_ARC_PIE);
				else
					imagefilledellipse($I, $px, $py, $s, $s, $col);

				if ($row['polarity'] && BO_EXPERIMENTAL_POLARITY_CHECK == true)
				{
					$t = intval($s / 2);
					imageline($I, $px-$t+1, $py, $px+$t-1, $py, $white);
					if ($row['polarity'] > 0)
						imageline($I, $px, $py-$t+1, $px, $py+$t-1, $white);
				}

				break;
		}

		if ($zoom >= $zoom_show_deviation)
		{
			imagesetthickness($I, 1);
			imageellipse($I, $px, $py, $deviation, $deviation, $col);
		}
		
	}
	
	imagecolortransparent($I, $blank);
	bo_tile_output($file, $caching, $I);
}

function bo_tile_tracks()
{
	global $_BO;
	bo_session_close();
	@set_time_limit(BO_TILE_CREATION_TIMEOUT);
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	$scantime = intval(BO_TRACKS_SCANTIME) * 60;
	
	if (!$scantime) //disabled
	{
		bo_tile_message('tile_tracks_disabled', 'tracks_na', $caching);
		exit;
	}
	
	$x = intval($_GET['x']);
	$y = intval($_GET['y']);
	$zoom = intval($_GET['zoom']);

	$file = 'tracks_'.$zoom.'_'.$x.'x'.$y.'.png';
	if (BO_CACHE_SUBDIRS === true)
		$file = strtr($file, array('_' => '/'));
	$file = BO_DIR.'cache/tiles/'.$file;
	
	
	//estimate the last update, otherwise we have to parse the array...
	$lastscan = floor(time() / $scantime) * $scantime;
	$exp_time = $lastscan + $scantime;
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $lastscan)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $exp_time)." GMT");
	header("Content-Disposition: inline; filename=\"MyBlitzortungTile.png\"");
	header("Pragma: ");
	header("Cache-Control: public, max-age=".($exp_time - time()));

	
	if (file_exists($file) && $caching)
	{
		if (file_exists($file) && filemtime($file) + intval(BO_UP_INTVL_TRACKS) > time())
		{
			header("Content-Type: image/png");
			bo_output_cache_file($file, $lastscan);
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

function bo_tile_output($file='', $caching=false, &$I=null, $tile_size = BO_TILE_SIZE)
{
	if (BO_TILE_CREATION_SIM_WAIT)
		bo_set_conf('is_creating_tile', 0);

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
			bo_image_cache_error($tile_size, $tile_size);
		
		header("Content-Type: image/png");
		echo $img;
		exit;
	}
		
	header("Content-Type: image/png");
	if ($caching)
	{
		$ok = imagepng($I, $file);
		
		if (!$ok)
			bo_image_cache_error($tile_size, $tile_size);
		
		readfile($file);
	}
	else
		imagepng($I);
		
	imagedestroy($I);

	exit;
}


function bo_tile_message($text, $type, $caching=false, $replace = array(), $tile_size = BO_TILE_SIZE)
{
	$dir = BO_DIR.'cache/tiles/';
	
	bo_load_locale();
	
	$file  = $dir.$type.'_';
	$file .= bo_user_get_level().'_';
	$file .= _BL().'.png';
	
	if (!file_exists($file) || !$caching)
	{
		
		$text = strtr(_BL($text, true), $replace);
		
		$I = imagecreate($tile_size, $tile_size);

		$blank = imagecolorallocate($I, 255, 255, 255);
		$textcol = imagecolorallocate($I, 70, 70, 70);
		$box_bg  = imagecolorallocate($I, 210, 210, 255);
		$box_line  = imagecolorallocate($I, 255, 255, 255);

		imagefilledrectangle( $I, 0, 0, $tile_size, $tile_size, $blank);
		
		$text = strtr($text, array('\n' => "\n"));
		
		$lines = explode("\n", $text);
		$height = (count($lines));
		$width = 0;
		foreach($lines as $line)
			$width = max(strlen($line), $width);
		
		$fwidth  = imagefontwidth(BO_MAP_NA_FONTSIZE);
		$fheight = imagefontheight(BO_MAP_NA_FONTSIZE);

		//rows/columns if tile > 256
		for ($x=0; $x < $tile_size; $x+=256)
		{
			for ($y=0; $y < $tile_size; $y+=256)
			{

				//draw text
				imagefilledrectangle( $I, 25+$x, 115+$y, 35+$width*$fwidth+$x, 127+$height*$fheight+$y, $box_bg  );
				imagerectangle(       $I, 25+$x, 115+$y, 35+$width*$fwidth+$x, 127+$height*$fheight+$y, $box_line);

				foreach($lines as $i=>$line)
					imagestring($I, BO_MAP_NA_FONTSIZE, 30+$x, 120+$i*$fheight+$y, $line, $textcol);
			
			}
		}
		
		imagecolortransparent($I, $blank);
		
		if (!$caching)
		{
			header("Content-Type: image/png");
			imagepng($I);
			exit;
		}
		
		$ok = imagepng($I, $file);
		
		if (!$ok)
			bo_image_cache_error($tile_size, $tile_size);

	}

	header("Content-Type: image/png");
	readfile($file);


}

?>