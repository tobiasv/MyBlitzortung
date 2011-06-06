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

function bo_show_archive()
{
	$maps_enabled = (defined('BO_ENABLE_ARCHIVE_MAPS') && BO_ENABLE_ARCHIVE_MAPS) || (bo_user_get_level() & BO_PERM_ARCHIVE);
	
	$densities_enabled = defined('BO_CALC_DENSITIES') && BO_CALC_DENSITIES
							&& ((defined('BO_ENABLE_DENSITIES') && BO_ENABLE_DENSITIES) || (bo_user_get_level() & BO_PERM_ARCHIVE));

	
	if ($_GET['bo_show'])
		$show = $_GET['bo_show'];
	else if ($maps_enabled)
		$show = 'maps';
	else if ($densities_enabled)
		$show = 'density';
	else
		$show = 'search';

	if (!$maps_enabled && $show == 'maps')
		$show = 'search';
		
	echo '<ul id="bo_menu">';

	if ($maps_enabled)
		echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'maps').'" class="bo_navi'.($show == 'maps' ? '_active' : '').'">'._BL('arch_navi_maps').'</a></li>';

	if ($densities_enabled)
		echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'density').'" class="bo_navi'.($show == 'density' ? '_active' : '').'">'._BL('arch_navi_density').'</a></li>';
		
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'search').'" class="bo_navi'.($show == 'search' ? '_active' : '').'">'._BL('arch_navi_search').'</a></li>';
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
	}



	bo_copyright_footer();

}

