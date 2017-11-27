<?php


function bo_show_archive_map()
{
	global $_BO;

	require_once 'functions_html.inc.php';
	
	$ani_div = intval(BO_ANIMATIONS_INTERVAL);
	$ani_pic_range = intval(BO_ANIMATIONS_STRIKE_TIME);
	$ani_default_range = intval(BO_ANIMATIONS_DEFAULT_RANGE);
	$ani_max_range = intval(BO_ANIMATIONS_MAX_RANGE);
	$ani_delay = BO_ANIMATIONS_WAITTIME;
	$ani_delay_end = BO_ANIMATIONS_WAITTIME_END;
	
	$map = isset($_GET['bo_map']) ? $_GET['bo_map'] : -1;
	$year = intval($_GET['bo_year']);
	$month = intval($_GET['bo_month']);
	$day = intval($_GET['bo_day']);
	$day_add = intval($_GET['bo_day_add']);
	$ani = isset($_GET['bo_animation']) && $_GET['bo_animation'] !== '0' ? 1 : 0;
	$ani_preset = $_GET['bo_animation'] && $_GET['bo_animation'] != 1 ? trim($_GET['bo_animation']) : '';
	$hour_from = (int)($_GET['bo_hour_from']);
	$minute_from = fmod($_GET['bo_hour_from'], 1) * 60;
	$hour_range = (float)($_GET['bo_hour_range']);
	$map_changed = isset($_GET['bo_oldmap']) && $map != $_GET['bo_oldmap'];
	$ani_changed = isset($_GET['bo_oldani']) && $ani != $_GET['bo_oldani'];
	
	//Map
	$select_map = bo_archive_select_map($map);
		
	//image dimensions
	$img_dim = bo_archive_get_dim_html($map);
	
	//Config
	$cfg     = $_BO['mapimg'][$map];
	$ani_cfg = $_BO['mapimg'][$map]['animation'];
	$mapname = _BL($cfg['name'], false, BO_CONFIG_IS_UTF8);
	
	//Animation-config
	if (isset($ani_cfg['force']) && $ani_cfg['force'])
	{
		$ani_forced = true;
		$ani = true;
	}
	
	if (isset($ani_cfg['range']))
		$ani_pic_range = $ani_cfg['range'];

	if (isset($ani_cfg['default_range']))
		$ani_default_range = $ani_cfg['default_range'];

	if (isset($ani_cfg['max_range']))
		$ani_max_range = $ani_cfg['max_range'];

	if (isset($ani_cfg['delay']))
		$ani_delay = $ani_cfg['delay'];
		
	if (isset($ani_cfg['delay_end']))
		$ani_delay_end = $ani_cfg['delay_end'];
	
	if (isset($ani_cfg['interval']))
		$ani_div = $ani_cfg['interval'];

	//Defaults...
	if (isset($cfg['maxrange']) && intval($cfg['maxrange'])) //max. time range in one pic
		$max_range = $cfg['maxrange'];
	else
		$max_range = BO_SMAP_MAX_RANGE;

	if (isset($cfg['hoursinterval']) && $cfg['hoursinterval'] > 0) //interval of hours
		$hours_interval = $cfg['hoursinterval'];
	elseif ($ani)
		$hours_interval = BO_ANIMATIONS_RANGE_STEP;
	else
		$hours_interval = BO_SMAP_RANGE_STEP;
		
	//Date
	if (!$year || $year < 0)
		$year = date('Y');

	if (!$month || $month < 0)
		$month = date('m');

	if (!$day)
		$day = date('d') - 1;

	if ($_GET['bo_prev'])
		$day_add = -1;
	else if ($_GET['bo_next'])
		$day_add = +1;
	
	//Hours & Range
	if ($ani)
	{
		//now is default for maps with changing backgrounds
		//if (!$ani_preset && isset($cfg['file_time']))
		//	$ani_preset = 'now';
		
		if ($ani_changed || $ani_preset)
		{
			if ($ani_preset == 'now')
			{
				$end 	= floor(time()/$hours_interval/3600) * $hours_interval*3600 - $ani_pic_range * 60;
				$start 	= $end - $ani_default_range*3600;
				
				if (!is_float($hours_interval))
				{
					$hour_range 	= $ani_default_range + intval((time()/3600 - floor(time()/3600))*3600) / 3600;
					$minute_from = 0;
				}
				else
				{
					$minute_from 	= (int)date('i', $start);
					$hour_range 	= $ani_default_range + $hours_interval;
				}
				
				$hour_from   	= (int)date('H', $start);
				$year      		= (int)date('Y', $start);
				$month     		= (int)date('m', $start);
				$day       		= (int)date('d', $start);
			}
			elseif ($ani_preset == 'day')
			{
				$hour_from = 0;
				$hour_range = 24;
			}
			else
			{
				$hour_from = 12;
				$hour_range = $ani_default_range;
			}
		}
		
		if ($hour_range < $ani_pic_range / 60)
			$hour_range = round($ani_pic_range / 60);
		
		if ($hour_range > $ani_max_range)
			$hour_range = $ani_max_range;
		
		$max_range = $ani_max_range;
	}
	elseif (!$ani)
	{
		if ($ani_changed)
		{		
			$hour_from = 0;
		}
		
		if ($map_changed || $ani_changed)
		{
			$hour_range = $max_range < 24 || $cfg['trange'] < 24 ? min($cfg['trange'], $hour_range) : 24;
		}
	}
	

	if ($_GET['bo_prev_hour'])
		$hour_from -= $hours_interval;
	else if ($_GET['bo_next_hour'])
		$hour_from += $hours_interval;

	$hour_from = floor($hour_from / $hours_interval) * $hours_interval;
		
	//Set to correct time
	$time      = mktime($hour_from,$minute_from,0,$month,$day+$day_add,$year);
	
	if ($time > time())
		$time = time() - $hour_range * 3600;
	
	$year      = date('Y', $time);
	$month     = date('m', $time);
	$day       = date('d', $time);
	$hour_from = date('H', $time) + $minute_from/60;

	//show time period select?
	$show_range_sel = $ani || (!$ani && !isset($cfg['file_time_search']) && !isset($cfg['overlays']));
	
	//use standard time period
	if (!$show_range_sel && $cfg['trange'] != 24)
		$hour_range = $hours_interval;
	
	//min/max strike-time
	$row = BoDb::query("SELECT MIN(time) mintime, MAX(time) maxtime FROM ".BO_DB_PREF."strikes")->fetch_assoc();
	$strikes_available = $row['mintime'] > 0 || $row['maxtime'] > 0;
	$start_time = strtotime($row['mintime'].' UTC');
	$end_time = strtotime($row['maxtime'].' UTC');
	
	
	if (isset($_GET['bo_oldmap']) || isset($_GET['bo_oldani']) 
		|| isset($_GET['bo_next']) || isset($_GET['bo_prev'])
		|| isset($_GET['bo_next_hour']) || isset($_GET['bo_prev_hour'])
		|| isset($_GET['bo_day_add'])
		|| (isset($_GET['bo_animation']) && !$ani) 
		|| (!$show_range_sel && isset($_GET['bo_hour_range'])) 
		)
	{
		$url = '';
		
		$url .= '&bo_year='.$year;
		$url .= '&bo_month='.$month;
		$url .= '&bo_day='.$day;
		$url .= '&bo_hour_from='.$hour_from;
		
		if ($show_range_sel)
			$url .= '&bo_hour_range='.$hour_range;
		
		if ($ani) 
			$url .= '&bo_animation=1';
		
		bo_try_redirect(array('bo_year', 'bo_month', 'bo_day', 'bo_day_add', 'bo_oldmap', 'bo_oldani', 'bo_animation', 'bo_next', 'bo_prev', 'bo_next_hour', 'bo_prev_hour', 'bo_hour_range', 'bo_hour_from'), $url);
	}
	
	//Output
	echo '<div id="bo_arch_maps">';
	
	if ($strikes_available)
	{
		echo '<p class="bo_general_description" id="bo_archive_density_info">';
		echo strtr(_BL('archive_map_info'), array('{DATE_START}' => _BD($start_time),'{DATE_END}' => _BD($end_time)));
		echo '</p>';

		echo '<a name="bo_arch_strikes_maps_form"></a>';
		echo '<form action="?#bo_arch_strikes_maps_form" method="GET" class="bo_arch_strikes_form" id="bo_arch_strikes_maps_form">';
		echo bo_insert_html_hidden(array('bo_map', 'bo_year', 'bo_month', 'bo_day', 'bo_animation', 'bo_day_add', 'bo_hour_from', 'bo_next', 'bo_prev', 'bo_next_hour', 'bo_prev_hour', 'bo_get', 'bo_oldmap', 'bo_oldani'));
		echo '<input type="hidden" name="bo_oldmap" value="'.$map.'">';
		echo '<input type="hidden" name="bo_oldani" value="'.($ani ? 1 : 0).'">';
		
		echo '<fieldset>';
		echo '<legend>'._BL('legend_arch_strikes').'</legend>';
		echo $select_map;

		echo '<span class="bo_form_descr">'._BL('Date').':</span> ';
		echo '<select name="bo_year" id="bo_arch_strikes_select_year">';
		for($i=date('Y', $start_time); $i<=date('Y');$i++)
			echo '<option value="'.$i.'" '.($i == $year ? 'selected' : '').'>'.$i.'</option>';
		echo '</select>&nbsp;';

		echo '<select name="bo_month" id="bo_arch_strikes_select_month">';
		for($i=1;$i<=12;$i++)
			echo '<option value="'.$i.'" '.($i == $month ? 'selected' : '').'>'._BL(date('M', strtotime("2000-$i-01"))).'</option>';
		echo '</select>&nbsp;';

		echo '<select name="bo_day" id="bo_arch_strikes_select_day" onchange="submit()">';
		for($i=1;$i<=31;$i++)
			echo '<option value="'.$i.'" '.($i == $day ? 'selected' : '').'>'.$i.'</option>';
		echo '</select>';

		echo '&nbsp;<input type="submit" name="bo_prev" value=" &lt; " id="bo_archive_maps_prevday" class="bo_form_submit">';
		echo '&nbsp;<input type="submit" name="bo_next" value=" &gt; " id="bo_archive_maps_nextday" class="bo_form_submit">';
		echo '<input type="submit" value="'._BL('update map').'" id="bo_archive_maps_submit" class="bo_form_submit">';

		echo '<div class="bo_input_container">';
		
		echo '<span class="bo_form_descr">'._BL('Time range').':</span> ';
		
		echo '<select name="bo_hour_from" id="bo_arch_strikes_select_hour_from"';
		echo !$show_range_sel ? ' onchange="submit()"' : '';
		echo '>';
		for($i=0;$i<=23;$i+=$hours_interval)
		{
			echo '<option value="'.$i.'" '.(floor($i) == floor($hour_from) && fmod($i, 1)*60 == $minute_from ? 'selected' : '').'>';
			
			if (is_float($hours_interval))
				echo bo_hours($i)." "._BL('oclock');
			else
				echo $i.' '._BL('oclock');
			
			echo '</option>';
		}
		echo '</select>';

		if (!is_float($hours_interval) || $hours_interval > 1)
		{
			echo '&nbsp;<input type="submit" name="bo_prev_hour" value=" &lt; " id="bo_archive_maps_prevhour" class="bo_form_submit">';
			echo '&nbsp;<input type="submit" name="bo_next_hour" value=" &gt; " id="bo_archive_maps_nexthour" class="bo_form_submit">';
		}
		
		if ($show_range_sel)
		{
			echo ' <select name="bo_hour_range" id="bo_arch_strikes_select_hour_to" '.($ani ? '' : ' onchange="submit()"').'>';
			for($i=$hours_interval;$i<=$max_range;$i+=$hours_interval)
			{
				echo '<option value="'.$i.'" ';
				
				if (is_float($hours_interval))
				{
					echo $i == intval($hour_range / $hours_interval) * $hours_interval  ? 'selected' : '';
					echo '>+ '.bo_hours($i)." h";
				}
				else
				{
					echo (int)$i == (int)$hour_range ? 'selected' : '';
					echo '>+'.$i.' '._BL('hours');
				}

				echo '</option>';
			}
			echo '</select> ';
		}
		else
		{
			echo '<input type="hidden" name="bo_hour_range" value="'.$hours_interval.'">';
		}
		
		if ($ani_div)
		{
			echo ' &nbsp;&nbsp;&nbsp; ';
			echo '<span class="bo_form_descr">'._BL('Animation').':</span> ';
			echo '<input type="radio" name="bo_animation" value="0" id="bo_archive_maps_animation_off" class="bo_form_radio" '.(!$ani ? ' checked' : '').' onclick="bo_enable_timerange(false, true);" '.($ani_forced ? ' disabled' : '').'>';
			echo '<label for="bo_archive_maps_animation_off">'._BL('Off').'</label>';
			echo '<input type="radio" name="bo_animation" value="'.($ani_preset ? htmlentities($ani_preset) : 1).'" id="bo_archive_maps_animation_on" class="bo_form_radio" '.($ani ? ' checked' : '').' onclick="bo_enable_timerange(true, true);">';
			echo '<label for="bo_archive_maps_animation_on">'._BL('On').'</label>';
		}
		
		echo '</div>';	
		
		
		echo '<div class="bo_input_container">';

		echo '<div class="bo_arch_map_form_links">';
		echo '<span class="bo_form_descr">';
		echo _BL('Yesterday').': &nbsp; ';
		echo '</span>';
		
		if (!$ani_cfg['force'])
			echo ' &nbsp; <a href="'.bo_insert_url('bo_*').'&bo_map='.$map.'&bo_day_add=0#bo_arch_strikes_maps_form" >'._BL('Picture').'</a> ';
		
		if ($ani_div)
			echo ' &nbsp; <a href="'.bo_insert_url('bo_*').'&bo_map='.$map.'&bo_day_add=0&bo_hour_from=0&bo_hour_range=24&bo_animation=day#bo_arch_strikes_maps_form" >'._BL('Animation').'</a> ';
		
		echo '  &nbsp;  &nbsp; &nbsp; ';
		
		echo '<span class="bo_form_descr">';
		echo _BL('Today').': &nbsp; ';
		echo '</span>';
		
		if (!$ani_cfg['force'])
			echo ' &nbsp; <a href="'.bo_insert_url('bo_*').'&bo_map='.$map.'&bo_day_add=1#bo_arch_strikes_maps_form" >'._BL('Picture').'</a> ';
		
		if ($ani_div)
			echo ' &nbsp; <a href="'.bo_insert_url('bo_*').'&bo_map='.$map.'&bo_day_add=1&bo_hour_from=0&bo_hour_range=24&bo_animation=day#bo_arch_strikes_maps_form" >'._BL('Animation').'</a> ';

		echo '  &nbsp;  &nbsp; &nbsp; ';
		
		echo '<span class="bo_form_descr">';
		echo _BL('Now').': &nbsp; ';
		echo '</span>';
		
		if (!$ani_cfg['force'])
			echo ' &nbsp; <a href="'.bo_insert_url(array('bo_page', 'bo_*')).'&bo_showmap='.$map.'" >'._BL('Picture').'</a> ';
		
		if ($ani_div)
			echo ' &nbsp; <a href="'.bo_insert_url('bo_*').'&bo_map='.$map.'&bo_animation=now" >'._BL('Animation').'</a> ';
			
		echo '</div>';
		
		
		echo '</div>';	
		
		echo '</fieldset>';
		echo '</form>';
	}
	
	if ($cfg['date_min'] && ($min = strtotime($cfg['date_min'])))
	{
		$start_time = $min;
		$end_time   = max($start_time, $end_time);
	}
	
	echo '<div id="bo_arch_map_container_all">';
	
	if ($cfg['archive'])
	{
		if ($_BO['mapimg'][$map]['header'])
			echo '<div class="bo_map_header">'._BC($_BO['mapimg'][$map]['header'], true, BO_CONFIG_IS_UTF8).'</div>';
	
		echo '<div style="display:inline-block;" id="bo_arch_maplinks_container">';

		if ($ani)
		{
			echo '<div class="bo_arch_map_links">';
			
			echo ' <a href="javascript:bo_animation_prev();" id="bo_animation_doprev">&lt;&nbsp;'._BL('ani_prev').'</a>';
			echo ' <a href="javascript:bo_animation_pause();" id="bo_animation_dopause">'._BL('ani_pause').'</a>';
			echo ' <a href="javascript:bo_animation_next();" id="bo_animation_donext">'._BL('ani_next').'&nbsp;&gt;</a>';
			
			echo '</div>';
		}
		
		echo '<div style="position:relative;display:inline-block; min-width: 300px; " id="bo_arch_map_container">';
		
		if (!$strikes_available || $time < floor($start_time/3600/24)*3600*24 || $time > $end_time + 3600 * 24)
		{
			if ($strikes_available)
				$text = _BL('arch_select_dates_between');
			else
				$text = _BL('no_lightning_data');
				
			$img_file = bo_bofile_url().'?map='.$map.'&blank&blank_background'.bo_lang_arg('map');
			
			echo '<div style="position:relative;'.bo_archive_get_dim_css($map).'" id="bo_arch_map_nodata">';
			echo '<img style="position:absolute;background-image:url(\''.bo_bofile_url().'?image=wait\');" '.$img_dim.' id="bo_arch_map_noimg" src="'.$img_file.'">';
			echo '<div style="position:absolute;top:0px;left:0px" id="bo_arch_map_nodata_white"></div>';
			echo '<div style="position:absolute;top:0px;left:0px" id="bo_arch_map_nodata_text">';
			echo '<p>';
			echo strtr($text, array('{START}' => _BD($start_time), '{END}' => _BD($end_time) ));
			echo '</p>';
			echo '</div>';
			echo '</div>';
		}
		else if ($ani)
		{

			//use transparency?
			if ($ani_cfg['transparent'] === false)
			{
				$img_file = null;
				$bo_file_url = bo_bofile_url().'?map='.$map.bo_lang_arg('map').'&date=';
			}
			else
			{
				$img_file = bo_bofile_url().'?map='.$map.'&blank'.bo_lang_arg('map');
				$bo_file_url = bo_bofile_url().'?map='.$map.'&transparent'.bo_lang_arg('map').'&date=';
			}
		
			$images = array();
			for ($i=$hour_from*60; $i< ($hour_from+$hour_range) * 60; $i+= $ani_div)
			{
				$time = strtotime("$year-$month-$day 00:00:00 +$i minutes");
				$images[] .= gmdate('YmdHi', $time).'-'.$ani_pic_range;

				if ($time > $end_time - $ani_pic_range * 60)
					break;
			}

			$alt = _BL('Lightning map').' '.$mapname.' '._BD($time).' ('._BL('Animation').')';
			
			bo_insert_animation_js($images, $bo_file_url, $img_file, $ani_delay, $ani_delay_end, $img_dim, $alt);
		}
		else
		{
			
			if ($hour_range == 24 && $hour_from == 0)
			{
				$date_arg = sprintf('%04d%02d%02d', $year, $month, $day);
			}
			else
			{
				//image url needs UTC-time!
				$uyear      = gmdate('Y', $time);
				$umonth     = gmdate('m', $time);
				$uday       = gmdate('d', $time);
				$uhour_from = gmdate('H', $time);
				$umin_from  = gmdate('i', $time);
				$date_arg = sprintf('%04d%02d%02d', $uyear, $umonth, $uday);
				
				if ($hour_range)
					$date_arg .= sprintf('%02d', $uhour_from).sprintf('%02d', $umin_from).'-'.($hour_range*60);
			}
		
			$alt = _BL('Lightning map').' '.$mapname.' '._BD($time);
			$img_file = bo_bofile_url().'?map='.$map.'&date='.$date_arg.bo_lang_arg('map');
			echo '<img style="position:relative;background-image:url(\''.bo_bofile_url().'?image=wait\');" '.$img_dim.' id="bo_arch_map_img" src="'.$img_file.'" alt="'.htmlspecialchars($alt).'">';
		}
		
		if ($_BO['mapimg'][$map]['footer'])
			echo '<div class="bo_map_footer">'._BC($_BO['mapimg'][$map]['footer'], true, BO_CONFIG_IS_UTF8).'</div>';
		
		echo '</div>';
		echo '</div>';
		
	}

	echo '</div>';
	

?>
<script type="text/javascript">
function bo_enable_timerange(enable, s)
{
	//document.getElementById('bo_arch_strikes_select_hour_from').disabled=!enable;
	//document.getElementById('bo_arch_strikes_select_hour_to').disabled=!enable;
	if (s) document.getElementById('bo_arch_strikes_maps_form').submit();
}
bo_enable_timerange(<?php echo $ani ? 'true' : 'false'; ?>);
</script>
<?php
	
	
	echo '</div>';
	
}



