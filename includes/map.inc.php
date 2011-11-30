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

function bo_show_map($var1=null,$var2=null)
{
	bo_show_lightning_map($var1,$var2);
	bo_copyright_footer();
}

function bo_insert_map($show_station=3, $lat=BO_LAT, $lon=BO_LON, $zoom=BO_DEFAULT_ZOOM, $type=BO_DEFAULT_MAP)
{
	global $_BO;

	$radius = $_BO['radius'] * 1000;

	$info = bo_station_info();
	
	$station_lat = BO_LAT;
	$station_lon = BO_LON;
	$station_text = $info['city'];
	
?>


	<script type="text/javascript" id="bo_script_map">
	
	var bo_map;
	var bo_home;
	var bo_home_zoom;
	var bo_infobox;
	var bo_loggedin = <?php echo intval(bo_user_get_level()) ?>;
	
	function bo_gmap_init() 
	{ 
		bo_home = new google.maps.LatLng(<?php echo  $lat ?>, <?php echo  $lon ?>);
		bo_home_zoom = <?php echo  $zoom ?>;
		
		var mapOptions = {
		  zoom: bo_home_zoom,
		  center: bo_home,
		  mapTypeId: google.maps.MapTypeId.<?php echo $type ?>,
		  scaleControl: true,
		  streetViewControl: false,
		  scrollwheel: false
		};

		bo_map = new google.maps.Map(document.getElementById("bo_gmap"), mapOptions);
<?php
	if ($show_station & 1)
	{
?>
		var myLatlng = new google.maps.LatLng(<?php echo  "$station_lat,$station_lon" ?>);
		var marker = new google.maps.Marker({
		  position: myLatlng, 
		  map: bo_map, 
		  title:"<?php echo _BC($station_text) ?>",
		  icon: '<?php echo BO_MAP_STATION_ICON ?>' 
		});
		
<?php
	}
	
	if (($show_station & 2) && $radius)
	{
?>
		var boDistCircle = {
			clickable: false,
			strokeColor: "<?php echo  BO_MAP_CIRCLE_COLOR_LINE ?>",
			strokeOpacity: <?php echo  BO_MAP_CIRCLE_OPAC_LINE ?>,
			strokeWeight: 1,
			fillColor: "<?php echo  BO_MAP_CIRCLE_COLOR_FILL ?>",
			fillOpacity: <?php echo  BO_MAP_CIRCLE_OPAC_FILL ?>,
			map: bo_map,
			center: new google.maps.LatLng(<?php echo  "$station_lat,$station_lon" ?>),
			radius: <?php echo  $radius + 1000 ?>
		};

		new google.maps.Circle(boDistCircle);
<?php
	}
?>

		bo_gmap_init2();
	}

	function bo_setcookie(name, value)
	{
		var now = new Date();
		now = new Date(now.getTime()+ 3600*24*365);
		document.cookie = name+'='+value+'; expires='+now.toGMTString()+';';
	}

	
	function bo_getcookie( check_name ) 
	{
		// first we'll split this cookie up into name/value pairs
		// note: document.cookie only returns name=value, not the other components
		var a_all_cookies = document.cookie.split( ';' );
		var a_temp_cookie = '';
		var cookie_name = '';
		var cookie_value = '';
		var b_cookie_found = false; // set boolean t/f default f

		for ( i = 0; i < a_all_cookies.length; i++ )
		{
			// now we'll split apart each name=value pair
			a_temp_cookie = a_all_cookies[i].split( '=' );

			// and trim left/right whitespace while we're at it
			cookie_name = a_temp_cookie[0].replace(/^\s+|\s+$/g, '');

			// if the extracted name matches passed check_name
			if ( cookie_name == check_name )
			{
				b_cookie_found = true;
				// we need to handle case where cookie has no value but exists (no = sign, that is):
				if ( a_temp_cookie.length > 1 )
				{
					cookie_value = unescape( a_temp_cookie[1].replace(/^\s+|\s+$/g, '') );
				}
				// note that in cases where cookie is initialized but no value, null is returned
				return cookie_value;
				break;
			}
			a_temp_cookie = null;
			cookie_name = '';
		}
		if ( !b_cookie_found )
		{
			return '';
		}
	}


	</script>

        <script type="text/javascript" id="bo_script_google" src="http://maps.googleapis.com/maps/api/js?sensor=false&callback=bo_gmap_init&<?php echo BO_GMAP_API_VERSION ?>">
        </script>



<?php

}



