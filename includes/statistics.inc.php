<?php



//strike statistics
function bo_show_statistics_strikes($station_id = 0, $own_station = true, $add_graph = '')
{
	global $_BO;

	$year = intval($_GET['bo_year']);
	$month = intval($_GET['bo_month']);
	$region = $_GET['bo_region'] ? $_GET['bo_region'] : 0;
	

	/*** Strikes NOW ***/
	$last_update = BoData::get('uptime_strikes');
	$last_update_minutes = round((time()-$last_update)/60,1);
	$group_minutes = BO_GRAPH_STAT_STRIKES_NOW_GROUP_MINUTES;


	if (substr($region, 0, 4) == 'dist' || substr($region, 0, 1) == '-')
	{
		$sql_time = " time BETWEEN '".gmdate('Y-m-d H:i:s', $last_update - 60*$group_minutes*2 )."' AND '".gmdate('Y-m-d H:i:s', $last_update-60*$group_minutes*1)."' ";
		$sql = "SELECT COUNT(*) cnt
				FROM ".BO_DB_PREF."strikes s
				WHERE $sql_time ".bo_region2sql($region, $station_id);
		$row = BoDb::query($sql)->fetch_assoc();
		$strike_rate = $row['cnt'] / $group_minutes;

		//can take a very very long time without 
		//specifing a time range!
		$sql = "SELECT MAX(time) mtime
				FROM ".BO_DB_PREF."strikes s
				WHERE $sql_time ".bo_region2sql($region, $station_id);
		$row = BoDb::query($sql)->fetch_assoc();
		$last_strike = $row['mtime'] ? strtotime($row['mtime'].' UTC') : 0;
	}
	else
	{
		$last_strikes_region = unserialize(BoData::get('last_strikes_region'));
		$rate_strikes_region = unserialize(BoData::get('rate_strikes_region'));
		$strike_rate = $rate_strikes_region[$region];
		$last_strike = $last_strikes_region[$region];
	}

	if (!$region && intval(BO_TRACKS_SCANTIME))
	{
		$num_cells = -1;
		$cells_data = unserialize(BoData::get('strike_cells'));
		if (is_array($cells_data['cells']))
		{
			$num_cells = count($cells_data['cells'][BO_TRACKS_DIVISOR-1]);
		}
	}


	/*** Strikes by month/year ***/
	$time = mktime(0,0,0,date('m'), date('d'), date('Y'));

	if (!$year)
		$year = date('Y', $time);

	if (!$month)
		$month = date('m', $time);

	$D = array();

	$years = array();
	$months = array();
	$months[-1] = _BL('All');

	$res = BoDb::query("SELECT DISTINCT SUBSTRING(name, 9, 6) time
					FROM ".BO_DB_PREF."conf
					WHERE name LIKE 'strikes_%'
					ORDER BY time");
	while($row = $res->fetch_assoc())
	{
		$y = (int)substr($row['time'], 0, 4);
		$m = (int)substr($row['time'], 4, 2);

		if ($y)
			$years[$y] = $y;

		if ($y == $year)
			$months[$m] = _BL(date('M', strtotime("$y-$m-01")).'_short');
	}

	//Add current month
	if (!$year || $year == date('Y'))
	{
		$years[(int)date('Y')] = date('Y');
		$months[(int)date('m')] = _BL(date('M').'_short');
	}

	if (!$years[(int)$year])
		$year = max($years);

	if (!$months[(int)$month])
		$month = max(array_flip($months));


	echo '<div id="bo_stat_strikes">';

	echo '<a name="graph_strikes_now"></a>';
	echo '<h3>'._BL('h3_stat_strikes_now').'</h3>';

	echo '<p class="bo_stat_description" id="bo_stat_strikes_now_descr">';
	echo strtr(_BL('bo_descr_strikes_now'), array('{UPDATE_INTERVAL}' => _BLN(BO_UP_INTVL_STRIKES, 'every_minute'), '{RATE_INTERVAL}' => _BLN($group_minutes)));
	echo '</p>';

	$region_select = bo_get_select_region($region, $station_id);
	
	if ($region_select)
	{
		echo '<form action="?" method="GET" class="bo_stat_strikes_form">';
		echo bo_insert_html_hidden(array('bo_year', 'bo_month', 'bo_region'));
		echo '<fieldset>';
		echo '<legend>'._BL('legend_stat_strikes_now').'</legend>';
		echo '<span class="bo_form_descr">'._BL('Region').': </span>';
		echo $region_select;
		echo '</fieldset>';
		echo '</form>';
	}

	echo '<ul class="bo_stat_overview">';

	echo '<li><span class="bo_descr">'._BL('Last update').': </span>';
	echo '<span class="bo_value">'._BL('_before')._BN($last_update_minutes, 1).' '.($last_update_minutes == 1 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span></li>';

	echo '<li><span class="bo_descr">'._BL('Last detected strike').': </span>';
	echo '<span class="bo_value">'.($last_strike ? _BDT($last_strike) : '?').'</span></li>';

	echo '<li><span class="bo_descr">'._BL('Current strike rate').': </span>';
	echo '<span class="bo_value">';
	echo _BN($strike_rate, 1).' '.(0 && $strike_rate === 1.0 ? _BL('unit_strikesperminute_one') : _BL('unit_strikesperminute'));
	echo '</span></li>';

	if (!$region && intval(BO_TRACKS_SCANTIME) && $num_cells >= 0)
	{
		echo '<li><span class="bo_descr">'._BL('Thunder cells').': </span>';
		echo '<span class="bo_value">'._BN($num_cells, 0).' ('._BL('experimental').')</span></li>';
	}

	echo '</ul>';

	bo_show_graph('strikes_now', $add_graph.'&region='.$region, true);

	if (substr($region, 0, 4) != 'dist' && substr($region, 0, 5) != '-dist')
	{
	
		echo '<h3>'._BL('h3_stat_strikes_time').'</h3>';
		echo '<a name="graph_strikes_time_select"></a>';

		echo '<form action="?#graph_strikes_time_select" method="GET" class="bo_stat_strikes_form">';
		echo bo_insert_html_hidden(array('bo_year', 'bo_month', 'bo_region'));

		echo '<fieldset>';
		echo '<legend>'._BL('legend_stat_strikes').'</legend>';

		echo '<span class="bo_form_descr">'._BL('time_year_month').': </span>';

		echo '<select name="bo_year" onchange="submit();" id="bo_stat_strikes_select_year">';
		foreach($years as $i => $y)
			echo '<option value="'.$i.'" '.($i == $year ? 'selected' : '').'>'.$y.'</option>';
		echo '</select> ';

		echo '<select name="bo_month" onchange="submit();" id="bo_stat_strikes_select_month">';
		foreach($months as $i => $m)
			echo '<option value="'.$i.'" '.($i == $month ? 'selected' : '').' style="'.($i <= 0 ? 'font-weight:bold;' : '').'">'.$m.'</option>';
		echo '</select>';

		$region_select = bo_get_select_region($region, false, false);
	
		if ($region_select)
		{
			echo ' &nbsp; &bull; &nbsp; <span class="bo_form_descr">'._BL('Region').': </span>';
			echo $region_select;
		}	
		
		echo '</fieldset>';


		echo '</fieldset>';

		echo '</form>';

		echo '<a name="graph_strikes"></a>';
		echo '<h4>'._BL('h4_graph_strikes_time').'</h4>';
		echo '<p class="bo_graph_description" id="bo_graph_descr_strikes_time">';
		echo _BL('bo_graph_descr_strikes_time');
		echo '</p>';
		bo_show_graph('strikes_time', '&year='.$year.'&month='.$month.'&region='.$region.$add_graph);

		if (!$region && $own_station)
		{
			echo '<a name="graph_strikes"></a>';
			echo '<h4>'._BL('h4_graph_strikes_time_radius').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_descr_strikes_time_radius">';
			echo strtr(_BL('bo_graph_descr_strikes_time_radius'), array('{RADIUS}' => BO_RADIUS_STAT));
			echo '</p>';
			bo_show_graph('strikes_time', '&year='.$year.'&month='.$month.'&radius=1'.$add_graph);
		}
	}
	
	echo '</div>';
}

//show station-statistics
function bo_show_statistics_station($station_id = 0, $own_station = true, $add_graph = '')
{

	$strikesh_own = 0;
	$signalsh_own = 0;
	$stInfo = bo_station_info($station_id);
	$city = _BC($stInfo['city']);

	if ($own_station)
	{
		$own_station_info = bo_station_info();
		if ($stInfo['country'] != $own_station_info['country'])
			$city .= ' ('._BL($stInfo['country']).')';
	}

	//Last overall stroke count and time
	$row = BoDb::query("SELECT strikesh, time FROM ".BO_DB_PREF."stations_stat WHERE station_id='0' AND time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)")->fetch_assoc();
	$strikesh = $row['strikesh'];
	$stations_time = strtotime($row['time'].' UTC');
	$last_update = (time()-$stations_time)/60;

	//Whole active station count
	$row = BoDb::query("SELECT COUNT(station_id) cnt FROM ".BO_DB_PREF."stations_stat a WHERE time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)")->fetch_assoc();
	$stations = $row['cnt'] - 1;

	//Own strokes
	if ($own_station)
	{
		$add = '';

		$sql = "SELECT COUNT(*) cnt FROM ".BO_DB_PREF."strikes
				WHERE time BETWEEN '".gmdate('Y-m-d H:i:s', $stations_time - 3600)."' AND '".gmdate('Y-m-d H:i:s', $stations_time)."'
						AND part>0 AND stations='".bo_participants_locating_min()."'";
		$row = BoDb::query($sql)->fetch_assoc();
		$strikes_part_min_own = $row['cnt'];
	}
	else
	{
		$add = '#'.$station_id.'#';

		$sql = "SELECT COUNT(*) cnt FROM ".BO_DB_PREF."strikes s
				JOIN ".BO_DB_PREF."stations_strikes ss
				ON s.id=ss.strike_id AND ss.station_id='$station_id'
				WHERE time BETWEEN '".gmdate('Y-m-d H:i:s', $stations_time - 3600)."' AND '".gmdate('Y-m-d H:i:s', $stations_time)."'
						AND stations='".bo_participants_locating_min()."'";
		$row = BoDb::query($sql)->fetch_assoc();
		$strikes_part_min_own = $row['cnt'];
	}

	if ($own_station || (defined('BO_STATISTICS_ALL_STATIONS') && BO_STATISTICS_ALL_STATIONS))
	{
		$act_time = BoData::get('station_last_active'.$add);
		$inact_time = BoData::get('station_last_inactive'.$add);
		$nogps_last_time = BoData::get('station_last_nogps'.$add);
	}

	//Get the last non-zero signals or strikes for the station
	$sql = "SELECT signalsh, strikesh, time FROM ".BO_DB_PREF."stations_stat WHERE station_id='$station_id' AND time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat WHERE station_id='$station_id')";
	$row = BoDb::query($sql)->fetch_assoc();
	$time_last_good_signals = $row['time'] ? strtotime($row['time'].' UTC') : false;
	$last_active = $time_last_good_signals ? (time()-$time_last_good_signals)/60 : false;

	if ($stations_time == $time_last_good_signals)
	{
		$strikesh_own = $row['strikesh'];
		$signalsh_own = $row['signalsh'];
	}




	$tmp = @unserialize(BoData::get('last_strikes_stations'));
	$last_strike = $tmp[$station_id][0];
	$last_signal = strtotime($stInfo['last_time'].' UTC');
	$active = $stInfo['status'] >= STATUS_RUNNING*10;


	if (defined('BO_ENABLE_DENSITIES') && BO_ENABLE_DENSITIES && defined('BO_CALC_DENSITIES') && BO_CALC_DENSITIES)
	{
		require_once 'density.inc.php';
		$dens_stations = bo_get_density_stations();
	}

	echo '<h3>'.strtr(_BL('h3_stat_station'), array('{STATION_CITY}' => $city)).'</h3>';

	echo '<div id="bo_stat_station">';

	echo '<p class="bo_stat_description" id="bo_stat_station_descr_lasth">';
	echo strtr(_BL('bo_stat_station_descr_lasth'), array('{STATION_CITY}' => $city, '{MIN_PARTICIPANTS}' => bo_participants_locating_min()));
	echo '</p>';

	
	echo '<h4>'._BL('h4_stat_station_general').'</h4>';
	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Station active').': </span>';
	echo '<span class="bo_value">';

	echo '<strong>';
	echo $active ? _BL('yes') : _BL('no');
	echo '</strong>';


	if ($active)
	{
		if (bo_status($stInfo['status'], STATUS_BAD_GPS))
		{
			echo ' / ';
			echo '<span class="bo_err_text">';
			echo _BL('no GPS signal');
			echo '</span>';
		}

		if (!$signalsh_own)
		{
			echo ' / ';
			echo '<span class="bo_err_text">';
			echo _BL('no reception');
			echo '</span>';
		}

	}

	echo '</span>';

	if (!$active)
	{
		echo '<li><span class="bo_descr">'._BL('Last active').': </span><span class="bo_value">';

		if ($last_active === false)
		{
			echo _BL('Never before');
		}
		else
		{
			echo _BL('_before')._BN($last_active, 1)." ";
			echo (0 && $last_active == 1 ? _BL('_minute_ago') : _BL('_minutes_ago'));
		}

		echo '</span>';
	}

	echo '<li><span class="bo_descr">'._BL('Last update').': </span><span class="bo_value">'._BL('_before')._BN($last_update, 1)." ".(0 && $last_update == 1 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Last detected strike').': </span>';
	echo '<span class="bo_value">';
	echo $last_strike ? _BDT($last_strike) : _BL('no_strike_yet');
	echo '</span>';
	echo '</li>';

	if ($active) //don't display this part when inactive, there may be still some non-zero values
	{
		echo '<li><span class="bo_descr">'._BL('Strikes').': </span><span class="bo_value">'._BN($strikesh_own, 0);
		
		if (bo_user_get_level() & BO_PERM_ARCHIVE)
			echo '&nbsp;(<a href="'.BO_ARCHIVE_URL.bo_add_sess_parms().'&bo_show=strikes&bo_station_id='.$station_id.'&bo_datetime_start='.urlencode(date('Y-m-d H:i', time() - 4000)).'">'._BL('List').'</a>)';

		echo '</span>';
		echo '<li><span class="bo_descr">'._BL('Signals').': </span><span class="bo_value">'._BN($signalsh_own, 0).'</span>';

		echo '<li><span class="bo_descr">'._BL('Locating ratio').': </span><span class="bo_value">';
		echo $signalsh_own ? _BN($strikesh_own / $signalsh_own * 100, 1).'%' : '-';
		echo '</span></li>';
		echo '<li><span class="bo_descr">'._BL('Strike ratio').': </span><span class="bo_value">';
		echo $strikesh ? _BN($strikesh_own / $strikesh * 100, 1).'%' : '-';

		if ($dens_stations[$station_id])
			echo '&nbsp;(<a href="'.BO_ARCHIVE_URL.bo_add_sess_parms().'&bo_show=density&bo_station_id='.$station_id.'&bo_ratio=1">'._BL('Map').'</a>)';

		echo '</span></li>';

		echo '<li><span class="bo_descr">'._BL('Strikes station min participants').': </span>';
		echo '<span class="bo_value">';
		echo _BN($strikes_part_min_own, 0);

		if ($strikesh)
		{
			$part_own_percent = $strikes_part_min_own / $strikesh * 100;

			echo ' (';
			echo _BN($part_own_percent, 1).'%';
			//echo ' - '._BL('Score').': '._BN($part_own_percent * $stations, 0).'%';
			echo ') ';
		}

		echo '</span></li>';
	}
	elseif ($last_signal)
	{
		echo '<li><span class="bo_descr">'._BL('Last signal').': </span><span class="bo_value">'._BDT($last_signal).'</span>';
	}

	if ($stInfo['firmware'])
	{
		if (preg_match('/[A-Z]/i', $stInfo['firmware']))
			$name = 'Tracker';
		else
			$name = 'Firmware';
			
		echo '<li><span class="bo_descr">'._BL($name).': </span><span class="bo_value">'._BC($stInfo['firmware']).'</span>';
	}

	list($pcb) = explode(';', $stInfo['controller_pcb']);
	
	if ($pcb)
	{
		echo '<li><span class="bo_descr">'._BL("Controller").': </span><span class="bo_value">'.$pcb.'</span>';
	}
	
	
	echo '</ul>';

	
	
	$antenna = $own_station ? bo_get_antenna_data() : false;
	$show_overview = (double)$stInfo['lat'] != 0.0 && (double)$stInfo['lon'] != 0.0;
	$show_gps = $show_overview && (
	              ($own_station && ((defined("BO_SHOW_GPS_INFO") && BO_SHOW_GPS_INFO) || (bo_user_get_level() & BO_PERM_SETTINGS)))
				  || (BO_ENABLE_LONGTIME_ALL === true && (bo_user_get_level() & BO_PERM_SETTINGS))
				  );
	
	//Show GPS Info
	if ($show_gps)
	{
		$js_text = '';
		$js_time = '';
		$height = array();
		$lat = array();
		$lon = array();
		$pos_text = array();
		$mean_lat = 0;
		$mean_lat = 0;
		$show_map = false;

		$data = unserialize(BoData::get('station_data24h'.$add));
		$add = $own_station ? '' : '#'.$station_id.'#';
		$data = unserialize(BoData::get('station_data24h'.$add));
		if (isset($data) && is_array($data))
		{
			$tmp = array();

			//sort by time
			foreach($data as $t => $d)
			{
				if (time() - $d['time'] < 3600 * 26)
					$tmp[$d['time']] = $d;
			}

			ksort($tmp);
			foreach($tmp as $t => $d)
			{
				
				if ($d['lat'] == 0.0 && $d['lon'] == 0.0)
					continue;
				
				$lat[] = $d['lat'];
				$lon[] = $d['lon'];
				$pos_text[] = _BDT($t).' '.
								(isset($d['height']) ? _BL('Height').': '._BM($d['height']) : '').' '.
								_BL('Status').': '.$d['status'].' '.
								_BL('Signals/h').': '.$d['sig'].' '.
								_BL('Strikes/h').': '.$d['strikes'].' '
								;
				
				if (isset($d['height']))
					$height[] = $d['height'];
			}

		}
		
		if (count($lat))
		{
			$show_map = true;
			$mean_lat = array_sum($lat) / count($lat);
			$mean_lon = array_sum($lon) / count($lon);

			//distance: mean deviation
			$dist_dev = 0;
			foreach($lat as $id => $val)
			{
				$dist_dev += bo_latlon2dist($mean_lat, $mean_lon, $lat[$id], $lon[$id]);
			}
			$dist_dev /= count($lat);

			//height: standard deviation
			$height_dev = 0;
			if (count($height) > 1)
			{
				$st_height = round(array_sum($height) / count($height));
				foreach($height as $val)
				{
					$height_dev += pow($val-$st_height,2);
				}
				$height_dev = sqrt($height_dev/(count($height)-1));
			}

			//Javascript data
			foreach($lat as $id => $val)
			{
				$js_pos  .= ($js_pos  ? ',' : '').'new google.maps.LatLng('.$lat[$id].','.$lon[$id].')';
				$js_text .= ($js_text ? ',' : '').'"'.$pos_text[$id].'"';
			}
			
		}
		elseif ((double)$stInfo['lat'] != 0.0 && (double)$stInfo['lon'] != 0.0)
		{
			$show_map = true;
			$mean_lat = $stInfo['lat'];
			$mean_lon = $stInfo['lon'];
		}

		if ($own_station)
		{
			$lat_marker = BO_LAT;
			$lon_marker = BO_LON;
		}
		else
		{
			$lat_marker = $stInfo['lat'];
			$lon_marker = $stInfo['lon'];
		}
		
		if ($antenna !== false && $own_station)
		{
			$show_map = true;
			$dist = 50;
			list($lat1a, $lon1a) = bo_distbearing2latlong($dist, $antenna[1],     BO_LAT, BO_LON);
			list($lat1b, $lon1b) = bo_distbearing2latlong($dist, $antenna[1]+180, BO_LAT, BO_LON);
			list($lat2a, $lon2a) = bo_distbearing2latlong($dist, $antenna[2],     BO_LAT, BO_LON);
			list($lat2b, $lon2b) = bo_distbearing2latlong($dist, $antenna[2]+180, BO_LAT, BO_LON);

			$js_data_ant = '
			var ant1 = [ new google.maps.LatLng('.$lat1a.','.$lon1a.'), new google.maps.LatLng('.$lat1b.','.$lon1b.') ];
			var ant2 = [ new google.maps.LatLng('.$lat2a.','.$lon2a.'), new google.maps.LatLng('.$lat2b.','.$lon2b.') ];

			var ant1Path = new google.maps.Polyline({
				path: ant1,
				strokeColor: "#ff0000",
				strokeOpacity: 0.5,
				strokeWeight: 2,
				clickable: false
			});
			ant1Path.setMap(bo_map);

			var ant1Path = new google.maps.Polyline({
				path: ant2,
				strokeColor: "#00ff00",
				strokeOpacity: 0.5,
				strokeWeight: 2,
				clickable: false
			});
			ant1Path.setMap(bo_map);

			';
		}

		echo '<h4>'._BL('h4_stat_other_gps').'</h4>';
		echo '<p class="bo_stat_description" id="bo_stat_other_descr_gps">';
		echo _BL('bo_stat_other_gps_descr');
		echo '</p>';
		echo '<ul class="bo_stat_overview">';
		if ($lat[0] && $lon[0])
		{
			echo '<li><span class="bo_descr">'._BL('Mean coordinates').': </span>';
			echo '<span class="bo_value">'._BN($mean_lat,6).'&deg; / '._BN($mean_lon,6).'&deg (&plusmn;'._BM($dist_dev, 1).')</span>';

			if (count($height) > 1)
				echo '<li><span class="bo_descr">'._BL('Height').': </span><span class="bo_value">'._BM($st_height).' (&plusmn;'._BM($height_dev, 1).')</span>';
		}
		elseif ($show_map)
		{
			echo '<li><span class="bo_descr">'._BL('Last position').': </span>';
			echo '<span class="bo_value">'._BN($stInfo['lat'],6).'&deg; / '._BN($stInfo['lon'],6).'&deg</span>';
		}
		else
			echo '<li><span class="bo_descr">'._BL('Currently no GPS coordinates available!').'</span>';

		if ($nogps_last_time)
		{
			echo '<li><span class="bo_descr">'._BL('Last time without GPS').': </span><span class="bo_value">'._BDT($nogps_last_time).'</span>';
		}

			
		echo '</ul>';


		//Show the map
		if ($show_map)
		{
			echo '<div id="bo_gmap" class="bo_map_gps" style="width:550px;height:200px"></div>';
?>
			<script type="text/javascript">

			function bo_gmap_init2()
			{
				var bounds = new google.maps.LatLngBounds();			
				var myLatlng = new google.maps.LatLng(<?php echo (double)$lat_marker.','.(double)$lon_marker ?>);
				
				var marker = new google.maps.Marker({
				  position: myLatlng, 
				  map: bo_map, 
				  icon: '<?php echo BO_MAP_STATION_ICON ?>' 
				});
				
				bounds.extend(myLatlng);
			
				var coordinates = [ <?php echo $js_pos ?> ];
				var postext     = [ <?php echo $js_text ?> ];

				if (coordinates.length > 0)
				{
					var gpsPath = new google.maps.Polyline({
						path: coordinates,
						strokeColor: "#0000FF",
						strokeOpacity: 0.5,
						strokeWeight: 2,
						clickable: false
						});
					gpsPath.setMap(bo_map);

					var bo_gps_image = new google.maps.MarkerImage("<?php echo  bo_bofile_url() ?>?size=1&bo_icon=0000ff");
									
					for (var i = 0; i < coordinates.length; i++) 
					{
						bounds.extend(coordinates[i]);

						var bo_gps_marker = new google.maps.Marker({
							position: coordinates[i], 
							map: bo_map, 
							title:postext[i],
							icon:bo_gps_image
							});
						
					}

					<?php echo BO_STATISTICS_GPS_MAP_ZOOM == 0 ? 'bo_map.fitBounds(bounds);' : ''; ?>

				}
				else
				{
					bo_map.setZoom(<?php echo BO_STATISTICS_GPS_MAP_ZOOM == 0 ? 12 : BO_STATISTICS_GPS_MAP_ZOOM; ?>);
				}

				
				<?php echo $js_data_ant ?>
			}

			</script>

<?php

			require_once 'functions_dynmap.inc.php';
			bo_insert_map(0, $mean_lat, $mean_lon, BO_STATISTICS_GPS_MAP_ZOOM, BO_STATISTICS_GPS_MAPTYPE);
		}
	}
	elseif ($show_overview)
	{
		echo '<h4>'._BL('h4_stat_station_area').'</h4>';
		
		echo '<div id="bo_gmap" class="bo_map_station" style="width:550px;height:200px"></div>';

		?>
		<script type="text/javascript">
		function bo_gmap_init2()
		{
			//noting to do ;-)
		}
		</script>
		<?php

		$round = (bo_user_get_level() & BO_PERM_SETTINGS) ? 8 : 1;
		require_once 'functions_dynmap.inc.php';
		bo_insert_map(0, round($stInfo['lat'],$round), round($stInfo['lon'],$round), 10, BO_STATISTICS_STATION_MAPTYPE);
	}
	

	echo '<a name="graph_strikes"></a>';
	echo '<h4>'._BL('h4_graph_strikes').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_strikes">';
	echo strtr(_BL('bo_graph_descr_strikes'), array('{STATION_CITY}' => $city));
	echo '</p>';
	bo_show_graph('strikes', $add_graph, true);

	echo '<a name="graph_signals"></a>';
	echo '<h4>'._BL('h4_graph_signals').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_signals">';
	echo strtr(_BL('bo_graph_descr_signals'), array('{STATION_CITY}' => $city));
	echo '</p>';
	bo_show_graph('signals', $add_graph, true);


	echo '<a name="graph_ratio"></a>';
	echo '<h4>'._BL('h4_graph_ratio').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_ratio">';
	echo strtr(_BL('bo_graph_descr_ratio'), array('{STATION_CITY}' => $city));
	echo '</p>';
	bo_show_graph('ratio', $add_graph, true);

	echo '<a name="graph_ratio_distance"></a>';
	echo '<h4>'._BL('h4_graph_ratio_distance').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_radi">';
	echo strtr(_BL('bo_graph_descr_radi'), array('{STATION_CITY}' => $city));
	echo '</p>';
	bo_show_graph('ratio_distance', $add_graph, true);

	echo '<a name="graph_ratio_bearing"></a>';
	echo '<h4>'._BL('h4_graph_ratio_bearing').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_bear">';
	echo strtr(_BL('bo_graph_descr_bear'), array('{STATION_CITY}' => $city));
	echo '</p>';

	if (BO_GRAPH_STAT_RATIO_BEAR_WINDROSE === true)
		bo_show_graph('ratio_bearing', $add_graph, true, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE);
	else
		bo_show_graph('ratio_bearing', $add_graph, true);

	if ($antenna !== false)
	{
		echo '<h4>'._BL('h4_stat_other_antennas').'</h4>';
		echo '<p class="bo_stat_description" id="bo_stat_other_descr_antennas">';
		echo _BL('bo_stat_other_antennas_descr');
		echo '</p>';

		echo '<ul class="bo_stat_overview">';
		echo '<li><span class="bo_descr">'._BL('Direction antenna 1').': </span><span class="bo_value">'.$antenna[1].'&deg; - '.($antenna[1]+180).'&deg ('.(_BL(bo_bearing2direction($antenna[1])).'-'._BL(bo_bearing2direction($antenna[1]+180))).')</span>';
		echo '<li><span class="bo_descr">'._BL('Direction antenna 2').': </span><span class="bo_value">'.$antenna[2].'&deg; - '.($antenna[2]+180).'&deg ('.(_BL(bo_bearing2direction($antenna[2])).'-'._BL(bo_bearing2direction($antenna[2]+180))).')</span>';
		echo '</ul>';
	}


	echo '</div>';
}

//show network-statistics
function bo_show_statistics_network($station_id = 0, $own_station = true, $add_graph = '')
{
	$sort 					= $_GET['bo_sort'];
	$range                  = abs(intval($_GET['bo_hours']));
	$mybo_first_update		= BoData::get('first_update_time');
	$stations_nogps         = BoData::get('active_stations_nogps');
	$whole_sig_count 		= 0;
	$whole_sig_ratio 		= 0;
	$whole_sig_ratio_cnt 	= 0;
	$whole_strike_ratio 	= 0;
	$whole_strike_ratio_cnt = 0;
	$active_stations        = 0;

	if (intval(BO_STATISTICS_NETWORK_RANGES))
		$ranges = explode(',', BO_STATISTICS_NETWORK_RANGES);

	$ranges[] = 1;
	if ($range < 1 || array_search($range, $ranges) === false)
		$range = 1;


	//Last update, time range
	$row = BoDb::query("SELECT MAX(time) mtime FROM ".BO_DB_PREF."stations_stat WHERE time < '".gmdate("Y-m-d H:i:s", time() - 10)."'")->fetch_assoc();
	$time = strtotime($row['mtime'].' UTC');
	$strikes_time_start = gmdate('Y-m-d H:i:s', $time - $range * 3600);
	$table_time_start   = gmdate('Y-m-d H:i:s', $time - ($range>1 ? ($range-1)*3600 : 0) );
	$table_time_end     = $row['mtime'];
	$last_update = (time()-strtotime($row['mtime'].' UTC'))/60;


	//participants
	$row = BoDb::query("SELECT MAX(stations) max_stations, AVG(stations) avg_stations
					FROM ".BO_DB_PREF."strikes
					WHERE time BETWEEN '$strikes_time_start' AND '$table_time_end'")->fetch_assoc();
	$max_part = $row['max_stations'];
	$avg_part = $row['avg_stations'];


	//Strikes in timerange
	$sql = "SELECT AVG(strikesh) strikesh, DATE_FORMAT(time, '%Y%m%d%H') h
			FROM ".BO_DB_PREF."stations_stat
			WHERE station_id='0'
			AND time BETWEEN '$table_time_start' AND '$table_time_end'
			GROUP BY h";
	$res = BoDb::query($sql);
	while($row = $res->fetch_assoc())
	{
		$strikes_range += $row['strikesh'];
	}


	//Station statistics
	$D = array();
	$count = 0;
	$sql = "SELECT  	SUM(signalsh) signalsh_sum,
						SUM(strikesh) strikesh_sum,
						COUNT(*) cnt, station_id,
						DATE_FORMAT(time, '%Y%m%d%H') h
					FROM ".BO_DB_PREF."stations_stat
					WHERE time BETWEEN '$table_time_start' AND '$table_time_end'
					GROUP BY station_id, h
					ORDER BY h, station_id";
	$res = BoDb::query($sql);
	while($row = $res->fetch_assoc())
	{
		if ($row['station_id'] == 0)
		{
			$count = $row['cnt'];
			continue;
		}
		
		$D[$row['station_id']]['strikesh'] += $row['strikesh_sum'] / $count;
		$D[$row['station_id']]['signalsh'] += $row['signalsh_sum'] / $count;
	}
	//$active_stations = count($D);
	

	// currently available stations
	$sql = "SELECT COUNT(*) cnt
			FROM ".BO_DB_PREF."stations
			WHERE 
				id < ".intval(BO_DELETED_STATION_MIN_ID)."
				AND status >= ".((int)STATUS_OFFLINE)."";
				
	$res = BoDb::query($sql);
	$row = $res->fetch_assoc();
	$available = $row['cnt'];


	if (!$own_station)
		$sinfo = bo_station_info($station_id);

	$stations = bo_stations();
	$countries = array();

	foreach($stations as $d)
	{
		$id = $d['id'];
		
		if ($d['status'] <= STATUS_IDLE*10 && $station_id != $id && !$D[$id]['strikesh'] && !$D[$id]['signalsh'])
			continue;

		if ($d['lat'] == 0.0 && $d['lon'] == 0.0)
			continue;
			
		if ($d['country'] && !isset($countries[$d['country']]))
			$countries[$d['country']] = _BL($d['country']);

		$D[$id]['country'] = $d['country'] ? _BL($d['country']) : '?';
		$D[$id]['city'] = $d['city'] ? $d['city'] : '?';
		$D[$id]['bo_id'] = $d['bo_station_id'];
		$D[$id]['firmware'] = $d['firmware'];
		list($D[$id]['pcb']) = explode(';', $d['controller_pcb']);
		
		if ($D[$id]['pcb'] == 'unknown')
			$D[$id]['pcb'] = '?';

		if (!$own_station)
			$D[$id]['distance'] = bo_latlon2dist($d['lat'], $d['lon'], $sinfo['lat'], $sinfo['lon']);
		else
			$D[$id]['distance'] = $d['distance'];

		if ($D[$id]['signalsh'])
		{
			$D[$id]['signalsh_ratio'] = $D[$id]['strikesh'] / $D[$id]['signalsh'];
			$D[$id]['signalsh_ratio'] = $D[$id]['signalsh_ratio'] > 1 ? 1 : $D[$id]['signalsh_ratio'];
			$whole_sig_ratio += $D[$id]['strikesh'] / $D[$id]['signalsh'];
			$whole_sig_ratio_cnt++;
		}
		else
		{
			$D[$id]['signalsh_ratio'] = null;
		}

		if ($strikes_range)
		{
			$D[$id]['strikesh_ratio'] = $D[$id]['strikesh'] / $strikes_range;
			$D[$id]['strikesh_ratio'] = $D[$id]['strikesh_ratio'] > 1 ? 1 : $D[$id]['strikesh_ratio'];
			$whole_strike_ratio += $D[$id]['strikesh'] / $strikes_range;
			$whole_strike_ratio_cnt++;
		}
		else
		{
			$D[$id]['strikesh_ratio'] = null;
		}

		//ToDo: Perhaps better algorithm
		if ($D[$id]['strikesh'] == 0 && $D[$id]['signalsh'] && $strikes_range)
			$D[$id]['efficiency'] = -$D[$id]['signalsh'] / $strikes_range;
		else
			$D[$id]['efficiency'] = sqrt($D[$id]['strikesh_ratio'] * $D[$id]['signalsh_ratio']);

		$whole_sig_count += $D[$id]['signalsh'];

		if (time() - strtotime($D['last_time'].' UTC') < 3600)
			$active_stations++;
		
		if (!$sort)
			$sort = BO_STATISTICS_STATIONS_SORT;

		switch($sort)
		{
			case 'strikes':
				$S[$id] = $D[$id]['strikesh'];
				break;

			case 'city':
				$S[$id] = $D[$id]['city'];
				break;

			case 'country':
				$S[$id] = $D[$id]['country'];
				break;

			default: $sort = 'distance';
			case 'distance':
				$S[$id] = $D[$id]['distance'];
				break;

			case 'bo_id':
				$S[$id] = $D[$id]['bo_id'];
				break;

			case 'signals':
				$S[$id] = $D[$id]['signalsh'];
				break;

			case 'signals_ratio':
				$S[$id] = $D[$id]['signalsh_ratio'];
				break;

			case 'efficiency':
				$S[$id] = $D[$id]['efficiency'];
				break;
				
			case 'firmware':
				$S[$id] = $D[$id]['firmware'];
				break;

			case 'pcb':
				$S[$id] = $D[$id]['pcb'];
				break;

		}

	}

	if ($whole_strike_ratio_cnt)
		$whole_strike_ratio /= $whole_strike_ratio_cnt;

	if ($whole_sig_ratio_cnt)
		$whole_sig_ratio /= $whole_sig_ratio_cnt;

	echo '<div id="bo_stat_network">';

	echo '<img src="'.bo_bofile_url().'?map=stations_mini&blank" id="bo_stat_network_stations_map">';

	if (count($ranges) > 1)
	{
		echo '<p class="bo_stat_description" id="bo_stat_network_descr_range">';
		echo _BL('bo_stat_network_descr');
		echo '</p>';

		echo '<p id="bo_stat_timeranges" class="bo_stat_description">';
		echo _BL('Period').': ';

		sort($ranges);
		foreach($ranges as $r)
		{

			echo '<a href="'.bo_insert_url('bo_hours', $r).'" class="';
			echo $r == $range ? 'bo_selected' : '';
			echo '">';
			echo $r.'h';
			echo '</a>&nbsp;';
		}

		echo '</p>';


	}
	else
	{
		echo '<p class="bo_stat_description" id="bo_stat_network_descr_lasth">';
		echo _BL('bo_stat_network_descr_lasth');
		echo '</p>';
	}

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Last update').': </span><span class="bo_value">'._BL('_before')._BN($last_update, 1).' '.($last_update == 1 && 0 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span></li>';
	echo '<li><span class="bo_descr">'._BL('Sum of Strikes').': </span><span class="bo_value">'._BN($strikes_range, 0).'</span></li>';
	echo '<li><span class="bo_descr">'._BL('Max participants per strike').': </span><span class="bo_value">'._BN($max_part, 0).'</span></li>';
	echo '<li><span class="bo_descr">'._BL('Mean participants per strike').': </span><span class="bo_value">'._BN($avg_part, 1).'</span></li>';
	
	if (BO_STATION_STAT_DISABLE !== true)
	{
		echo '<li><span class="bo_descr">'._BL('Mean locating ratio').': </span><span class="bo_value">';
		echo $whole_sig_ratio ? _BN($whole_sig_ratio * 100, 1).'%' : '-';
		echo '</span></li>';

		echo '<li><span class="bo_descr">'._BL('Mean strike ratio').': </span><span class="bo_value">';
		echo $whole_strike_ratio ? _BN($whole_strike_ratio * 100, 1).'%' : '-';
		echo '</span></li>';
	}
	
	echo '<li><span class="bo_descr">'._BL('Sum of Signals').': </span><span class="bo_value">'._BN($whole_sig_count, 0).'</span></li>';
	echo '<li><span class="bo_descr">'._BL('Active Stations').': </span><span class="bo_value">'._BN($active_stations, 0);
	
	if (BO_STATION_STAT_DISABLE !== true)
		echo ' ('._BN($stations_nogps, 0).' '._BL('w/o GPS-signal').')';
		
	echo '</span></li>';
	echo '<li><span class="bo_descr">'._BL('Available stations').': </span><span class="bo_value">'._BN($available, 0).'</span></li>';

	
	echo '</ul>';

	if (BO_STATION_STAT_DISABLE !== true)
	{
		echo '<a name="table_network"></a>';
		echo '<h4>'._BL('h4_table_network').'</h4>';

		echo '<p class="bo_stat_description" id="bo_stat_network_descr_table">';
		echo _BL('bo_stat_network_descr_table');
		echo '</p>';

		echo '<div id="bo_network_stations_container">';
		echo '<table id="bo_network_stations">';
		echo '<tr>
				<th rowspan="2">'._BL('Pos.').'</th>';

		if ((bo_user_get_level() & BO_PERM_SETTINGS))
		{
			echo '
				<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'bo_id').'#table_network" rel="nofollow">'._BL('Id').'</a></th>';
		}

		if (isset($_GET['firmware']))
		{
			echo '<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'firmware').'#table_network" rel="nofollow">'._BL('Ver.').'</a></th>';
		}
		
		echo '
				<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'pcb').'#table_network" rel="nofollow">'._BL('PCB').'</a></th>
				<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'country').'#table_network" rel="nofollow">'._BL('Country').'</a></th>
				<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'city').'#table_network" rel="nofollow">'._BL('City').'</a></th>
				<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'distance').'#table_network" rel="nofollow">'._BL('Distance').'</a></th>
				<th colspan="2">'._BL('Strikes').'/'.($range > 1 ? $range : '').'h</th>
				<th colspan="2">'._BL('Signals').'/'.($range > 1 ? $range : '').'h</th>
				<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'efficiency').'#table_network" rel="nofollow">'._BL('Efficiency').'</a></th>
				</tr>
				<tr>
					<th><a href="'.bo_insert_url('bo_sort', 'strikes').'#table_network" rel="nofollow">'._BL('Count').'</a></th>
					<th><a href="'.bo_insert_url('bo_sort', 'strikes').'#table_network" rel="nofollow">'._BL('Ratio').'</a></th>
					<th><a href="'.bo_insert_url('bo_sort', 'signals').'#table_network" rel="nofollow">'._BL('Count').'</a></th>
					<th><a href="'.bo_insert_url('bo_sort', 'signals_ratio').'#table_network" rel="nofollow">'._BL('Ratio').'</a></th>
				</tr>
				';


		// Stations table
		switch($sort)
		{
			case 'city': case 'country': case 'distance': case 'id':
				asort($S);
				break;
			default:
				arsort($S);
				break;
		}

		//disabled
		//$urls = unserialize(BoData::get('mybo_stations'));

		$pos = 1;
		foreach($S as $id => $d)
		{
			$d = $D[$id];

			if ($station_id == $id)
				echo '<tr class="bo_highlight">';
			else
				echo '<tr>';

			echo '<td class="bo_text">';

			if ( (bo_user_get_level() & BO_PERM_NOLIMIT) || (BO_STATISTICS_ALL_STATIONS == 2) )
				echo '<a href="'.bo_insert_url('bo_*').'&bo_show=station&bo_station_id='.$id.'" rel="nofollow">'.$pos.'</a>';
			else
				echo $pos;

			echo '</td>';

			if ((bo_user_get_level() & BO_PERM_SETTINGS))
			{
				echo '<td class="bo_text '.($sort == 'id' ? 'bo_marked' : '').'">';
				echo $d['bo_id'];
				echo '</td>';

			}

			if (isset($_GET['firmware']))
			{
				echo '<td class="bo_text '.($sort == 'firmware' ? 'bo_marked' : '').'">';
				echo $d['firmware'];
				echo '</td>';
			}

			echo '<td class="bo_text '.($sort == 'pcb' ? 'bo_marked' : '').'">';
			echo $d['pcb'];
			echo '</td>';
			
			echo '<td class="bo_text '.($sort == 'country' ? 'bo_marked' : '').'">';
			echo $d['country'];
			echo '</td>';

			echo '<td class="bo_text '.($sort == 'city' ? 'bo_marked' : '').'">';
			if (isset($urls[$id]))
				echo '<a href="'.$urls[$id].'" target="_blank">'._BC($d['city']).'</a>';
			else
				echo _BC($d['city']);
			echo '</td>';

			echo '<td class="bo_numbers '.($sort == 'distance' ? 'bo_marked' : '').'">';
			
			if ($own_station || $station_id > 0)
				echo _BK($d['distance'] / 1000, 0);
			else
				echo '-';
				
			echo '</td>';

			echo '<td class="bo_numbers '.($sort == 'strikes' ? 'bo_marked' : '').'">';
			echo round($d['strikesh']);
			echo '</td>';

			echo '<td class="bo_numbers '.($sort == 'strikes' ? 'bo_marked' : '').'">';
			echo _BN($d['strikesh_ratio'] * 100, 1).'%';
			echo '</td>';

			echo '<td class="bo_numbers '.($sort == 'signals' ? 'bo_marked' : '').'">';
			echo round($d['signalsh']);
			echo '</td>';

			echo '<td class="bo_numbers '.($sort == 'signals_ratio' ? 'bo_marked' : '').'">';
			echo _BN($d['signalsh_ratio'] * 100, 1).'%';
			echo '</td>';

			echo '<td class="bo_numbers '.($sort == 'efficiency' ? 'bo_marked' : '').'">';

			if ($d['efficiency'] <= -10)
				echo '< -'._BN(999, 0).'%';
			else
				echo _BN($d['efficiency'] * 100, 1).'%';

			echo '</td>';

			echo '</tr>';

			$pos++;
		}

		echo '</table>';

		echo '</div>';

	}

	/*** New Stations ***/

	if ($new = intval(BO_STATISTICS_SHOW_NEW_STATIONS))
	{
		$new_stations = array();

		$sql = "SELECT id, country, city, first_seen
				FROM ".BO_DB_PREF."stations
				WHERE status >= ".((int)STATUS_OFFLINE)." 
					AND id < ".intval(BO_DELETED_STATION_MIN_ID)."
					AND first_seen > (SELECT MIN(first_seen) FROM ".BO_DB_PREF."stations) 
				ORDER BY first_seen DESC
				LIMIT $new";
		$res = BoDb::query($sql);
		while ($row = $res->fetch_assoc())
		{
			$new_stations[$row['id']] = array(
				strtotime($row['first_seen'].' UTC'), 
				$row['city'], $row['country']
				);
		}

		if (count($new_stations))
		{
			echo '<a name="new_stations"></a>';
			echo '<h4>'._BL('h4_new_stations').'</h4>';

			echo '<ul class="bo_stat_overview" id="bo_new_stations">';

			$i = 0;
			foreach($new_stations as $id => $d)
			{
				if (!trim($d[1]))
					continue;
					
				$text = bo_str_max(_BC($d[1]));
				$text .= $d[2] ? ' ('.trim(_BL($d[2])).')' : '';
				
				echo '<li><span class="bo_descr">';

				if ( (bo_user_get_level() & BO_PERM_NOLIMIT) || (BO_STATISTICS_ALL_STATIONS == 2) )
				{
					echo '<a href="'.bo_insert_url('bo_*').'&bo_show=station&bo_station_id='.$id.'" rel="nofollow">';
					echo $text;
					echo '</a>';
				}
				else
					echo $text;

				echo '</span>';
				echo '<span class="bo_value">';
				echo _BD($d[0]).' '.date('H', $d[0]).' '._BL('oclock');
				echo '</span>';
				$i++;

				if ($i >= BO_STATISTICS_SHOW_NEW_STATIONS)
					break;
			}

			echo '</ul>';

		}

	}

	if (BO_STATION_STAT_DISABLE !== true)
	{
		/*** Active Stations ***/
		echo '<a name="graph_stations"></a>';
		echo '<h4>'._BL('h4_graph_stations').'</h4>';
		echo '<p class="bo_graph_description" id="bo_graph_stations">';
		echo strtr(_BL('bo_graph_stations'), array('{STATION_CITY}' => $city));
		echo '</p>';
		
		echo '<fieldset>';
		echo '<legend>'._BL('legend_stat_active_stations').'</legend>';
		echo '<span class="bo_form_group">';
		echo '<span class="bo_form_descr">'._BL('Country').': </span>';
		echo '<select name="bo_country" onchange="bo_change_value(this.value, \'stations\', \'bo_country\');" id="bo_stat_stations_country">';
		echo '<option value="">'._BL('All').'</option>';

		asort($countries);
		foreach($countries as $country => $name)
		{
			if ($country == '-' || !trim($country))
				continue;
				
			echo '<option value="'._BC($country).'">'.$name.'</option>';
		}
		echo '</select> ';
		echo '</fieldset>';
		
		bo_show_graph('stations', $add_graph, 1);

		/*** Signals ***/
		echo '<a name="graph_signals_all"></a>';
		echo '<h4>'._BL('h4_graph_signals_all').'</h4>';
		bo_show_graph('signals_all', $add_graph, 1);
	}
	
	echo '</div>';

}

