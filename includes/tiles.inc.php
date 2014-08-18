<?php


function bo_tile()
{
	@set_time_limit(BO_TILE_CREATION_TIMEOUT);
	global $_BO;
	bo_session_close();

	/***********************************************************/
	/*** Variables  ********************************************/
	/***********************************************************/

	

	
	$x            = intval($_GET['x']);
	$y            = intval($_GET['y']);
	$zoom         = intval($_GET['zoom']);
	$tile_size	  = intval($_GET['s']);
	$station_info_id = intval($_GET['sid']);
	$only_station = isset($_GET['os']);
	$only_info    = isset($_GET['info']);
	$show_count   = isset($_GET['count']);
	$type         = intval($_GET['type']);
	$caching      = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	$cfg          = $_BO['mapcfg'][$type];
	list($min_zoom, $max_zoom) = bo_get_zoom_limits();
	$restricted   = false;
	$user_nolimit = (bo_user_get_level() & BO_PERM_NOLIMIT);
	
	if ($tile_size)
	{
		if (($tile_size%256) || $tile_size > BO_TILE_SIZE)
			bo_tile_message('tile_wrong_size', 'wrong_size', $caching, array(), BO_TILE_SIZE);
	}
	else if ($show_count)
		$tile_size = BO_TILE_SIZE_COUNT;
	else
		$tile_size = BO_TILE_SIZE;
	
	if ($station_info_id && $only_station)
		$station_id = $station_info_id;
	else
		$station_id = false;
	
	
	
	
	/***********************************************************/
	/*** Restriction *******************************************/
	/***********************************************************/
	
	if (!$only_info && ($zoom < $min_zoom || $zoom > $max_zoom))
	{
		bo_tile_message('tile_zoom_not_allowed', 'zoom_na', $caching, array(), $tile_size);
	}
	
	if ( $station_id > 0 && $station_id != bo_station_id() && BO_MAP_STATION_SELECT !== true )
	{
		if (!$user_nolimit)
		{
			bo_tile_message('tile_station_not_allowed', 'station_na', $caching, array(), $tile_size);
		}
		
		$restricted = true;
	}

	if ($zoom >= BO_MAX_ZOOM_LIMIT && $user_nolimit)
	{
		//for correct naming of cache file, we have to set this here
		//real testing of limit is done after caching
		$restricted = true;
	}

	//correct x parameter
	$x_max = pow(2,$zoom)/($tile_size/256);
	$x     = $x%$x_max;
	$x    += $x<0 ? $x_max : 0;
	
	/***********************************************************/
	/*** Time periods ******************************************/
	/***********************************************************/
	
	if (isset($_GET['from']) && isset($_GET['to']))
	{
		if (!$only_info && BO_MAP_MANUAL_TIME_ENABLE !== true)
		{
			if (!$user_nolimit)
			{
				bo_tile_message('tile_time_range_na_err', 'range_na', $caching, array(), $tile_size);
			}
			
			$restricted = true;
		}
		
		$time_manual_from = strtotime($_GET['from']);
		$time_manual_to = strtotime($_GET['to']);

		// set "current" time
		$last_update_time = $time_manual_to;
		
		// add config
		$cfg['trange'] = ($time_manual_to - $time_manual_from) / 60; 
		$cfg['upd_intv'] = 1;
		$cfg['tstart'] = ($time_manual_to - $time_manual_from) / 60;

		if (!$only_info && $time_manual_to < $time_manual_from)
		{
			bo_tile_message('tile_wrong_time_range_err', 'range_wrong', $caching, array(), $tile_size);
		}
		else if (!$only_info && intval(BO_MAP_MANUAL_TIME_MAX_HOURS) < $cfg['trange']/60 && bo_user_get_id() != 1)
		{
			bo_tile_message('tile_maximum_time_range_err', 'range_max', $caching, array('{HOURS}' => intval(BO_MAP_MANUAL_TIME_MAX_HOURS)), $tile_size);
		}
		
		//disable caching
		$caching = false;
	}
	else
	{
		$time_manual_from = false;
	}

	/***********************************************************/
	/*** Update intervals **************************************/
	/***********************************************************/

	if ($show_count) 
	{
		$update_intervals = array();
		$type = 0;
		
		if ($time_manual_from)
			$count_types[0] = -1;
		else
			$count_types = explode(',',$_GET['count']);

		foreach($count_types as $i)
		{
			$type += pow(2, $i);
			$ccfg = $time_manual_from ? $cfg : $_BO['mapcfg'][$i];
			$update_intervals[$i] = $ccfg['upd_intv'];
		}

		if ($_GET['stat'] == 2)
			$type = 'count_stat_'.($only_station ? 'only_' : '').'_'.$station_info_id.$type;
		else
			$type = 'count_'.$type;
			
		$update_interval = count($update_intervals) ? min($update_intervals) : 0;
	}
	else
	{
		$update_interval = $cfg['upd_intv'];
	}
	
	
	/***********************************************************/
	/*** Early caching *****************************************/
	/***********************************************************/
	
	//estimate last update
	$last_update_time = bo_get_latest_strike_calc_time($update_interval * 60, 'tiles');

	bo_tile_headers($update_interval, $last_update_time, $caching);

	
	//Caching, but not for info image
	if ($caching && !$only_info)
	{
		$dir = BO_DIR.BO_CACHE_DIR.'/tiles/';
		$filename = $type.'_'.$zoom.'_'.($station_id ? $station_id.'_' : '').$x.'x'.$y.'x'.$tile_size.'-'.($restricted ? 1 : 0).'.png';

		if (BO_CACHE_SUBDIRS === true)
			$filename = strtr($filename, array('_' => '/'));

		$file = $dir.$filename;

		bo_output_cachefile_if_exists($file, $last_update_time, $update_interval * 60);
	}
	

	
	
	/***********************************************************/
	/*** Is in Radius around station? **************************/
	/***********************************************************/
	
	if (!$only_info)
	{
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
				$circle_center_lat = BO_MAP_LAT ? BO_MAP_LAT : BO_LAT;
				$circle_center_lon = BO_MAP_LON ? BO_MAP_LON : BO_LON;
				$na_positions = bo_tile_check_na_positions($radius, $x, $y, $zoom, $tile_size, $lat1, $lon1, $lat2, $lon2, $circle_center_lat, $circle_center_lon, $caching);
				
				//all positions are not available
				if ($na_positions && $na_positions == bo_tile_positions_all($tile_size))
				{
					bo_tile_message('tile not available', 'na', $caching, array(), $tile_size);		
				}
			}
		}
	}
	
	/***********************************************************/
	/*** Time periods (2) **************************************/
	/***********************************************************/
	
	//FIRST DB ACCESS!
	if (!$time_manual_from && BO_CACHE_FAST != 'tiles')
	{
		$last_update_time = bo_get_latest_strike_calc_time(1, 'tiles');
	}
		
	if ($show_count) 
	{
		// display strike count
		$time_min = array();
		$time_max = array();

		if ($time_manual_from)
		{
			$times_min[-1] = $time_manual_from;
			$times_max[-1] = $time_manual_to;
		}
		else
		{
			foreach($count_types as $i)
			{
				$ccfg = $time_manual_from ? $cfg : $_BO['mapcfg'][$i];
				
				if (!is_array($ccfg) || !$ccfg['upd_intv'])
					continue;
				
				$times_min[$i] = ceil(($last_update_time - 60*$ccfg['tstart']) / $ccfg['upd_intv'] / 60) * $ccfg['upd_intv'] * 60;
				$times_max[$i] = $times_min[$i] + 60 * $ccfg['trange'];
			}
		}
		
		$time_min        = count($times_min) ? min($times_min) : 0;
		$time_max        = count($times_max) ? max($times_max) : 0;
		
	}
	elseif ($time_manual_from)
	{
		$c = $cfg['col'];
		$time_min = $time_manual_from;
		$time_max = $time_manual_to;
	}
	else 
	{
		//normal strike display
		$c = $cfg['col'];
		$time_min   = ceil(($last_update_time - 60*$cfg['tstart']) / $update_interval / 60) * $update_interval * 60;
		$time_max   = $time_min + 60 * $cfg['trange'];
	}
	
	if (!$time_min || !$time_max)
		bo_tile_output($file, $caching);

		
	//Update headers, time may be updated now!
	if ($caching) 
		bo_tile_headers($update_interval, $last_update_time, $caching);
	
	//send only the info/color-legend image (colors, time)
	if ($only_info)
	{
		//get real last update var
		$show_date = $time_manual_from || ($time_max-$time_min) > 3600 * 12 ? true : false;
		bo_tile_time_colors($type, $time_min, $time_max, $show_date, $caching ? $update_interval : false);
		exit;
	}


	
	
	/***********************************************************/
	/*** Start of calculations *********************************/
	/***********************************************************/

	$max_strikes_tile = BO_MAP_MAX_STRIKES_PER_TILE * pow(2, $tile_size/256);
	
	//Radius
	$sql_where_radius = '';
	if ($radius)
	{
		if (!BO_MAP_LAT && !BO_MAP_LON)
		{
			$sql_where_radius .= " AND s.distance < $radius ";
		}
		else
		{
			$sql_where_radius .= " AND ".bo_sql_latlon2dist(BO_MAP_LAT, BO_MAP_LON, 's.lat', 's.lon')." < $radius ";
		}
	}

	//Display only strike count
	if ($show_count) 
	{
		$tile_sub_size = 256;
	
		if ($_GET['stat'] == 2)
			$max = $max_strikes_tile / 10;
		else
			$max = 0;
	
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
		$sql_where .= ' AND '.bo_strikes_sqlkey($index_sql, min($times_min), max($times_max), $lat1, $lat2, $lon1, $lon2, $max);
		$sql_where .= $sql_where_radius;
		
		if (is_array($max) && $max['divisor'])
			$multiplicator = $max['divisor'];
		else
			$multiplicator = 1;
		
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
		
	
		//divide bigger tile
		$sub_tiles = bo_tile_get_subcoordinates($x, $y, $zoom, $tile_size, $tile_sub_size);
		
		if (count($sub_tiles) > 1)
		{
			$sql_sub_tiles = ' CASE ';
			
			foreach($sub_tiles as $i => $d)
			{
				$sql_sub_tiles .= " WHEN (s.lat BETWEEN '".$d[2]."' AND '".$d[4]."' AND s.lon BETWEEN '".$d[3]."' AND '".$d[5]."') ";
				$sql_sub_tiles .= " THEN $i ";
			}
			
			$sql_sub_tiles .= ' END ';
		}
		else
		{
			$sql_sub_tiles = '0';
		}
		
	
		//get count of all other stations
		if ($_GET['stat'] == 2)
		{
			$stations = bo_stations();
			$stations_count = array();
			
			if ($station_info_id)
				$stations_count[$station_info_id] = 0;
			
			$sql = "SELECT ss.station_id sid, COUNT(s.time) cnt, $sql_sub_tiles tile_no
					FROM ".BO_DB_PREF."stations_strikes ss 
					JOIN ".BO_DB_PREF."strikes s $index_sql ON s.id=ss.strike_id
					$sql_join2
					WHERE $sql_where $sql_where2
					GROUP BY tile_no, sid
					";

			$erg = BoDb::query($sql);
			while ($row = $erg->fetch_assoc())
			{
				$row['cnt'] *= $multiplicator;
				$stations_count[$row['tile_no']][$row['sid']] = $row['cnt'];
			}
		}
		
		//all strike count and participated count
	 	$sql = "SELECT COUNT(s.time) cnt, $sql_participated participated, $sql_sub_tiles tile_no
				FROM ".BO_DB_PREF."strikes s $index_sql $sql_join1
				WHERE $sql_where 
				GROUP BY tile_no, participated
				";
				
				
		$erg = BoDb::query($sql);
		while ($row = $erg->fetch_assoc())
		{
			$row['cnt'] *= $multiplicator;
			
			if ($station_id)
			{
				if ($row['participated'])
					$strike_count[$row['tile_no']] += $row['cnt'];
				
				$whole_strike_count[$row['tile_no']] += $row['cnt'];
			}
			else
				$strike_count[$row['tile_no']] += $row['cnt'];
		}

		BoDb::close();
		bo_session_close(true);
		
		require_once 'functions_image.inc.php';
		
		//create tile image
		$I = imagecreate($tile_size, $tile_size);
		imagealphablending($I, true); 
		imagesavealpha($I, true);

		$blank = imagecolorallocatealpha($I, 255, 255, 255, 127);
		imagefilledrectangle($I, 0, 0, $tile_size, $tile_size, $blank);
	
		//number
		$textsize = BO_MAP_COUNT_FONTSIZE;
		$bold = BO_MAP_COUNT_FONTBOLD;

		//border
		$col1 = imagecolorallocatealpha($I, 100,100,100,50);
		$col2 = imagecolorallocatealpha($I, 255,220,170,0);
		$white = imagecolorallocatealpha($I, 255,255,255,0);
		

		foreach($sub_tiles as $tile_id => $tile_data)
		{
			$dx = $tile_data[0] * $tile_sub_size;
			$dy = $tile_data[1] * $tile_sub_size;
			
			$text = '';
			
			if ($multiplicator > 1 && $strike_count[$tile_id])
				$text .= '~';
			
			$text .= intval($strike_count[$tile_id]);
			if ($station_id > 0 && intval($whole_strike_count[$tile_id]))
			{
				$ratio = $strike_count[$tile_id] / intval($whole_strike_count[$tile_id]) * 100;
				$text .= ' / '._BN($ratio, 1).'%';
			}
			
			$twidth = bo_imagetextwidth($textsize, $bold, $text)+3;
			$theight = bo_imagetextheight($textsize, $bold, $text)+2;
			imagerectangle( $I, $dx, $dy, $dx+$tile_sub_size-1, $dy+$tile_sub_size-1, $col1);
			imagefilledrectangle( $I, $dx, $dy, $dx+$twidth, $dy+$theight, $col1);
			bo_imagestring($I, $textsize, $dx+2, $dy+2, $text, $white, $bold);
			
			
			//Stations
			if ($_GET['stat'] == 2)
			{
				if ($strike_count[$tile_id] > 0)
				{
					arsort($stations_count[$tile_id]);
					$theight-=1;
					$selected_station_displayed = $station_info_id ? false : true;
					
					$i = 0;
					$j = 0;
					foreach($stations_count[$tile_id] as $sid => $cnt)
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
						
						$text = round($cnt / $strike_count[$tile_id] * 100).'% ';
						$text .= trim($stations[$sid]['city']);
						
						if ($sid == $station_info_id)
							$text .= " ($cnt)";
						
						$twidth = bo_imagetextwidth($textsize-1, $bold, $text);
						
						imagefilledrectangle($I, $dx, $dy + $theight*$i, $dx+$twidth+10, $dy+$theight*($i+1), $col1);
						bo_imagestring($I, $textsize-1, $dx, $dy + $theight*$i+3, $text, $sid == $station_info_id ? $col2 : $white, false);
						

						
					}
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
	$sql_where  = bo_strikes_sqlkey($index_sql, $time_min, $time_max, $lat1, $lat2, $lon1, $lon2, $max_strikes_tile);
	$sql_where .= $sql_where_radius;
	
	/* Station Filter */
	if ($station_id && $station_id == bo_station_id())
	{
		$sql_where .= " AND s.part>0 ";
	}
	elseif ($station_id > 0)
	{
		$sql_join   = " JOIN ".BO_DB_PREF."stations_strikes ss ON s.id=ss.strike_id AND ss.station_id='".$station_id."'";
	}
	
	
	//strike grouping?
	//no grouping when showing text
	if ($zoom >= BO_MAP_STRIKE_SHOW_TEXT)
	{
		require_once 'functions_image.inc.php';
		bo_load_locale();
	
		$sql_select = " s.time mtime, s.lat lat, s.lon lon, 
						s.time_ns mtime_ns, s.stations stations, s.stations_calc stations_calc ";
		$grouping   = false;
	}
	//no grouping for deviation-circle
	else if ($zoom >= $zoom_show_deviation) 
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
	$sql = "SELECT s.id id, s.deviation deviation, s.type type,
				$sql_select	
			FROM ".BO_DB_PREF."strikes s $index_sql $sql_join
			WHERE $sql_where
			".($grouping ? " GROUP BY x, y" : "")."
			ORDER BY mtime ASC";
	$erg = BoDb::query($sql);
	
	$num = $erg->num_rows;
	
	
	//no points --> blank tile
	if ($num == 0 && $na_positions == 0)
	{
		bo_tile_output($file, $caching);
	}

	//create Image
	$I = imagecreate($tile_size, $tile_size);
	$blank = imagecolorallocate($I, 0, 0, 0);
	$black = imagecolorallocate($I, 1, 1, 1);
	$white = imagecolorallocate($I, 255, 255, 255);
	imagefilledrectangle( $I, 0, 0, $tile_size, $tile_size, $blank);

	//prepare "brushes" and styles
	foreach($c as $i => $rgb)
		$color[$i] = imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);

	if ($cfg['type'] == 1 && isset($cfg['size']))
	{
		$s = $cfg['size'] + round(pow(2,$zoom*($cfg['grow'])));
		$style = 0;
	}
	else if ($zoom >= BO_MAP_STRIKE_SHOW_CIRCLE_ZOOM || $cfg['type'] == 1) //circle (grows with zoom)
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
		
		if ($zoom < 6)
			$style = 3;
		else
			$style = 1;
	}
	

	// get the data and paint tile
	while ($row = $erg->fetch_assoc())
	{

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

			case 3: // plot a "+"
				
				imagesetthickness($I, 1);
				imageline($I, $px-$s+1, $py, $px+$s-1, $py, $col);
				imageline($I, $px, $py-$s+1, $px, $py+$s-1, $col);
				break;

				
			case 2:
				if (!$row['type']) //plot circle (no type known)
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
					if ($row['type'] > 0)
						imageline($I, $px, $py-$t, $px, $py+$t-1, $col);
				}

				break;

			default: // plot circle
				imagesetthickness($I, 1);
				
				if ($nice_circles)
					imagefilledarc($I, $px, $py, $s, $s, 0, 360, $col, IMG_ARC_PIE);
				else
					imagefilledellipse($I, $px, $py, $s, $s, $col);

				if ($row['type'] && BO_EXPERIMENTAL_POLARITY_CHECK == true)
				{
					$t = intval($s / 2);
					imageline($I, $px-$t+1, $py, $px+$t-1, $py, $white);
					if ($row['type'] > 0)
						imageline($I, $px, $py-$t+1, $px, $py+$t-1, $white);
				}

				break;
		}

		if ($zoom >= $zoom_show_deviation)
		{
			imagesetthickness($I, 1);
			imageellipse($I, $px, $py, $deviation, $deviation, $col);
		}
		
		if ($zoom >= BO_MAP_STRIKE_SHOW_TEXT)
		{
			$text = _BD($strike_time);
			bo_imagestring($I, 1, $px+3+$s/2, $py-15, $text, $white, false, 0, $black, 1);
			
			$text = _BT($strike_time, false).sprintf('.%09d', $row['mtime_ns']);
			bo_imagestring($I, 1, $px+3+$s/2, $py-6, $text, $white, false, 0, $black, 1);
			
			$text = 'S: '.$row['stations_calc'].'/'.$row['stations'].'';
			bo_imagestring($I, 1, $px+3+$s/2, $py+3, $text, $white, false, 0, $black, 1);
		}
	}
	
	if ($na_positions > 0)
	{
		bo_tile_insert_text($I, 'tile not available', $tile_size, array(), $na_positions);
	}
	
	imagecolortransparent($I, $blank);
	bo_tile_output($file, $caching, $I);
}