function bo_show_archive_map()
{
	global $_BO;

	$ani_div = 15;
	$ani_pic_interval = 120;
	
	$map = isset($_GET['bo_map']) ? intval($_GET['bo_map']) : -1;
	$year = intval($_GET['bo_year']);
	$month = intval($_GET['bo_month']);
	$day = intval($_GET['bo_day']);
	$day_add = intval($_GET['bo_day_add']);
	$ani = isset($_GET['bo_animation']);
	
	$time = mktime(0,0,0,date('m'), date('d')-1+$day_add, date('Y'));
	
	if (!$year || $year < 0 || $day_add)
		$year = date('Y', $time);

	if (!$month || $month < 0 || $day_add)
		$month = date('m', $time);

	if (!$day || $day_add)
		$day = date('d', $time);
	
	$time = strtotime("$year-$month-$day 00:00:00 UTC");
	
	$row = bo_db("SELECT MIN(time) mintime, MAX(time) maxtime FROM ".BO_DB_PREF."strikes")->fetch_assoc();
	$start_time = strtotime($row['mintime'].' UTC');
	$end_time = strtotime($row['maxtime'].' UTC');
	
	echo '<div id="bo_arch_maps">';

	echo '<form action="?" method="GET" class="bo_arch_strikes_form">';
	echo bo_insert_html_hidden(array('bo_map', 'bo_year', 'bo_month', 'bo_day', 'bo_animation', 'bo_day_add'));

	echo '<fieldset>';
	echo '<legend>'._BL('legend_arch_strikes').'</legend>';

	
	echo '<span class="bo_form_descr">'._BL('Map').':</span> ';
	
	ksort($_BO['mapimg']);
	echo '<select name="bo_map" id="bo_arch_strikes_select_map" onchange="submit();">';
	foreach($_BO['mapimg'] as $id => $d)
	{
		if (!$d['name'] || !$d['archive'])
			continue;
			
		echo '<option value="'.$id.'" '.($id == $map ? 'selected' : '').'>'._BL($d['name']).'</option>';
		
		if ($map < 0)
			$map = $id;
	}
	echo '</select>';
	
	echo '<span class="bo_form_descr">'._BL('Date').':</span> ';
	echo '<select name="bo_year" id="bo_arch_strikes_select_year">';
	for($i=date('Y', $start_time); $i<=date('Y');$i++)
		echo '<option value="'.$i.'" '.($i == $year ? 'selected' : '').'>'.$i.'</option>';
	echo '</select>';

	echo '<select name="bo_month" id="bo_arch_strikes_select_month">';
	for($i=1;$i<=12;$i++)
		echo '<option value="'.$i.'" '.($i == $month ? 'selected' : '').'>'._BL(date('M', strtotime("2000-$i-01"))).'</option>';
	echo '</select>';

	echo '<select name="bo_day" id="bo_arch_strikes_select_day">';
	for($i=1;$i<=31;$i++)
		echo '<option value="'.$i.'" '.($i == $day ? 'selected' : '').'>'.$i.'</option>';
	echo '</select>';

	echo '<input type="submit" name="bo_ok" value="'._BL('Picture').'" id="bo_archive_maps_submit" class="bo_form_submit">';
	echo '<input type="submit" name="bo_animation" value="'._BL('Animation').'" id="bo_archive_maps_animation" class="bo_form_submit">';
	
	echo '</fieldset>';
	echo '</form>';
	
	if ($_BO['mapimg'][$map]['archive'])
	{
		echo '<div style="display:inline-block;" id="bo_arch_maplinks_container">';

		echo '<div class="bo_arch_map_links">';
		echo _BL('Yesterday').': &nbsp; ';
		echo ' &nbsp; <a href="'.bo_insert_url(array('bo_year', 'bo_month', 'bo_day', 'bo_animation', 'bo_day_add')).'&bo_day_add=0" >'._BL('Picture').'</a> ';
		echo ' &nbsp; <a href="'.bo_insert_url(array('bo_year', 'bo_month', 'bo_day', 'bo_animation', 'bo_day_add')).'&bo_day_add=0&bo_animation" >'._BL('Animation').'</a> ';
		echo '  &nbsp;  &nbsp; &nbsp; ';
		echo _BL('Today').': &nbsp; ';
		echo ' &nbsp; <a href="'.bo_insert_url(array('bo_year', 'bo_month', 'bo_day', 'bo_animation', 'bo_day_add')).'&bo_day_add=1" >'._BL('Picture').'</a> ';
		echo ' &nbsp; <a href="'.bo_insert_url(array('bo_year', 'bo_month', 'bo_day', 'bo_animation', 'bo_day_add')).'&bo_day_add=1&bo_animation" >'._BL('Animation').'</a> ';
		echo '</div>';

		echo '<div style="position:relative;display:inline-block; min-width: 300px; " id="bo_arch_map_container">';
		
		if ($time < $start_time || $time > $end_time)
		{
			$text = _BL('arch_select_dates_beween');
			echo '<p>';
			echo strtr($text, array('{START}' => date(_BL('_date'), $start_time), '{END}' => date(_BL('_date'), $end_time) ));
			echo '</p>';
		}
		else if ($ani)
		{
			$img_file = BO_FILE.'?map='.$map.'&blank&bo_lang='._BL().'';
			$bo_file_url = BO_FILE.'?map='.$map.'&transparent&date=';
			
			
			$images = '';
			
			for ($i=0; $i<= 24 * 60; $i+= $ani_div)
			{
				$time = strtotime("$year-$month-$day 00:00:00 +$i minutes");
				
				if ($time + 60 * $ani_pic_interval > $end_time)
					break;
				
				$images .= ($images ? ',' : '').'"'.date('YmdHi', $time).'-'.$ani_pic_interval.'"';
			}
			
			echo '<img style="position:relative;" id="bo_arch_map_img" src="'.$img_file.'">';
			echo '<img style="position:absolute;top:0;left:0;" id="bo_arch_map_img_ani" src="'.BO_FILE.'?map='.$map.'&transparent&date='.sprintf('%04d%02d%02d0000-%d', $year, $month, $day, $ani_pic_interval).'&bo_lang='._BL().'">';
		}
		else
		{
			$img_file = BO_FILE.'?map='.$map.'&date='.sprintf('%04d%02d%02d', $year, $month, $day).'&bo_lang='._BL();
			echo '<img style="position:relative;" id="bo_arch_map_img" src="'.$img_file.'">';
		}
		
		$footer = $_BO['mapimg'][$map]['footer'];
		echo '<div class="bo_map_footer">'._BC($footer, true).'</div>';
		
		echo '</div>';
		echo '</div>';
		
	}
	
?>
	
<script type="text/javascript">

var bo_maps_pics   = new Array(<?php echo $images ?>);
var bo_maps_img    = new Array();
var bo_maps_loaded = 0;

function bo_maps_animation(nr)
{
	var timeout = 100;
	document.getElementById('bo_arch_map_img_ani').src=bo_maps_img[nr].src;
	if (nr >= bo_maps_pics.length-1) { nr=-1; timeout += 2000; }
	window.setTimeout("bo_maps_animation("+(nr+1)+");",timeout);
	
}

function bo_maps_load()
{
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
	bo_maps_loaded++;
	
	if (bo_maps_loaded+1 >= bo_maps_pics.length && bo_maps_loaded > 0)
	{
		bo_maps_animation(0);
		bo_maps_loaded = -1;
	}
	else
	{
		document.getElementById('bo_arch_map_img_ani').src = bo_maps_img[bo_maps_loaded-1].src;
	}
}
window.setTimeout("bo_maps_load();", 500);
</script>

<?php
	
	
	echo '</div>';
	
}