//show longtime statistics
function bo_show_statistics_longtime($station_id = 0, $own_station = true, $add_graph = '')
{
	if ($station_id == -1)
	{
		$station_id = 0;
		$own_station = false;
	}

	$own_station_info = bo_station_info();
	$stInfo = bo_station_info($station_id);
	$city = _BC($stInfo['city']);

	if ($stInfo['country'] != $own_station_info['country'])
		$city .= ' ('._BL($stInfo['country']).')';

	$max_str_day_all	= unserialize(BoData::get('longtime_max_strikes_day_all'));
		
	if (!$own_station)
	{
		$add .= '#'.$station_id.'#';

		//not exact signal count for other stations
		$signals 			= BoData::get('count_raw_signals2'.$add);
	}
	else
	{
		//whole signal count
		$signals 			= BoData::get('count_raw_signals');

		//Daily longtime: Own
		$max_str_day_own	= unserialize(BoData::get('longtime_max_strikes_day_own'));
		$max_str_dayrad_own	= unserialize(BoData::get('longtime_max_strikes_day_own_rad'));

		//Daily longtime: All
		$max_str_dayrad_all	= unserialize(BoData::get('longtime_max_strikes_day_all_rad'));
	}

	//Own
	$str_own	 		  = BoData::get('count_strikes_own'.$add);
	$active_days 		  = BoData::get('longtime_station_active_time'.$add) / 3600 / 24;
	$inactive_days 		  = BoData::get('longtime_station_inactive_time'.$add) / 3600 / 24;
	$min_dist_own 		  = BoData::get('longtime_min_dist_own'.$add) / 1000;
	$max_dist_own 		  = BoData::get('longtime_max_dist_own'.$add) / 1000;
	$max_str_own 		  = (double)BoData::get('longtime_max_strikesh_own'.$add);
	$max_sig_own 		  = (double)BoData::get('longtime_max_signalsh_own'.$add);
	$first_update_station = BoData::get('longtime_station_first_time'.$add);
	$nogps_whole_time     = BoData::get('longtime_station_nogps_time'.$add);


	//Global
	$strikes	  		= BoData::get('count_strikes'.$add);
	$min_dist_all 		= BoData::get('longtime_min_dist_all'.$add) / 1000;
	$max_dist_all 		= BoData::get('longtime_max_dist_all'.$add) / 1000;
	$max_str_all 		= (double)BoData::get('longtime_max_strikesh');
	$max_sig_all 		= (double)BoData::get('longtime_max_signalsh');
	$max_active 		= (double)BoData::get('longtime_count_max_active_stations');
	$max_active_sig		= (double)BoData::get('longtime_count_max_active_stations_sig');
	$max_available		= (double)BoData::get('longtime_count_max_avail_stations');
	$max_part			= (double)BoData::get('longtime_max_participants');

	//MyBO
	$first_update		= BoData::get('first_update_time');
	$download_count     = BoData::get('upcount_strikes');
	$download_stat      = unserialize(BoData::get('download_statistics'));

	$kb_per_day = array();
	$kb_today = 0;
	$kb_traffic = 0;
	foreach($download_stat as $type => $d)
	{
		$kb_traffic += $d['traffic'] / 1024;
		$kb_today += $d['traffic_today']  / 1024;
		if ($d['time_first'])
		{
			$kb_per_day[$type] = $d['traffic']  / 1024 / (time() - $d['time_first']) * 3600 * 24;
		}
	}
	

	//set date to 0 if count is zero
	//compatibility for older entries
	if (!$max_str_day_all[0])
		$max_str_day_all[1] = 0;
	else if (strpos($max_str_day_all[1], '-'))
		$max_str_day_all[1] = strtotime($max_str_day_all[1]);

	if (!$max_str_dayrad_all[0])
		$max_str_dayrad_all[1] = 0;
	else if (strpos($max_str_dayrad_all[1], '-'))
		$max_str_dayrad_all[1] = strtotime($max_str_dayrad_all[1]);

	if (!$max_str_day_own[0])
		$max_str_day_own[1] = 0;
	else if (strpos($max_str_day_own[1], '-'))
		$max_str_day_own[1] = strtotime($max_str_day_own[1]);

	if (!$max_str_dayrad_own[0])
		$max_str_dayrad_own[1] = 0;
	else if (strpos($max_str_dayrad_own[1], '-'))
		$max_str_dayrad_own[1] = strtotime($max_str_dayrad_own[1]);


	if (intval($strikes))
		$strike_ratio = _BN($str_own / $strikes * 100, 1).'%';
	else
		$strike_ratio = '-';

	if (intval($signals))
		$signal_ratio = _BN($str_own / $signals * 100, 1).'%';
	else
		$signal_ratio = '-';

	echo '<div id="bo_stat_longtime">';

	if ($station_id)
	{
		echo '<p class="bo_stat_description" id="bo_stat_longtime_descr">';
		echo strtr(_BL('bo_stat_longtime_descr'), array('{STATION_CITY}' => $city));
		echo '</p>';

		echo '<a name="longtime_station"></a>';
		echo '<h4>'.strtr(_BL('h4_stat_longtime_station'), array('{STATION_CITY}' => $city)).'</h4>';

		echo '<ul class="bo_stat_overview">';

		if (!$own_station && $first_update_station)
			echo '<li><span class="bo_descr">'._BL('Record longtime data since').': </span><span class="bo_value">'._BDT($first_update_station).'</span>';

		echo '<li><span class="bo_descr">'._BL('Strikes detected').': </span><span class="bo_value">'._BN($str_own, 0).'</span>';
		echo '<li><span class="bo_descr">'._BL('Active').': </span><span class="bo_value">'._BN($active_days, 1).' '._BL('days').'</span>';
		echo '<li><span class="bo_descr">'._BL('Inactive').': </span><span class="bo_value">';
		echo _BN($inactive_days, 1).' '._BL('days');
		echo $nogps_whole_time > 360 ? ' ('._BN($nogps_whole_time / 3600, 1).' '._BL('hours').' '._BL('without GPS').')' : '';
		echo '</span>';
		echo '<li><span class="bo_descr">'._BL('Max strikes per hour').': </span><span class="bo_value">'._BN($max_str_own, 0).'</span>';

		if ($own_station)
		{
			echo '<li><span class="bo_descr">'._BL('Max strikes per day').': </span><span class="bo_value">'._BN($max_str_day_own[0], 0).($max_str_day_own[1] > 0 ? ' ('._BD($max_str_day_own[1]).')' : '' ).'</span>';
			echo '<li><span class="bo_descr">'._BL('Max strikes per day').' (< '._BK(BO_RADIUS_STAT).') : </span><span class="bo_value">'._BN($max_str_dayrad_own[0], 0).($max_str_dayrad_own[1] ? ' ('._BD($max_str_dayrad_own[1]).')' : '').'</span>';
		}

		echo '<li><span class="bo_descr">'._BL('Min dist').': </span><span class="bo_value">'._BK($min_dist_own, 1).'</span>';
		echo '<li><span class="bo_descr">'._BL('Max dist').': </span><span class="bo_value">'._BK($max_dist_own, 1).'</span>';
		echo '<li><span class="bo_descr">'._BL('Signals detected').': </span><span class="bo_value">'._BN($signals, 0).'</span>';
		echo '<li><span class="bo_descr">'._BL('Strike ratio').': </span><span class="bo_value">'.$strike_ratio.'</span>';
		echo '<li><span class="bo_descr">'._BL('Signal ratio').': </span><span class="bo_value">'.$signal_ratio.'</span>';
		echo '<li><span class="bo_descr">'._BL('Max signals per hour').': </span><span class="bo_value">'._BN($max_sig_own, 0).'</span>';
		echo '</ul>';
	}
	
	echo '<a name="longtime_network"></a>';
	echo '<h4>'._BL('h4_stat_longtime_network').'</h4>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Max strikes per hour').': </span><span class="bo_value">'._BN($max_str_all, 0).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max strikes per day').': </span><span class="bo_value">'._BN($max_str_day_all[0], 0).($max_str_day_all[1] ? ' ('._BD($max_str_day_all[1]).')' : '').'</span>';
	
	if ($own_station)
	{
		echo '<li><span class="bo_descr">'._BL('Max strikes per day').' (< '._BK(BO_RADIUS_STAT).') : </span><span class="bo_value">'._BN($max_str_dayrad_all[0], 0).($max_str_dayrad_all[1] ? ' ('._BD($max_str_dayrad_all[1]).')' : '').'</span>';
	}

	echo '<li><span class="bo_descr">'._BL('Min dist').': </span><span class="bo_value">'._BK($min_dist_all, 1).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max dist').': </span><span class="bo_value">'._BK($max_dist_all, 1).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max signals per hour').': </span><span class="bo_value">'._BN($max_sig_all, 0).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max participants per strike').': </span><span class="bo_value">'._BN($max_part, 0).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max active stations').': </span><span class="bo_value">'._BN($max_active_sig, 0).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max available stations').': </span><span class="bo_value">'._BN($max_available, 0).'</span>';
	echo '</ul>';

	echo '<a name="longtime_network"></a>';
	echo '<h4>'._BL('MyBlitzortung_notags').'</h4>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('First data').': </span><span class="bo_value">'._BDT($first_update).'</span>';
	echo '<li><span class="bo_descr">'._BL('Lightning data imports').': </span><span class="bo_value">'._BN($download_count, 0).'</span>';
	echo '<li><span class="bo_descr">'._BL('Traffic to Blitzortung.org').': </span><span class="bo_value">'._BN(array_sum($kb_per_day), 0).' kB/'._BL('day').'</span>';

	//print detailed stat
	if (bo_user_get_level() & BO_PERM_NOLIMIT)
	{
		$traffic_show = array('strikes' => 'Strikes', 'stations' => 'Stations', 'archive' => 'Signals');

		foreach($traffic_show as $type => $name)
		{
			$kb_per_day_single = $kb_per_day[$type];

			if (!$kb_per_day_single)
				continue;
				
			unset($kb_per_day[$type]);

			echo '<li><span class="bo_descr">'._BL('Traffic').' - '._BL($name).': </span><span class="bo_value">'._BN($kb_per_day_single, 0).' kB/'._BL('day').'</span>';
		}

		//echo '<li><span class="bo_descr">'._BL('Traffic').' - '._BL('Other').': </span><span class="bo_value">'._BN(array_sum($kb_per_day), 0).' kB/'._BL('day').'</span>';
		echo '<li><span class="bo_descr">'._BL('Traffic').' - '._BL('Total').': </span><span class="bo_value">'._BN($kb_traffic, 0).' kB</span>';

	}

	echo '</ul>';

	
	if ($station_id)
	{
		echo '<a name="graph_ratio_distance"></a>';
		echo '<h4>'._BL('h4_graph_ratio_distance_longtime').'</h4>';
		echo '<p class="bo_graph_description" id="bo_graph_descr_radi_longtime">';
		echo _BL('bo_graph_descr_radi_longtime');
		echo '</p>';
		bo_show_graph('ratio_distance_longtime', $add_graph);

		echo '<a name="graph_ratio_bearing"></a>';
		echo '<h4>'._BL('h4_graph_ratio_bearing_longtime').'</h4>';
		echo '<p class="bo_graph_description" id="bo_graph_descr_bear_longtime">';
		echo _BL('bo_graph_descr_bear_longtime');
		echo '</p>';
	

	if (BO_GRAPH_STAT_RATIO_BEAR_WINDROSE === true)
		bo_show_graph('ratio_bearing_longtime', $add_graph, false, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE);
	else
		bo_show_graph('ratio_bearing_longtime', $add_graph);

	}

	echo '</div>';

}