function bo_tile_tracks()
{
	require_once 'functions_image.inc.php';
	
	global $_BO;
	bo_session_close();
	@set_time_limit(BO_TILE_CREATION_TIMEOUT);
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	$scantime = intval(BO_TRACKS_SCANTIME) * 60;
	
	if (!$scantime) //disabled
	{
		bo_tile_message('tile_tracks_disabled', 'tracks_na', $caching);
	}
	
	$x = intval($_GET['x']);
	$y = intval($_GET['y']);
	$zoom = intval($_GET['zoom']);

	$file = 'tracks_'.$zoom.'_'.$x.'x'.$y.'.png';
	if (BO_CACHE_SUBDIRS === true)
		$file = strtr($file, array('_' => '/'));
	$file = BO_DIR.BO_CACHE_DIR.'/tiles/'.$file;
	
	
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
		if ( ($data = gzinflate(BoData::get('strike_cells'))) === false )
			$data = BoData::get('strike_cells');
			
		$data = unserialize($data);
		
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
								
								$strikepermin = _BN($cell['count'] / $time_range * 60, 1);

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
	BoDb::close();
	bo_session_close(true);
	
	if ($caching && _BL())
	{
		//add language string when locale has been loaded
		//as tiles may contain text from "bo_tile_message"
		$file = dirname($file).'/'._BL().(BO_CACHE_SUBDIRS === true ? '/' : '_').basename($file);
	}
	
	if ($caching && BO_CACHE_SUBDIRS === true)
	{
		$dir = dirname($file);
		if (!file_exists($dir))
			@mkdir($dir, 0777, true);
	}


	if ($I === null)
	{
		$img = file_get_contents(BO_DIR.'images/blank_tile.png');
		
		if ($caching && $file)
		{
			file_put_contents($file, $img);
			bo_output_cache_file($file, $time_max + $update_interval * 60);
		}
		else
		{
			header("Content-Type: image/png");
			echo $img;
		}
		
		exit;
	}
		
	
	if ($caching)
	{
		$ok = bo_imageout($I, 'png', $file);
		
		if (!$ok)
			bo_image_cache_error($tile_size, $tile_size);
		
		bo_output_cache_file($file, $time_max + $update_interval * 60);
	}
	else
	{
		header("Content-Type: image/png");
		bo_imageout($I);
	}
		
	imagedestroy($I);

	exit;
}