function bo_show_archive_density()
{
	global $_BO;

	$map = isset($_GET['bo_map']) ? intval($_GET['bo_map']) : 0;
	$year = intval($_GET['bo_year']) ? intval($_GET['bo_year']) : date('Y');
	$month = intval($_GET['bo_month']);
	$station_id = intval($_GET['bo_station']);
	$ratio = isset($_GET['bo_ratio']);

	// Map infos
	$cfg = $_BO['mapimg'][$map];
	$latN = $cfg['coord'][0];
	$lonE = $cfg['coord'][1];
	$latS = $cfg['coord'][2];
	$lonW = $cfg['coord'][3];

	
	$sql = "SELECT MIN(date_start) mindate, MAX(date_start) maxdate, MAX(date_end) maxdate_end FROM ".BO_DB_PREF."densities ";
	$res = bo_db($sql);
	$row = $res->fetch_assoc();
	$start_time = strtotime($row['mindate']);
	$end_time = strtotime($row['maxdate_end']);
	
	$station_infos = bo_stations();
	$station_infos[0]['city'] = _BL('All', false);

	$stations = array();
	$stations[0] = 0;
	$stations[bo_station_id()] = bo_station_id();
	
	if (defined('BO_DENSITY_STATIONS') && BO_DENSITY_STATIONS)
	{
		$tmp = explode(',', BO_DENSITY_STATIONS);
		foreach($tmp as $id)
			$stations[$id] = $id;
	}
	
	echo '<div id="bo_dens_maps">';

	echo '<form action="?" method="GET" class="bo_arch_strikes_form">';
	echo bo_insert_html_hidden(array('bo_year', 'bo_map', 'bo_station', 'bo_ratio'));

	echo '<fieldset>';
	echo '<legend>'._BL('legend_arch_densities').'</legend>';

	
	echo '<span class="bo_form_descr">'._BL('Map').':</span> ';
	
	ksort($_BO['mapimg']);
	echo '<select name="bo_map" id="bo_arch_dens_select_map" onchange="submit();">';
	foreach($_BO['mapimg'] as $id => $d)
	{
		if (!$d['name'] || !$d['density'])
			continue;
			
		echo '<option value="'.$id.'" '.($id == $map ? 'selected' : '').'>'._BL($d['name']).'</option>';
		
		if ($map < 0)
			$map = $id;
	}
	echo '</select>';
	
	echo '<span class="bo_form_descr">'._BL('Year').':</span> ';
	echo '<select name="bo_year" id="bo_arch_dens_select_year" onchange="submit();">';
	for($i=date('Y', $start_time); $i<=date('Y');$i++)
		echo '<option value="'.$i.'" '.($i == $year ? 'selected' : '').'>'.$i.'</option>';
	echo '</select>';

	echo '<span class="bo_form_descr">'._BL('Station').':</span> ';
	echo '<select name="bo_station" id="bo_arch_dens_select_station" onchange="submit();">';
	foreach ($stations as $id )
		echo '<option value="'.$id.'" '.($id == $station_id ? 'selected' : '').'>'._BC($station_infos[$id]['city']).'</option>';
	echo '</select>';
	
	echo '<input type="checkbox" name="bo_ratio" value="1" '.($ratio && $station_id ? 'checked="checked"' : '').' '.($station_id ? '' : 'disabled').' onchange="submit();" onclick="submit();" id="bo_arch_dens_ratio">';
	echo '<label for="bo_arch_dens_ratio"> '._BL('Strike ratio').'</label> &nbsp; ';
	
	echo '<input type="submit" name="bo_ok" value="'._BL('Ok').'" id="bo_archive_density_submit" class="bo_form_submit">';
	
	echo '<div id="bo_archive_density_yearmonth_container">';
	echo ' <a href="'.bo_insert_url(array('bo_year', 'bo_month'), $year).'" class="bo_archive_density_yearurl';
	echo !$month ? ' bo_archive_density_active' : '';
	echo '">';
	echo date('Y', strtotime($year."-01-01"));
	echo '</a> &nbsp; ';

	for($i=1;$i<=12;$i++)
	{
		if ($year == date('Y', $end_time) && $i > date('m', $end_time))
		{
			echo '<span class="bo_archive_density_monthurl">';
			echo _BL(date('M', strtotime("2000-$i-01")));
			echo '</span>';
		}
		else
		{
			echo ' <a href="'.bo_insert_url('bo_month', $i).'" class="bo_archive_density_monthurl';
			echo $month == $i ? ' bo_archive_density_active' : '';
			echo '">';
			echo _BL(date('M', strtotime("2000-$i-01")));
			echo '</a> ';
		}
	}

	echo '</div>';
	
	echo '</fieldset>';

	echo '</form>';

	
	echo '<div style="position:relative;display:inline-block; min-width: 300px; " id="bo_arch_map_container">';
	
	$img_file = BO_FILE.'?density&map='.$map.'&bo_year='.$year.'&bo_month='.$month.'&id='.$station_id.($ratio ? '&ratio' : '').'&bo_lang='._BL();
	echo '<img style="position:relative;" id="bo_arch_map_img" src="'.$img_file.'">';

	$footer = $_BO['mapimg'][$map]['footer'];
	echo '<div class="bo_map_footer">'._BC($footer, true).'</div>';

	echo '</div>';
}

