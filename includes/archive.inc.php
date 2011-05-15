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
	$show = $_GET['bo_show'] ? $_GET['bo_show'] : 'search';

	echo '<ul id="bo_menu">';

	echo '<li><a href="'.bo_insert_url('bo_show', 'search').'" class="bo_navi'.($show == 'search' ? '_active' : '').'">'._BL('arch_navi_search').'</a></li>';
	echo '<li><a href="'.bo_insert_url('bo_show', 'signals').'" class="bo_navi'.($show == 'signals' ? '_active' : '').'">'._BL('arch_navi_signals').'</a></li>';

	echo '</ul>';


	switch($show)
	{
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
		$sql = "SELECT  s.id id, s.distance distance, s.lat lat, s.lon lon, s.time time, s.time_ns time_ns,
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
			$description .= '</ul>';

			if ($row['raw_id'])
				$description .= '<img src=\''.BO_FILE.'?graph='.$row['raw_id'].'\' style=\'width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px\' class=\'bo_archiv_map_signal\'>';

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
			echo $more_found ? _BL('More than') : _BL('Exact');
			echo ' '.$count.' ';
			echo $count == 1 ? _BL('Strike') : _BL('Strikes');
			echo ' '._BL('found');
			echo '</li>';

			echo '<li><span class="bo_descr">'._BL('Oldest').':</span><span class="bo_value"> '.date(_BL('_datetime'), $time_min).'</value></li>';
			echo '<li><span class="bo_descr">'._BL('Newest').':</span><span class="bo_value"> '.date(_BL('_datetime'), $time_max).'</value></li>';
		}
		echo '</ul>';
	}


	echo '<h4>'._BL('Search Options').'</h4>';

	echo '<form action="?" method="GET" class="bo_archive_form">';
	echo bo_insert_html_hidden(array('bo_lat', 'bo_lon', 'bo_map_zoom', 'bo_map_lat', 'bo_map_lon'));

	echo '<fieldset class="bo_archive_fieldset">';
	echo '<legend>'._BL('archive_legend').'</legend>';

	echo '<span class="bo_form_descr">'._BL('Coordinates').':</span>';
	echo '<input type="text" name="bo_lat" value="'.htmlentities($lat).'" id="bo_archive_lat" class="bo_form_text bo_archive_latlon">';
	echo '<input type="text" name="bo_lon" value="'.htmlentities($lon).'" id="bo_archive_lon" class="bo_form_text bo_archive_latlon">';

	echo '<span class="bo_form_descr">'._BL('Distance').' '.'('._BL('unit_meters').'):</span>';
	echo '<input type="text" name="bo_dist" value="'.htmlentities($delta_dist).'" id="bo_archive_dist" class="bo_form_text bo_archive_dist">';


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

	$only_strikes = isset($_GET['only_strikes']);
	$page = intval($_GET['page']);
	$page = $page < 0 ? 0 : $page;

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

		echo bo_insert_html_hidden(array('only_strikes', 'page'));

		echo '<fieldset>';
		echo '<legend>'._BL('settings').'</legend>';

		echo '<input type="checkbox" name="only_strikes" value="1" '.($only_strikes ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="check_only_strikes">';
		echo '<label for="check_only_strikes"> '._BL('check_only_strikes').'</label> &nbsp; ';

		echo '</fieldset>';

		$hours_back = 24;
	}

	$row = bo_db("SELECT MAX(time) time FROM ".BO_DB_PREF."raw")->fetch_assoc();

	$time_end  = strtotime($row['time'].' UTC');
	$date_end  = gmdate('Y-m-d H:i:s', $time_end - 60 * 5);
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
		$sql_join = BO_DB_PREF."raw r LEFT OUTER JOIN ".BO_DB_PREF."strikes s ON s.raw_id=r.id ";
		$table = 'r';
	}

	$count = 0;
	$sql = "SELECT  s.id strike_id, s.distance distance, s.lat lat, s.lon lon,
					s.deviation deviation, s.current current,
					s.time stime, s.time_ns stimens,
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
		echo '<a href="'.bo_insert_url('page', $page+1).'" class="bo_sig_prev" index="nofollow">'.htmlentities('< '._BL('Older')).'</a>';
	if ($page)
		echo '<a href="'.bo_insert_url('page', $page-1).'" class="bo_sig_next" index="nofollow">'.htmlentities(_BL('Newer').' >').'</a>';
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
			echo '<img src="'.BO_FILE.'?graph='.$row['raw_id'].'" style="width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px">';
		else
			echo _BL('No signal recieved.');
		echo '</td>';

		echo '</tr><tr>';

		echo '<td class="bo_sig_table_strikeinfo">';


		if ($row['strike_id'])
		{
			$pol = bo_strike2polarity($row['data'], $bearing);

			echo '<ul>';

			/*
			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Time').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo date(_BL('_datetime'), $stime).'.'.sprintf('%09d', $row['stimens']);
			echo '</span>';
			echo '</li>';
			*/

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
			echo '</span>';
			echo '</li>';

			echo '<li>';
			echo '<span class="bo_descr">';
			echo _BL('Bearing').': ';
			echo '</span>';
			echo '<span class="bo_value">';
			echo _BL(bo_bearing2direction($bearing));
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
			echo '<a href="'.bo_insert_url('page', $page+1).'" class="bo_sig_prev" index="nofollow">'.htmlentities('< '._BL('Older')).'</a>';
		if ($page)
			echo '<a href="'.bo_insert_url('page', $page-1).'" class="bo_sig_next" index="nofollow">'.htmlentities(_BL('Newer').' >').'</a>';
		echo '</div>';
	}

	echo '</form>';

}





?>