function bo_show_archive_search()
{
	global $_BO;
	
	require_once 'functions_dynmap.inc.php';
	
	$radius = $_BO['radius'] * 1000;
	$max_count = intval(BO_ARCHIVE_SEARCH_STRIKECOUNT);
	$select_count = $max_count;
	$perm = (bo_user_get_level() & BO_PERM_NOLIMIT);
	
	
	echo '<div id="bo_archive">';
	echo '<p class="bo_general_description" id="bo_archive_search_info">';
	echo strtr(_BL('archive_search_info'), array('{COUNT}' => $perm ? '' : $max_count));
	echo '</p>';
	echo '<h4>'._BL('Map').'</h4>';
	
	echo '<input id="bo_gmap_search" class="bo_gmap_controls" type="text" placeholder="'._BL('Search...').'">';
	echo '<div id="bo_gmap" class="bo_map_archive"></div>';

	
	if ($_GET['bo_lat'])
	{
		$lat = (double)$_GET['bo_lat'];
		$lon = (double)$_GET['bo_lon'];
		$map_lat = (double)$_GET['bo_map_lat'];
		$map_lon = (double)$_GET['bo_map_lon'];
		$zoom = (int)$_GET['bo_map_zoom'];
		$delta_dist = (int)$_GET['bo_dist'];
		$getit = isset($_GET['bo_get']);
		
		if ( $perm && (int)$_GET['bo_count'])
		{
			$select_count = (int)$_GET['bo_count'];
			$time_from = trim($_GET['bo_time_from']);
			$time_to = trim($_GET['bo_time_to']);
			$utime_from = 0;
			$utime_to = 0;
			
			if (preg_match('/([0-9]{2,4})(-([0-9]{2}))?(-([0-9]{2}))? *([0-9]{2})?(:([0-9]{2}))?(:([0-9]{2}))?/', $time_from, $r))
				$utime_from = mktime($r[6], $r[8], $r[10], $r[3], $r[5], $r[1]);
			else
				$time_from = '';
				
			if (preg_match('/([0-9]{2,4})(-([0-9]{2}))?(-([0-9]{2}))? *([0-9]{2})?(:([0-9]{2}))?(:([0-9]{2}))?/', $time_to, $r))
				$utime_to = mktime($r[6], $r[8], $r[10], $r[3], $r[5], $r[1]);
			else
				$time_to = '';
				
		}
		//lat,lon is limited to a region
		elseif (!$perm && bo_latlon2dist($lat, $lon, BO_LAT, BO_LON) > $radius && $radius)
		{
			//marker is too far away from home
			$getit = false;
			
			echo '<p>'._BL('search_outside_radius').'</p>';
		}
		//circle radius is limited
		elseif (!$perm && $delta_dist > BO_ARCHIVE_SEARCH_RADIUS_MAX * 1000)
		{
			$delta_dist = BO_ARCHIVE_SEARCH_RADIUS_MAX * 1000;
		}
	}
	elseif ($perm && $_GET['bo_strike_id'])
	{
		$getit = true;
		$get_by_id = intval($_GET['bo_strike_id']);
		$zoom = 4;
		$lat = $lon = false;
		$map_lat = BO_LAT;
		$map_lon = BO_LON;
	}
	else
	{
		$map_lat = $lat = BO_LAT;
		$map_lon = $lon = BO_LON;
		$zoom = BO_DEFAULT_ZOOM_ARCHIVE;
		$delta_dist = BO_ARCHIVE_SEARCH_RADIUS_DEFAULT * 1000;
	}



	
	if ($radius && $delta_dist > $radius)
		$delta_dist = $radius;
	
	/*** Get data from Database ***/

	if ($getit)
	{
		$time_min = 0;
		$time_max = 0;
		$count = 0;
		$text = '';
		$more_found = false;
		
		$sql_where = '';
		if ($get_by_id)
		{
			$sql_where .= " AND s.id='$get_by_id' ";
		}
		else
		{
			//max and min latitude for strikes
			list($str_lat_min, $str_lon_min) = bo_distbearing2latlong($delta_dist * sqrt(2), 225, $lat, $lon);
			list($str_lat_max, $str_lon_max) = bo_distbearing2latlong($delta_dist * sqrt(2), 45, $lat, $lon);

			$sql_where .= " AND ".bo_strikes_sqlkey($index_sql, $utime_from, $utime_to, $str_lat_min, $str_lat_max, $str_lon_min, $str_lon_max);
			$sql_where .= ($radius ? "AND distance < $radius" : "");
			$sql_where .= " AND ". bo_sql_latlon2dist($lat, $lon, 's.lat', 's.lon').' <= '.$delta_dist.' ';
		}
		
		$sql = "SELECT  s.id id, s.distance distance, s.lat lat, s.lon lon, s.time time, s.time_ns time_ns, s.stations stations,
						s.current current, s.deviation deviation, s.type type, s.part part, s.raw_id raw_id
				FROM ".BO_DB_PREF."strikes s $index_sql
				WHERE 1
					$sql_where
				ORDER BY s.time DESC
				LIMIT ".intval($select_count + 1);

		$res = BoDb::query($sql);
		while($row = $res->fetch_assoc())
		{
			if ($count >= $select_count)
			{
				$more_found = true;
				break;
			}

			$time = strtotime($row['time'].' UTC');

			$description  = '<div class=\'bo_archiv_map_infowindow\'>';
			$description .= '<ul class=\'bo_archiv_map_infowindow_list\'>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Time').':</span><span class=\'bo_value\'> '._BDT($time, false).'.'.$row['time_ns']._BZ($time).'</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Deviation').':</span><span class=\'bo_value\'> '._BK($row['deviation'] / 1000, 1).'</span></li>';
			//$description .= '<li><span class=\'bo_descr\'>'._BL('Current').':</span><span class=\'bo_value\'> '._BN($row['current'], 1).'kA ('._BL('experimental').')</span></li>';
			if (bo_station_id() > 0)
				$description .= '<li><span class=\'bo_descr\'>'._BL('Participated').':</span><span class=\'bo_value\'> '.($row['part'] > 0 ? _BL('yes') : _BL('no')).'</span></li>';
				
			$description .= '<li><span class=\'bo_descr\'>'._BL('Participants').':</span><span class=\'bo_value\'> '.intval($row['stations']).'</span></li>';

			if ($perm)
				$description .= '<li><span class=\'bo_value\'><a href=\''.bo_insert_url(array('bo_show', 'bo_*'), 'strikes').'&bo_strike_id='.$row['id'].'\' target=\'_blank\'>'._BL('more').'<a></span></li>';

			$description .= '</ul>';

			if ($row['raw_id'] && BO_UP_INTVL_RAW > 0)
			{
				$alt = _BL('Signals');
				$description .= '<a href=\''.bo_bofile_url().'?bo_graph='.$row['raw_id'].bo_lang_arg('graph').'&bo_size=3\' target=\'_blank\'>';
				$description .= '<img src=\''.bo_bofile_url().'?bo_graph='.$row['raw_id'].bo_lang_arg('graph').'\' style=\'width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px\' class=\'bo_archiv_map_signal\' alt=\''.htmlspecialchars($alt).'\'>';
				$description .= '</a>';
			}

			$description .= '</div>';

			$text .= "\n".'lightnings['.$row['id'].']={'.
							'center: new google.maps.LatLng('.$row['lat'].','.$row['lon'].'), '.
							'participated: '.$row['part'].', '.
							'utime: '.$time.', '.
							'description: "'.$description.'" '.
							'};';

			if (!$time_max)
				$time_max = $time;

			$count++;
		}

		$time_min = $time;
		$time_int = ($time_max - $time_min) / 9;

		echo '<h4>'._BL('Result').'</h4>';
		echo '<ul>';

		if ($utime_from || $utime_to)
		{
			echo '<li><span class="bo_descr">'._BL('Time range').': </span> ';
			
			if ($utime_from)
				echo _BL('time_from').' '._BDT($utime_from).' ';
				
			if ($utime_to)
				echo _BL('time_to').' '._BDT($utime_to).' ';
			
			echo '</li>';
		}
		
		if ($radius && $dist > $radius)
		{
			echo '<li>'._BL('You have to place the pointer inside the red circle!').'</li>';
		}
		elseif ($count)
		{
			echo '<li>';
			echo '<span class="bo_descr">'._BL('Count').':</span> ';
			echo '<span class="bo_value">';
			echo $more_found ? _BL('More than') : _BL('Exact');
			echo ' '.$count.' ';
			echo $count == 1 ? _BL('Strike') : _BL('Strikes');
			echo ' '._BL('found');
			echo '</span>';
			echo '</li>';

			echo '<li><span class="bo_descr">'._BL('Oldest').':</span><span class="bo_value"> '._BDT($time_min).'</value></li>';
			echo '<li><span class="bo_descr">'._BL('Newest').':</span><span class="bo_value"> '._BDT($time_max).'</value></li>';
		}
		else
		{
			echo '<li>'._BL('No strikes found!').'</li>';
		}
		
		echo '</ul>';
	}

	if (!$get_by_id)
	{
		echo '<h4>'._BL('Search Options').'</h4>';

		echo '<form action="?" method="GET" class="bo_archive_form">';
		echo bo_insert_html_hidden(array('bo_lat', 'bo_lon', 'bo_map_zoom', 'bo_map_lat', 'bo_map_lon'));

		echo '<fieldset class="bo_archive_fieldset">';
		echo '<legend>'._BL('archive_legend').'</legend>';

		echo '<span class="bo_form_descr">'._BL('Coordinates').':</span>';
		echo '<input type="text" name="bo_lat" value="'._BC($lat).'" id="bo_archive_lat" class="bo_form_text bo_archive_latlon">';
		echo '<input type="text" name="bo_lon" value="'._BC($lon).'" id="bo_archive_lon" class="bo_form_text bo_archive_latlon">';

		echo '<span class="bo_form_descr">'._BL('Distance').' '.'('._BL('unit_meters').'):</span>';
		echo '<input type="text" name="bo_dist" value="'._BC($delta_dist).'" id="bo_archive_dist" class="bo_form_text bo_archive_dist">';
			
		if (bo_user_get_level() & BO_PERM_NOLIMIT)
		{
			echo '<span class="bo_form_descr">'._BL('Count').':';
			echo '<input type="text" name="bo_count" value="'._BC($select_count).'" id="bo_archive_count" class="bo_form_text bo_archive_count">';
			echo '</span>';
			
			echo '<span class="bo_form_descr">'._BL('Min time').':';
			echo '<input type="text" name="bo_time_from" value="'._BC($time_from).'" id="bo_archive_time_from" class="bo_form_text bo_archive_time_from">';
			echo '</span>';
			
			echo '<span class="bo_form_descr">'._BL('Max time').':';
			echo '<input type="text" name="bo_time_to" value="'._BC($time_to).'" id="bo_archive_time_to" class="bo_form_text bo_archive_time_to">';
			echo '</span>';
			
		}
		
		echo '<input type="hidden" name="bo_map_zoom" id="bo_map_zoom">';
		echo '<input type="hidden" name="bo_map_lat" id="bo_map_lat">';
		echo '<input type="hidden" name="bo_map_lon" id="bo_map_lon">';
		echo '<input type="hidden" name="bo_get">';
		
		echo '<input type="submit" value="'._BL('button_search').'" id="bo_archive_submit" class="bo_form_submit">';

		echo '</fieldset>';

		if (bo_user_get_level() & BO_PERM_NOLIMIT)
		{
			echo '<p class="bo_enter_time_hint">'._BL('enter_time_hint').'</p>';
		}
		
		echo '</form>';
	
	}
	
	echo '</div>';