function bo_tile_message($text, $type, $caching=false, $replace = array(), $tile_size = BO_TILE_SIZE)
{
	require_once 'functions_image.inc.php';
	
	$dir = BO_DIR.BO_CACHE_DIR.'/tiles/';
	
	bo_load_locale();
	
	$file  = $dir.$type.'_'.$tile_size.'_';
	$file .= bo_user_get_level().'_';
	$file .= _BL().'.png';
	
	if (!file_exists($file) || !$caching)
	{
		$I = imagecreate($tile_size, $tile_size);
		$blank = imagecolorallocate($I, 255, 255, 255);
		imagefilledrectangle($I, 0, 0, $tile_size, $tile_size, $blank);
		bo_tile_insert_text($I, $text, $tile_size, $replace);
		imagecolortransparent($I, $blank);
		
		if (!$caching)
		{
			bo_imageout($I, 'png');
			exit;
		}
		
		$ok = bo_imageout($I, 'png', $file);
		
		if (!$ok)
			bo_image_cache_error($tile_size, $tile_size);

	}

	header("Content-Type: image/png");
	readfile($file);

	exit;
}

function bo_tile_insert_text($I, $text, $tile_size = BO_TILE_SIZE, $replace = array(), $positions = false)
{
	require_once 'functions_image.inc.php';
	bo_load_locale();
	
	if ($positions === false)
		$positions = bo_tile_positions_all($tile_size);
		
	$text = strtr(_BL($text), $replace);
	$text = strtr($text, array('\n' => "\n"));
	
	$width   = bo_imagetextwidth(BO_MAP_NA_FONTSIZE, false, $text);
	$height  = bo_imagetextheight(BO_MAP_NA_FONTSIZE, false, $text);
	$textcol  = imagecolorallocate($I, 70, 70, 70);
	$box_bg   = imagecolorallocate($I, 210, 210, 255);
	$box_line = imagecolorallocate($I, 255, 255, 255);
	imagesetthickness($I, 1);
	
	//rows/columns if tile > 256
	$pos = 0;
	for ($y=0; $y < $tile_size; $y+=256)
	{
		for ($x=0; $x < $tile_size; $x+=256)
		{
			//draw text if position is set
			if ( (1<<$pos) & $positions)
			{
				imagefilledrectangle( $I, 25+$x, 114+$y, 34+$width+$x, 119+$height+$y, $box_bg  );
				imagerectangle(       $I, 25+$x, 114+$y, 34+$width+$x, 119+$height+$y, $box_line);
				bo_imagestring($I, BO_MAP_NA_FONTSIZE, 30+$x, 120+$y, $text, $textcol);
			}
			
			$pos++;
		}
	}
}