//show own other statistics
function bo_show_statistics_other($station_id = 0, $own_station = true, $add_graph = '')
{
	
	$D = @unserialize(BoData::get('db_table_status'));
	
	$last_str = BoData::get('uptime_strikes');
	$last_net = BoData::get('uptime_stations');
	$last_sig = BoData::get('uptime_raw');

	$mem_all = (array_sum($D['data']) + array_sum($D['keys'])) / 1024 / 1024;
	$mem_keys = array_sum($D['keys']) / (array_sum($D['data']) + array_sum($D['keys'])) * 100;
	$entries_all = array_sum($D['rows']);

	$download_stat      = unserialize(BoData::get('download_statistics'));
	$kb_today = 0;
	foreach($download_stat as $type => $d)
		$kb_today += $d['traffic_today']  / 1024;

	echo '<h4>'._BL('h4_stat_other_updates').'</h4>';
	echo '<p class="bo_stat_description" id="bo_stat_other_descr_updates">';
	echo _BL('bo_stat_other_updates_descr');
	echo '</p>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'
				._BL('Last update strikes').': </span><span class="bo_value">'
					._BDT($last_str)
					.' ('._BL('update every').' '.intval(BO_UP_INTVL_STRIKES).' '._BL('unit_minutes').')'
				.'</span>';
	echo '<li><span class="bo_descr">'
				._BL('Last update stations').': </span><span class="bo_value">'
					._BDT($last_net)
					.' ('._BL('update every').' '.intval(BO_UP_INTVL_STATIONS).' '._BL('unit_minutes').')'
				.'</span>';

	if (BO_UP_INTVL_RAW > 0)
	{
		echo '<li><span class="bo_descr">'
					._BL('Last update signals').': </span><span class="bo_value">'
						._BDT($last_sig)
						.' ('._BL('update every').' '.intval(BO_UP_INTVL_RAW).' '._BL('unit_minutes').')'
						.'</span>';
	}

	echo '<li><span class="bo_descr">'._BL('Traffic to Blitzortung.org').': </span><span class="bo_value">'._BN($kb_today, 0).' kB ('._BL('Today').')</span>';

	echo '</ul>';


	echo '<h4>'._BL('h4_stat_other_database').'</h4>';
	echo '<p class="bo_stat_description" id="bo_stat_other_descr_database">';
	echo _BL('bo_stat_other_database_descr');
	echo '</p>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Strikes').': </span><span class="bo_value">'._BN($D['rows']['strikes'], 0).'</span>';
	
	if ($D['rows']['raw'])
		echo '<li><span class="bo_descr">'._BL('Signals').': </span><span class="bo_value">'._BN($D['rows']['raw'], 0).'</span>';
		
	echo '<li><span class="bo_descr">'._BL('Entries (all data)').': </span><span class="bo_value">'._BN($entries_all, 0).'</span>';
	echo '<li>
			<span class="bo_descr">'._BL('Memory usage').':
			</span><span class="bo_value">'._BN($mem_all, 1).'MB
					('._BN($mem_keys, 1).'% '._BL('for keys').')
			</span>';


	if (bo_user_get_level() & BO_PERM_NOLIMIT)
	{
		foreach($D['rows'] as $type => $rows)
		{
			if (!$D['rows'][$type])
				continue;
				
			echo '<li><span class="bo_descr">'._BL('Usage').' "'.$type.'": </span><span class="bo_value">';
			echo _BN($D['rows'][$type], 0).' rows / ';
			echo _BN( ($D['data'][$type]+$D['keys'][$type])  / 1024 / 1024, 1).' MB / ';
			echo _BN( $D['keys'][$type]/($D['data'][$type]+$D['keys'][$type])*100, 1).'% '._BL('for keys');
			echo '</span>';

		}
	}

	echo '</ul>';

	

}

