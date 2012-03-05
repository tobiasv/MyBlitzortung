<?php



function bo_show_archive()
{
	if (BO_DISABLE_ARCHIVE === true)
		return;
	
	$show = $_GET['bo_show'];
	$perm = (bo_user_get_level() & BO_PERM_ARCHIVE);
	$enabled['maps']       = ($perm || (defined('BO_ENABLE_ARCHIVE_MAPS') && BO_ENABLE_ARCHIVE_MAPS));
	$enabled['density']    = ($perm || (defined('BO_ENABLE_DENSITIES') && BO_ENABLE_DENSITIES)) && defined('BO_CALC_DENSITIES') && BO_CALC_DENSITIES;
	$enabled['search']     = ($perm || (defined('BO_ENABLE_ARCHIVE_SEARCH') && BO_ENABLE_ARCHIVE_SEARCH));
	$enabled['signals']    = ($perm || (defined('BO_ENABLE_ARCHIVE_SIGNALS') && BO_ENABLE_ARCHIVE_SIGNALS)) && BO_UP_INTVL_RAW > 0 && bo_station_id() > 0;
	$enabled['strikes']    = (bo_user_get_level() & BO_PERM_ARCHIVE); // to see strike table => only logged in users with archive permission!
	
	if (($show && !$enabled[$show]) || !$show )
	{
		foreach($enabled as $type => $e)
		{
			if ($e)
			{
				$show = $type;
				break;
			}
		}
	}
	
	if (!$show)
		return;
	
	
	echo '<ul id="bo_menu">';

	if ($enabled['maps'])
		echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'maps').'" class="bo_navi'.($show == 'maps' ? '_active' : '').'">'._BL('arch_navi_maps').'</a></li>';

	if ($enabled['density'])
		echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'density').'" class="bo_navi'.($show == 'density' ? '_active' : '').'">'._BL('arch_navi_density').'</a></li>';
	
	if ($enabled['search'])
		echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'search').'" class="bo_navi'.($show == 'search' ? '_active' : '').'">'._BL('arch_navi_search').'</a></li>';

	if ($enabled['strikes'])
		echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'strikes').'" class="bo_navi'.($show == 'strikes' ? '_active' : '').'">'._BL('arch_navi_strikes').'</a></li>';

	if ($enabled['signals'])
		echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'signals').'" class="bo_navi'.($show == 'signals' ? '_active' : '').'">'._BL('arch_navi_signals').'</a></li>';		

	echo '</ul>';

	switch($show)
	{
		
		case 'maps':
			echo '<h3>'._BL('h3_arch_maps').' </h3>';
			bo_show_archive_map();
			break;

		case 'density':
			echo '<h3>'._BL('h3_arch_density').' </h3>';
			bo_show_archive_density();
			break;
		
		default:
		case 'search':
			echo '<h3>'._BL('h3_arch_search').' </h3>';
			bo_show_archive_search();
			break;

		case 'signals':
			echo '<h3>'._BL('h3_arch_last_signals').'</h3>';
			bo_show_archive_table();
			break;
		
		case 'strikes':
			echo '<h3>'._BL('h3_arch_last_strikes').'</h3>';
			bo_show_archive_table(true);
			break;
	}



	bo_copyright_footer();

}