function bo_tile_time_colors($type, $time_min, $time_max, $show_date, $update_interval)
{
	global $_BO;
	
	$cfg = $_BO['mapcfg'][$type];
	$c = $cfg['col'];

	if ($update_interval)
	{
		$dir = BO_DIR.BO_CACHE_DIR.'/tiles/';
		$cache_file = $dir.'tileinfo_'.$type;
		$cache_file .= '_'.(bo_user_get_level() ? 1 : 0).'.png';
		bo_output_cachefile_if_exists($cache_file, $time_max, $update_interval * 60);
	}

	bo_load_locale();
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

	
	
	if ($update_interval)
	{
		$ok = bo_imageout($I, 'png', $cache_file, $time_max);

		if (!$ok)
			bo_image_cache_error($w, $h);
		
		bo_output_cache_file($cache_file, $time_max + $update_interval * 60);
	}
	else
	{
		header("Content-Type: image/png");
		bo_imageout($I, $extension);
	}
	
	exit;
}


function bo_tile_headers($update_interval, $last_update_time, $caching)
{
	if (headers_sent())
		return;
		
	$exp_time    = floor( ($last_update_time + 60 * $update_interval) / 60) * 60;

	if ($exp_time - time() < 10)
        $exp_time = ceil((time()+1) / 60) * 60;	
		
	//Headers
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_update_time)." GMT");
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
}