//show own other statistics
function bo_show_statistics_advanced($station_id = 0, $own_station = true, $add_graph = '')
{
	global $_BO;
	require_once 'functions_html.inc.php';
	
	$show_options = array('strikes');

	$show = $_GET['bo_show2'];
	$region = $_GET['bo_region'];
	$channel = intval($_GET['bo_channel']);

	//Regions
	$add_graph .= '&region='.$region;

	if (!$own_station)
	{
		$show = '';
	}
	else
	{
		
		if (BO_UP_INTVL_RAW > 0)
		{
			$show_options[] = 'strike_ratios';
			$show_options[] = 'signals';
		}
		
		if ($channel)
			$add_graph .= '&channel='.$channel;
	}

	$channels = BO_ANTENNAS;
	$bpv      = BoData::get('raw_bitspervalue');
	$values   = BoData::get('raw_values');
	$utime    = BoData::get('raw_ntime') / 1000;
	$last_update = BoData::get('uptime_raw');


	echo '<div id="bo_stat_advanced">';

	echo '<p class="bo_stat_description" id="bo_stat_advanced_info">';
	echo _BL('bo_stat_advanced_info');
	echo '</p>';

	echo '<form action="?" method="GET" class="bo_stat_advanced_form">';

	echo bo_insert_html_hidden(array('bo_show2', 'bo_region', 'bo_channel'));
	echo '<fieldset>';
	echo '<legend>'._BL('legend_stat_advanced_options').'</legend>';

	echo '<span class="bo_form_group">';
	
	if (count($show_options) > 1)
	{
		echo '<span class="bo_form_descr">'._BL('Show').': </span>';
		echo '<select name="bo_show2" onchange="submit();" id="bo_stat_advanced_select_show" '.($own_station ? '' : 'disabled').'>';
		foreach($show_options as $opt_name)
		{
			echo '<option value="'.$opt_name.'" '.($show == $opt_name ? 'selected' : '').'>'._BL('stat_advanced_show_'.$opt_name).'</option>';
		}
		echo '</select> ';
		echo '</span>&nbsp;';
	}
	
	$region_select = bo_get_select_region($region, $station_id);
	
	if ($region_select)
	{
		echo '<span class="bo_form_group">';
		echo '<span class="bo_form_descr">'._BL('Region').': </span>';
		echo $region_select;
		echo '</span>&nbsp;';
	}
	
	if ($own_station && $channels > 1)
	{
		echo '<span class="bo_form_group">';
		echo ' <span class="bo_form_descr">'._BL('Channel').': </span>';
		echo '<select name="bo_channel" onchange="submit();" id="bo_stat_advanced_select_channel">';
		echo '<option value="0" '.($channel == 0 ? 'selected' : '').'>'._BL('All').$y.'</option>';
		for($i=1;$i<=2;$i++)
			echo '<option value="'.$i.'" '.($i == $channel ? 'selected' : '').'>'._BL('Channel').' '.$i.'</option>';
		echo '</select>';
		echo '</span>&nbsp;';
	}


	echo '</fieldset>';
	echo '</form>';

	switch($show)
	{
		default: //Strikes

			//Residual
			/*
			if ($own_station && BO_UP_INTVL_RAW > 0)
			{
				echo '<h4>'._BL('h4_graph_residual_time').'</h4>';
				echo '<p class="bo_graph_description" id="bo_graph_descr_strikes_station_residual_time">';
				echo _BL('bo_graph_descr_strikes_station_residual_time');
				echo '</p>';
				bo_show_graph('strikes_station_residual_time', $add_graph, true);
			}
			*/

		
			/*** PARTICIPANTS ***/

			echo '<a name="graph_participants"></a>';
			echo '<h4>'._BL('h4_graph_participants').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_participants">';
			echo strtr(_BL('bo_graph_participants'), array('{MIN_PARTICIPANTS}' => bo_participants_locating_min()));
			echo '</p>';
			if (BO_GRAPH_STAT_PARTICIPANTS_LOG === true)
				echo '<p class="bo_graph_description bo_graph_log_warn" ><strong>'._BL('bo_graph_log_warn').'</strong></p>';

			bo_show_graph('participants', $add_graph, true);

			echo '<h4>'._BL('h4_graph_participants_time').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_participants_time">';
			echo _BL('bo_graph_participants_time');
			echo '</p>';

			echo '<a name="graph_participants_time"></a>';

			echo '<fieldset>';
			echo '<legend>'._BL('legend_stat_participants_time').'</legend>';
			echo '<input type="radio" name="bo_participants_type" id="bo_participants_radio_avg" value="1" checked onclick="bo_change_radio(this.value, \'participants\');">';
			echo '<label for="bo_participants_radio_avg">'._BL('Average').'</label>';
			echo ' <input type="radio" name="bo_participants_type" id="bo_participants_radio_val" value="2" onclick="bo_change_radio(this.value, \'participants\');">';
			echo '<label for="bo_participants_radio_val">'._BL('Values').'</label> &nbsp; &bull; &nbsp; ';
			echo '<span class="bo_form_group">';
			echo '<span class="bo_form_descr">'._BL('Min').':</span> ';
			echo '<select name="bo_participants_min" onchange="bo_change_value(this.value, \'participants_time\', \'value\');" id="bo_stat_participants_time_min" disabled>';
			//echo '<option value="0">'._BL('Average').'</option>';
			for($i=bo_participants_locating_min();$i<150;$i+=$i<20?1:10)
				echo '<option value="'.$i.'">'.$i.'</option>';
			echo '</select> ';
			echo '</span>';
			echo '<span class="bo_form_group">';
			echo '<span class="bo_form_descr">'._BL('Max').':</span>';
			echo '<select name="bo_participants_max" onchange="bo_change_value(this.value, \'participants_time\', \'value_max\');" id="bo_stat_participants_time_max" disabled>';
			for($i=bo_participants_locating_min();$i<150;$i+=$i<20?1:10)
				echo '<option value="'.$i.'">'.$i.'</option>';
			echo '</select> ';
			echo '</span>';
			echo '</fieldset>';

			bo_show_graph('participants_time', $add_graph.'&average', true);

			/*** DEVIATIONS ***/

			echo '<a name="graph_deviations"></a>';
			echo '<h4>'._BL('h4_graph_deviations').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_deviations">';
			echo _BL('bo_graph_deviations');
			echo '</p>';
			if (BO_GRAPH_STAT_DEVIATIONS_LOG === true)
				echo '<p class="bo_graph_description bo_graph_log_warn" ><strong>'._BL('bo_graph_log_warn').'</strong></p>';

			bo_show_graph('deviations', $add_graph, true);


			echo '<a name="graph_deviations_time"></a>';
			echo '<h4>'._BL('h4_graph_deviations_time').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_deviations_time">';
			echo _BL('bo_graph_deviations_time');
			echo '</p>';

			echo '<fieldset>';
			echo '<legend>'._BL('legend_stat_deviations_time').'</legend>';

			echo '<input type="radio" name="bo_deviations_type" id="bo_deviations_radio_avg" value="1" checked onclick="bo_change_radio(this.value, \'deviations\');">';
			echo '<label for="bo_deviations_radio_avg">'._BL('Average').'</label>';
			echo ' <input type="radio" name="bo_deviations_type" id="bo_deviations_radio_val" value="2" onclick="bo_change_radio(this.value, \'deviations\');">';
			echo '<label for="bo_deviations_radio_val">'._BL('Values').'</label> &nbsp; &bull; &nbsp; ';

			echo '<span class="bo_form_group">';
			echo '<span class="bo_form_descr">'._BL('Min').':</span>';
			echo '<select name="bo_region" onchange="bo_change_value(this.value, \'deviations_time\', \'value\');" id="bo_stat_deviations_time_min" disabled>';
			for($i=0;$i<20000;$i+=100)
				echo '<option value="'.$i.'">'._BN($i / 1000, 1).'</option>';
			echo '</select> ';
			echo '</span>';
			echo '<span class="bo_form_group">';
			echo '<span class="bo_form_descr">'._BL('Max').':</span>';
			echo '<select onchange="bo_change_value(this.value, \'deviations_time\', \'value_max\');" id="bo_stat_deviations_time_max" disabled>';
			for($i=0;$i<20000;$i+=100)
				echo '<option value="'.$i.'" '.($i == 1000 ? 'selected' : '').'>'._BN($i / 1000, 1).'</option>';
			echo '</select> ';
			echo '</span>';
			echo '</fieldset>';

			bo_show_graph('deviations_time', $add_graph.'&average', true);


			/*** DISTANCE ***/
			if ($own_station)
			{
				echo '<a name="graph_distance"></a>';
				echo '<h4>'._BL('h4_graph_distance').'</h4>';
				echo '<p class="bo_graph_description" id="bo_graph_distance">';
				echo _BL('bo_graph_distance');
				echo '</p>';
				bo_show_graph('distance', $add_graph, true);
			}

			break;


		case 'strike_ratios':

			/*** RATIO DISTANCE ***/
			echo '<a name="graph_ratio_distance"></a>';
			echo '<h4>'._BL('h4_graph_ratio_distance').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_descr_radi">';
			echo _BL('bo_graph_descr_radi_adv');
			echo '</p>';
			bo_show_graph('ratio_distance', $add_graph, true);

			/*** RATIO BEARING ***/
			echo '<a name="graph_ratio_bearing"></a>';
			echo '<h4>'._BL('h4_graph_ratio_bearing').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_descr_bear">';
			echo _BL('bo_graph_descr_bear_adv');
			echo '</p>';

			if (BO_GRAPH_STAT_RATIO_BEAR_WINDROSE === true)
				bo_show_graph('ratio_bearing', $add_graph, true, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE);
			else
				bo_show_graph('ratio_bearing', $add_graph, true);

			/*** EVALUATED RATIO ***/
			if (BO_UP_INTVL_RAW)
			{
				echo '<a name="graph_evaluated_signals"></a>';
				echo '<h4>'._BL('h4_graph_evaluated_signals').' ('._BL('experimental').')</h4>';
				echo '<p class="bo_graph_description" id="bo_graph_evaluated_signals">';
				echo _BL('bo_graph_evaluated_signals');
				echo '</p>';
				bo_show_graph('evaluated_signals', $add_graph, true);
			}

			break;

		case 'signals':

			echo '<h4>'._BL('h4_stat_signals').'</h4>';
			bo_signal_info_list();

			if ($bpv == 8 && $values > 10)
			{
				/*** SPECTRUM ***/
				echo '<a name="graph_spectrum"></a>';
				echo '<h4>'._BL('h4_graph_spectrum').'</h4>';
				echo '<p class="bo_graph_description" id="bo_graph_spectrum">';
				echo _BL('bo_graph_spectrum');
				echo '</p>';
				echo '<fieldset>';
				echo '<legend>'._BL('legend_stat_spectrum').'</legend>';
				bo_show_select_strike_connected('spectrum');
				echo '<span class="bo_form_group">';
				echo '<span class="bo_form_descr">'._BL('Amplitude').':</span> ';
				echo '<select onchange="bo_change_value(this.value, \'spectrum\', \'type2\');" id="bo_stat_specttrum_amps">';
				echo '<option value="amp">'._BL('amp_first_signal').'</option>';
				echo '<option value="amp_max">'._BL('amp_max_signal').'</option>';
				echo '<option value="">'._BL('amp_spec').'</option>';
				echo '</select> ';
				echo '</fieldset>';
				bo_show_graph('spectrum', $add_graph.'&type2=amp', true);

				/*** FREQUENCIES BY TIME ***/
				echo '<a name="graph_frequencies_time"></a>';
				echo '<h4>'._BL('h4_graph_frequencies_time').'</h4>';
				echo '<p class="bo_graph_description" id="bo_graph_frequencies_time">';
				echo _BL('bo_graph_frequencies_time');
				echo '</p>';
				echo '<fieldset>';
				echo '<legend>'._BL('legend_stat_frequencies_time').'</legend>';
				bo_show_select_strike_connected('frequencies_time');
				echo '<div id="bo_frequencies_time_value_div" class="bo_stat_minmax_values_div">';
				echo '<input type="radio" name="bo_frequencies_type" id="bo_frequencies_radio_avg" value="1" checked onclick="bo_change_radio(this.value, \'frequencies\');">';
				echo '<label for="bo_frequencies_radio_avg">'._BL('Average').'</label>';
				echo ' <input type="radio" name="bo_frequencies_type" id="bo_frequencies_radio_val" value="2" onclick="bo_change_radio(this.value, \'frequencies\');">';
				echo '<label for="bo_frequencies_radio_val">'._BL('Values').'</label> &nbsp; &bull; &nbsp; ';
				echo '<span class="bo_form_group">';
				echo '<span class="bo_form_descr">'._BL('Min').':</span> ';
				echo '<select name="bo_participants_min" onchange="bo_change_value(this.value, \'frequencies_time\', \'value\');" id="bo_stat_frequencies_time_min" disabled>';
				for($i=0;$i<=BO_GRAPH_RAW_SPEC_MAX_X;$i+=10)
					echo '<option value="'.$i.'">'.$i.'kHz</option>';
				echo '</select> ';
				echo '</span>';
				echo '<span class="bo_form_group">';
				echo '<span class="bo_form_descr">'._BL('Max').':</span>';
				echo '<select name="bo_participants_max" onchange="bo_change_value(this.value, \'frequencies_time\', \'value_max\');" id="bo_stat_frequencies_time_max" disabled>';
				for($i=0;$i<=BO_GRAPH_RAW_SPEC_MAX_X;$i+=10)
					echo '<option value="'.$i.'" '.($i == 30 ? 'selected' : '').'>'.$i.'kHz</option>';
				echo '</select> ';
				echo '</span>';
				echo '</div>';
				echo '</fieldset>';
				bo_show_graph('frequencies_time', $add_graph.'&average&value_max=30', true);

				/*** AMPLITUDES ***/
				echo '<a name="graph_spectrum"></a>';
				echo '<h4>'._BL('h4_graph_amplitudes').'</h4>';
				echo '<p class="bo_graph_description" id="bo_graph_amplitudes">';
				echo _BL('bo_graph_amplitudes');
				echo '</p>';
				echo '<fieldset>';
				echo '<legend>'._BL('legend_stat_amplitudes').'</legend>';
				bo_show_select_strike_connected('amplitudes');
				echo '<span class="bo_form_group">';
				echo '<span class="bo_form_descr">'._BL('Amplitude').':</span> ';
				echo '<select onchange="bo_change_value(this.value, \'amplitudes\', \'graph_statistics\');" id="bo_stat_amplitudes_max">';
				echo '<option value="amplitudes">'._BL('amp_first').'</option>';
				echo '<option value="amplitudes_max">'._BL('Max').'</option>';
				echo '</select> ';
				echo '</fieldset>';
				bo_show_graph('amplitudes', $add_graph, true);

				/*** AMPLITUDES BY TIME ***/
				echo '<a name="graph_spectrum"></a>';
				echo '<h4>'._BL('h4_graph_amplitudes_time').'</h4>';
				echo '<p class="bo_graph_description" id="bo_graph_amplitudes_time">';
				echo _BL('bo_graph_amplitudes_time');
				echo '</p>';
				echo '<fieldset>';
				echo '<legend>'._BL('legend_stat_amplitudes_time').'</legend>';
				bo_show_select_strike_connected('amplitudes_time');
				echo '<span class="bo_form_group">';
				echo '<span class="bo_form_descr">'._BL('Amplitude').':</span> ';
				echo '<select onchange="bo_change_value(this.value, \'amplitudes_time\', \'graph_statistics\');" id="bo_stat_amplitudes_max">';
				echo '<option value="amplitudes_time">'._BL('amp_first').'</option>';
				echo '<option value="amplitudes_max_time">'._BL('Max').'</option>';
				echo '</select> ';
				echo '</span>';
				echo '<div id="bo_amplitudes_time_value_div" class="bo_stat_minmax_values_div">';
				echo '<input type="radio" name="bo_amplitudes_type" id="bo_amplitudes_radio_avg" value="1" checked onclick="bo_change_radio(this.value, \'amplitudes\');">';
				echo '<label for="bo_amplitudes_radio_avg">'._BL('Average').'</label>';
				echo ' <input type="radio" name="bo_amplitudes_type" id="bo_amplitudes_radio_val" value="2" onclick="bo_change_radio(this.value, \'amplitudes\');">';
				echo '<label for="bo_amplitudes_radio_val">'._BL('Values').'</label> &nbsp; &bull; &nbsp; ';
				echo '<span class="bo_form_group">';
				echo '<span class="bo_form_descr">'._BL('Min').':</span> ';
				echo '<select name="bo_participants_min" onchange="bo_change_value(this.value, \'amplitudes_time\', \'value\');" id="bo_stat_amplitudes_time_min" disabled>';
				for($i=0;$i<=26;$i++)
					echo '<option value="'.round(($i/26)*BO_MAX_VOLTAGE*10).'">'._BN(($i/26)*BO_MAX_VOLTAGE, 1).'V</option>';
				echo '</select> ';
				echo '</span>';
				echo '<span class="bo_form_group">';
				echo '<span class="bo_form_descr">'._BL('Max').':</span>';
				echo '<select name="bo_participants_max" onchange="bo_change_value(this.value, \'amplitudes_time\', \'value_max\');" id="bo_stat_amplitudes_time_max" disabled>';
				for($i=0;$i<=26;$i++)
					echo '<option value="'.round(($i/26)*BO_MAX_VOLTAGE*10).'" '.($i == 10 ? 'selected' : '').'>'._BN(($i/26)*BO_MAX_VOLTAGE, 1).'V</option>';
				echo '</select> ';
				echo '</span>';
				echo '</div>';
				echo '</fieldset>';
				bo_show_graph('amplitudes_time', $add_graph.'&average&value_max=10', true);
			}

			break;

		break;

	}

	echo '</div>';

}

