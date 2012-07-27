<?php


// GoogleMap with Markers
function bo_show_lightning_map($show_gmap=null, $show_static_maps=null)
{
	global $_BO;

	require_once 'functions_html.inc.php';
	require_once 'functions_dynmap.inc.php';
	
	
	$disabled = ((defined('BO_MAP_DISABLE') && BO_MAP_DISABLE && !bo_user_get_level())) || $show_gmap === 0;
	$no_google = isset($_GET['bo_showmap']) || $disabled;
	$period = (float)$_GET['bo_period'];
	$static_map_id = $show_static_maps ? $show_static_maps : $_GET['bo_showmap'];
	$show_static_maps = $show_static_maps === null || $show_static_maps;
	$show_menu = $show_static_maps;
	$last_update = BoData::get('uptime_strikes_modified');
	
	if ($show_static_maps)
	{
		$map_groups = array();
		
		$menu = array();
		
		foreach($_BO['mapimg'] as $id => $d)
		{
			if (!$d['name'] || !$d['menu'])
				continue;
			
			if ($d['group'])
			{
				$map_groups[$d['group']][$id] = $d['name'];
				$name = $d['group'];
				$menu_id  = 'group_'.$d['group'];
				$menu_active = (string)$static_map_id === (string)$id;
				
				if (!isset($menu[$menu_id]))
					$menu[$menu_id] = array($menu_active, $name, $id);
				else
					$menu[$menu_id][0] = $menu[$menu_id][0] || $menu_active;
			}
			else
			{
				$name = $d['name'];
				$menu_active = (string)$static_map_id === (string)$id;
				$menu[$id] = array($menu_active, $name, $id);
			}
		}

		if (!empty($menu) && $show_menu)
		{
			echo '<ul id="bo_menu">';
			
			if (!$disabled)
				echo '<li><a href="'.bo_insert_url(array('bo_*')).'" class="bo_navi'.(!$no_google ? '_active' : '').'">'._BL('Dynamic map').'</a></li>';
			
			foreach($menu as $menu_id => $d)
			{
				echo '<li><a href="'.bo_insert_url(array('bo_showmap', 'bo_*'), $d[2]);
				echo count($_BO['mapimg'][$d[2]]['trange']) > 1 ? 'bo_period='.$period : '';
				echo '" ';
				echo ' class="bo_navi'.($d[0] ? '_active' : '').'">'._BL($d[1], false, BO_CONFIG_IS_UTF8).'</a></li>';
			}
			
			echo '</ul>';
		}

		if ($no_google)
		{	
			$cfg = $_BO['mapimg'][$static_map_id];
			
			//look for different time ranges for the live-view
			if (!is_array($cfg['trange']))
				$ranges[$cfg['trange']] = $cfg['trange'];
			else
				$ranges = $cfg['trange'];

			//find period ID
			if ($period > 0)
			{
				$period_id = (int)array_search($period, $ranges);
				$cache_file .= '_p'.$ranges[$period_id];
			}
			else
				$period_id = 0; //set the default range!
			
			//update intervals
			if (!is_array($cfg['upd_intv']))
				$update_interval = $cfg['upd_intv'] * 60;
			elseif (!$cfg['upd_intv'][$period_id])
				$update_interval = $cfg['upd_intv'][0] * 60;
			else
				$update_interval = $cfg['upd_intv'][$period_id] * 60;
					

			$archive_maps_enabled = (defined('BO_ENABLE_ARCHIVE_MAPS') && BO_ENABLE_ARCHIVE_MAPS) || bo_user_get_level();		
			$url = bo_bofile_url().'?map='.$static_map_id.($period_id ? '&period='.$period : '').bo_lang_arg('map');
			$img_dim = bo_archive_get_dim_html($static_map_id);

			echo '<form method="GET">';
			echo bo_insert_html_hidden(array('bo_showmap'));
			echo '<fieldset class="bo_map_options_static">';
			echo '<legend>'._BL("map_options_static").'</legend>';
			echo ' <input type="submit" value="'._BL('update map').'" onclick="bo_map_reload_static(1); return false;" id="bo_map_reload">';

			if ($cfg['group'] && isset($map_groups[$cfg['group']]) && count($map_groups[$cfg['group']]) > 1)
			{
				
				echo '<span class="bo_form_checkbox_text">';
				echo _BL('Map').': ';
				echo '<select name="bo_showmap" onchange="submit();">';
				foreach($map_groups[$cfg['group']] as $map_id => $map_name)
				{
					echo '<option value="'.$map_id.'" '.($map_id == $static_map_id ? 'selected' : '').'>';
					echo _BL($map_name, false, BO_CONFIG_IS_UTF8);
					echo '</option>';
				}
				echo '</select> &bull; ';
			}
			else
			{
				echo '<input type="hidden" name="bo_showmap" value="'.$static_map_id.'">';
			}

			echo '<span class="bo_form_checkbox_text">';
			echo '<input type="checkbox" onclick="bo_toggle_autoupdate_static(this.checked);" id="bo_check_autoupdate_static"> ';
			echo '<label for="bo_check_autoupdate_static">'._BL('auto update').'</label> ';
			echo '</span>';
			
			if (count($ranges) > 1)
			{
				echo '<span id="bo_livemap_select_periods">';
				echo ' '._BL('Period').': ';
				
				asort($ranges);
				foreach($ranges as $i => $r)
				{
					echo '<a href="'.bo_insert_url("bo_period", $r).'" class="';
					echo $i == $period_id ? 'bo_selected' : ''; 
					echo '">';
					
					if ($r < 2)
						echo ($r * 60).'min';
					else
						echo $r.'h';
						
					echo '</a> &nbsp;';
				
				}
				echo '</span>';
			}
			
			echo '</fieldset>';
			echo '</form>';
			
			echo '<div id="bo_arch_maplinks_container_all">';
			
			if ($cfg['header'])
				echo '<div class="bo_map_header">'._BC($cfg['header'], true, BO_CONFIG_IS_UTF8).'</div>';
			
			echo '<div style="display:inline-block;" id="bo_arch_maplinks_container">';
			
			if ($archive_maps_enabled && intval(BO_ANIMATIONS_INTERVAL) && $cfg['archive'])
			{
				echo '<div class="bo_arch_map_links">';
				echo '<a href="'.BO_ARCHIVE_URL.'&bo_map='.$static_map_id.'&bo_animation=now" >'._BL('Animation').'</a> ';
				echo '</div>';
			}

			$alt = _BL('Lightning map').' '._BL($_BO['mapimg'][$static_map_id]['name'], false, BO_CONFIG_IS_UTF8).' '._BDT($last_update);
			echo '<div style="position:relative;display:inline-block;" id="bo_arch_map_container">';
			echo '<img src="'.$url.'" '.$img_dim.' id="bo_arch_map_img" style="background-image:url(\''.bo_bofile_url().'?image=wait\');" alt="'.htmlspecialchars($alt).'">';

			if ($cfg['footer'])
				echo '<div class="bo_map_footer">'._BC($cfg['footer'], true, BO_CONFIG_IS_UTF8).'</div>';

			echo '</div>';
			echo '</div>';
			echo '</div>';

?>

<script type="text/javascript">

var bo_autoupdate_running = false;
function bo_toggle_autoupdate_static(on)
{
	if (on && bo_autoupdate_running)
		return;
		
	bo_autoupdate_running = on;

	if (on)
		bo_map_reload_static(0);
}

function bo_map_reload_static(manual)
{
	if (bo_autoupdate_running || manual)
	{
		var now = new Date();
		document.getElementById('bo_arch_map_img').src='<?php echo $url ?>&bo_t=' + Math.floor(now.getTime() / <?php echo 1000 * $update_interval; ?>);
	}
	
	if (!manual)
		bo_map_reload_static_wait();
}

function bo_map_reload_static_wait()
{
	if (bo_autoupdate_running)
	{
		window.setTimeout("bo_map_reload_static(0);", <?php echo 1000 * ceil($update_interval / 2) ?>);
	}
}

if (<?php echo BO_MAPS_AUTOUPDATE_DEFAULTON ? 'true' : 'false'; ?>)
{
	bo_autoupdate_running=true;
	bo_map_reload_static_wait();
	document.getElementById('bo_check_autoupdate_static').checked=true;
}


</script>

<?php
			
			return;
		}
	}
	
	if ($disabled)
		return;
	
	//Max,min striketime
	$row = BoDb::query("SELECT MIN(time) mintime, MAX(time) maxtime FROM ".BO_DB_PREF."strikes")->fetch_assoc();
	$start_time = strtotime($row['mintime'].' UTC');
	$end_time = strtotime($row['maxtime'].' UTC');
	
	//Get Stations
	$sid = bo_station_id();
	$js_stations = '';
	$res = BoDb::query("SELECT id, city, lat, lon, status
					FROM ".BO_DB_PREF."stations a
					WHERE id != '$sid'");
	while($row = $res->fetch_assoc())
	{
		if ($row['status'] != '-' && $row['lat'] && $row['lon'])
		{
			$round = (bo_user_get_level() & BO_PERM_SETTINGS) ? 8 : 1;
			$js_stations .= $js_stations ? ",\n" : '';
			$js_stations .= '{';
			$js_stations .= 'stid:'.$row['id'].', lat:'.round($row['lat'],$round).', lon:'.round($row['lon'], $round).', city:"'._BC($row['city']).'"';
			$js_stations .= ', status:"'.$row['status'].'"';
			$js_stations .= ', text:"';
			
			switch($row['status'])
			{
				case 'A': $js_stations .= ' ('._BL('Active').')'; break;
				case 'O': $js_stations .= ' ('._BL('Inactive').')'; break;
				case 'V': $js_stations .= ' ('._BL('No GPS').')'; break;
			}
			
			$js_stations .= '"';
			$js_stations .= '}';
		}
		
		$st_cities[$row['id']] = $row['city'].($row['status'] != 'A' ? ' (Offline)' : '');
	}
	
	//Get MyBo Stations
	$mybo_info = unserialize(BoData::get('mybo_stations_info'));
	$js_mybo_stations = '';
	if (is_array($mybo_info) && count($mybo_info) > 1)
	{
		$mybo_urls = unserialize(BoData::get('mybo_stations'));
		
		foreach($mybo_info['lats'] as $id => $dummy)
		{
			if ($id == $sid || !trim($st_cities[$id]))
				continue;
			
			if ($mybo_info['lats'][$id] && $mybo_info['lons'][$id])
			{
				$rad = $mybo_info['rads'][$id] ? $mybo_info['rads'][$id] : BO_RADIUS;
				
				$js_mybo_stations .= $js_mybo_stations ? ",\n" : '';
				$js_mybo_stations .= '{';
				$js_mybo_stations .= 'lat:'.$mybo_info['lats'][$id].', lon:'.$mybo_info['lons'][$id].', rad:'.$rad.', url:"'.$mybo_urls[$id].'", city:"'._BC($st_cities[$id]).'"';
				$js_mybo_stations .= '}';
			}
		}
	}


	//Static maps for admin (for testing)
	if ((bo_user_get_level() & BO_PERM_ADMIN))
	{
		foreach($_BO['mapimg'] as $id => $data)
		{
			$_BO['mapovl'][] = array(
				'img' => bo_bofile_url().'?map='.$id,
				'coord' => $data['coord'],
				'default_show' => false,
				'sel_name' => $data['name'],
				'only_loggedin' => true,
				'to_mercator' => $data['proj'] == 'plate' ? true : false,
				'opacity' => 50,
				'is_map' => true
				);
		}
	}
	
	//Get extra map overlays
	$Overlays = array();
	foreach ($_BO['mapovl'] as $id => $data)
	{
		if ( (!(bo_user_get_level() & BO_PERM_NOLIMIT) && $data['only_loggedin'])
		     || empty($data)
			)
			continue;
		
		$Overlays[$id] = $data;
	}


	ksort($Overlays);
	
	$radius = $_BO['radius'] * 1000;

	$lat = (double)$_GET['bo_lat'];
	$lon = (double)$_GET['bo_lon'];
	$zoom = intval($_GET['bo_zoom']);

	if ($lat || $lon || $zoom)
		$cookie_load_defaults = false;
	else
		$cookie_load_defaults = true;
	
	list($min_zoom, $max_zoom) = bo_get_zoom_limits();
	$zoom = $zoom ? $zoom : BO_DEFAULT_ZOOM;
	$lat = $lat ? $lat : BO_LAT;
	$lon = $lon ? $lon : BO_LON;

	if ($zoom < $min_zoom || $zoom > $max_zoom)
		$zoom = $min_zoom;
	
	
	echo '<fieldset class="bo_map_options">';
	echo '<legend>'._BL("map_options").'</legend>';
	ksort($_BO['mapcfg']);
	$min_upd_interval = null;
	$i = 0;
	foreach ($_BO['mapcfg'] as $mapid => $cfg)
	{
		if (!is_array($cfg) || empty($cfg) || ($cfg['only_loggedin'] && !bo_user_get_level()) || !$cfg['sel_name'] || !$cfg['upd_intv'])
			continue;
		
		$mapcfg[$i] = $cfg;
		$mapcfg[$i]['id'] = $mapid;
		$mapcfg[$i]['upd_intv'] = max($cfg['upd_intv'], BO_UP_INTVL_STRIKES);
		
		if ($min_upd_interval == null || $min_upd_interval > $mapcfg[$i]['upd_intv'])
			$min_upd_interval = $mapcfg[$i]['upd_intv'];
		
		$name = strtr($cfg['sel_name'], array('min' => _BL('unit_minutes'), 'h' => _BL('unit_hours'), 'days' => _BL('unit_days')));
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" onclick="bo_map_toggle_overlay(this.checked, '.$i.');" ';
		echo $cfg['default_show'] ? ' checked="checked" ' : '';
		echo ' id="bo_map_opt'.$i.'"> ';
		echo '<label for="bo_map_opt'.$i.'">'.$name.'</label> &nbsp; ';
		echo '</span>';
		
		$i++;
	}

	$mapcfg[-1] = $_BO['mapcfg'][-1];
	$mapcfg[-1]['id'] = -1;
	
	echo ' <input type="submit" value="'._BL('more').' &dArr;" onclick="return bo_map_show_more();" id="bo_map_more">';
	echo ' <input type="submit" value="'._BL('update map').'" onclick="bo_map_update(); return false;" id="bo_map_reload">';

	if (intval(BO_MAP_AUTOUPDATE))
	{
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" onclick="bo_map_toggle_autoupdate(this.checked);" id="bo_check_autoupdate"> ';
		echo '<label for="bo_check_autoupdate">'._BL('auto update').'</label> ';
		echo '</span>';
	}
	
	echo '<div id="bo_map_more_container" style="display: none">';

	
	/*** Manual time range ***/
	
	if (BO_MAP_MANUAL_TIME_ENABLE === true || (bo_user_get_level() & BO_PERM_NOLIMIT))
	{
		$max_range = intval(BO_MAP_MANUAL_TIME_MAX_HOURS);
		if ($max_range > 24)
			$max_range = 24;
	
		$yesterday = strtotime('now -1 day');
		$year1     = (int)date('Y', $yesterday);
		$month1    = (int)date('m', $yesterday);
		$day1      = (int)date('d', $yesterday);
		$hour1     = 0;
		$minute1   = 0;
		
		$time2   = strtotime("$year1-$month1-$day1 $hour1:$minute1:00 UTC") + $max_range * 3600;
		$year2   = (int)date('Y', $time2);
		$month2  = (int)date('m', $time2);
		$day2    = (int)date('d', $time2);
		$hour2   = (int)date('H', $time2);
		$minute2 = (int)date('i', $time2);
		
		
		echo '<div class="bo_input_container" id="bo_map_timerange">';
		echo '<span class="bo_form_descr">'._BL('Time range').':</span> ';

		echo '<select id="bo_map_select_year1" disabled>';
		for($i=date('Y', $start_time); $i<=date('Y');$i++)
			echo '<option value="'.$i.'" '.($i == $year1 ? 'selected' : '').'>'.$i.'</option>';
		echo '</select> ';
		echo '<select id="bo_map_select_month1" disabled>';
		for($i=1;$i<=12;$i++)
			echo '<option value="'.$i.'" '.($i == $month1 ? 'selected' : '').'>'._BL(date('M', strtotime("2000-$i-01"))).'</option>';
		echo '</select> ';
		echo '<select id="bo_map_select_day1" disabled>';
		for($i=1;$i<=31;$i++)
			echo '<option value="'.$i.'" '.($i == $day1 ? 'selected' : '').'>'.$i.'</option>';
		echo '</select> &nbsp; ';
		echo '<select id="bo_map_select_hour1" disabled>';
		for($i=0;$i<=23;$i++)
			echo '<option value="'.$i.'" '.($i == $hour1 ? 'selected' : '').'>'.sprintf('%02d', $i).'</option>';
		echo '</select> : ';
		echo '<select id="bo_map_select_minute1" disabled>';
		for($i=0;$i<=59;$i+=5)
			echo '<option value="'.$i.'" '.($i == $minute1 ? 'selected' : '').'>'.sprintf('%02d', $i).'</option>';
		echo '</select>';
		echo ' - ';
		echo '<select id="bo_map_select_year2" disabled>';
		for($i=date('Y', $start_time); $i<=date('Y');$i++)
			echo '<option value="'.$i.'" '.($i == $year2 ? 'selected' : '').'>'.$i.'</option>';
		echo '</select> ';
		echo '<select id="bo_map_select_month2" disabled>';
		for($i=1;$i<=12;$i++)
			echo '<option value="'.$i.'" '.($i == $month2 ? 'selected' : '').'>'._BL(date('M', strtotime("2000-$i-01"))).'</option>';
		echo '</select> ';
		echo '<select id="bo_map_select_day2" disabled>';
		for($i=1;$i<=31;$i++)
			echo '<option value="'.$i.'" '.($i == $day2 ? 'selected' : '').'>'.$i.'</option>';
		echo '</select> &nbsp; ';
		echo '<select id="bo_map_select_hour2" disabled>';
		for($i=0;$i<=23;$i++)
			echo '<option value="'.$i.'" '.($i == $hour2 ? 'selected' : '').'>'.sprintf('%02d', $i).'</option>';
		echo '</select> : ';
		echo '<select id="bo_map_select_minute2" disabled>';
		for($i=0;$i<=59;$i+=5)
			echo '<option value="'.$i.'" '.($i == $minute2 ? 'selected' : '').'>'.sprintf('%02d', $i).'</option>';
		echo '</select>';
		
		echo ' &nbsp; ';
		
		echo ' <span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" id="bo_map_timerange_check" onclick="bo_toggle_timerange(this.checked);"> ';
		echo '<label for="bo_map_timerange_check">'._BL('Activated').'</label>';
		echo '</span>';
		
		echo '</div>';
	}

	$show_adv_counter    = (bo_user_get_level() & BO_PERM_NOLIMIT);
	$show_station_select = (bo_user_get_level() & BO_PERM_NOLIMIT) && BO_MAP_STATION_SELECT === true; 
	
	if (!$show_adv_counter || intval(BO_TRACKS_SCANTIME) || bo_station_id() > 0)
	{
		echo '<div class="bo_input_container" id="bo_map_advanced_options">';
		echo '<span class="bo_form_descr">'._BL('Advanced').':</span> ';
		
		if (intval(BO_TRACKS_SCANTIME))
		{
			echo '<span class="bo_form_checkbox_text">';
			echo '<input type="checkbox" onclick="bo_map_toggle_tracks(this.checked);" id="bo_map_opt_tracks"> ';
			echo '<label for="bo_map_opt_tracks">'._BL("show tracks").'</label> &nbsp; ';
			echo '</span>';
		}
		
		if (!$show_station_select && bo_station_id() > 0)
		{
			echo '<span class="bo_form_checkbox_text">';
			echo '<input type="checkbox" onclick="bo_map_toggle_stationid_display(this.checked);" id="bo_map_opt_own"> ';
			echo '<label for="bo_map_opt_own">'._BL("only own strikes").'</label> &nbsp; ';
			echo '</span>';
		}
		
		if (!$show_adv_counter)
		{
			echo '<span class="bo_form_checkbox_text">';
			echo '<input type="checkbox" onclick="bo_map_toggle_count(this.checked);" id="bo_map_opt_count"> ';
			echo '<label for="bo_map_opt_count">'._BL("show strike counter").'</label> &nbsp; ';
			echo '</span>';
		}

		echo '</div>';
	}
	
	if ($show_adv_counter)
	{

		echo '<div class="bo_input_container" id="bo_map_statistic_options">';
		echo '<span class="bo_form_descr">'._BL('Statistics').':</span> ';
		
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="radio" name="bo_map_counter" value="0" onclick="bo_map_toggle_count(this.value);" id="bo_map_opt_count0"> ';
		echo '<label for="bo_map_opt_count0">'._BL("Off").'</label> &nbsp; ';
		echo '</span>';
		
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="radio" name="bo_map_counter" value="1" onclick="bo_map_toggle_count(this.value);" id="bo_map_opt_count"> ';
		echo '<label for="bo_map_opt_count">'._BL("show strike counter").'</label> &nbsp; ';
		echo '</span>';
		
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="radio" name="bo_map_counter" value="2" onclick="bo_map_toggle_count(this.value);" id="bo_map_opt_count2"> ';
		echo '<label for="bo_map_opt_count2">'._BL("show strike counter").' ('._BL('stations').')</label> &nbsp; ';
		echo '</span>';
		
		echo '</div>';
	}

	if ($show_station_select)
	{
		$opts = bo_get_station_list($style_class);
		
		echo '<div class="bo_input_container" id="bo_map_statistic_options">';
		echo '<span class="bo_form_descr">'._BL('Station').':</span> ';
		
		echo '<span class="bo_form_checkbox_text">';
		
		echo '<select name="bo_station_id" id="bo_only_station_id" onchange="bo_map_toggle_stationid(this.value);">';
		echo '<option></option>';
		foreach($opts as $id => $name)
		{
			echo '<option value="'.$id.'" class="'.$style_class[$id].'">';
			echo $name;
			echo '</option>';
		}
		echo '</select>';
		echo '</span>';
		
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" onclick="bo_map_toggle_stationid_display(this.checked);" id="bo_map_opt_strikes_station"> ';
		echo '<label for="bo_map_opt_strikes_station">'._BL("Only strikes of selected station").'</label> &nbsp; ';
		echo '</span>';
		
		echo '</div>';

	}
	
	echo '<div class="bo_input_container" id="bo_map_stations_options">';
	echo '<span class="bo_form_descr">'._BL('Show Stations').':</span> ';
	echo '<span class="bo_form_checkbox_text">';
	echo '<input type="radio" onclick="bo_map_toggle_stations(this.value);" value="1" name="bo_map_station" id="bo_map_station0" checked>';
	echo '<label for="bo_map_station0">'._BL('None').'</label> &nbsp; ';
	echo '</span>';

	echo '<span class="bo_form_checkbox_text">';
	echo '<input type="radio" onclick="bo_map_toggle_stations(this.value);" value="2" name="bo_map_station" id="bo_map_station1">';
	echo '<label for="bo_map_station1">'._BL('Stations').'</label> &nbsp; ';
	echo '</span>';

	if (count($mybo_info) > 1)
	{
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="radio" onclick="bo_map_toggle_stations(this.value);" value="3" name="bo_map_station" id="bo_map_station2">';
		echo '<label for="bo_map_station2">'._BL('MyBlitzortung').' '._BL('stations').'</label> &nbsp; ';
		echo '</span>';
	}
	
	echo '</div>';
	
	if (count($Overlays))
	{
		
		echo '<div class="bo_input_container" id="bo_map_overlay_options">';
		echo '<span class="bo_form_descr">'._BL('Extra overlays').':</span> ';

		$ovl_maps_showed = false;
		
		foreach($Overlays as $id => $cfg)
		{
			if (!$ovl_maps_showed && $cfg['is_map'])
			{
				echo '<a href="#" onclick="javascript:document.getElementById(\'bo_extra_ovl_maps\').style.display=\'inline\';this.style.display=\'none\';">'._BL('Own maps').'</a>';

				echo '<span style="display:none" id="bo_extra_ovl_maps">';
				$ovl_maps_showed = true;
			}
			
			echo '<span class="bo_form_checkbox_text">';
			echo '<input type="checkbox" onclick="bo_map_toggle_extraoverlay(this.checked, this.value);" value="'.$id.'" name="bo_map_overlay" id="bo_map_overlay'.$id.'" ';
			echo $cfg['default_show'] ? ' checked="checked" ' : '';
			echo '>';
			echo '<label for="bo_map_overlay'.$id.'">';
			if ($cfg['sel_name'])
				echo _BL($cfg['sel_name'], false, BO_CONFIG_IS_UTF8);
			else
				echo '<em>'._BL('Id').' '.$id.'</em>';
			
			if ($cfg['kml'])
				echo ' <em>('._BL('KML').')</em>';
			
			echo '</label> &nbsp; ';
			echo '</span>';
		}
		
		if ($ovl_maps_showed)
			echo '</span>';
		
		echo '</div>';
	}
	
	echo '</div>';
	echo '</fieldset>';

	echo '<div id="bo_gmap" class="bo_map" style="width:500px; height:400px;"></div>';

	?>
	
	<script type="text/javascript">
	
	var bo_OverlayMaps = new Array();
	var bo_OverlayCount;
	var bo_OverlayTracks;
	var bo_ExtraOverlay = [];
	var bo_ExtraOverlayMaps = [];
	var bo_show_only_stationid = false;
	var bo_select_stationid = 0;
	var bo_show_count = 0;
	var bo_show_tracks = 0;
	var bo_mybo_markers = [];
	var bo_mybo_circles = [];
	var bo_station_markers = [];
	var bo_station2_marker;
	var bo_stations_display = 1;
	var bo_mybo_stations = [ <?php echo $js_mybo_stations ?> ];
	var bo_stations      = [ <?php echo $js_stations ?> ];
	var bo_infowindow;
	var bo_autoupdate = false;
	var bo_autoupdate_running = false;
	var bo_manual_timerange = false;
	var bo_user_last_activity = 0;
	var bo_reload_mapinfo_next = false;
	var bo_start_time_local = new Date();
	var bo_start_time_server = <?php echo time() * 1000 ?>;

	function bo_gmap_init2()
	{ 
		var i;
		bo_infowindow = new google.maps.InfoWindow({content: ''});

		
<?php
		
		foreach($mapcfg as $mapid => $cfg)
		{
			echo '
			bo_OverlayMaps['.$mapid.'] = {
				getTileUrl: function (coord, zoom) { return bo_get_tile(zoom, coord, '.$cfg['id'].', '.intval($cfg['upd_intv']).', '.BO_TILE_SIZE.'); },
				tileSize: new google.maps.Size('.BO_TILE_SIZE.','.BO_TILE_SIZE.'), 
				isPng:true, 
				bo_show:'.($cfg['default_show'] ? 'true' : 'false').',
				bo_interval:'.intval($cfg['upd_intv']).',
				bo_mapid:'.$cfg['id'].'
			};
			';
		}
		
		foreach($Overlays as $ovlid => $cfg)
		{
			if (isset($cfg['img']))
			{
				if (strpos($cfg['img'], '?') === false)
					$cfg['img'] .= '?';

				echo '
					bo_ExtraOverlay['.$ovlid.'] = {
						bo_image: "'.$cfg['img'].'",
						bo_bounds: new google.maps.LatLngBounds(
									   new google.maps.LatLng('.(double)$cfg['coord'][2].','.(double)$cfg['coord'][3].'),
									   new google.maps.LatLng('.(double)$cfg['coord'][0].','.(double)$cfg['coord'][1].')),
						bo_show: '.($cfg['default_show'] ? 'true' : 'false').',
						bo_opacity: '.((double)$cfg['opacity']).',
						bo_tomercator: '.($cfg['to_mercator'] ? 'true' : 'false').',
						bo_layer: '.((int)$cfg['layer']).'

				};
				';
			}
			else if (isset($cfg['kml']))
			{
				echo '
					bo_ExtraOverlay['.$ovlid.'] = {
						bo_kml: "'.$cfg['kml'].'",
						bo_show: '.($cfg['default_show'] ? 'true' : 'false').'
				};
				';
			}

		}
		

?>		

		bo_OverlayCount = {
			getTileUrl: function (coord, zoom) { return bo_get_tile_counts(zoom, coord, <?php echo BO_TILE_SIZE_COUNT ?>); },
			tileSize: new google.maps.Size(<?php echo BO_TILE_SIZE_COUNT.','.BO_TILE_SIZE_COUNT ?>), 
			isPng:true, 
			bo_show:false
		};

		bo_OverlayTracks = {
			getTileUrl: function (coord, zoom) { return bo_get_tile_tracks(zoom, coord, <?php echo BO_TILE_SIZE ?>); },
			tileSize: new google.maps.Size(<?php echo BO_TILE_SIZE.','.BO_TILE_SIZE ?>), 
			isPng:true, 
			opacity:<?php echo (double)BO_TRACKS_MAP_OPACITY; ?>,
			minZoom:<?php echo (int)BO_TRACKS_MAP_ZOOM_MIN; ?>,
			maxZoom:<?php echo (int)BO_TRACKS_MAP_ZOOM_MAX; ?>,
			bo_show:false
		};
		
		var c = bo_getcookie('bo_select_stationid');
		if (c)
		{
			bo_select_stationid = c == -1 ? 0 : c;
			
			if (document.getElementById('bo_map_opt_own') != null)
				document.getElementById('bo_map_opt_own').checked = c == -1 ? false : true;
			
			if (document.getElementById('bo_only_station_id') != null)
				document.getElementById('bo_only_station_id').value = c > 0 ? c : 0;
			
			if (c > 0) bo_map_show_more();
		}

		<?php if (!$show_station_select) { ?>
		bo_select_stationid = <?php echo bo_station_id(); ?>;
		<?php } ?>
		
		var c = bo_getcookie('bo_show_count');
		if (c)
		{
			bo_show_count = c == -1 ? 0 : 1;
			document.getElementById('bo_map_opt_count').checked = c == -1 ? false : true;
			if (c > 0) bo_map_show_more();
		}

		
<?php	
	if (intval(BO_TRACKS_SCANTIME)) 
	{
?>
		var c = bo_getcookie('bo_show_tracks');
		if (c)
		{
			bo_show_tracks = c == -1 ? 0 : 1;
			document.getElementById('bo_map_opt_tracks').checked = c == -1 ? false : true;
			if (c > 0) bo_map_show_more();
		}
<?php
	}
?>

		for (i=0;i<bo_OverlayMaps.length;i++)
		{
			var c = bo_getcookie('bo_show_ovl'+i);
			if (c && bo_OverlayMaps[i] != null)
			{
				bo_OverlayMaps[i].bo_show = c == -1 ? false : true;
				document.getElementById('bo_map_opt' + i).checked = c == -1 ? false : true;
			}
		}

		for (i=0;i<bo_ExtraOverlay.length;i++)
		{
			var c = bo_getcookie('bo_show_extraovl'+i);
			if (c && bo_ExtraOverlay[i] != null)
			{
				bo_ExtraOverlay[i].bo_show = c == -1 ? false : true;
				document.getElementById('bo_map_overlay' + i).checked = c == -1 ? false : true;
				if (c != -1) bo_map_show_more();
			}
		}
		
		bo_infobox = document.createElement('DIV');
		bo_infobox.index = 1;
		bo_map.controls[google.maps.ControlPosition.RIGHT_TOP].push(bo_infobox);
		
		bo_map_reload_overlays();  
		
		bo_station2_marker = new google.maps.Marker();
		bo_map_toggle_stations(0);
		
<?php  if (bo_user_get_level()) { ?>
		google.maps.event.addListener(bo_map, 'rightclick', function(event) {
		if (bo_map.getZoom() > 3)
		{
			window.open("<?php echo BO_ARCHIVE_URL ?>&bo_show=strikes&bo_lat="+event.latLng.lat()+"&bo_lon="+event.latLng.lng()+"&bo_zoom="+bo_map.getZoom(), '_blank');
		}
		});
<?php  } ?>
		
		google.maps.event.addListener(bo_map, 'dragend', function() {
			bo_setcookie('bo_map_lat', bo_map.getCenter().lat());
			bo_setcookie('bo_map_lon', bo_map.getCenter().lng());
			bo_setcookie('bo_map_zoom', bo_map.getZoom());
			bo_map_user_activity();
		});

		google.maps.event.addListener(bo_map, 'zoom_changed', function() {
			if (this.getZoom() < <?php echo $min_zoom ?>)
				 this.setZoom(<?php echo $min_zoom ?>);
			if (this.getZoom() > <?php echo $max_zoom ?>)
				 this.setZoom(<?php echo $max_zoom ?>);
			else
				bo_setcookie('bo_map_zoom', this.getZoom());
			
			bo_map_toggle_stations(0);
			bo_map_user_activity();
			bo_show_circle(this.getZoom());
		}); 

		google.maps.event.addListener(bo_map, 'maptypeid_changed', function() {
			bo_setcookie('bo_map_type', bo_map.getMapTypeId());
			bo_map_user_activity();
		});
		
				
		var map_lat = bo_getcookie('bo_map_lat');
		var map_lon = bo_getcookie('bo_map_lon');
		var map_zoom = bo_getcookie('bo_map_zoom');
		var map_type = bo_getcookie('bo_map_type');
		
		if (map_zoom < <?php echo $min_zoom ?> || map_zoom > <?php echo $max_zoom ?>)
		{
			map_zoom = <?php echo BO_DEFAULT_ZOOM ?>;
			map_lat  = <?php echo BO_LAT ?>;
			map_lon  = <?php echo BO_LON ?>;
		}
		
		if (map_lat > 0 && map_lon > 0 && <?php echo $cookie_load_defaults ? 'true' : 'false' ?>)
			bo_map.setOptions({ center: new google.maps.LatLng(map_lat,map_lon) });
		
		if (map_zoom > 0 && <?php echo $cookie_load_defaults ? 'true' : 'false' ?>)
			bo_map.setOptions({ zoom: parseInt(map_zoom) });

		if (map_type.match(/[a-z]+/i))
			bo_map.setOptions({ mapTypeId: map_type });

		bo_map_user_activity();
		window.setTimeout("bo_map_timer();", 1000 * 60);
	}	
	
	function bo_map_timer()
	{
		var now = new Date();
		var time = now.getTime() / 1000;
		
		if (bo_autoupdate && time-bo_user_last_activity > <?php echo intval(BO_MAP_AUTOUPDATE)*60 ?>)
		{
			bo_map_toggle_autoupdate(false);
			document.getElementById("bo_check_autoupdate").checked=false;
			
			<?php if (BO_MAP_AUTOUPDATE_STALL_MSG === true) { 
			echo 'alert("'.strtr(_BL(map_autoupdate_stalled_msg), array('"' => '\"')).'");';
			} ?>

			<?php if (BO_MAP_AUTOUPDATE_STALL_RELOAD === true) { ?>
			bo_map_reload_page();
			<?php } ?>			
			
		}
		
		bo_reload_mapinfo_next = true;
		window.setTimeout("bo_map_timer();", 1000 * 60);
	}
	
	function bo_map_user_activity()
	{
		var now = new Date();
		var time = now.getTime() / 1000;

		<?php if (intval(BO_MAP_PAGE_RELOAD_INACTIVITY) > 0) { ?>
		if (bo_user_last_activity > 0 && time-bo_user_last_activity > <?php echo intval(BO_MAP_PAGE_RELOAD_INACTIVITY)*60 ?>)
		{
			bo_map_reload_page();
		}
		<?php } ?>	

		bo_user_last_activity = now.getTime() / 1000;
		
		if (bo_reload_mapinfo_next)
		{
			bo_reload_mapinfo_next = false;
			bo_reload_mapinfo();
		}
	}
	
	function bo_map_reload_page()
	{
		window.location.reload();
	}
	
	function bo_map_show_more()
	{
		document.getElementById('bo_map_more_container').style.display='block';
		document.getElementById('bo_map_more').style.display='none';
		bo_map_user_activity();
		return false;
	}
	
	function bo_map_toggle_autoupdate(auto)
	{
		bo_autoupdate = auto;
		bo_map_start_autoupdate();
		bo_map_user_activity();
	}

	function bo_map_start_autoupdate()
	{
		if (bo_autoupdate_running)
			return;
		
		if (bo_autoupdate)
		{
			bo_autoupdate_running = true;
			window.setTimeout("bo_map_do_autoupdate();", <?php echo 1000 * 60 * intval($min_upd_interval) ?>);
		}
	}

	function bo_map_do_autoupdate()
	{
		bo_map_reload_overlays();
		bo_autoupdate_running = false;
		bo_map_start_autoupdate();
	}

	
	function bo_map_toggle_stations(display)
	{
		bo_map_user_activity();
		var auto = display == 0;
		
		if (display)
			bo_stations_display = display;
		else
			display = bo_stations_display;
			
		for (i in bo_mybo_markers)
			bo_mybo_markers[i].setMap(null);

		for (i in bo_mybo_circles)
			bo_mybo_circles[i].setMap(null);

		for (i in bo_station_markers)
			bo_station_markers[i].setMap(null);

		bo_station2_marker.setMap(null);

		if (auto && bo_map.getZoom() > <?php echo (bo_user_get_level() & BO_PERM_SETTINGS) ? 20 : 9; ?>)
		{
			document.getElementById('bo_map_station1').disabled = true;
			if (bo_stations_display == 2)
				return;
		}
		else if (auto && bo_map.getZoom() <= <?php echo (bo_user_get_level() & BO_PERM_SETTINGS) ? 20 : 9; ?>)
		{
			document.getElementById('bo_map_station1').disabled = false;
			if (bo_stations_display == 2)
				display = 2;
		}
		
		if (display == 2)
		{
			if (bo_station_markers.length == 0)
			{
				var color;
				for (i in bo_stations)
				{
					switch (bo_stations[i].status)
					{
						case 'A': color = '00cc00'; break;
						case 'O': color = 'cc0000'; break;
						case 'V': color = 'cc8800'; break;
						default:  color = '888888'; break;
					}
					
					bo_station_markers[i] = new google.maps.Marker({
					  position: new google.maps.LatLng(bo_stations[i].lat,bo_stations[i].lon), 
					  map: bo_map, 
					  title:bo_stations[i].city + bo_stations[i].text,
					  icon: new google.maps.MarkerImage(
								'<?php echo bo_bofile_url() ?>?bo_icon='+color+'&size=2&square',
								new google.maps.Size(9,9),
								new google.maps.Point(0,0),
								new google.maps.Point(4,4)
							),
					  stid: bo_stations[i].stid
					});  

					
<?php if (BO_STATISTICS_ALL_STATIONS == 2 || ((bo_user_get_level() & BO_PERM_NOLIMIT))) { ?>
					
					google.maps.event.addListener(bo_station_markers[i], 'click', function() {
						window.open('<?php echo BO_STATISTICS_URL ?>&bo_show=station&bo_station_id=' + this.stid, '_blank');
					}); 

<?php } ?>

					
				}
			}
			else
			{
				for (i in bo_station_markers)
					bo_station_markers[i].setMap(bo_map);
			}
		}
		else if (display == 3)
		{
			if (bo_mybo_markers.length == 0)
			{
				for (i in bo_mybo_stations)
				{
					var latlon = new google.maps.LatLng(bo_mybo_stations[i].lat,bo_mybo_stations[i].lon);
					
					bo_mybo_markers[i] = new google.maps.Marker({
					  position: latlon, 
					  map: bo_map, 
					  title: bo_mybo_stations[i].city,
					  icon: '<?php echo  BO_MAP_MYBO_ICON ?>',
					  content: '<a href="'+bo_mybo_stations[i].url+'" target="_blank">' + bo_mybo_stations[i].city + '</a>'
					});
					
					bo_mybo_circles[i] = new google.maps.Circle({
					  clickable: false,
					  strokeColor: "<?php echo  BO_MAP_MYBO_CIRCLE_COLOR_LINE ?>",
  					  strokeOpacity: <?php echo  BO_MAP_MYBO_CIRCLE_OPAC_LINE ?>,
	  				  strokeWeight: 1,
					  fillColor: "<?php echo  BO_MAP_MYBO_CIRCLE_COLOR_FILL ?>",
					  fillOpacity: <?php echo  BO_MAP_MYBO_CIRCLE_OPAC_FILL ?>,
					  map: bo_map,
					  center: latlon,
					  radius: bo_mybo_stations[i].rad * 1000
					});
					
					google.maps.event.addListener(bo_mybo_markers[i], 'click', function() {
						bo_infowindow.setContent(this.content);
						bo_infowindow.open(bo_map, this);
					});
				}

			}
			else
			{
				for (i in bo_mybo_markers)
					bo_mybo_markers[i].setMap(bo_map);
				
				for (i in bo_mybo_circles)
					bo_mybo_circles[i].setMap(bo_map);
			}
		}
		
		if (bo_select_stationid > 0)
		{
			for (i in bo_stations)
			{
				if (bo_stations[i].stid == bo_select_stationid)
				{
					bo_station2_marker = new google.maps.Marker({
					  position: new google.maps.LatLng(bo_stations[i].lat,bo_stations[i].lon), 
					  map: bo_map, 
					  title:bo_stations[i].city,
					  icon: '<?php echo  BO_MAP_STATION_ICON2 ?>',
					  stid: bo_stations[i].stid
					});  
				}
			}
		}
		
		
	}
	
	function bo_map_update()
	{
		bo_map_reload_overlays();
		bo_map_user_activity();
	}
	
	function bo_map_toggle_overlay(checked, type)
	{
		bo_setcookie('bo_show_ovl'+type, checked ? 1 : -1);
		bo_OverlayMaps[type].bo_show = checked;
		bo_map_reload_overlays();
		bo_map_user_activity();
	}
	
	function bo_map_toggle_stationid(id)
	{
		bo_setcookie('bo_select_stationid', id > 0 ? id : -1);
		bo_select_stationid = id > 0 ? id : 0;
		bo_map_reload_overlays();
		bo_map_toggle_stations();
		bo_map_user_activity();
	}

	function bo_map_toggle_stationid_display(checked)
	{
		bo_show_only_stationid = checked;
		bo_map_reload_overlays();
		bo_map_user_activity();
	}	
	

	function bo_map_toggle_extraoverlay(checked, type)
	{
		bo_setcookie('bo_show_extraovl'+type, checked ? 1 : -1);
		bo_ExtraOverlay[type].bo_show = checked;
		bo_map_reload_overlays();
		bo_map_user_activity();
	}
	
	function bo_map_toggle_count(value, type)
	{ 
		value = value == '0' ? false : value;
		bo_setcookie('bo_show_count', value ? 1 : -1);
		bo_show_count = value;
		bo_map_reload_overlays();
		bo_map_user_activity();
	}

	function bo_map_toggle_tracks(checked)
	{
		bo_setcookie('bo_show_tracks', checked ? 1 : -1);
		bo_show_tracks = checked ? 1 : 0;
		bo_map_reload_overlays();
		bo_map_user_activity();
	}
	
	function bo_map_reload_overlays()
	{
		var i;
		var bo_add_transparent_layer = false;
		var now = new Date();
		var time = now.getTime() / 1000;
		
		if (bo_ExtraOverlayMaps.length == 0)
		{
			for (i in bo_ExtraOverlay)
			{
				if (bo_ExtraOverlay[i].bo_kml)
				{
					bo_ExtraOverlayMaps[i] = new google.maps.KmlLayer(bo_ExtraOverlay[i].bo_kml, {preserveViewport: true} );
				}
				else if (bo_ExtraOverlay[i].bo_tomercator)
				{
					bo_ExtraOverlayMaps[i] = new google.maps.GroundOverlay(bo_ExtraOverlay[i].bo_image + '&bo_t=' + Math.floor(time / 60 / 5), bo_ExtraOverlay[i].bo_bounds);
					bo_ExtraOverlayMaps[i].clickable = false;
				}
				else
				{
					bo_ExtraOverlayMaps[i] = new bo_ProjectedOverlay(bo_map, bo_ExtraOverlay[i].bo_image + '&bo_t=' + Math.floor(time / 60 / 5), bo_ExtraOverlay[i].bo_bounds, {opacity: bo_ExtraOverlay[i].bo_opacity, layer: bo_ExtraOverlay[i].bo_layer}) ;
				}
			}
		}
		
		for (i in bo_ExtraOverlay)
		{
			for (i in bo_ExtraOverlay)
			{
				if (bo_ExtraOverlay[i].bo_show)
				{
					if (bo_ExtraOverlay[i].bo_layer == 1)
						bo_add_transparent_layer = true;

					bo_ExtraOverlayMaps[i].setMap(bo_map);
				}
				else
					bo_ExtraOverlayMaps[i].setMap(null);
			}
		}
		
		while (bo_map.overlayMapTypes.length)
			bo_map.overlayMapTypes.pop();
		
		//adds a "dummy" layer when an overlay *behind* strikes is present
		//this is a quick&dirty workaround due to googlemaps limitations (causes warnings in console!)
		if (bo_add_transparent_layer)
		{
			
			var tmp = [];
			bo_map.overlayMapTypes.push(tmp);
		}
		
		var overlay_count=0;
		if (bo_manual_timerange == true)
		{
			bo_map.overlayMapTypes.push(new google.maps.ImageMapType(bo_OverlayMaps[-1]));
			overlay_count=1;
		}
		else
		{
			for (i=bo_OverlayMaps.length-1; i>=0;i--)
			{
				if (bo_OverlayMaps[i].bo_show)
				{
					bo_map.overlayMapTypes.push(new google.maps.ImageMapType(bo_OverlayMaps[i]));
					overlay_count++;
				}
			}

			if (bo_show_tracks)
				bo_map.overlayMapTypes.push(new google.maps.ImageMapType(bo_OverlayTracks));

		}

		
		if (bo_show_count && overlay_count)
			bo_map.overlayMapTypes.push(new google.maps.ImageMapType(bo_OverlayCount));
			
		bo_reload_mapinfo();
		
	}
	
	function bo_tile_coord(zoom, coord, tile_size)
	{
		this.failimg = "<?php echo bo_bofile_url() ?>?image=bt";
		var a = Math.pow(2, zoom) / (tile_size/256);
		
		if (coord.y < 0 || coord.y >= a)
		{
			this.ok = false;
		}
		else
		{
			this.x = coord.x < 0 ? coord.x%a+a : coord.x%a;
			this.y = coord.y;
			this.ok = true;
		}
	}
	
	function bo_get_tile(zoom, coord, type, interval, tile_size)
	{
		c = new bo_tile_coord(zoom, coord, tile_size);
		if (!c.ok) return c.failimg;
		
		var url = "<?php echo bo_bofile_url() ?>?tile&zoom="+zoom+"&x="+c.x+"&y="+c.y+"<?php echo bo_lang_arg('tile'); ?>";
		
		if (bo_select_stationid > 0 && bo_show_only_stationid)
			url=url+"&os&sid="+bo_select_stationid;
			
		var add = "";
		
		//manual time range
		if (type == -1)
		{
			return url+"&type="+type+"&from="+bo_get_time_man(1)+"&to="+bo_get_time_man(2)+"&"+add;
		}
		else
		{
			//defined time range
			add = bo_get_time_arg(interval) + (bo_loggedin ? '_1' : '');
			return url+"&type="+type+add;
		}
		
	}

	function bo_get_tile_counts(zoom, coord, tile_size)
	{
		c = new bo_tile_coord(zoom, coord, tile_size);
		if (!c.ok) return c.failimg;

		var types='';
		var interval=0;
		var add = "";
		
		if (bo_manual_timerange)
		{
			types = '-1';
			add = "&from="+bo_get_time_man(1)+"&to="+bo_get_time_man(2);
		}
		else
		{
			add = bo_get_time_arg(interval);
			
			for (i in bo_OverlayMaps)
			{
				if (bo_OverlayMaps[i].bo_show)
				{
					types = types + (types ? ',' : '') + bo_OverlayMaps[i].bo_mapid;
					
					if (!interval || interval > bo_OverlayMaps[i].bo_interval)
						interval = bo_OverlayMaps[i].bo_interval;
				}
			}
		}
		
		var url="<?php echo bo_bofile_url() ?>?tile&count="+types+"&stat="+bo_show_count+"&zoom="+zoom+"&x="+c.x+"&y="+c.y+"&<?php bo_lang_arg('tile'); ?>";
		
		if (bo_select_stationid > 0)
		{
			if (bo_show_count == 2 || bo_show_only_stationid)
				url=url+"&sid="+bo_select_stationid;
		
			if (bo_show_only_stationid)
				url=url+"&os";
		}
			
		return url+add;
	}
	
	function bo_get_tile_tracks(zoom, coord, tile_size)
	{
		c = new bo_tile_coord(zoom, coord, tile_size);
		if (!c.ok) return c.failimg;

		if (zoom < <?php echo (int)BO_TRACKS_MAP_ZOOM_MIN; ?> || zoom > <?php echo (int)BO_TRACKS_MAP_ZOOM_MAX; ?>)
			return c.failimg;

		var add = bo_get_time_arg(<?php echo intval(BO_UP_INTVL_TRACKS) ?>);
		
		return "<?php echo bo_bofile_url() ?>?tile&tracks&zoom="+zoom+"&x="+c.x+"&y="+c.y+"<?php bo_lang_arg('tile'); ?>"+"&bo_t="+add;
	}
	
	function bo_reload_mapinfo() 
	{
		bo_infobox.innerHTML = '';
		

		var infoUI = document.createElement('DIV');
		infoUI.style.backgroundColor = '#f9f9f9';
		infoUI.style.borderStyle = 'solid';
		infoUI.style.borderWidth = '1px';
		infoUI.style.borderColor = '#bbe';
		infoUI.style.cursor = 'pointer';
		infoUI.style.textAlign = 'center';
		infoUI.style.width = '79px';
		infoUI.style.margin = '0 0 15px 0';
		infoUI.title = '<?php echo _BL('Click to set the map to Home') ?>';

		var infoText = document.createElement('DIV');
		infoText.style.fontFamily = 'Arial,sans-serif';
		infoText.style.fontSize = '12px';
		infoText.style.paddingLeft = '7px';
		infoText.style.paddingRight = '7px';
		infoText.innerHTML = '<?php echo _BL('Home') ?>';
		infoUI.appendChild(infoText);
		
		bo_infobox.style.width = '80px';
		bo_infobox.style.marginTop = '20px';
		bo_infobox.style.padding = '5px';
		bo_infobox.style.textAlign = 'right';
		bo_infobox.appendChild(infoUI);
		
		for (i in bo_OverlayMaps)
		{
			if ( ((i >= 0 && !bo_manual_timerange) || (bo_manual_timerange && i == -1)) && bo_OverlayMaps[i].bo_show)
			{
				var now = new Date();
				var add = now.getUTCDate() + '_' + now.getUTCHours() + '_' + Math.floor(now.getUTCMinutes() / bo_OverlayMaps[i].bo_interval * 5);
				var infoImg = document.createElement('IMG');
				
				if (bo_manual_timerange)
					add = "from="+bo_get_time_man(1)+"&to="+bo_get_time_man(2)+"&" + add;
				
				infoImg.src = "<?php echo bo_bofile_url() ?>?tile&info<?php bo_lang_arg('tile_info'); ?>&type="+bo_OverlayMaps[i].bo_mapid+"&"+add;
				infoImg.style.paddingTop = '5px';
				infoImg.style.display = 'block';
				infoImg.style.opacity = 0.7;
				bo_infobox.appendChild(infoImg);
			}
		}
		
		var infoText = document.createElement('DIV');
		infoText.style.fontFamily = 'Arial,sans-serif';
		infoText.style.fontSize = '10px';
		infoText.style.padding = '1px 1px';
		infoText.style.background = '#ddd';
		infoText.style.margin = '20px 0 0 0';
		infoText.style.textAlign = 'center';
		infoText.style.lineHeight = '12px';
		infoText.style.opacity = 0.9;

		infoText.innerHTML = '<?php echo _BL('lightning data') ?>:<br>&copy;&nbsp;Blitzortung.org';
		bo_infobox.appendChild(infoText);

		google.maps.event.addDomListener(infoUI, 'click', function() {
			var mapOptions = {
			  zoom: bo_home_zoom,
			  center: bo_home
			}
			bo_map.setOptions(mapOptions);
		});

	}
	
	function bo_get_time_man(i)
	{
		return  document.getElementById('bo_map_select_year'+i).value + '-'
			+ document.getElementById('bo_map_select_month'+i).value + '-'
			+ document.getElementById('bo_map_select_day'+i).value + escape(' ')
			+ document.getElementById('bo_map_select_hour'+i).value + escape(':')
			+ document.getElementById('bo_map_select_minute'+i).value;
	}

	function bo_date2tstamp(date)
	{
		 return Date.UTC(
			date.getUTCFullYear(),
			date.getUTCMonth(),
			date.getUTCDate(),
			date.getUTCHours(),
			date.getUTCMinutes(),
			date.getUTCSeconds());
	}	

	function bo_get_time_arg(interval)
	{
		//adjust to server timestamp
		var now_local = new Date();
		var tstamp_local_start = bo_date2tstamp(bo_start_time_local);
		var diff = tstamp_local_start - bo_start_time_server;
		var tstamp_now = bo_date2tstamp(now_local);
		var now = new Date(tstamp_now - diff);

		var multiplicator = <?php echo (int)BO_TILE_UPDATE_MULTI; ?>; //update twice in interval
		var sub = <?php echo (int)BO_TILE_UPDATE_SUB; ?>; //our strikes are a bit behind
		var arg = now.getUTCDate() + '_' + now.getUTCHours();
		
		if (interval > 0)
		{
			if (sub > interval)
				sub = 0;
				
			arg = arg + '_' + Math.floor( (now.getUTCMinutes()-sub) / interval * multiplicator);
		}
		else
		{
			arg = arg + '_0';
		}
		
		if (bo_loggedin)
			arg = arg + '_1';
		
		return "&bo_t=" + arg;
	}

	function bo_toggle_timerange(enable)
	{
		bo_OverlayMaps[-1].bo_show = enable;
		
		bo_manual_timerange=enable;
		
		document.getElementById('bo_map_select_year1').disabled = !enable;
		document.getElementById('bo_map_select_month1').disabled = !enable;
		document.getElementById('bo_map_select_day1').disabled = !enable;
		document.getElementById('bo_map_select_hour1').disabled = !enable;
		document.getElementById('bo_map_select_minute1').disabled = !enable;

		document.getElementById('bo_map_select_year2').disabled = !enable;
		document.getElementById('bo_map_select_month2').disabled = !enable;
		document.getElementById('bo_map_select_day2').disabled = !enable;
		document.getElementById('bo_map_select_hour2').disabled = !enable;
		document.getElementById('bo_map_select_minute2').disabled = !enable;
		
		for (i in bo_OverlayMaps)
		{
			if (i >= 0)
				document.getElementById('bo_map_opt' + i).disabled = enable;
		}

<?php	
	if (intval(BO_TRACKS_SCANTIME)) 
	{
?>
		document.getElementById('bo_map_opt_tracks').disabled = enable;
<?php	
	}
?>
		
		document.getElementById('bo_check_autoupdate').disabled = enable;
		bo_map_reload_overlays();
	}
	
		
	</script>
	
	<?php

	bo_insert_map(3, $lat, $lon, $zoom);
}


?>