function bo_show_archive_map()
{
	global $_BO;

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
	$ani_preset = trim($_GET['bo_animation']);
	$hour_from = intval($_GET['bo_hour_from']);
	$hour_range = intval($_GET['bo_hour_range']);
	$map_changed = isset($_GET['bo_oldmap']) && $map != $_GET['bo_oldmap'];
	$ani_changed = !isset($_GET['bo_oldani']) || $ani != $_GET['bo_oldani'] || $map_changed;
	
	//Map
	$select_map = bo_archive_select_map($map);
		
	//image dimensions
	$img_dim = bo_archive_get_dim($map);
	
	//Config
	$cfg     = $_BO['mapimg'][$map];
	$ani_cfg = $_BO['mapimg'][$map]['animation'];
	$mapname = _BL($cfg['name']);
	
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

	if (isset($cfg['hoursinterval']) && intval($cfg['hoursinterval'])) //interval of hours
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
		if ($ani_changed)
		{
			if ($ani_preset == 'now')
			{
				$hour_from = date('H') - ($ani_pic_range / 60) * 2;
				$hour_range = $ani_default_range + ($hours_interval <= 6 ? $hours_interval : 0);
			}
			elseif ($ani_preset == 'day')
			{
				$hour_from = 0;
				$hour_range = 24;
			}
			else
			{
				$hour_from = 0;
				$hour_range = $ani_default_range;
			}
		}
		
		if ($hour_range < $ani_default_range)
			$hour_range = $ani_default_range;
		
		if ($hour_range > $ani_max_range)
			$hour_range = $ani_max_range;
		
		$max_range = $ani_max_range;
	}
	elseif (!$ani && $ani_changed)
	{
		$hour_from  = 0;
		$hour_range = $max_range < 24 ? $max_range : 24;
	}

	if ($_GET['bo_prev_hour'])
		$hour_from -= $hours_interval;
	else if ($_GET['bo_next_hour'])
		$hour_from += $hours_interval;

	$hour_from = floor($hour_from / $hours_interval) * $hours_interval;
		
	//Set to correct time
	$time      = mktime($hour_from,0,0,$month,$day+$day_add,$year);
	$year      = date('Y', $time);
	$month     = date('m', $time);
	$day       = date('d', $time);
	$hour_from = date('H', $time);

	
	
	
	
	//min/max strike-time
	$row = BoDb::query("SELECT MIN(time) mintime, MAX(time) maxtime FROM ".BO_DB_PREF."strikes")->fetch_assoc();
	$strikes_available = $row['mintime'] > 0;
	$start_time = strtotime($row['mintime'].' UTC');
	$end_time = strtotime($row['maxtime'].' UTC');

	//Output
	echo '<div id="bo_arch_maps">';
	
	if ($strikes_available)
	{
		echo '<p class="bo_general_description" id="bo_archive_density_info">';
		echo strtr(_BL('archive_map_info'), array('{DATE_START}' => date(_BL('_date'), $start_time),'{DATE_END}' => date(_BL('_date'), $end_time)));
		echo '</p>';

		echo '<a name="bo_arch_strikes_maps_form"></a>';
		echo '<form action="?#bo_arch_strikes_maps_form" method="GET" class="bo_arch_strikes_form" id="bo_arch_strikes_maps_form">';
		echo bo_insert_html_hidden(array('bo_map', 'bo_year', 'bo_month', 'bo_day', 'bo_animation', 'bo_day_add', 'bo_next', 'bo_prev', 'bo_next_hour', 'bo_prev_hour', 'bo_get', 'bo_oldmap'));
		echo '<input type="hidden" name="bo_oldmap" value="'.$map.'">';
		echo '<input type="hidden" name="bo_oldani" value="'.($ani ? 1 : 0).'">';
		
		echo '<fieldset>';
		echo '<legend>'._BL('legend_arch_strikes').'</legend>';
		echo $select_map;

		echo '<span class="bo_form_descr">'._BL('Date').':</span> ';
		echo '<select name="bo_year" id="bo_arch_strikes_select_year">';
		for($i=date('Y', $start_time); $i<=date('Y');$i++)
			echo '<option value="'.$i.'" '.($i == $year ? 'selected' : '').'>'.$i.'</option>';
		echo '</select>';

		echo '<select name="bo_month" id="bo_arch_strikes_select_month">';
		for($i=1;$i<=12;$i++)
			echo '<option value="'.$i.'" '.($i == $month ? 'selected' : '').'>'._BL(date('M', strtotime("2000-$i-01"))).'</option>';
		echo '</select>';

		echo '<select name="bo_day" id="bo_arch_strikes_select_day" onchange="submit()">';
		for($i=1;$i<=31;$i++)
			echo '<option value="'.$i.'" '.($i == $day ? 'selected' : '').'>'.$i.'</option>';
		echo '</select>';

		echo '&nbsp;<input type="submit" name="bo_prev" value=" &lt; " id="bo_archive_maps_prevday" class="bo_form_submit">';
		echo '&nbsp;<input type="submit" name="bo_next" value=" &gt; " id="bo_archive_maps_nextday" class="bo_form_submit">';
		echo '<input type="submit" value="'._BL('update map').'" id="bo_archive_maps_submit" class="bo_form_submit">';

		echo '<div class="bo_input_container">';
		echo '<span class="bo_form_descr">'._BL('Time range').':</span> ';
		echo '<select name="bo_hour_from" id="bo_arch_strikes_select_hour_from">';
		for($i=0;$i<=23;$i+=$hours_interval)
			echo '<option value="'.$i.'" '.($i == $hour_from ? 'selected' : '').'>'.$i.' '._BL('oclock').'</option>';
		echo '</select>';

		echo '&nbsp;<input type="submit" name="bo_prev_hour" value=" &lt; " id="bo_archive_maps_prevhour" class="bo_form_submit">';
		echo '&nbsp;<input type="submit" name="bo_next_hour" value=" &gt; " id="bo_archive_maps_nexthour" class="bo_form_submit">';

		echo ' <select name="bo_hour_range" id="bo_arch_strikes_select_hour_to" '.($ani ? '' : ' onchange="submit()"').'>';
		for($i=$hours_interval;$i<=$max_range;$i+=$hours_interval)
			echo '<option value="'.$i.'" '.($i == $hour_range ? 'selected' : '').'>+'.$i.' '._BL('hours').'</option>';
		echo '</select> ';

		
		if ($ani_div)
		{
			echo ' &nbsp;&nbsp;&nbsp; ';
			echo '<span class="bo_form_descr">'._BL('Animation').':</span> ';
			echo '<input type="radio" name="bo_animation" value="0" id="bo_archive_maps_animation_off" class="bo_form_radio" '.(!$ani ? ' checked' : '').' onclick="bo_enable_timerange(false, true);" '.($ani_forced ? ' disabled' : '').'>';
			echo '<label for="bo_archive_maps_animation_off">'._BL('Off').'</label>';
			echo '<input type="radio" name="bo_animation" value="1" id="bo_archive_maps_animation_on" class="bo_form_radio" '.($ani ? ' checked' : '').' onclick="bo_enable_timerange(true, true);">';
			echo '<label for="bo_archive_maps_animation_on">'._BL('On').'</label>';
		}
		
		echo '</div>';	
		
		
		echo '<div class="bo_input_container">';

		echo '<div class="bo_arch_map_form_links">';
		echo '<span class="bo_form_descr">';
		echo _BL('Yesterday').': &nbsp; ';
		echo '</span>';
		
		if (!$ani_cfg['force'])
			echo ' &nbsp; <a href="'.bo_insert_url('bo_*').'bo_map='.$map.'&bo_day_add=0#bo_arch_strikes_maps_form" >'._BL('Picture').'</a> ';
		
		if ($ani_div)
			echo ' &nbsp; <a href="'.bo_insert_url('bo_*').'bo_map='.$map.'&bo_day_add=0&bo_hour_from=0&bo_hour_range=24&bo_animation=day#bo_arch_strikes_maps_form" >'._BL('Animation').'</a> ';
		
		echo '  &nbsp;  &nbsp; &nbsp; ';
		
		echo '<span class="bo_form_descr">';
		echo _BL('Today').': &nbsp; ';
		echo '</span>';
		
		if (!$ani_cfg['force'])
			echo ' &nbsp; <a href="'.bo_insert_url('bo_*').'bo_map='.$map.'&bo_day_add=1#bo_arch_strikes_maps_form" >'._BL('Picture').'</a> ';
		
		if ($ani_div)
			echo ' &nbsp; <a href="'.bo_insert_url('bo_*').'bo_map='.$map.'&bo_day_add=1&bo_hour_from=0&bo_hour_range=24&bo_animation=day#bo_arch_strikes_maps_form" >'._BL('Animation').'</a> ';
			
		echo '</div>';
		
		
		echo '</div>';	
		
		echo '</fieldset>';
		echo '</form>';
	}
	
	if ($cfg['date_min'] && ($min = strtotime($cfg['date_min'])))
		$start_time = $min + 3600*24;
	
	if ($cfg['archive'])
	{
		if ($_BO['mapimg'][$map]['header'])
			echo '<div class="bo_map_header">'._BC($_BO['mapimg'][$map]['header'], true).'</div>';
	
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
		
		if (!$strikes_available || $time < $start_time - 3600 * 24 || $time > $end_time)
		{
			if ($strikes_available)
				$text = _BL('arch_select_dates_between');
			else
				$text = _BL('no_lightning_data');
				
			$img_file = bo_bofile_url().'?map='.$map.'&blank&bo_lang='._BL().'';
			
			echo '<div style="position:relative;'.bo_archive_get_dim($map, 0, true).'" id="bo_arch_map_nodata">';
			echo '<img style="position:absolute;background-image:url(\''.bo_bofile_url().'?image=wait\');" '.$img_dim.' id="bo_arch_map_noimg" src="'.$img_file.'">';
			echo '<div style="position:absolute;top:0px;left:0px" id="bo_arch_map_nodata_white"></div>';
			echo '<div style="position:absolute;top:0px;left:0px" id="bo_arch_map_nodata_text">';
			echo '<p>';
			echo strtr($text, array('{START}' => date(_BL('_date'), $start_time), '{END}' => date(_BL('_date'), $end_time) ));
			echo '</p>';
			echo '</div>';
			echo '</div>';
		}
		else if ($ani)
		{

			//use transparency?
			if ($ani_cfg['transparent'] === false)
			{
				$img_file = bo_bofile_url().'?image=bt'; //blank "tile"
				$bo_file_url = bo_bofile_url().'?map='.$map.'&bo_lang='._BL().'&date=';
			}
			else
			{
				$img_file = bo_bofile_url().'?map='.$map.'&blank&bo_lang='._BL().'';
				$bo_file_url = bo_bofile_url().'?map='.$map.'&transparent&bo_lang='._BL().'&date=';
			}
		
			$images = array();
			for ($i=$hour_from*60; $i< ($hour_from+$hour_range) * 60; $i+= $ani_div)
			{
				$time = strtotime("$year-$month-$day 00:00:00 +$i minutes");
				$images[] .= gmdate('YmdHi', $time).'-'.$ani_pic_range;

				if ($time > $end_time - $ani_pic_range * 60)
					break;
			}

			$alt = _BL('Lightning map').' '.$mapname.' '.date(_BL('_date'), $time).' ('._BL('Animation').')';
			
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
				$date_arg = sprintf('%04d%02d%02d', $uyear, $umonth, $uday);
				$date_arg .= sprintf('%02d', $uhour_from).'00-'.($hour_range*60);
			}
		
			$alt = _BL('Lightning map').' '.$mapname.' '.date(_BL('_date'), $time);
			$img_file = bo_bofile_url().'?map='.$map.'&date='.$date_arg.'&bo_lang='._BL();
			echo '<img style="position:relative;background-image:url(\''.bo_bofile_url().'?image=wait\');" '.$img_dim.' id="bo_arch_map_img" src="'.$img_file.'" alt="'.htmlspecialchars($alt).'">';
		}
		
		if ($_BO['mapimg'][$map]['footer'])
			echo '<div class="bo_map_footer">'._BC($_BO['mapimg'][$map]['footer'], true).'</div>';
		
		echo '</div>';
		echo '</div>';
		
	}

	

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
	$radius = $_BO['radius'] * 1000;
	$max_count = intval(BO_ARCHIVE_SEARCH_STRIKECOUNT);
	$select_count = $max_count;
	$perm = (bo_user_get_level() & BO_PERM_NOLIMIT);
	
	
	echo '<div id="bo_archive">';
	echo '<p class="bo_general_description" id="bo_archive_search_info">';
	echo strtr(_BL('archive_search_info'), array('{COUNT}' => $perm ? '' : $max_count));
	echo '</p>';
	echo '<h4>'._BL('Map').'</h4>';
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
		elseif (!$perm && bo_latlon2dist($lat, $lon, BO_LAT, BO_LON) > $radius)
		{
			//marker is too far away from home
			$getit = false;
			
			echo '<p>'._BL('search_outside_radius').'</p>';
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
		
		$sql = "SELECT  s.id id, s.distance distance, s.lat lat, s.lon lon, s.time time, s.time_ns time_ns, s.users users,
						s.current current, s.deviation deviation, s.current current, s.polarity polarity, s.part part, s.raw_id raw_id
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
			$description .= '<li><span class=\'bo_descr\'>'._BL('Time').':</span><span class=\'bo_value\'> '.date(_BL('_datetime'), $time).'.'.$row['time_ns'].'</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Deviation').':</span><span class=\'bo_value\'> '.number_format($row['deviation'] / 1000, 1, _BL('.'), _BL(',')).'km</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Current').':</span><span class=\'bo_value\'> '.number_format($row['current'], 1, _BL('.'), _BL(',')).'kA ('._BL('experimental').')</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Polarity').':</span><span class=\'bo_value\'> '.($row['polarity'] === null ? '?' : ($row['polarity'] < 0 ? _BL('negative') : _BL('positive'))).' ('._BL('experimental').')</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Participated').':</span><span class=\'bo_value\'> '.($row['part'] > 0 ? _BL('yes') : _BL('no')).'</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Participants').':</span><span class=\'bo_value\'> '.intval($row['users']).'</span></li>';

			if ($perm)
				$description .= '<li><span class=\'bo_value\'><a href=\''.bo_insert_url(array('bo_show', 'bo_*'), 'strikes').'&bo_strike_id='.$row['id'].'\' target=\'_blank\'>'._BL('more').'<a></span></li>';

			$description .= '</ul>';

			if ($row['raw_id'] && BO_UP_INTVL_RAW > 0)
			{
				$alt = _BL('Signals');
				$description .= '<img src=\''.bo_bofile_url().'?graph='.$row['raw_id'].'&bo_lang='._BL().'\' style=\'width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px\' class=\'bo_archiv_map_signal\' alt=\''.htmlspecialchars($alt).'\'>';
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
				echo _BL('time_from').' '.date(_BL('_datetime'), $utime_from).' ';
				
			if ($utime_to)
				echo _BL('time_to').' '.date(_BL('_datetime'), $utime_to);
			
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

			echo '<li><span class="bo_descr">'._BL('Oldest').':</span><span class="bo_value"> '.date(_BL('_datetime'), $time_min).'</value></li>';
			echo '<li><span class="bo_descr">'._BL('Newest').':</span><span class="bo_value"> '.date(_BL('_datetime'), $time_max).'</value></li>';
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
			
			<?php if ($lat !== false && $lon !== false) { ?>
			var myLatlng = new google.maps.LatLng(<?php echo  "$lat,$lon" ?>);

			centerMarker = new google.maps.Marker({
				position: myLatlng,
				draggable: true,
				map: bo_map,
				icon: 'http://maps.google.com/mapfiles/ms/micons/blue-dot.png'
			});

			google.maps.event.addListener(centerMarker, 'dragend', function() {
				document.getElementById('bo_archive_lat').value=this.getPosition().lat();
				document.getElementById('bo_archive_lon').value=this.getPosition().lng();
			});
/*
			google.maps.event.addListener(bo_map, 'click', function(event) {
				centerMarker.setPosition(event.latLng);
				document.getElementById('bo_archive_lat').value=event.latLng.lat();
				document.getElementById('bo_archive_lon').value=event.latLng.lng();
			});
*/
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

					images[i+j*img_count] = new google.maps.MarkerImage("<?php echo  bo_bofile_url() ?>?icon=ff"+d+"00"+(j ? '1' : ''),
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
function bo_show_archive_table($show_empty_sig = false, $lat = null, $lon = null, $fuzzy = null)
{
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
	$date = $_GET['bo_datetime_to'];
	$region = $_GET['bo_region'];
	$show_details = $_GET['bo_show_details'];
	$map = isset($_GET['bo_map']) ? intval($_GET['bo_map']) : 0;
	$other_graphs = isset($_GET['bo_other_graphs']) && $perm;
	
	$channels   = bo_get_conf('raw_channels');
	$raw_bpv    = bo_get_conf('raw_bitspervalue');
	$raw_values = bo_get_conf('raw_values');
	
	if ($page < 0)
		$page = 0;
	else if ($page > $max_pages)
		$page = $max_pages;
	
	$sql_where = '';
	$date_end_max_sec = 0;
	$datetime_to = 0;
	
	if (!$perm)
	{
		$show_empty_sig = false;
		$strike_id = 0;
		$strike_id_to = 0;
	}
	else if ($date)
	{
		$datetime_to = strtotime($date);
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

		$sql_where .= " AND NOT (s.lat < '$latS' OR s.lat > '$latN' OR s.lon < '$lonW' OR s.lon > '$lonE') ";
		$show_empty_sig = true;

		$hours_back = 24 * 50;
	}
	else if ($strike_id && $strikes_before)
	{
		//Special effects ;-)
		$hyps = intval($_GET['bo_hyps']);
		
		$images = array();
		for($i=$strike_id-$strikes_before;$i<=$strike_id;$i++)
			$images[] = $i;
		
		$img_file = bo_bofile_url().'?map='.$map.'&blank&bo_lang='._BL().'';
		$bo_file_url = bo_bofile_url().'?map='.$map.'&transparent&bo_lang='._BL().($hyps ? '&hyps' : '').'&strike_id=';

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
		$show_empty_sig = true;
		$hours_back = time() / 3600;
	}
	
	if ($show_empty_sig)
	{
		echo '<p class="bo_general_description" id="bo_archive_striketable_info">';
		echo _BL('archive_striketable_info');
		echo '</p>';
	}
	else
	{
		echo '<p class="bo_general_description" id="bo_archive_signaltable_info">';
		echo _BL('archive_signaltable_info');
		echo '</p>';
		bo_signal_info_list();
	}
	
	if (!$strike_id)
	{
		echo '<a name="bo_arch_table_form"></a>';
		echo bo_insert_html_hidden(array('bo_only_strikes', 'bo_action', 'bo_all_strikes', 'bo_show_details', 'bo_region'));
		echo '<fieldset>';
		echo '<legend>'._BL('settings').'</legend>';

		if (!$show_empty_sig)
		{
			echo '<input type="checkbox" name="bo_only_strikes" value="1" '.($only_strikes ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="check_only_strikes">';
			echo '<label for="check_only_strikes"> '._BL('check_only_strikes').'</label> &nbsp; ';
		}
		else
		{
			echo '<input type="checkbox" name="bo_only_participated" value="1" '.($only_participated ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="check_only_participated">';
			echo '<label for="check_only_participated"> '._BL('check_only_participated').'</label> &nbsp; ';
		}
		
		if ($perm)
		{
			if ($show_empty_sig || $only_strikes)
			{
				echo '<input type="checkbox" name="bo_show_details" value="1" '.($show_details ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="check_show_details">';
				echo '<label for="check_show_details"> '._BL('Details').'</label> &nbsp; ';
			}
			
			echo ' &nbsp; <span class="bo_form_descr">'._BL('Time').':</span> ';
			echo '<input type="text" name="bo_datetime_to" value="'._BC($date).'" id="bo_archive_date" class="bo_archive_date">';
			
			if ($show_empty_sig || $only_strikes)
			{
				echo ' &nbsp; <span class="bo_form_descr">'._BL('Region').': ';
				bo_show_select_region($region);
				echo '</span>  &nbsp; ';
			}
			
			if ($show_details)
				echo bo_archive_select_map($map);

			echo ' &nbsp; <input type="submit" value="'._BL('Ok').'">';
		}
		
		echo '</fieldset>';

		$hours_back = $show_empty_sig ? 24 * 30 : 24;
		$date_end_max_sec = 180;
	}

	
	if ($datetime_to)
	{
		$time_end = $datetime_to;
	}
	else if ($show_empty_sig) // display strikes
	{
		$time_end = time();
	}
	else //display signals
	{
		//time of last strike update
		$last_modified_strikes = bo_get_conf('uptime_strikes_modified');
		$last_modified_strikes -= BO_MIN_MINUTES_STRIKE_CONFIRMED * 60 - 60;

		//time of last signal
		$row = BoDb::query("SELECT MAX(time) time FROM ".BO_DB_PREF."raw")->fetch_assoc();
		$last_signal = strtotime($row['time'].' UTC') - $date_end_max_sec;
		$time_end = min($last_signal, $last_modified_strikes);

	}
	
	$date_end  = gmdate('Y-m-d H:i:s', $time_end);
	$date_start    = gmdate('Y-m-d H:i:s', $time_end - 3600 * $hours_back);
	
	if ($show_empty_sig) // all strikes, maybe with own sigs
	{
		$sql_join = BO_DB_PREF."strikes s 
					LEFT OUTER JOIN ".BO_DB_PREF."raw r 
					ON s.raw_id=r.id ";
		$table = 's';
		
		if ($only_participated)
			$sql_where .= " AND s.part>0 ";
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
	
	if (bo_user_get_id())
	{
		$stations = bo_stations();
	}
	
	$show_xy_graph = $channels > 1 && BO_ARCHIVE_SHOW_XY;
	$show_spectrum = BO_ARCHIVE_SHOW_SPECTRUM;
	
	
	$count = 0;
	$sql = "SELECT  s.id strike_id, s.distance distance, s.lat lat, s.lon lon,
					s.deviation deviation, s.current current, s.polarity polarity,
					s.time stime, s.time_ns stimens, s.users users, s.part part,
					s.status status,
					r.id raw_id, r.time rtime, r.time_ns rtimens, r.data data,
					r.amp1 amp1, r.amp2 amp2, r.amp1_max amp1_max, r.amp2_max amp2_max
			FROM $sql_join
			WHERE 1
					AND $table.time BETWEEN '$date_start' AND '$date_end'
					$sql_where
					".bo_region2sql($region)."
			ORDER BY $table.time DESC, $table.time_ns DESC
			LIMIT ".($page * $per_page).", ".($per_page+1)."";
	$res = BoDb::query($sql);

	echo '<div class="bo_sig_navi">';

	if ($res->num_rows > $per_page && $page < $max_pages)
		echo '<a href="'.bo_insert_url('bo_action', $page+1).'#bo_arch_table_form" class="bo_sig_prev" rel="nofollow">&lt; '._BL('Older').'</a>';
	if ($page)
		echo '<a href="'.bo_insert_url('bo_action', $page-1).'#bo_arch_table_form" class="bo_sig_next" rel="nofollow">'._BL('Newer').' &gt;</a>';
	echo '</div>';

	echo '<table class="bo_sig_table';
	echo $show_spectrum ? ' bo_sig_table_spectrum' : '';
	echo $show_xy_graph ? ' bo_sig_table_xy_graph' : '';
	echo '">';

	
	while($row = $res->fetch_assoc())
	{

		if ($show_empty_sig && $res->num_rows == 1)
			$strike_id = $row['strike_id'];

		$count++;
		$stime = strtotime($row['stime'].' UTC');
		$cdev_text = '';
		
		if ($row['raw_id'])
		{
			$rtime = strtotime($row['rtime'].' UTC') + 1;
		
			if ($row['strike_id'])
			{
				$time_diff = $rtime - $stime + ($row['rtimens'] - $row['stimens']) * 1E-9;
				$residual_time = $time_diff - $row['distance'] / BO_C;

				$cdev = $row['distance'] / $time_diff / BO_C;
				$cdev_text =  number_format($residual_time*1E6, 1, _BL('.'), _BL(','))._BC('µs');
				$cdev_text .= ' / '.number_format($cdev, 5, _BL('.'), _BL(',')).'c';
				$cdev_text .= ' / '.round(($cdev-1)*$row['distance']).'m';
			}
		}

		$bearing = bo_latlon2bearing($row['lat'], $row['lon']);

		
		
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
			if (!isset($participated_stations[bo_station_id()]) && $row['raw_id'])
			{
				$participated_stations[ bo_station_id() ] = $stations[bo_station_id()];
				$participated_stations[ bo_station_id() ]['part'] = false;
			}
			
			if (count($participated_stations))
			{

				foreach ($participated_stations as $sid => $dummy)
				{
					$s_dists[0][$sid] = bo_latlon2dist($row['lat'], $row['lon'], $participated_stations[$sid]['lat'], $participated_stations[$sid]['lon']);
					$s_bears[0][$sid] = bo_latlon2bearing($participated_stations[$sid]['lat'], $participated_stations[$sid]['lon'], $row['lat'], $row['lon']);
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
		echo ($show_empty_sig ? _BL('Time') : _BL('Received')).': ';
		echo '</span>';
		echo '<span class="bo_value">';

		if ($show_empty_sig)
			$ttime = date(_BL('_datetime'), $stime).'.'.sprintf('%09d', $row['stimens']);
		else
			$ttime = date(_BL('_datetime'), $rtime).'.'.sprintf('%09d', $row['rtimens']);

		if (!$strike_id && $perm && $row['strike_id'])
		{
			echo '<a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'strikes').'&bo_strike_id='.$row['strike_id'].'" target="_blank" ';
			echo ' title="Confirmed: '.$row['status'].'" ';
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

		if ($raw_bpv == 8 && $raw_values > 10 && BO_UP_INTVL_RAW > 0)
		{
			$alt = _BL('rawgraph');
			echo '<td rowspan="2" class="bo_sig_table_graph"  style="width:'.BO_GRAPH_RAW_W.'px;">';
			if ($row['raw_id'])
			{
				$url = bo_bofile_url().'?graph='.$row['raw_id'].'&bo_lang='._BL();
				echo '<img src="'.$url.'" style="width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px" alt="'.htmlspecialchars($alt).'" id="bo_graph_sig_'.$row['raw_id'].'" onmouseover="this.src+=\'&full\'" onmouseout="this.src=\''.$url.'\'">';
			}
			else if ($row['strike_id'] && !$row['raw_id'] && $row['part'] > 0)
			{
				echo _BL('signal not found');
			}
			else
				echo _BL('No signal received');
				
			echo '</td>';

			if ($show_spectrum)
			{
				echo '<td rowspan="2" class="bo_sig_table_graph"  style="width:'.BO_GRAPH_RAW_W.'px;">';
				if ($row['raw_id'])
				{
					$url = bo_bofile_url().'?graph='.$row['raw_id'].'&spectrum&bo_lang='._BL();
					echo '<img src="'.$url.'" style="width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px" alt="'.htmlspecialchars($alt).'" id="bo_graph_spec_'.$row['raw_id'].'" onmouseover="this.src+=\'&full\'" onmouseout="this.src=\''.$url.'\'">';
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
                if ($row['raw_id'])
                {
                    $url = bo_bofile_url().'?graph='.$row['raw_id'].'&xy&bo_lang='._BL();
                    echo '<img src="'.$url.'" style="width:'.BO_GRAPH_RAW_H.'px;height:'.BO_GRAPH_RAW_H.'px" alt="'.htmlspecialchars($alt).'" id="bo_graph_xy_'.$row['raw_id'].'" onmouseover="this.src+=\'&full\'" onmouseout="this.src=\''.$url.'\'">';
                }
                elseif ($row['strike_id'] && !$row['raw_id'] && $row['part'] > 0)
                {
                    echo _BL('signal not found');
                }
                else
                    echo _BL('No signal received');

                echo '</td>';
            }
		}
		
		echo '</tr><tr>';

		echo '<td class="bo_sig_table_strikeinfo">';
		echo '<ul>';
		
		if ($row['strike_id'])
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
				echo number_format($time_diff * 1000, 4, _BL('.'), _BL(','))._BL('unit_millisec');
			}
			else
			{
				echo '">';
				echo '-';
			}
			
			echo '</span>';
			echo '</li>';

			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Distance').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo number_format($row['distance'] / 1000, 1, _BL('.'), _BL(','))._BL('unit_kilometers');
			echo '&nbsp;('._BL(bo_bearing2direction($bearing)).')';
			echo '</span>';
			echo '</li>';

			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Deviation').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo number_format($row['deviation'] / 1000, 1, _BL('.'), _BL(','))._BL('unit_kilometers');
			echo '</span>';
			echo '</li>';

			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Current').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo number_format($row['current'], 1, _BL('.'), _BL(',')).'kA';
			echo '</span>';
			echo '</li>';

			if (BO_EXPERIMENTAL_POLARITY_CHECK === true)
			{
				echo '<li>';
				echo '<span class="bo_descr">';
				echo _BL('Polarity').': ';
				echo '</span>';
				echo '<span class="bo_value">';

				if (!$row['polarity'])
					echo '?';
				elseif ($row['polarity'] > 0)
					echo _BL('positive');
				elseif ($row['polarity'] < 0)
					echo _BL('negative');
				echo '</span>';
				echo '</li>';
			}
			
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Participants').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo number_format($row['users'], 0, _BL('.'), _BL(','));
			echo '</span>';
			echo '</li>';

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
		
		if ($row['raw_id'])
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
			
			echo number_format($loc_angle[1], 0, _BL('.'), _BL(','));
			echo '&deg; ';

			echo '('.number_format($loc_angle[0], 0, _BL('.'), _BL(','));
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

		
		if ($row['strike_id'] && $row['status'] == 0)
		{
			echo '<li>';
			echo '<span class="bo_value bo_strike_not_confirmed">';
			echo _BL('Strike is not confirmed');
			echo '</span>';
			echo '</li>';
		}
		
		if ($row['strike_id'])
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

		
		if ( (bo_user_get_level() & BO_PERM_ARCHIVE) && count($participated_stations) && ($strike_id || ($row['strike_id'] && $show_details)) )
		{
				
			$i = 0;
			echo '<tr><td class="bo_sig_table_strikeinfo bo_sig_table_stations" colspan="4">';
			
			echo '<h5>'._BL('Stations').'</h5> ';
			
					
			foreach ($s_dists[0] as $sid => $dist)
			{
				echo $i && !$other_graphs ? ', ' : '';
				
				echo '<span class="bo_arch_other_participants">';
				
				echo '<a ';
				echo ' href="'.BO_STATISTICS_URL.'&bo_show=station&bo_station_id='.$sid.'" ';
				echo ' title="';
				echo htmlentities($participated_stations[$sid]['city']).': ';
				echo round($dist/1000).'km / ';
				echo round($s_bears[0][$sid]).'&deg; '.bo_bearing2direction($s_bears[0][$sid]);
				
				echo '" style="';
				
				if ($i < bo_participants_locating_min())
					echo 'font-weight: bold;';

				if ($participated_stations[$sid]['part'] === false)
					echo 'text-decoration:line-through;';
				elseif ($i < bo_participants_locating_max())
					echo 'text-decoration:underline;';
				else
					echo 'text-decoration:none;';
				
				if ($sid == bo_station_id())
					echo 'color:red;';
				else
					echo 'color:inherit;';
				
				echo '">';
				
				if ((bo_user_get_level() & BO_PERM_SETTINGS))
					echo $participated_stations[$sid]['user'];
				else
					echo _BC($participated_stations[$sid]['city']);
					
				echo '</a>';
				
				if ($other_graphs)
				{
					//station time to signal
					$station_time  = $stime;
					$station_ntime = $dist / BO_C + $row['stimens'] * 1E-9;
					$station_time  = $station_ntime > 1 ? $stime+1 : $stime;
					$station_ntime = $station_ntime > 1 ? $station_ntime-1 : $station_ntime;
					
					echo ' +'.round($dist/1000).'km / ';
					echo round($s_bears[0][$sid]).'&deg;';
					$url = bo_bofile_url().'?graph&bo_station_id='.$sid.'&bo_time='.urlencode(gmdate('Y-m-d H:i:s',$station_time).'.'.round($station_ntime * 1E9)).'&bo_lang='._BL();
					echo '<img src="'.$url.'" style="width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px" alt="'.htmlspecialchars($alt).'" class="bo_graph_sig_other">';

					echo '';
				
				}
				
				echo '</span>';
				
				$i++;
			}
			
			
			if ($perm && !$other_graphs && time() - $stime < 3600 * 23)
				echo ' (<a href="'.bo_insert_url(array('bo_action', 'bo_show_details')).'&bo_strike_id='.$row['strike_id'].'&bo_other_graphs">'._BL('Show their signals').'</a>)';

			echo '</p>';
			
			
			//if ($show_empty_sig && $count == 1 && $strike_id)
			{
				
				$img_dim = bo_archive_get_dim($map);
				echo '<h5>'._BL('Participated stations').':</h5>';
				
				if (!$show_details)
				{
					echo '<form action="?" method="GET" class="bo_arch_strikes_form">';
					echo bo_insert_html_hidden(array('bo_map'));
					echo '<fieldset>';
					echo bo_archive_select_map($map);
					echo ' &bull; <a href="'.bo_insert_url(array('bo_strike_id', 'bo_lat', 'bo_lon', 'bo_zoom'), $row['strike_id']).'&bo_strikes_before=100">'._BL('Animation').'</a>';
					echo '</fieldset>';
				}
				
				$img_file = bo_bofile_url().'?map='.$map.'&strike_id='.$row['strike_id'].'&hyps&bo_lang='._BL();
				echo '<img style="position:relative;background-image:url(\''.bo_bofile_url().'?image=wait\');" '.$img_dim.' id="bo_arch_map_img" src="'.$img_file.'">';
			}
			
			
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
					
					echo '<th style="text-align:right">'.round($d1/1000).'km</th>';
					
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

	if ($count && ($count == $per_page && $page < $max_pages || $page))
	{
		echo '<div class="bo_sig_navi">';
		if ($count == $per_page && $page < $max_pages)
			echo '<a href="'.bo_insert_url('bo_action', $page+1).'#bo_arch_table_form" class="bo_sig_prev" rel="nofollow">&lt; '._BL('Older').'</a>';
		if ($page)
			echo '<a href="'.bo_insert_url('bo_action', $page-1).'#bo_arch_table_form" class="bo_sig_next" rel="nofollow">'._BL('Newer').' &gt;</a>';
		echo '</div>';
	}

	
	echo '</form>';
	
	
	echo '<h4>'._BL('Additional information').'</h4>';
	echo '<div id="bo_archive_signaltable_info_bottom" class="bo_general_description">';
	echo _BL('archive_signaltable_info_bottom');
	echo '</div>';
	
}


function bo_archive_select_map(&$map)
{
	global $_BO;
	
	$map_ok = false;
	$map_default = false;
	
	$ret = '<span class="bo_form_descr">'._BL('Map').':';
	$ret .= ' <select name="bo_map" id="bo_arch_strikes_select_map" onchange="submit();">';
	foreach($_BO['mapimg'] as $id => $d)
	{
		if (!$d['name'] || !$d['archive'])
			continue;
		
		if ($map_default === false)
			$map_default = $id;
			
		$ret .= '<option value="'.$id.'" '.($id == $map ? 'selected' : '').'>'._BL($d['name']).'</option>';
		
		if ($map < 0)
			$map = $id;
		
		if ($map == $id)
			$map_ok = true;
	}
	$ret .= '</select></span> ';
	
	$map = $map_ok ? $map : $map_default;
	
	return $ret;
}


function bo_archive_get_dim($map, $addx=0, $css=false)
{
	global $_BO;
	
	$cfg = $_BO['mapimg'][$map];
	
	if ($cfg['dim'][0] && $cfg['dim'][1])
	{
		$x = $cfg['dim'][0];
		$y = $cfg['dim'][1];
	}
	else
	{
		$file = BO_DIR.'images/'.$cfg['file'];
		if (file_exists($file) && !is_dir($file))
		{
			list($x,$y) = getimagesize($file);
			
			if (isset($cfg['resize']) && $cfg['resize'] > 0)
			{
				$y = $y * ($cfg['resize'] / $y);
				$x = $cfg['resize'];
			}
			
		}
	}
	
	if ($x && $y)
	{
		if ($css)
			$img_dim = 'width:'.($x+$addx).'px;height:'.$y.'px;';	
		else
			$img_dim = ' width="'.($x+$addx).'" height="'.$y.'" ';	
	}
	else
		$img_dim = '';
		
	return $img_dim;
}




function bo_insert_animation_js($images, $bo_file_url, $img_file, $ani_delay=BO_ANIMATIONS_WAITTIME, $ani_delay_end=BO_ANIMATIONS_WAITTIME_END, $img_dim='', $alt="")
{
	echo '<img style="position:relative;background-image:url(\''.bo_bofile_url().'?image=wait\');" '.$img_dim.' id="bo_arch_map_img" src="'.$img_file.'" alt="'.htmlspecialchars($alt).'">';
	echo '<img style="position:absolute;top:1px;left:1px;" '.$img_dim.' id="bo_arch_map_img_ani" src="'.$bo_file_url.$images[0].'" alt="'.htmlspecialchars($alt).'">';

	echo '<div id="bo_ani_loading_container" style="display:none;">';
	echo '<div id="bo_ani_loading_white" style="position:absolute;top:0px;left:0px;"></div>';
	echo '<div id="bo_ani_loading_text"  style="position:absolute;top:0px;left:0px">';
	echo '<p id="bo_ani_loading_text_percent" >'._BL('Loading...').'</p>';
	echo '</div>';
	echo '</div>';
	
	$js_img = '';
	foreach($images as $image)
		$js_img .= ($js_img ? ',' : '').'"'.$image.'"';
	
?>
	
<script type="text/javascript">

var bo_maps_pics   = new Array(<?php echo $js_img ?>);
var bo_maps_img    = new Array();
var bo_maps_loaded = 0;
var bo_maps_playing = false;
var bo_maps_position = 0;

function bo_maps_animation(nr)
{
	if (bo_maps_playing)
	{
		document.getElementById('bo_arch_map_img_ani').src=bo_maps_img[nr].src;
		var timeout = <?php echo intval($ani_delay); ?>;
		if (nr >= bo_maps_pics.length-1) { nr=-1; timeout += <?php echo intval($ani_delay_end); ?>; }
		window.setTimeout("bo_maps_animation("+(nr+1)+");",timeout);
		bo_maps_position=nr;
	}
}

function bo_maps_load()
{
	document.getElementById('bo_ani_loading_container').style.display='block';
	for (var i=0; i<bo_maps_pics.length; i++)
	{
		bo_maps_img[i] = new Image();
		bo_maps_img[i].onload=bo_maps_animation_start;
		bo_maps_img[i].onerror=bo_maps_animation_start;
		bo_maps_img[i].src = "<?php echo $bo_file_url ?>" + bo_maps_pics[i];
	}
	
}

function bo_maps_animation_start()
{
	if (bo_maps_loaded >= 0)
		bo_maps_loaded++;
	
	if (bo_maps_loaded+1 >= bo_maps_pics.length && bo_maps_loaded >= 0)
	{
		bo_maps_loaded = -1;
		bo_maps_playing = true;
		document.getElementById('bo_ani_loading_container').style.display='none';
		bo_maps_animation(0);
	}
	else if (bo_maps_loaded > 0)
	{
		if (bo_maps_pics.length > 0)
			document.getElementById('bo_ani_loading_text_percent').innerHTML="<?php echo _BL('Loading...') ?> " + Math.round(bo_maps_loaded / bo_maps_pics.length * 100) + "%";
		
		document.getElementById('bo_arch_map_img_ani').src = bo_maps_img[bo_maps_loaded-1].src;
	}
}

function bo_animation_pause()
{
	if (bo_maps_loaded == -1)
	{
		if (bo_maps_playing)
		{
			bo_maps_playing = false;
			document.getElementById('bo_animation_dopause').innerHTML="<?php echo _BL('ani_play') ?>";
		}
		else
		{
			bo_maps_playing = true;
			document.getElementById('bo_animation_dopause').innerHTML="<?php echo _BL('ani_pause') ?>";
			bo_maps_animation(bo_maps_position);
		}
	}

}

function bo_animation_next()
{
	if (bo_maps_loaded == -1)
	{
		if (bo_maps_playing)
			bo_animation_pause();
		
		if (++bo_maps_position >= bo_maps_img.length) bo_maps_position=0;
		
		document.getElementById('bo_arch_map_img_ani').src=bo_maps_img[bo_maps_position].src;
		
	}
}

function bo_animation_prev()
{
	if (bo_maps_loaded == -1)
	{
		if (bo_maps_playing)
			bo_animation_pause();
		
		if (--bo_maps_position < 0) bo_maps_position=bo_maps_img.length-1;
		document.getElementById('bo_arch_map_img_ani').src=bo_maps_img[bo_maps_position].src;
		
		
	}
}

window.setTimeout("bo_maps_load();", 500);
</script>
<?php

}


?>