function bo_show_graph($type, $add_graph='', $hour_select = false, $width=BO_GRAPH_STAT_W, $height=BO_GRAPH_STAT_H)
{
	$hours = intval($_GET['bo_hours_graph']);
	$options = array();
	
	if (!(bo_user_get_level() & BO_PERM_NOLIMIT))
		$hour_select = false;
	
	if ($hour_select !== false)
	{
		if ($hour_select === true)
		{
			$options = explode(',',BO_GRAPH_STAT_HOURS_BACK);
		}
		elseif ($hour_select == 1)
		{
			$options_tmp = explode(',',BO_GRAPH_STAT_DAYS_BACK);
			foreach ($options_tmp as $day)
				$options[] = $day * 24;
		}
		
		if ($hours <= 0)
			$hours = $options[0];
			
		if ($hours > 0 && $hours != $options[0])
			$add_graph .= '&bo_hours='.$hours;
	}
	
	$alt = _BL('graph_stat_title_'.$type);

	if ($alt == 'graph_stat_title_'.$type)
		$alt = '';
	else
		$alt = ': '.$alt;

	$alt = _BL('h3_graphs').$alt;

	echo '<div 
			class="bo_graph_img_container"
			id="bo_graph_'.$type.'_img_container"
			style="width:'.$width.'px;height:'.$height.'px;"
			onmouseover="document.getElementById(\'bo_graph_img_form_'.$type.'\').style.display=\'block\';"
			onmouseout="document.getElementById(\'bo_graph_img_form_'.$type.'\').style.display=\'none\';"
			>';
	
	echo '<img src="'.bo_bofile_url().'?graph_statistics='.$type.'&'.BO_LANG_ARGUMENT.'='._BL().$add_graph.'"
			class="bo_graph_img"
			style="width:'.$width.'px;height:'.$height.'px;background-image:url(\''.bo_bofile_url().'?image=wait\');"
			id="bo_graph_'.$type.'_img"
			alt="'.htmlspecialchars($alt).'"
			>';
	
	if ($hour_select)
	{
		sort($options);
		echo '<a name="bo_graph_form_'.$type.'"></a>';
		echo '<form class="bo_graph_img_form" id="bo_graph_img_form_'.$type.'" action="?#bo_graph_form_'.$type.'">';
		echo '<select name="bo_hours_graph" onchange="submit();">';
		foreach ($options as $hour)
		{
			echo '<option value="'.$hour.'" '.($hour == $hours ? 'selected' : '').'>';
			echo $hour >= 48 ? round($hour / 24).' '._BL('days') : $hour.' '._BL('hours');
			echo '</option>';
		}
		echo '</select>';
		echo bo_insert_html_hidden(array('bo_hours_graph'));
		echo '</form>';
	}
	
	echo '</div>';
}


function bo_show_select_strike_connected($id)
{
	echo '<span class="bo_form_group">';
	echo '<span class="bo_form_descr">'._BL('With strikes connected').':</span> ';
	echo '<select name="bo_region" onchange="bo_change_value(this.value, \''.$id.'\', \'participated\');" id="bo_stat_amplitudes_strikes">';
	echo '<option value="0">'._BL('dontcare').'</option>';
	echo '<option value="1">'._BL('participated_assigned').'</option>';
	echo '<option value="2">'._BL('_participated').'</option>';
	echo '<option value="-1">'._BL('not_participated').'</option>';
	echo '</select> ';
	echo '</span>';
}



function bo_get_antenna_data()
{
	if (BO_ANTENNAS == 2)
	{
		$ant1 = BoData::get('antenna1_bearing');
		$ant2 = BoData::get('antenna2_bearing');

		if ($ant1 !== '' && $ant1 !== null && $ant2 !== '' && $ant2 !== null)
		{
			$ant1 = round($ant1);
			$ant2 = round($ant2);
			return array(1=>$ant1, 2=>$ant2);
			
		}
		else
			return false;
	}
	
	return false;
}

?>