?>
	<script type="text/javascript">

		var centerMarker;
		function bo_gmap_init2()
		{
			var markerOptions;
			var infowindow;
			var bounds = new google.maps.LatLngBounds();
			
			bo_map.setOptions({scrollwheel: true});
			
			<?php if ($lat !== false && $lon !== false) { ?>
			var myLatlng = new google.maps.LatLng(<?php echo  "$lat,$lon" ?>);

			centerMarker = new google.maps.Marker({
				position: myLatlng,
				draggable: true,
				map: bo_map,
				icon: '//maps.google.com/mapfiles/ms/micons/blue-dot.png'
			});

			google.maps.event.addListener(centerMarker, 'dragend', function() {
				document.getElementById('bo_archive_lat').value=this.getPosition().lat();
				document.getElementById('bo_archive_lon').value=this.getPosition().lng();
			});

			google.maps.event.addListener(bo_map, 'click', function(event) {
				centerMarker.setPosition(event.latLng);
				document.getElementById('bo_archive_lat').value=event.latLng.lat();
				document.getElementById('bo_archive_lon').value=event.latLng.lng();
			});

			google.maps.event.addListener(bo_map, 'dragend', function() {bo_gmap_map2form();});
			google.maps.event.addListener(bo_map, 'zoom_changed', function() {bo_gmap_map2form();});
			<?php } ?>
			
			<?php if ($getit && $lat !== false && $lon !== false) { ?>

			var boDistCircle = {
				clickable: false,
				strokeColor: "#5555ff",
				strokeOpacity: 0.5,
				strokeWeight: 1,
				fillColor: "#5555ff",
				fillOpacity: 0.1,
				map: bo_map,
				center: new google.maps.LatLng(<?php echo  "$lat,$lon" ?>),
				radius: <?php echo $delta_dist ?>
			};

			new google.maps.Circle(boDistCircle);

			<?php } ?>

			var lightnings = [ ];
			<?php echo  $text ?>

			var images = new Array();
			var img_count = 10;
			var d;
			var time_min=<?php echo intval($time_min) ?>;
			var time_int=<?php echo intval($time_int) ?>;
			
			for (var j=0;j<2;j++)
			{
				for (var i=0;i<img_count;i++)
				{
					d = i * 25;
					d = d.toString(16);
					d = d.length == 1 ? '0'+d : d;

					images[i+j*img_count] = new google.maps.MarkerImage("<?php echo  bo_bofile_url() ?>?bo_icon=ff"+d+"00"+(j ? '1' : ''),
									new google.maps.Size(11,11),
									new google.maps.Point(0,0),
									new google.maps.Point(5, 5));
				}
			}
			
			var color;
			var lmarker = Array();
			var infowindow = new google.maps.InfoWindow({
				content: '...'
			});
			var lcount=0;
			
			for (var lightning in lightnings)
			{
				if (time_int)
					color = Math.floor((lightnings[lightning].utime-time_min)/time_int);
				else
					color = 0;

				if (lightnings[lightning].participated > 0)
					color = color + img_count;
				
					
				markerOptions = {
				  map: bo_map,
				  position: lightnings[lightning].center,
				  flat: true,
				  icon: images[color],
				  content: lightnings[lightning].description
				};

				var marker = new google.maps.Marker(markerOptions);

				google.maps.event.addListener(marker, 'click', function() {
					infowindow.setContent(this.content);
					infowindow.open(bo_map, this);
				});
				
				bounds.extend(lightnings[lightning].center);
				lcount++;
			}

			if (lcount == 1 || (lcount > 0 && lcount <= <?php echo intval($select_count); ?>))
			{
				bounds.extend(new google.maps.LatLng(<?php echo  "$map_lat,$map_lon" ?>));
				bo_map.fitBounds(bounds);
			}
			
			<?php if ($lat !== false && $lon !== false) { ?>
			bo_gmap_map2form();
			<?php } ?>
		}




		function bo_gmap_map2form()
		{
			document.getElementById('bo_map_lat').value=bo_map.getCenter().lat();
			document.getElementById('bo_map_lon').value=bo_map.getCenter().lng();
			document.getElementById('bo_map_zoom').value=bo_map.getZoom();
		}

	</script>