function bo_show_archive_search()
{
	global $_BO;
	$radius = $_BO['radius'] * 1000;

	if ($_GET['bo_lat'])
	{
		$lat = (double)$_GET['bo_lat'];
		$lon = (double)$_GET['bo_lon'];
		$map_lat = (double)$_GET['bo_map_lat'];
		$map_lon = (double)$_GET['bo_map_lon'];
		$zoom = (int)$_GET['bo_map_zoom'];
		$delta_dist = (int)$_GET['bo_dist'];
	}
	else
	{
		$map_lat = $lat = BO_LAT;
		$map_lon = $lon = BO_LON;
		$zoom = BO_DEFAULT_ZOOM;
		$delta_dist = 10000;
	}

	$getit = isset($_GET['ok']);

	if ($radius && $delta_dist > $radius)
		$delta_dist = $radius;

	echo '<div id="bo_archive">';

	echo '<h4>'._BL('Map').'</h4>';

	echo '<div id="bo_gmap" class="bo_map_archive"></div>';


	/*** Get data from Database ***/

	if ($getit)
	{

		//distance for faster search (uses database index)
		$dist = bo_latlon2dist($lat, $lon);

		//max and min latitude for strikes
		list($str_lat_min, $str_lon_min) = bo_distbearing2latlong($delta_dist * sqrt(2), 225, $lat, $lon);
		list($str_lat_max, $str_lon_max) = bo_distbearing2latlong($delta_dist * sqrt(2), 45, $lat, $lon);

		$time_min = 0;
		$time_max = 0;
		$count = 0;
		$text = '';
		$more_found = false;
		$sql = "SELECT  s.id id, s.distance distance, s.lat lat, s.lon lon, s.time time, s.time_ns time_ns, s.users users,
						s.current current, s.deviation deviation, s.current current, s.polarity polarity, s.part part, s.raw_id raw_id
				FROM ".BO_DB_PREF."strikes s
				WHERE 1
					AND distance BETWEEN '".($dist - $delta_dist)."' AND '".($dist + $delta_dist)."'
					".($radius ? "AND distance < $radius" : "")."
					AND NOT (lat < $str_lat_min OR lat > $str_lat_max OR lon < $str_lon_min OR lon > $str_lon_max)
				ORDER BY s.time DESC
				LIMIT 100";

		$res = bo_db($sql);
		while($row = $res->fetch_assoc())
		{
			//ToDo: We search by lat/lon (square) but we need circle
			if (bo_latlon2dist($lat, $lon, $row['lat'], $row['lon']) > $delta_dist)
				continue;

			if ($count >= 10)
			{
				$more_found = true;
				break;
			}

			$time = strtotime($row['time'].' UTC');

			$description  = '<div class=\'bo_archiv_map_infowindow\'>';
			$description .= '<ul class=\'bo_archiv_map_infowindow_list\'>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Time').':</span><span class=\'bo_value\'> '.date(_BL('_datetime'), $time).'</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Deviation').':</span><span class=\'bo_value\'> '.number_format($row['deviation'] / 1000, 1, _BL('.'), _BL(',')).'km</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Current').':</span><span class=\'bo_value\'> '.number_format($row['current'], 1, _BL('.'), _BL(',')).'kA ('._BL('experimental').')</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Polarity').':</span><span class=\'bo_value\'> '.($row['polarity'] === null ? '?' : ($row['polarity'] < 0 ? _BL('negative') : _BL('positive'))).' ('._BL('experimental').')</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Participated').':</span><span class=\'bo_value\'> '.($row['part'] ? _BL('yes') : _BL('no')).'</span></li>';
			$description .= '<li><span class=\'bo_descr\'>'._BL('Participants').':</span><span class=\'bo_value\'> '.intval($row['users']).'</span></li>';
			$description .= '</ul>';

			if ($row['raw_id'])
				$description .= '<img src=\''.BO_FILE.'?graph='.$row['raw_id'].'&bo_lang='._BL().'\' style=\'width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px\' class=\'bo_archiv_map_signal\'>';

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


	echo '<input type="hidden" name="bo_map_zoom" id="bo_map_zoom">';
	echo '<input type="hidden" name="bo_map_lat" id="bo_map_lat">';
	echo '<input type="hidden" name="bo_map_lon" id="bo_map_lon">';

	echo '<input type="submit" name="ok" value="'._BL('button_search').'" id="bo_archive_submit" class="bo_form_submit">';

	echo '</fieldset>';

	echo '</form>';

	echo '</div>';