function bo_get_tile_dim($x,$y,$zoom, $size=BO_TILE_SIZE)
{
	$tilesZoom = ((1 << $zoom) * 256 / $size);
	$lonW = 360.0 / $tilesZoom;
	$lon = -180 + ($x * $lonW);

	$MtopLat = $y / $tilesZoom;
	$MbottomLat = $MtopLat + 1 / $tilesZoom;
	$lat  = (180 / M_PI) * ((2 * atan(exp(M_PI * (1 - (2 * $MbottomLat))))) - (M_PI / 2));
	$lat2 = (180 / M_PI) * ((2 * atan(exp(M_PI * (1 - (2 * $MtopLat)))))    - (M_PI / 2));

	//echo " $lat / $lat2 --- $lon / ".($lon+$lonW)." "; exit;
	
	$lon = fmod($lon, 360);
	
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

function bo_tile_positions_all($tile_size)
{
	return pow(2, pow($tile_size/256, 2)) - 1;
}


function bo_tile_check_na_positions($radius, $x, $y, $zoom, $tile_size, $lat1, $lon1, $lat2, $lon2, $circle_center_lat, $circle_center_lon, $caching)
{
	//Step 1: Easy and fast way to detect tile outside of square around circle
	list($max_lat, ) = bo_distbearing2latlong($radius, 0  , $circle_center_lat, $circle_center_lon);
	list(, $max_lon) = bo_distbearing2latlong($radius, 90 , $circle_center_lat, $circle_center_lon);
	if ( ($lat1 > $max_lat && $lat2 > $max_lat) || ($lon1 > $max_lon && $lon2 > $max_lon) )
		return bo_tile_positions_all($tile_size);
		
	list($min_lat, ) = bo_distbearing2latlong($radius, 180, $circle_center_lat, $circle_center_lon);
	list(, $min_lon) = bo_distbearing2latlong($radius, 270, $circle_center_lat, $circle_center_lon);
	if ( ($lat1 < $min_lat && $lat2 < $min_lat) || ($lon1 < $min_lon && $lon2 < $min_lon) )
		return bo_tile_positions_all($tile_size);
		
	//Step 2: Closer look, find center, split tile into 4 tiles
	$x = $x*2;
	$y = $y*2;
	list($dummy1, $dummy2, $tile_center_lat, $tile_center_lon) = bo_get_tile_dim($x, $y+1, $zoom, $tile_size/2);
	$bear = bo_latlon2bearing($tile_center_lat, $tile_center_lon, $circle_center_lat, $circle_center_lon);

	
	//Step 2b: Find closest/farthest edge of tile
	if (0 <= $bear && $bear < 90) //tile is NE of center
	{
		$tile_check_lat_close = $lat1;
		$tile_check_lon_close = $lon1;
		$circle_check_lat_border = $lat1 < $max_lat;
		$circle_check_lon_border = $lon1 < $max_lon;
	}
	else if (90 <= $bear && $bear < 180) //tile is SE of center
	{
		$tile_check_lat_close = $lat2;
		$tile_check_lon_close = $lon1;
		$circle_check_lat_border = $lat2 > $min_lat;
		$circle_check_lon_border = $lon1 < $max_lon;
	}
	else if (180 <= $bear && $bear < 270) //tile is SW of center
	{
		$tile_check_lat_close = $lat2;
		$tile_check_lon_close = $lon2;
		$circle_check_lat_border = $lat2 > $min_lat;
		$circle_check_lon_border = $lon2 > $min_lon;
	}
	else  //tile is NW of center
	{
		$tile_check_lat_close = $lat1;
		$tile_check_lon_close = $lon2;
		$circle_check_lat_border = $lat1 < $max_lat;
		$circle_check_lon_border = $lon2 > $min_lon;
	}
	
	//Step 2c: Distance of edges
	$dist_close = bo_latlon2dist($tile_check_lat_close, $tile_check_lon_close, $circle_center_lat, $circle_center_lon);
	
	
	
	//Step 2d: Closest edge is outside
	$na_positions = 0;
		
	
	
	//special case: center of circle is bewteen min/max height or min/max width of tile
	// -> tile border is a tangent to circle
	$tile_tangent = ($lat1 < $circle_center_lat && $circle_center_lat < $lat2 && $circle_check_lon_border)
				 || ($lon1 < $circle_center_lon && $circle_center_lon < $lon2 && $circle_check_lat_border);

	
	// OR closest edge is inside circle
	//  ===> border intersects with circle
	if ($dist_close < $radius || $tile_tangent)
	{
	
		//if tile is bigger than 256pixels, then check for each area 
		//output a message on blank parts of the tile
		if ($tile_size > 256)
		{
			//todo: works only for 512px tiles, not bigger!
			$res = bo_tile_check_na_positions($radius, $x, $y, $zoom, $tile_size/2, $tile_center_lat, $lon1, $lat2, $tile_center_lon, $circle_center_lat, $circle_center_lon, $caching);
			$na_positions |= $res << 0;
			$res = bo_tile_check_na_positions($radius, $x+1, $y, $zoom, $tile_size/2, $tile_center_lat, $tile_center_lon, $lat2, $lon2, $circle_center_lat, $circle_center_lon, $caching);
			$na_positions |= $res << 1;
			$res = bo_tile_check_na_positions($radius, $x, $y+1, $zoom, $tile_size/2, $lat1, $lon1, $tile_center_lat, $tile_center_lon, $circle_center_lat, $circle_center_lon, $caching);
			$na_positions |= $res << 2;
			$res = bo_tile_check_na_positions($radius, $x+1, $y+1, $zoom, $tile_size/2, $lat1, $tile_center_lon, $tile_center_lat, $lon2, $circle_center_lat, $circle_center_lon, $caching);
			$na_positions |= $res << 3;
		}
	}
	else if (!($min_lat > $lat1 && $max_lat < $lat2 && $min_lon > $lon1 && $max_lon < $lon2))
	{
		//if whole circle doesn't lie inside the tile
		return bo_tile_positions_all($tile_size);
	}
	
	
	return $na_positions;
}


function bo_tile_get_subcoordinates($x, $y, $zoom, $tile_size_from, $tile_size_to)
{
	$count = $tile_size_from / $tile_size_to;
	
	if ($count <= 1)
	{
		return array(0 => array(0, 0));
	}
	
	
	for ($i=0; $i< $count; $i++)
	{
		$sub_x = $x * $count;
		$sub_y = $y * $count + $i;
		
		list($lat1, $lon1, $lat2, $lon2) = bo_get_tile_dim($sub_x, $sub_y, $zoom, $tile_size_to);
		
		for ($j=0; $j< $count; $j++)
		{
			$dlon = ($lon2-$lon1);
			$sub_tiles[$i*$count + $j] = array($j, $i, $lat1, $lon1 + $dlon*$j, $lat2, $lon1 + $dlon*($j+1));
		}
	}
	
	return $sub_tiles;
}

?>