<?php


	bo_insert_map(2, $map_lat, $map_lon, $zoom);
}



//Last raw data and strikes table
function bo_show_archive_table($show_strike_list = false, $lat = null, $lon = null, $fuzzy = null)
{
	require_once 'functions_html.inc.php';
	
	$perm = bo_user_get_level() & BO_PERM_ARCHIVE;

	$per_page = BO_ARCHIVE_TABLE_PER_PAGE;
	$max_pages = $perm ? 1E9 : 10;

	$page = intval($_GET['bo_action']);
	$lat = $_GET['bo_lat'];
	$lon = $_GET['bo_lon'];
	$zoom = intval($_GET['bo_zoom']);
	$only_strikes = $_GET['bo_only_strikes'] == 1;
	$only_participated = $_GET['bo_only_participated'] == 1;
	$strike_id = intval($_GET['bo_strike_id']);
	$strikes_before = intval($_GET['bo_strikes_before']);
	$date = $_GET['bo_datetime_start'];
	$region = $_GET['bo_region'];
	$map = isset($_GET['bo_map']) ? $_GET['bo_map'] : 0;
	$show_details = $_GET['bo_show_details'];
	$show_other_graphs = isset($_GET['bo_other_graphs']) && $perm;
	$own_station = bo_station_id() > 0 && bo_station_id() == $station_id;

	if ($perm)
	{
		$station_id = bo_get_current_stationid();
	}
	else
		$station_id = bo_station_id();
	
	$channels   = BoData::get('raw_channels');
	$raw_bpv    = BoData::get('raw_bitspervalue');
	$raw_values = BoData::get('raw_values');
	$station_info = bo_station_info($station_id);
	
	if ($page < 0)
		$page = 0;
	else if ($page > $max_pages)
		$page = $max_pages;
	
	$sql_where = '';
	$datetime_start = 0;
	$hours_back = 0;
	
	if (!$perm)
	{
		$show_strike_list = false;
		$strike_id = 0;
		$strike_id_to = 0;
	}
	else if ($date)
	{
		$datetime_start = strtotime($date);
	}
	
	echo '<form action="?#bo_arch_table_form" method="GET" class="bo_arch_table_form" id="bo_arch_tableform">';	
	
	if ($lat !== null && $lon !== null)
	{
		if (!$fuzzy)
		{
			if ($zoom)
				$fuzzy = 1/pow(2,$zoom)*5;
			else
				$fuzzy = 0.005;
		}
		
		$latS = $lat - $fuzzy;
		$latN = $lat + $fuzzy;
		$lonW = $lon - $fuzzy;
		$lonE = $lon + $fuzzy;
		
		$sql_where .= "AND ".bo_latlon2sql($latS, $latN, $lonW, $lonE); //" AND NOT (s.lat < '$latS' OR s.lat > '$latN' OR s.lon < '$lonW' OR s.lon > '$lonE') ";
		$show_strike_list = true;

		$hours_back = 24 * 7;
	}
	else if ($strike_id && $strikes_before)
	{
		//Special effects ;-)
		$hyps = intval($_GET['bo_hyps']);
		
		if (BO_ARCHIVE_STRIKE_INFO_ANIM <= 0)
			exit('Disabled');
		
		$images = array();
		for($i=$strike_id-$strikes_before;$i<=$strike_id;$i++)
			$images[] = $i;
		
		$img_file = bo_bofile_url().'?map='.$map.'&blank'.bo_lang_arg('map');
		$bo_file_url = bo_bofile_url().'?map='.$map.'&transparent'.bo_lang_arg('map').($hyps ? '&hyps' : '').'&strike_id=';

		echo bo_insert_html_hidden(array('bo_show_hyp'));
		echo '<a name="bo_arch_table_form"></a>';
		echo '<fieldset>';
		echo '<legend>'._BL('settings').'</legend>';
		echo '<input type="checkbox" name="bo_hyps" value="1" '.($hyps ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="bo_check_hyps">';
		echo '<label for="bo_check_hyps"> '._BL('check_show_hyps').'</label> &nbsp; ';
		echo '</fieldset>';
		
		echo '<div style="position:relative;display:inline-block; min-width: 300px; " id="bo_arch_map_container">';
		bo_insert_animation_js($images, $bo_file_url, $img_file);
		echo '</div>';

		echo '</form>';
		
		return;
	}
	else if ($strike_id)
	{
		$sql_where .= " AND s.id='$strike_id' ";
		$show_strike_list = true;
		$hours_back = time() / 3600;
	}
	else
	{
		//default is displaying own signals!
		$hours_back = 24;
		
		if (bo_station_id() > 0)
			$own_station = true;
	}
	
	$show_strike_list = true;
	if ($datetime_start)
	{
		$time_start = $datetime_start;
		$time_end   = $datetime_start + 3600 * $hours_back;
		$sort = 'ASC';
	}
	else if ($show_strike_list) // display strikes
	{
		$time_end = time() - 120;
		$time_start = $time_end - 3600 * $hours_back;
		$sort = 'DESC';
	}
	else //display signals
	{
		$time_end = 0;
		
		/*
		if (!isset($_GET['bo_action']) && !$date && $station_id > 0 && $station_id == bo_station_id())
		{
			$sql = "SELECT MAX(time) time 
					FROM ".BO_DB_PREF."strikes 
					WHERE raw_id IS NOT NULL 
					AND time > NOW() - INTERVAL ".max(30, BO_UP_INTVL_RAW)." MINUTE";
			$row = BoDb::query($sql)->fetch_assoc();
			if ($row['time'])
				$time_end = strtotime($row['time'].' UTC');
		}
		*/

		
		if (!$time_end && $station_id > 0 && $station_id == bo_station_id())
		{
			$row = BoDb::query("SELECT MAX(time) time FROM ".BO_DB_PREF."raw")->fetch_assoc();
			$time_end = strtotime($row['time'].' UTC');
		}
		
		//wait some time until signals available for download
		if (!$time_end || $time_end > time() - 120)
			$time_end = time() - 120;
		
		$time_start = $time_end - 3600 * $hours_back;
		$sort = 'DESC';
	}
	
	$date_start = gmdate('Y-m-d H:i:s', $time_start);
	$date_end   = gmdate('Y-m-d H:i:s', $time_end);
	
	if ($show_strike_list) // all strikes, maybe with own sigs
	{
		$table = 's';
		
		if ($station_id > 0 && !$own_station && !$strike_id)
		{
			$sql_join = BO_DB_PREF."strikes s 
						JOIN ".BO_DB_PREF."stations_strikes ss
						ON s.id=ss.strike_id AND ss.station_id='".$station_id."'";
		}
		elseif ($own_station)
		{
			//$sql_join = BO_DB_PREF."strikes s 
			//			LEFT OUTER JOIN ".BO_DB_PREF."raw r 
			//			ON s.raw_id=r.id ";

			$sql_join = BO_DB_PREF."strikes s ";
			$sql_where .= " AND s.part>0";
			
			//if ($only_participated)
			//	$sql_where .= " AND s.part>0 ";
				
			$own_station = false;
			$station_id = bo_station_id();
		}
		else
		{
			$sql_join = BO_DB_PREF."strikes s";
			if (!$strike_id)
				$station_id = false;
		}
	}
	elseif ($only_strikes) // own raw signals, only with strikes
	{
		$sql_join = BO_DB_PREF."raw r JOIN ".BO_DB_PREF."strikes s ON s.raw_id=r.id ";
		$table = 'r';
		$sql_where .= " AND s.raw_id > 0 ";
	}
	else // all own raw signals
	{
		$sql_join = BO_DB_PREF."raw r LEFT OUTER JOIN ".BO_DB_PREF."strikes s ON s.raw_id=r.id ";
		$table = 'r';
	}
	
	if ($table == 'r')
		$sql_where .= " AND $table.time BETWEEN '$date_start' AND '$date_end'";
	else
		$sql_where .= " AND ".bo_times2sql($time_start, $time_end);
	
	if (bo_user_get_id())
	{
		$stations = bo_stations();
	}
	
	$show_signal   = BO_ARCHIVE_SHOW_FIRST_SIGNAL === true || (!$own_station && $station_id > 0) || ($own_station && $raw_bpv == 8 && $raw_values > 10 && BO_UP_INTVL_RAW > 0);
	$show_spectrum = $show_signal && BO_ARCHIVE_SHOW_SPECTRUM;
	$show_xy_graph = $show_signal && (!$own_station || $channels > 1) && BO_ARCHIVE_SHOW_XY;
	
	if (0 && $own_station)
		$sql_raw = ",	r.id raw_id, r.time rtime, r.time_ns rtimens, r.data data,
						r.amp1 amp1, r.amp2 amp2, r.amp1_max amp1_max, r.amp2_max amp2_max";

	$count = 0;
	$sql = "SELECT  s.id strike_id, s.distance distance, s.lat lat, s.lon lon,
					s.deviation deviation, s.current current, s.type type,
					s.time stime, s.time_ns stimens, s.stations stations, s.part part,
					s.status status 
					$sql_raw
			FROM $sql_join
			WHERE 1
					$sql_where
					".bo_region2sql($region, $station_id)."
			ORDER BY $table.time $sort
			LIMIT ".($page * $per_page).", ".($per_page+1)."";
	$res = BoDb::query($sql);



	$page_nav = '';
	if ($res->num_rows > $per_page && $page < $max_pages)
	{
		if ($sort == 'DESC')
			$page_nav .= '<a href="'.bo_insert_url('bo_action', $page+1).'#bo_arch_table_form" class="bo_sig_prev" rel="nofollow">&lt; '._BL('Older').'</a>';
		else
			$page_nav .= '<a href="'.bo_insert_url('bo_action', $page+1).'#bo_arch_table_form" class="bo_sig_next" rel="nofollow">'._BL('Newer').' &gt</a>';
	}
	
	if ($page)
	{
		if ($sort == 'DESC')
			$page_nav .= '<a href="'.bo_insert_url('bo_action', $page-1).'#bo_arch_table_form" class="bo_sig_next" rel="nofollow">'._BL('Newer').' &gt;</a>';
		else
			$page_nav .= '<a href="'.bo_insert_url('bo_action', $page-1).'#bo_arch_table_form" class="bo_sig_prev" rel="nofollow">&lt; '._BL('Older').'</a>';
	}
		
	
	if ($show_strike_list)
	{
		echo '<p class="bo_general_description" id="bo_archive_striketable_info">';
		if ($perm)
			echo _BL('archive_striketable_info');
		else
			echo _BL('archive_striketable_info_guests');
		echo '</p>';
	}
	else
	{
		echo '<p class="bo_general_description" id="bo_archive_signaltable_info">';
		echo _BL('archive_signaltable_info');
		echo '</p>';
		bo_signal_info_list();
	}
	
	if ($strike_id)
	{
		echo bo_insert_html_hidden(array('bo_map'));
	}
	else
	{
		echo '<a name="bo_arch_table_form"></a>';
		echo bo_insert_html_hidden(array('bo_only_strikes', 'bo_action', 'bo_all_strikes', 'bo_show_details', 'bo_region', 'bo_datetime_start', 'bo_station_id'));

		if ($perm && $show_strike_list)
		{
			echo '<fieldset>';
			echo '<legend>'._BL('Station').'</legend>';
			echo _BL('Select station').':&nbsp;';
			echo bo_get_stations_html_select($station_id);
			echo '&nbsp;&nbsp; ';
			echo '</fieldset>';
		}
		
		
		if ($perm)
		{
		
		
			echo '<fieldset>';
			echo '<legend>'._BL('settings').'</legend>';
			
			if (!$show_strike_list)
			{
				echo '<input type="checkbox" name="bo_only_strikes" value="1" '.($only_strikes ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="check_only_strikes">';
				echo '<label for="check_only_strikes"> '._BL('check_only_strikes').'</label>&nbsp;&nbsp; ';
			}
			elseif ($own_station)
			{
				echo '<input type="checkbox" name="bo_only_participated" value="1" '.($only_participated ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="check_only_participated">';
				echo '<label for="check_only_participated"> '._BL('check_only_participated').'</label>&nbsp;&nbsp; ';
			}

			if (($show_strike_list || $only_strikes) && BO_STATION_STAT_DISABLE !== true)
			{
				echo '<input type="checkbox" name="bo_show_details" value="1" '.($show_details ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="check_show_details">';
				echo '<label for="check_show_details"> '._BL('Details').'</label> &nbsp; ';
			}
			
			echo ' &nbsp; <span class="bo_form_descr">'._BL('Time').':</span>&nbsp;';
			echo '<input type="text" name="bo_datetime_start" value="'._BC($date).'" id="bo_archive_date" class="bo_archive_date">&nbsp;&nbsp; ';
			
			if ($show_strike_list || $only_strikes)
			{
				$region_select = bo_get_select_region($region, $station_id);
	
				if ($region_select)
				{
					echo ' <span class="bo_form_descr">'._BL('Region').':&nbsp;';
					echo $region_select;
					echo '</span>&nbsp;&nbsp; ';
				}
			}
			
			if ($show_details)
				echo bo_archive_select_map($map);

			echo '&nbsp;&nbsp; <input type="submit" value="'._BL('Ok').'">';
			
			echo '</fieldset>';
		}
		
		

	}

	echo '<div class="bo_sig_navi">'.$page_nav.'</div>';
	echo '<table class="bo_sig_table';
	echo $show_spectrum ? ' bo_sig_table_spectrum' : '';
	echo $show_xy_graph ? ' bo_sig_table_xy_graph' : '';
	echo '">';
	
	while($row = $res->fetch_assoc())
	{
		if ($row['strike_id'] && $res->num_rows == 1)
			$strike_id = $row['strike_id'];

		$count++;
		$stime = strtotime($row['stime'].' UTC');
		$cdev_text = '';

		
		if ($own_station)
		{
			$bearing  = bo_latlon2bearing_initial($row['lat'], $row['lon']);
			$distance = $row['distance'];
		}
		elseif ($station_id && !$own_station)
		{
			$bearing  = bo_latlon2bearing_initial($row['lat'], $row['lon'], $station_info['lat'], $station_info['lon']);
			$distance = bo_latlon2dist($row['lat'], $row['lon'], $station_info['lat'], $station_info['lon']);
		}
		
		if ($row['raw_id'])
		{
			$rtime = strtotime($row['rtime'].' UTC') + 1;
		
			if ($row['strike_id'])
			{
				$time_diff = $rtime - $stime + ($row['rtimens'] - $row['stimens']) * 1E-9;
				$residual_time = $time_diff - $distance / BO_C;

				$cdev = $distance / $time_diff / BO_C;
				$cdev_text = _BN($residual_time*1E6, 1)._BL('µs');
				$cdev_text .= ' / '._BN($cdev, 4).'c';
				//$cdev_text .= ' / '.round(($cdev-1)*$distance).'m';
			}
		}
		

		

		
		
		//Calculate angles and distances 
		$loc_angle = null;
		if ((bo_user_get_level() & BO_PERM_ARCHIVE) && $row['strike_id'])
		{
			$s_dists = array(array(),array());
			$s_sdists = array(array(),array());
			$s_bears = array(array(),array());
			$participated_stations  = array();
			
			//Participated stations
			$participated_stations = array();
			$sql2 = "SELECT ss.station_id id
				FROM ".BO_DB_PREF."stations_strikes ss
				WHERE ss.strike_id='".$row['strike_id']."'";
			$res2 = BoDb::query($sql2);
			while ($row2 = $res2->fetch_assoc())
			{
				$participated_stations[ $row2['id'] ] = $stations[$row2['id']];
				$participated_stations[ $row2['id'] ]['part'] = true;
			}
			
			//own station only evaluated, but not participated
			if (!isset($participated_stations[$station_id]) && $row['raw_id'])
			{
				$participated_stations[ $station_id ] = $stations[$station_id];
				$participated_stations[ $station_id ]['part'] = false;
			}
			
			if (count($participated_stations))
			{

				foreach ($participated_stations as $sid => $dummy)
				{
					$s_dists[0][$sid] = bo_latlon2dist($row['lat'], $row['lon'], $participated_stations[$sid]['lat'], $participated_stations[$sid]['lon']);
					
					//bearing must be this way, as it is always relative to the stroke!
					$s_bears[0][$sid] = bo_latlon2bearing_initial($participated_stations[$sid]['lat'], $participated_stations[$sid]['lon'], $row['lat'], $row['lon']);
				}

				//Get stations that participated in calculation
				asort($s_dists[0]);
				$i=0;
				foreach($s_dists[0] as $sid => $dist)
				{
					if ($i < bo_participants_locating_max())
					{
						$s_dists[1][$sid] = $s_dists[0][$sid];
						$s_bears[1][$sid] = $s_bears[0][$sid];
					}
					else
						break;
						
					$i++;
				}

				//Calculate distances and angles for calc and non-calc stations
				for ($i=0;$i<=1;$i++)
				{
					
					//Distances between stations
					$participated_stations_tmp = $participated_stations;
					foreach($participated_stations as $sid1 => $d1)
					{
						foreach($participated_stations_tmp as $sid2 => $d2)
						{
							$s_sdists[$i][$sid1.'.'.$sid2] = bo_latlon2dist($d1['lat'], $d1['lon'], $d2['lat'], $d2['lon']);
						}
					}
					asort($s_sdists[$i]);

					//Locating angles
					asort($s_bears[$i]);
					end($s_bears[$i]);
					list($sid, $lastbear) = each($s_bears[$i]);
					$s_bear_diffs = array();
					$lastbear -=360;
					foreach($s_bears[$i] as $sid => $bear)
					{
						$s_bear_diffs[$sid] = $bear - $lastbear;
						$lastbear = $bear;
					}
					
					$loc_angle[$i] = 360 - max($s_bear_diffs);
				}
			}
		}


		
		echo '<tr>';

		echo '<td class="bo_sig_table_time">';
		echo '<span class="bo_descr">';
		echo ($show_strike_list ? _BL('Time') : _BL('Received')).': ';
		echo '</span>';
		echo '<span class="bo_value">';

		if ($show_strike_list)
			$ttime = _BDT($stime, false).'.'.sprintf('%09d', $row['stimens']).' '._BZ($stime);
		else
			$ttime = _BDT($rtime, false).'.'.sprintf('%09d', $row['rtimens']).' '._BZ($stime);

		if (!$strike_id && $perm && $row['strike_id'] && BO_STATION_STAT_DISABLE !== true)
		{
			echo '<a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'strikes').'&bo_station_id='.$station_id.'&bo_strike_id='.$row['strike_id'].'" target="_blank" ';
			echo '>'.$ttime.'</a>';
		}
		else
			echo $ttime;
		
		if ($perm && $row['strike_id'] && BO_ENABLE_ARCHIVE_SEARCH === true)
		{
			echo ' (<a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'search').'&bo_strike_id='.$row['strike_id'].'" target="_blank" ';
			echo '>'._BL('Map').'</a>)';

		}
		
		echo '</span>';
		echo '</td>';

		/***** Graphs *****/
		$alt = htmlspecialchars(_BL('rawgraph'));
		$url = bo_signal_url($station_id, $row['raw_id'], $stime, $row['stimens'], $distance, 
				array(	'id' => $row['strike_id'],
						'lat' => $row['lat'],
						'lon' => $row['lon']
				));
		
		if ($show_signal)
		{
			echo '<td rowspan="2" class="bo_sig_table_graph"  style="width:'.BO_GRAPH_RAW_W.'px;">';
			if ($row['raw_id'] || !$own_station)
			{
				echo '<a href="'.$url.'&bo_size=3" target="_blank">';
				echo '<img src="'.$url.'" style="width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px" alt="'.$alt.'" id="bo_graph_sig_'.$row['raw_id'].'" onmouseover="this.src+=\'&full\'" onmouseout="this.src=\''.$url.'\'">';
				echo '</a>';
			}
			else if ($row['strike_id'] && !$row['raw_id'] && $row['part'] > 0)
			{
				echo _BL('signal not found');
			}
			else
				echo _BL('No signal received');
				
			echo '</td>';
		}
		
		if ($show_spectrum)
		{
			echo '<td rowspan="2" class="bo_sig_table_graph"  style="width:'.BO_GRAPH_RAW_W.'px;">';
			if ($row['raw_id'] || !$own_station)
			{
				$spec_url = $url.'&bo_spectrum';
				 
				echo '<a href="'.$spec_url.'&bo_size=3" target="_blank">';
				echo '<img src="'.$spec_url.'" style="width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px" alt="'.$alt.'" id="bo_graph_spec_'.$row['raw_id'].'" onmouseover="this.src+=\'&full\'" onmouseout="this.src=\''.$spec_url.'\'">';
				echo '</a>';
			}
			elseif ($row['strike_id'] && !$row['raw_id'] && $row['part'] > 0)
			{
				echo _BL('signal not found');
			}
			else
				echo _BL('No signal received');
			
			echo '</td>';
		}

		if ($show_xy_graph)
		{
			echo '<td rowspan="2" class="bo_sig_table_graph"  style="width:'.BO_GRAPH_RAW_H.'px;">';
			if ($row['raw_id'] || !$own_station)
			{
				$xy_url = $url.'&bo_xy';
				echo '<a href="'.$xy_url.'&bo_size=3" target="_blank">';
				echo '<img src="'.$xy_url.'" style="width:'.BO_GRAPH_RAW_H.'px;height:'.BO_GRAPH_RAW_H.'px" alt="'.$alt.'" id="bo_graph_xy_'.$row['raw_id'].'" onmouseover="this.src+=\'&full\'" onmouseout="this.src=\''.$xy_url.'\'">';
				echo '</a>';
			}
			elseif ($row['strike_id'] && !$row['raw_id'] && $row['part'] > 0)
			{
				echo _BL('signal not found');
			}
			else
				echo _BL('No signal received');

			echo '</td>';
		}
		
		
		echo '</tr><tr>';

		
		/**** Info ****/
		echo '<td class="bo_sig_table_strikeinfo">';
		echo '<ul>';
		
		if ($row['strike_id'])
		{
			if ($own_station)
			{
				echo '<li>';
				echo '<span class="bo_descr">';
				echo _BL('Runtime').': ';
				echo '</span>';
				
				echo '<span class="bo_value" title="';
				
				if ($row['raw_id'])
				{
					echo $cdev_text;
					echo '">';
					echo _BN($time_diff * 1000, 4)._BL('unit_millisec');
				}
				else
				{
					echo '">';
					echo '-';
				}
				
				echo '</span>';
				echo '</li>';

			}

			if ($own_station || $station_id > 0)
			{
				echo '<li>';
				echo '<span class="bo_descr">';
				echo _BL('Distance').': ';
				echo '</span>';
				echo '<span class="bo_value">';
				echo _BK($distance / 1000, 1);
				echo '&nbsp;('._BL(bo_bearing2direction($bearing)).')';
				echo '</span>';
				echo '</li>';
			}
			
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Deviation').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo _BK($row['deviation'] / 1000, 1);
			echo '</span>';
			echo '</li>';
/*			
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Current').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo _BN($row['current'], 1).'kA';
			echo '</span>';
			echo '</li>';
*/
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Participants').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo _BN($row['stations'], 0);
			echo '</span>';
			echo '</li>';

			if ($own_station)
			{
				echo '<li>';
				echo '<span class="bo_descr">';
				echo _BL('Participated').': ';
				echo '</span>';
				echo '<span class="bo_value">';
				
				if ($row['part'] > 0)
					echo _BL('yes');
				elseif ($row['raw_id'])
					echo '<span class="bo_archive_not_evaluated">'._BL('no').'</span>';
				else
					echo _BL('no');
					
				echo '</span>';
				echo '</li>';
			}
			
		}
		
		if ($row['raw_id'] && $cdev_text)
		{			
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Residual time').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo $cdev_text;
			echo '</span>';
			echo '</li>';
		
		}
		
		if ($loc_angle[0])
		{
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Locating angle').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			
			echo _BN($loc_angle[1], 0);
			echo '&deg; ';

			echo '('._BN($loc_angle[0], 0);
			echo '&deg;)';
			
			echo '</span>';
			echo '</li>';
		
		}

		if ($row['raw_id'] && $channels == 1)
		{
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Channel').': ';
			echo '</span>';
			echo '<span class="bo_value">';

			if (abs($row['amp1_max']-128))
				echo 'A';
			elseif (abs($row['amp2_max']-128))
				echo 'B';
			else
				echo '?';
			
			echo '</span>';
			echo '</li>';				
					
			echo '</span>';
			echo '</li>';

		}

		
	
		if ($row['strike_id'] && $perm)
		{
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Lat').'/'._BL('Lon').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo $row['lat'].' / '.$row['lon'];
			echo '</span>';
			echo '</li>';
		}
		
		echo '</ul>';
		
		echo '<div style="clear:both"></div>';
		
		if (!$row['strike_id'])
		{
			echo _BL('no strike detected');
		}

		echo '</td>';
		echo '</tr>';

		
		if ($perm && count($participated_stations) && ($strike_id || ($row['strike_id'] && $show_details)) )
		{
				
			$i = 0;
			echo '<tr><td class="bo_sig_table_strikeinfo bo_sig_table_stations" colspan="4">';
			
			echo '<h5>'._BL('Participated stations').'</h5>';		
		
			if (!$show_other_graphs && time() - $stime < 3600 * 23)
				echo '<a href="'.bo_insert_url(array('bo_action', 'bo_show_details', 'bo_strike_id', 'bo_lat', 'bo_lon', 'bo_zoom')).'&bo_strike_id='.$row['strike_id'].'&bo_other_graphs" class="bo_show_all_signals bo_sig_table_menu">'._BL('Show all signals').'</a>';

			echo '<div class="bo_arch_other_participants_container">';
			foreach ($s_dists[0] as $sid => $dist)
			{
				echo '<span class="bo_arch_other_participants">';
				
				echo '<a ';
				echo ' href="'.BO_STATISTICS_URL.'&bo_show=station&bo_station_id='.$sid.'" ';
				echo ' title="';
				echo htmlentities($participated_stations[$sid]['city'], ENT_COMPAT | ENT_HTML401, 'UTF-8').': ';
				echo _BK(round($dist/1000)).' / ';
				echo round($s_bears[0][$sid]).'&deg; '.bo_bearing2direction($s_bears[0][$sid]);
				
				echo '" style="display:inline-block;';
				
				if ($i < bo_participants_locating_min())
					echo 'font-weight: bold;';

				if ($participated_stations[$sid]['part'] === false)
					echo 'text-decoration:line-through;';
				elseif ($i < bo_participants_locating_max())
					echo 'text-decoration:underline;';
				else
					echo 'text-decoration:none;';
				
				if ($sid == $station_id)
					echo 'color:red;';
				else
					echo 'color:inherit;';
				
				echo 'width:280px;">';
				echo _BC($participated_stations[$sid]['city']);
				
				if ($show_other_graphs)
				{
					$url = bo_signal_url($sid, null, $stime, $row['stimens'], $dist);
					
					echo ' +'._BK(round($dist/1000)).' / ';
					echo round($s_bears[0][$sid]).'&deg;';
					echo '</a>';
					echo '<a href="'.$url.'&bo_size=3" target="_blank">';
					echo '<img src="'.$url.'&bo_size=2" style="width:'.BO_GRAPH_RAW_W2.'px;height:'.BO_GRAPH_RAW_H2.'px"  class="bo_graph_sig_other" onmouseover="this.src+=\'&bo_spectrum&full\'" onmouseout="this.src=\''.$url.'&bo_size=2\'">';
					echo '</a>';
				}
				else
					echo '</a>';
				
				echo '</span>';
				
				$i++;
			}
			echo '</div>';
			
				
			if (!$show_details && (int)BO_ARCHIVE_STRIKE_INFO_ANIM > 0)
			{
				echo '<fieldset>';
				echo bo_archive_select_map($map);
				echo ' &bull; <a href="'.bo_insert_url(array('bo_strike_id', 'bo_lat', 'bo_lon', 'bo_zoom'), $row['strike_id']).'&bo_strikes_before='.BO_ARCHIVE_STRIKE_INFO_ANIM.'">'._BL('Animation').'</a>';
				echo '</fieldset>';
			}
			
			$img_dim = bo_archive_get_dim_html($map);
			$img_file = bo_bofile_url().'?map='.$map.'&strike_id='.$row['strike_id'].bo_lang_arg('map');
			echo '<img style="position:relative;background-image:url(\''.bo_bofile_url().'?image=wait\');" '.$img_dim.' id="bo_arch_map_img" src="'.$img_file.'">';
			
			$start = 1; //$s_dists[0] == $s_dists[1] ? 1 : 0;
			for($i=$start;$i<=1;$i++)
			{
				if ($i == 0)
					echo '<h5>'._BL('Distances between all stations').' [km]:</h5>';
				else
					echo '<h5>'._BL('Distances between stations used for locating').' [km]:</h5>';
					
					
				echo '<table class="bo_archive_station_dist">';
				
				echo '<tr><th></th><th>'._BL('Id').'</th>';
				foreach($s_dists[$i] as $sid2 => $d2)
				{
					echo '<th>'.$sid2.'</th>';
				}
				echo '<th>'._BL('Strike').'</th></tr>';

				foreach($s_dists[$i] as $sid1 => $d1)
				{
					$nd = false;
					
					echo '<tr>';
					echo '<th>'._BC($participated_stations[$sid1]['city']).'</th>';
					
					
					echo '<th>'.$sid1.'</th>';
					foreach($s_dists[$i] as $sid2 => $d2)
					{
						echo '<td style="text-align:right">';
						
						if ($sid1 == $sid2 || $nd)
						{
							echo '&nbsp;';
							$nd = true;
						}
						else
						{
							$dist = round($s_sdists[$i][$sid1.'.'.$sid2]/1000);
							
							echo '<span style="';
							
							if ($dist < 20)
								echo 'color: red';
							elseif ($dist < 50)
								echo 'color: #fa0';
							elseif ($dist < 100)
								echo 'color: #53f';
							elseif ($dist < 200)
								echo 'color: #06f';
							
							echo '">';
							echo $dist;
							echo '</span>';
						}
							
						echo '</td>';
					}
					
					echo '<th style="text-align:right">'._BK(round($d1/1000)).'</th>';
					
					echo '</tr>';
				}
				
				echo '</table>';
			}
			
			
			echo '</td></tr>';
			
		}

	
		if ($count == $per_page)
			break;
	}

	echo '</table>';

	echo '<div class="bo_sig_navi">'.$page_nav.'</div>';
	
	echo '</form>';
	
	
	echo '<h4>'._BL('Additional information').'</h4>';
	echo '<div id="bo_archive_signaltable_info_bottom" class="bo_general_description">';
	echo _BL('archive_signaltable_info_bottom');
	echo '</div>';
	
}


					
function bo_signal_url($station_id, $raw_id = null, $strike_time = null, $strike_time_ns = null, $dist = null, $strike = null)
{
	$url = bo_bofile_url().'?';
	
	if (!$raw_id)
	{
		
		//no station id --> find first signal
		if (!$station_id && BO_ARCHIVE_SHOW_FIRST_SIGNAL === true && is_array($strike))
		{
			$sql = "SELECT s.id id, ".bo_sql_latlon2dist($strike['lat'], $strike['lon'], 's.lat', 's.lon')." dist
						FROM ".BO_DB_PREF."stations s
						JOIN ".BO_DB_PREF."stations_strikes ss
							ON s.id=ss.station_id AND ss.strike_id='".$strike['id']."'
						ORDER BY dist ASC
						LIMIT 1";
			$res = BoDb::query($sql);
			if ($res->num_rows)
			{
				$row = $res->fetch_assoc();
				$dist = $row['dist'];
				$station_id = $row['id'];
			}
		}
	
		//station time to signal
		$station_time  = $strike_time;
		$station_ntime = $dist / BO_C + $strike_time_ns * 1E-9;
		$station_time  = $station_ntime > 1 ? $strike_time+1 : $strike_time;
		$station_ntime = $station_ntime > 1 ? $station_ntime-1 : $station_ntime;
		
		$url .= 'bo_graph&bo_station_id='.$station_id.'&bo_dist='.round($dist).'&bo_time='.urlencode(gmdate('Y-m-d H:i:s',$station_time).'.'.round($station_ntime * 1E9));
		
	}
	else
	{
		$url .= 'bo_graph='.$raw_id;
	}

	$url .= bo_lang_arg('graph');
	
	return $url;

}


?>