?>
	<script type="text/javascript">

		var centerMarker;
		function bo_gmap_init2()
		{
			var markerOptions;
			var infowindow;

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

			google.maps.event.addListener(bo_map, 'click', function(event) {
				centerMarker.setPosition(event.latLng);
				document.getElementById('bo_archive_lat').value=event.latLng.lat();
				document.getElementById('bo_archive_lon').value=event.latLng.lng();
			});

			google.maps.event.addListener(bo_map, 'dragend', function() {bo_gmap_map2form();});
			google.maps.event.addListener(bo_map, 'zoom_changed', function() {bo_gmap_map2form();});

			<?php if ($getit) { ?>

			var boDistCircle = {
				clickable: false,
				strokeColor: "#5555ff",
				strokeOpacity: 0.5,
				strokeWeight: 1,
				fillColor: "#5555ff",
				fillOpacity: 0.1,
				map: bo_map,
				center: new google.maps.LatLng(<?php echo  "$lat,$lon" ?>),
				radius: <?php echo $delta_dist + 1000 ?>
			};

			new google.maps.Circle(boDistCircle);

			<?php } ?>

			var lightnings = {};
			<?php echo  $text ?>

			var images = new Array();
			var d;
			var time_min=<?php echo intval($time_min) ?>;
			var time_int=<?php echo intval($time_int) ?>;
			for (var i=0;i<10;i++)
			{
				d = i * 25;
				d = d.toString(16);
				d = d.length == 1 ? '0'+d : d;

				images[i] = new google.maps.MarkerImage("<?php echo  BO_FILE ?>?icon=ff"+d+"00",
								new google.maps.Size(11,11),
								new google.maps.Point(0,0),
								new google.maps.Point(5, 5));
			}

			var color;
			var lmarker = Array();
			var infowindow = new google.maps.InfoWindow({
				content: '...'
			});

			for (var lightning in lightnings)
			{
				if (time_int)
					color = Math.floor((lightnings[lightning].utime-time_min)/time_int);
				else
					color = 0;

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

			}

			bo_gmap_map2form();
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
function bo_show_archive_table($lat = null, $lon = null, $fuzzy = null)
{
	$per_page = 10;

	$only_strikes = isset($_GET['bo_only_strikes']);
	$page = intval($_GET['bo_action']);
	$page = $page < 0 ? 0 : $page;

	$show_empty_sig = (bo_user_get_level() & BO_PERM_ARCHIVE) && isset($_GET['bo_all_strikes']);
	
	
	if ($lat !== null && $lon !== null)
	{
		if (!$fuzzy)
			$fuzzy = 0.005;

		$latS = $lat - $fuzzy;
		$latN = $lat + $fuzzy;
		$lonW = $lon - $fuzzy;
		$lonE = $lon + $fuzzy;

		$latlon_sql = " AND NOT (s.lat < '$latS' OR s.lat > '$latN' OR s.lon < '$lonW' OR s.lon > '$lonE') ";
		$only_strikes = true;
		$show_empty_sig = true;

		$hours_back = 24 * 50;
	}
	else
	{

		echo '<form action="" method="GET">';

		echo bo_insert_html_hidden(array('bo_only_strikes', 'bo_action', 'bo_all_strikes'));

		echo '<fieldset>';
		echo '<legend>'._BL('settings').'</legend>';

		if (!$show_empty_sig)
		{
			echo '<input type="checkbox" name="bo_only_strikes" value="1" '.($only_strikes ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="check_only_strikes">';
			echo '<label for="check_only_strikes"> '._BL('check_only_strikes').'</label> &nbsp; ';
		}
		
		if (bo_user_get_level() & BO_PERM_ARCHIVE)
		{
			echo '<input type="checkbox" name="bo_all_strikes" value="1" '.($show_empty_sig ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="all_strikes">';
			echo '<label for="all_strikes"> '._BL('check_all_strikes_network').'</label> &nbsp; ';
		}
		
		echo '</fieldset>';

		$hours_back = 24;
	}

	$row = bo_db("SELECT MAX(time) time FROM ".BO_DB_PREF."raw")->fetch_assoc();

	$time_end  = strtotime($row['time'].' UTC');
	$date_end  = gmdate('Y-m-d H:i:s', $time_end);
	$date_start    = gmdate('Y-m-d H:i:s', time() - 3600 * $hours_back);
	$c = 299792458;

	if ($show_empty_sig)
	{
		$sql_join = BO_DB_PREF."strikes s LEFT OUTER JOIN ".BO_DB_PREF."raw r ON s.raw_id=r.id ";
		$table = 's';
	}
	elseif ($only_strikes)
	{
		$sql_join = BO_DB_PREF."raw r JOIN ".BO_DB_PREF."strikes s ON s.raw_id=r.id ";
		$table = 'r';
	}
	else
	{
		$date_end   = gmdate('Y-m-d H:i:s', $time_end - 120); // strike data my be older
		
		$sql_join = BO_DB_PREF."raw r LEFT OUTER JOIN ".BO_DB_PREF."strikes s ON s.raw_id=r.id ";
		$table = 'r';
	}

	$count = 0;
	$sql = "SELECT  s.id strike_id, s.distance distance, s.lat lat, s.lon lon,
					s.deviation deviation, s.current current,
					s.time stime, s.time_ns stimens, s.users users, 
					r.id raw_id, r.time rtime, r.time_ns rtimens, r.data data
			FROM $sql_join
			WHERE 1
					AND $table.time BETWEEN '$date_start' AND '$date_end'
					$latlon_sql
			ORDER BY $table.time DESC, $table.time_ns DESC
			LIMIT ".($page * $per_page).", ".($per_page+1)."";
	$res = bo_db($sql);

	echo '<div class="bo_sig_navi">';

	if ($res->num_rows > $per_page)
		echo '<a href="'.bo_insert_url('bo_action', $page+1).'" class="bo_sig_prev" index="nofollow">&lt; '._BL('Older').'</a>';
	if ($page)
		echo '<a href="'.bo_insert_url('bo_action', $page-1).'" class="bo_sig_next" index="nofollow">'._BL('Newer').' &gt;</a>';
	echo '</div>';

	echo '<table class="bo_sig_table">';


	while($row = $res->fetch_assoc())
	{

		$count++;
		$stime = strtotime($row['stime'].' UTC');

		if ($row['raw_id'])
		{
			$rtime = strtotime($row['rtime'].' UTC') + 1;
			$time_diff = $rtime - $stime + ($row['rtimens'] - $row['stimens']) * 1E-9;
			$dist_sig  = $c * $time_diff / 1000;
		}

		$bearing = bo_latlon2bearing($row['lat'], $row['lon']);

		echo '<tr>';

		echo '<td class="bo_sig_table_time">';
		echo '<span class="bo_descr">';
		echo ($show_empty_sig ? _BL('Time') : _BL('Recieved')).': ';
		echo '</span>';
		echo '<span class="bo_value">';

		if ($show_empty_sig)
			echo date(_BL('_datetime'), $stime).'.'.sprintf('%09d', $row['stimens']);
		else
			echo date(_BL('_datetime'), $rtime).'.'.sprintf('%09d', $row['rtimens']);


		echo '</span>';
		echo '</td>';


		echo '<td rowspan="2" class="bo_sig_table_graph"  style="width:'.BO_GRAPH_RAW_W.'px;">';
		if ($row['raw_id'])
			echo '<img src="'.BO_FILE.'?graph='.$row['raw_id'].'&bo_lang='._BL().'" style="width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px">';
		else
			echo _BL('No signal recieved.');

		echo '</td>';

		echo '</tr><tr>';

		echo '<td class="bo_sig_table_strikeinfo">';


		if ($row['strike_id'])
		{
			$pol = bo_strike2polarity($row['data'], $bearing);

			echo '<ul>';

			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Runtime').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			if ($row['raw_id'])
				echo number_format($time_diff * 1000, 4, _BL('.'), _BL(','))._BL('unit_millisec');
			else
				echo '-';
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
/*
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Bearing').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo _BL(bo_bearing2direction($bearing));
			echo '</span>';
			echo '</li>';
*/
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

			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Polarity').': ';
			echo '</span>';
			echo '<span class="bo_value">';

			if ($pol === null)
				echo '?';
			elseif ($pol > 0)
				echo _BL('positive');
			elseif ($pol < 0)
				echo _BL('negative');
			echo '</span>';
			echo '</li>';

			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Participants').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo number_format($row['users'], 0, _BL('.'), _BL(','));
			echo '</span>';
			echo '</li>';

			echo '</ul>';

		}
		else
		{
			echo _BL('no strike detected');
		}

		echo '</td>';
		echo '</tr>';

		if ($count == $per_page)
			break;

	}

	echo '</table>';

	if ($count)
	{
		echo '<div class="bo_sig_navi">';
		if ($count == $per_page)
			echo '<a href="'.bo_insert_url('bo_action', $page+1).'" class="bo_sig_prev" index="nofollow">&lt; '._BL('Older').'</a>';
		if ($page)
			echo '<a href="'.bo_insert_url('bo_action', $page-1).'" class="bo_sig_next" index="nofollow">'._BL('Newer').' &gt;</a>';
		echo '</div>';
	}

	echo '</form>';

}





?>