// GoogleMap with Markers
function bo_show_lightning_map($show_gmap=null, $show_static_maps=null)
{
	global $_BO;
	
	$disabled = ((defined('BO_MAP_DISABLE') && BO_MAP_DISABLE && !bo_user_get_level())) || $show_gmap === 0;
	$no_google = isset($_GET['bo_showmap']) || $disabled;
	$static_map_id = intval($show_static_maps) > 0 ? intval($show_static_maps) : intval($_GET['bo_showmap']);
	$show_menu = intval($show_static_maps) == 0;
	$show_static_maps = ($show_static_maps === null) || $show_static_maps > 0;
	$last_update = bo_get_conf('uptime_strikes_modified');
	
	if ($show_static_maps)
	{
		$menu_text = '';

		foreach($_BO['mapimg'] as $id => $d)
		{
			if (!$d['name'] || !$d['menu'])
				continue;
			
			$menu_text .= '<li><a href="'.bo_insert_url(array('bo_showmap', 'bo_*'), "$id").'" class="bo_navi'.($no_google && $static_map_id == $id ? '_active' : '').'">'._BL($d['name']).'</a></li>';
		}

		if ($menu_text && $show_menu)
		{
			echo '<ul id="bo_menu">';
			
			if (!$disabled)
				echo '<li><a href="'.bo_insert_url(array('bo_*')).'" class="bo_navi'.(!$no_google ? '_active' : '').'">'._BL('Dynamic map').'</a></li>';
			
			echo $menu_text;
			echo '</ul>';
		}

		if ($no_google)
		{	
			$cfg = $_BO['mapimg'][$static_map_id];
			$archive_maps_enabled = (defined('BO_ENABLE_ARCHIVE_MAPS') && BO_ENABLE_ARCHIVE_MAPS) || bo_user_get_level();		
			$url = bo_bofile_url().'?map='.$static_map_id.'&bo_lang='._BL();
			$img_dim = bo_archive_get_dim($static_map_id);
			$interval = $cfg['upd_intv'];
			
			
			
			
			
			
			
			$interval = 1;
			
			echo '<fieldset class="bo_map_options_static">';
			echo '<legend>'._BL("map_options_static").'</legend>';
			echo ' <input type="submit" value="'._BL('update map').'" onclick="bo_map_reload_static(); return false;" id="bo_map_reload">';
			echo '<span class="bo_form_checkbox_text">';
			echo '<input type="checkbox" onclick="bo_toggle_autoupdate_static(this.checked);" id="bo_check_autoupdate"> ';
			echo '<label for="bo_check_autoupdate">'._BL('auto update').'</label> ';
			echo '</span>';
			echo '</fieldset>';
			
			echo '<div style="display:inline-block;" id="bo_arch_maplinks_container">';
			
			if ($cfg['header'])
				echo '<div class="bo_map_header">'._BC($cfg['header'], true).'</div>';
			
			if ($archive_maps_enabled && intval(BO_ANIMATIONS_INTERVAL) && $cfg['archive'])
			{
				echo '<div class="bo_arch_map_links">';
				echo '<a href="'.BO_ARCHIVE_URL.'&bo_map='.$static_map_id.'&bo_day_add=1&bo_animation=now" >'._BL('Animation').'</a> ';
				echo '</div>';
			}

			$alt = _BL('Lightning map').' '._BL($_BO['mapimg'][$static_map_id]['name']).' '.date(_BL('_datetime'), $last_update);
			echo '<div style="position:relative;display:inline-block;" id="bo_arch_map_container">';
			echo '<img src="'.$url.'" '.$img_dim.' id="bo_arch_map_img" style="background-image:url(\''.bo_bofile_url().'?image=wait\');" alt="'.htmlspecialchars($alt).'">';

			if ($cfg['footer'])
				echo '<div class="bo_map_footer">'._BC($cfg['footer'], true).'</div>';

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
	{
		bo_map_reload_static();
	}
}

function bo_map_reload_static()
{
	if (bo_autoupdate_running)
	{
		var now = new Date();
		document.getElementById('bo_arch_map_img').src='<?php echo $url ?>&' + Math.floor(now.getTime() / <?php echo $interval; ?>);
		window.setTimeout("bo_map_reload_static();", <?php echo 1000 * 60 * ceil($interval / 2) ?>);
	}
}

</script>

<?php
			
			return;
		}
	}
	
	if ($disabled)
		return;
	
	//Max,min striketime
	$row = bo_db("SELECT MIN(time) mintime, MAX(time) maxtime FROM ".BO_DB_PREF."strikes")->fetch_assoc();
	$start_time = strtotime($row['mintime'].' UTC');
	$end_time = strtotime($row['maxtime'].' UTC');
	
	//Get Stations
	$sid = bo_station_id();
	$js_stations = '';
	$res = bo_db("SELECT id, city, lat, lon, status
					FROM ".BO_DB_PREF."stations a
					WHERE id != '$sid'");
	while($row = $res->fetch_assoc())
	{
		if ($row['status'] == 'A')
		{
			$js_stations .= $js_stations ? ",\n" : '';
			$js_stations .= '{';
			$js_stations .= 'stid:'.$row['id'].', lat:'.round($row['lat'],1).', lon:'.round($row['lon'], 1).', city:"'._BC($row['city']).'"';
			$js_stations .= '}';
		}
		
		$st_cities[$row['id']] = $row['city'].($row['status'] != 'A' ? ' (Offline)' : '');
	}
	
	//Get MyBo Stations
	$mybo_info = unserialize(bo_get_conf('mybo_stations_info'));
	$js_mybo_stations = '';
	if (is_array($mybo_info) && count($mybo_info) > 1)
	{
		$mybo_urls = unserialize(bo_get_conf('mybo_stations'));
		
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

	$lat = (double)$_GET['lat'];
	$lon = (double)$_GET['lon'];
	$zoom = intval($_GET['zoom']);

	if ($lat || $lon || $zoom)
		$cookie_load_defaults = false;
	else
		$cookie_load_defaults = true;
	
	$zoom = $zoom ? $zoom : BO_DEFAULT_ZOOM;
	$lat = $lat ? $lat : BO_LAT;
	$lon = $lon ? $lon : BO_LON;

	
	if ((bo_user_get_level() & BO_PERM_NOLIMIT)) //allow all zoom levels on logged in users with access rights
	{
		$max_zoom = 999;
		$min_zoom = 0;
	}
	else
	{
		$max_zoom = defined('BO_MAX_ZOOM_IN') ? intval(BO_MAX_ZOOM_IN) : 999;
		$min_zoom = defined('BO_MIN_ZOOM_OUT') ? intval(BO_MIN_ZOOM_OUT) : 0;
	}

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
		
		if ($min_upd_interval == null || $min_upd_interval > $cfg['upd_intv'])
			$min_upd_interval = $cfg['upd_intv'];
		
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
	
	echo ' <input type="submit" value="'._BL('more').' &dArr;" onclick="return bo_show_more();" id="bo_map_more">';
	echo ' <input type="submit" value="'._BL('update map').'" onclick="bo_map_reload_overlays(); return false;" id="bo_map_reload">';

	echo '<span class="bo_form_checkbox_text">';
	echo '<input type="checkbox" onclick="bo_toggle_autoupdate(this.checked);" id="bo_check_autoupdate"> ';
	echo '<label for="bo_check_autoupdate">'._BL('auto update').'</label> ';
	echo '</span>';
	
	echo '<div id="bo_map_more_container" style="display: none">';

	
	/*** Manual time range ***/
	
	if (BO_MAP_MANUAL_TIME_ENABLE === true || (bo_user_get_level() & BO_PERM_NOLIMIT))
	{
		$max_range = intval(BO_MAP_MANUAL_TIME_MAX_HOURS);
	
		$yesterday = strtotime('now -1 day');
		$year1 = (int)date('Y', $yesterday);
		$month1 = (int)date('m', $yesterday);
		$day1 = (int)date('d', $yesterday);
		$hour1 = $minute1 = 0;
		
		$today = strtotime("$year1-$month1-$day1 00:00:00");
		if ($max_range < 24)
			$today += $max_range * 3600;
		else
			$today += $max_range * 3600 * 24;
		
		$year2 = (int)date('Y', $today);
		$month2 = (int)date('m', $today);
		$day2 = (int)date('d', $today);
		$hour2 = (int)date('H', $today);
		$minute2 = (int)date('i', $today);
		
		
		echo '<span class="bo_form_descr">'._BL('Time range').':</span> ';
		
		echo '<div class="bo_input_container" id="bo_map_timerange">';

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
	
	echo '<span class="bo_form_descr">'._BL('Advanced').':</span> ';

	echo '<div class="bo_input_container">';
	echo '<span class="bo_form_checkbox_text">';
	echo '<input type="checkbox" onclick="bo_map_toggle_own(this.checked);" id="bo_map_opt_own"> ';
	echo '<label for="bo_map_opt_own">'._BL("only own strikes").'</label> &nbsp; ';
	echo '</span>';

	if ((bo_user_get_level() & BO_PERM_NOLIMIT))
	{
		echo '</div>';
		
		echo '<span class="bo_form_descr">'._BL('Statistics').':</span> ';
		
		echo '<div class="bo_input_container">';

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
	}
	else
	{
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" onclick="bo_map_toggle_count(this.checked);" id="bo_map_opt_count"> ';
		echo '<label for="bo_map_opt_count">'._BL("show strike counter").'</label> &nbsp; ';
		echo '</span>';
	}
	
	if (intval(BO_TRACKS_SCANTIME))
	{
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" onclick="bo_map_toggle_tracks(this.checked);" id="bo_map_opt_tracks"> ';
		echo '<label for="bo_map_opt_tracks">'._BL("show tracks").'</label> &nbsp; ';
		echo '</span>';
	}
	
	echo '</div>';

	
	echo '<span class="bo_form_descr">'._BL('Show Stations').':</span> ';
	echo '<div class="bo_input_container">';
	echo '<span class="bo_form_checkbox_text">';
	echo '<input type="radio" onclick="bo_map_toggle_stations(this.value);" value="1" name="bo_map_station" id="bo_map_station0" checked>';
	echo '<label for="bo_map_station0">'._BL('None').'</label> &nbsp; ';
	echo '</span>';

	echo '<span class="bo_form_checkbox_text">';
	echo '<input type="radio" onclick="bo_map_toggle_stations(this.value);" value="2" name="bo_map_station" id="bo_map_station1">';
	echo '<label for="bo_map_station1">'._BL('Active stations').'</label> &nbsp; ';
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
		echo '<span class="bo_form_descr">'._BL('Extra overlays').':</span> ';
		echo '<div class="bo_input_container">';

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
			echo '<label for="bo_map_overlay'.$id.'">'._BL($cfg['sel_name']).'</label> &nbsp; ';
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
	var bo_show_only_own = 0;
	var bo_show_count = 0;
	var bo_show_tracks = 0;
	var bo_mybo_markers = [];
	var bo_mybo_circles = [];
	var bo_station_markers = [];
	var bo_stations_display = 1;
	var bo_mybo_stations = [ <?php echo $js_mybo_stations ?> ];
	var bo_stations      = [ <?php echo $js_stations ?> ];
	var bo_infowindow;
	var bo_autoupdate = false;
	var bo_autoupdate_running = false;
	var bo_manual_timerange = false;

	//ProjectedOverlay
	//Source: http://www.usnaviguide.com/v3maps/js/ProjectedOverlay.js
	var ProjectedOverlay = function(map, imageUrl, bounds, opts)
	{
	 google.maps.OverlayView.call(this);
	 this.url_ = imageUrl ;
	 this.bounds_ = bounds ;
	 this.addZ_ = opts.addZoom || '' ;				// Add the zoom to the image as a parameter
	 this.id_ = opts.id || this.url_ ;				// Added to allow for multiple images
	 this.percentOpacity_ = opts.opacity || 50 ;
	 this.layer_ = opts.layer || 0;
	 this.map_ = map;
	}

	function bo_gmap_init2()
	{ 

		ProjectedOverlay.prototype = new google.maps.OverlayView();	
		
		// Remove the main DIV from the map pane
		ProjectedOverlay.prototype.remove = function()
		{
			 if (this.div_) 
			 {
			  this.div_.parentNode.removeChild(this.div_);
			  this.div_ = null;
			 }
		}

		ProjectedOverlay.prototype.onAdd = function() 
		{
			  // Note: an overlay's receipt of onAdd() indicates that
			  // the map's panes are now available for attaching
			  // the overlay to the map via the DOM.

			  // Create the DIV and set some basic attributes.
			  var div = document.createElement('DIV');
			  div.style.border = "none";
			  div.style.borderWidth = "0px";
			  div.style.position = "absolute";

			  // Create an IMG element and attach it to the DIV.
			  var img = document.createElement("img");
			  img.src = this.url_;
			  img.style.width = "100%";
			  img.style.height = "100%";
			  div.appendChild(img);

			  // Set the overlay's div_ property to this DIV
			  this.div_ = div;
				  
			  if( this.percentOpacity_ )
			  {
			   this.setOpacity(this.percentOpacity_) ;
			  }
			  
			  // We add an overlay to a map via one of the map's panes.
			  // We'll add this overlay to the overlayImage pane.
			  var panes = this.getPanes();
			  
			  if (this.layer_ == 1)
				panes.mapPane.appendChild(div); //map pane = same as strikes
			  else
			    panes.overlayLayer.appendChild(div);
				
		}
		
		// Redraw based on the current projection and zoom level...
		ProjectedOverlay.prototype.draw = function(firstTime)
		{
			 if (!this.div_)
			 {
			  return ;
			 }

			 var c1 = this.get('projection').fromLatLngToDivPixel(this.bounds_.getSouthWest());
			 var c2 = this.get('projection').fromLatLngToDivPixel(this.bounds_.getNorthEast());

			 if (!c1 || !c2) return;

			 // Now position our DIV based on the DIV coordinates of our bounds
			 this.div_.style.width = Math.abs(c2.x - c1.x) + "px";
			 this.div_.style.height = Math.abs(c2.y - c1.y) + "px";
			 this.div_.style.left = Math.min(c2.x, c1.x) + "px";
			 this.div_.style.top = Math.min(c2.y, c1.y) + "px";

			 // Do the rest only if the zoom has changed...
			 if ( this.lastZoom_ == this.map_.getZoom() )
			 {
			  return ;
			 }

			 this.lastZoom_ = this.map_.getZoom() ;

			 var url = this.url_ ;

			 if ( this.addZ_ )
			 {
			  url += this.addZ_ + this.map_.getZoom() ;
			 }

			 this.div_.innerHTML = '<img src="' + url + '"  width=' + this.div_.style.width + ' height=' + this.div_.style.height + ' >' ;
		}

		ProjectedOverlay.prototype.setOpacity=function(opacity)
		{
			 if (opacity < 0)
			 {
			  opacity = 0 ;
			 }
			 if(opacity > 100)
			 {
			  opacity = 100 ;
			 }
			 var c = opacity/100 ;

			 if (typeof(this.div_.style.filter) =='string')
			 {
			  this.div_.style.filter = 'alpha(opacity:' + opacity + ')' ;
			 }
			 if (typeof(this.div_.style.KHTMLOpacity) == 'string' )
			 {
			  this.div_.style.KHTMLOpacity = c ;
			 }
			 if (typeof(this.div_.style.MozOpacity) == 'string')
			 {
			  this.div_.style.MozOpacity = c ;
			 }
			 if (typeof(this.div_.style.opacity) == 'string')
			 {
			  this.div_.style.opacity = c ;
			 }
		}


		var i;
	
		bo_infowindow = new google.maps.InfoWindow({content: ''});
<?php
		
		foreach($mapcfg as $mapid => $cfg)
		{
			echo '
			bo_OverlayMaps['.$mapid.'] = {
				getTileUrl: function (coord, zoom) { return bo_get_tile(zoom, coord, '.$cfg['id'].', '.intval($cfg['upd_intv']).'); },
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
			getTileUrl: function (coord, zoom) { return bo_get_tile_counts(zoom, coord); },
			tileSize: new google.maps.Size(<?php echo BO_TILE_SIZE.','.BO_TILE_SIZE ?>), 
			isPng:true, 
			bo_show:false
		};

		bo_OverlayTracks = {
			getTileUrl: function (coord, zoom) { return bo_get_tile_tracks(zoom, coord); },
			tileSize: new google.maps.Size(<?php echo BO_TILE_SIZE.','.BO_TILE_SIZE ?>), 
			isPng:true, 
			opacity:<?php echo (double)BO_TRACKS_MAP_OPACITY; ?>,
			minZoom:<?php echo (int)BO_TRACKS_MAP_ZOOM_MIN; ?>,
			maxZoom:<?php echo (int)BO_TRACKS_MAP_ZOOM_MAX; ?>,
			bo_show:false
		};
		
		var c = bo_getcookie('bo_show_only_own');
		if (c)
		{
			bo_show_only_own = c == -1 ? 0 : 1;
			document.getElementById('bo_map_opt_own').checked = c == -1 ? false : true;
			if (c != -1) bo_show_more();
		}

		var c = bo_getcookie('bo_show_count');
		if (c)
		{
			bo_show_count = c == -1 ? 0 : 1;
			document.getElementById('bo_map_opt_count').checked = c == -1 ? false : true;
			if (c != -1) bo_show_more();
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
			if (c != -1) bo_show_more();
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
				if (c != -1) bo_show_more();
			}
		}
		
		bo_infobox = document.createElement('DIV');
		bo_infobox.index = 1;
		bo_map.controls[google.maps.ControlPosition.RIGHT_TOP].push(bo_infobox);
		
		bo_map_reload_overlays();  

		var map_lat = bo_getcookie('bo_map_lat');
		var map_lon = bo_getcookie('bo_map_lon');
		var map_zoom = bo_getcookie('bo_map_zoom');
		var map_type = bo_getcookie('bo_map_type');
		
		if (map_lat > 0 && map_lon > 0 && <?php echo $cookie_load_defaults ? 'true' : 'false' ?>)
			bo_map.setOptions({ center: new google.maps.LatLng(map_lat,map_lon) });
		
		if (map_zoom > 0 && <?php echo $cookie_load_defaults ? 'true' : 'false' ?>)
			bo_map.setOptions({ zoom: parseInt(map_zoom) });

		if (map_type.match(/[a-z]+/i))
			bo_map.setOptions({ mapTypeId: map_type });
		
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
		});

		google.maps.event.addListener(bo_map, 'zoom_changed', function() {
			if (this.getZoom() < <?php echo $min_zoom ?>)
				 this.setZoom(<?php echo $min_zoom ?>);
			if (this.getZoom() > <?php echo $max_zoom ?>)
				 this.setZoom(<?php echo $max_zoom ?>);
			else
				bo_setcookie('bo_map_zoom', bo_map.getZoom());
			
			bo_map_toggle_stations(0);
		}); 

		google.maps.event.addListener(bo_map, 'maptypeid_changed', function() {
			bo_setcookie('bo_map_type', bo_map.getMapTypeId());
		});
		
	}	
	
	function bo_show_more()
	{
		document.getElementById('bo_map_more_container').style.display='block';
		document.getElementById('bo_map_more').style.display='none';
		return false;
	}
	
	function bo_toggle_autoupdate(auto)
	{
		bo_autoupdate = auto;
		bo_start_autoupdate();
	}

	function bo_start_autoupdate()
	{
		if (bo_autoupdate_running)
			return;
		
		if (bo_autoupdate)
		{
			bo_autoupdate_running = true;
			window.setTimeout("bo_do_autoupdate();", <?php echo 1000 * 60 * intval($min_upd_interval) ?>);
		}
	}

	function bo_do_autoupdate()
	{
		bo_map_reload_overlays();
		bo_autoupdate_running = false;
		bo_start_autoupdate();
	}

	
	function bo_map_toggle_stations(display)
	{
		if (display)
			bo_stations_display = display;
		else if (bo_stations_display == 3)
			display = 3;
			
		for (i in bo_mybo_markers)
			bo_mybo_markers[i].setMap(null);

		for (i in bo_mybo_circles)
			bo_mybo_circles[i].setMap(null);

		for (i in bo_station_markers)
			bo_station_markers[i].setMap(null);

		if (display == 0 && bo_map.getZoom() > 10)
		{
			document.getElementById('bo_map_station1').disabled = true;
			
			if (bo_stations_display == 2)
				return;
		}
		else if (display == 0 && bo_map.getZoom() <= 10)
		{
			document.getElementById('bo_map_station1').disabled = false;
			
			if (bo_stations_display == 2)
				display = 2;
		}
		
		if (display == 2)
		{
			if (bo_station_markers.length == 0)
			{
				for (i in bo_stations)
				{
					bo_station_markers[i] = new google.maps.Marker({
					  position: new google.maps.LatLng(bo_stations[i].lat,bo_stations[i].lon), 
					  map: bo_map, 
					  title:bo_stations[i].city,
					  icon: '<?php echo  BO_MAP_STATIONS_ICON ?>',
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
		
	}
	
	function bo_map_toggle_overlay(checked, type)
	{
		bo_setcookie('bo_show_ovl'+type, checked ? 1 : -1);
		bo_OverlayMaps[type].bo_show = checked;
		bo_map_reload_overlays();
	}
	
	function bo_map_toggle_own(show_only_own)
	{
		bo_setcookie('bo_show_only_own', show_only_own ? 1 : -1);
		bo_show_only_own = show_only_own ? 1 : 0;
		bo_map_reload_overlays();
	}

	function bo_map_toggle_extraoverlay(checked, type)
	{
		bo_setcookie('bo_show_extraovl'+type, checked ? 1 : -1);
		bo_ExtraOverlay[type].bo_show = checked;
		bo_map_reload_overlays();
	}
	
	function bo_map_toggle_count(value, type)
	{ 
		value = value == '0' ? false : value;
		bo_setcookie('bo_show_count', value ? 1 : -1);
		bo_show_count = value;
		bo_map_reload_overlays();
	}

	function bo_map_toggle_tracks(checked)
	{
		bo_setcookie('bo_show_tracks', checked ? 1 : -1);
		bo_show_tracks = checked ? 1 : 0;
		bo_map_reload_overlays();
	}
	
	function bo_map_reload_overlays()
	{
		var i;
		var bo_add_transparent_layer = false;
		
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
					bo_ExtraOverlayMaps[i] = new google.maps.GroundOverlay(bo_ExtraOverlay[i].bo_image, bo_ExtraOverlay[i].bo_bounds);
					bo_ExtraOverlayMaps[i].clickable = false;
				}
				else
				{
					bo_ExtraOverlayMaps[i] = new ProjectedOverlay(bo_map, bo_ExtraOverlay[i].bo_image, bo_ExtraOverlay[i].bo_bounds, {opacity: bo_ExtraOverlay[i].bo_opacity, layer: bo_ExtraOverlay[i].bo_layer}) ;
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
	
	
	function bo_get_tile(zoom, coord, type, interval)
	{
		var url = "<?php echo bo_bofile_url() ?>?tile&own="+bo_show_only_own+"&zoom="+zoom+"&x="+coord.x+"&y="+coord.y;
		var now = new Date();
		var add = "";
		
		//manual time range
		if (type == -1)
		{
			return url+"&type="+type+"&from="+bo_get_time_man(1)+"&to="+bo_get_time_man(2)+"&"+add;
		}
		else
		{
			//defined time range
			add = now.getDate() + '_' + now.getHours() + '_' + Math.floor(now.getMinutes() / interval) + (bo_loggedin ? '_1' : '');
			return url+"&type="+type+"&"+add;
		}
		
	}

	function bo_get_tile_counts(zoom, coord)
	{
		var types='';
		var interval=0;
		var now = new Date();
		var add = "";
		
		if (bo_manual_timerange)
		{
			types = '-1';
			add = "&from="+bo_get_time_man(1)+"&to="+bo_get_time_man(2)+"&";
		}
		else
		{
			add = now.getDate() + '_' + now.getHours() + '_' + Math.floor(now.getMinutes() / interval) + (bo_loggedin ? '_1' : '');
			for (i in bo_OverlayMaps)
			{
				if (bo_OverlayMaps[i].bo_show)
				{
					types = types + (types ? ',' : '') + i;
					
					if (!interval || interval > bo_OverlayMaps[i].bo_interval)
						interval = bo_OverlayMaps[i].bo_interval;
				}
			}
		}
		
		
		return "<?php echo bo_bofile_url() ?>?tile&count="+types+"&stat="+bo_show_count+"&own="+bo_show_only_own+"&zoom="+zoom+"&x="+coord.x+"&y="+coord.y+"&"+add;
	}
	
	function bo_get_tile_tracks(zoom, coord)
	{
		if (zoom < <?php echo (int)BO_TRACKS_MAP_ZOOM_MIN; ?> || zoom > <?php echo (int)BO_TRACKS_MAP_ZOOM_MAX; ?>)
			return "<?php echo bo_bofile_url() ?>?image=bt";
		
		var interval=<?php echo intval(BO_UP_INTVL_TRACKS) ?>;

		var now = new Date();
		var add = now.getDate() + '_' + now.getHours() + '_' + Math.floor(now.getMinutes() / interval) + (bo_loggedin ? '_1' : '');
		
		return "<?php echo bo_bofile_url() ?>?tile&tracks&zoom="+zoom+"&x="+coord.x+"&y="+coord.y+"&"+add;
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
				var add = now.getDate() + '_' + now.getHours() + '_' + Math.floor(now.getMinutes() / bo_OverlayMaps[i].bo_interval);
				var infoImg = document.createElement('IMG');
				
				if (bo_manual_timerange)
					add = "from="+bo_get_time_man(1)+"&to="+bo_get_time_man(2)+"&" + add;
				
				infoImg.src = "<?php echo bo_bofile_url() ?>?tile&info&type="+bo_OverlayMaps[i].bo_mapid+"&"+add+now.getTime();
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