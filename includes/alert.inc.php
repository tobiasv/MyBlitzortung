<?php



function bo_alert_settings()
{
	$level = bo_user_get_level();
	$show_all = ($level & BO_PERM_ADMIN) && $_GET['bo_all'];
	
	if (substr($_GET['bo_action2'],0,10) == 'alert_form')
	{
		if (!bo_alert_settings_form())
			return;
	}
	else if ($_GET['bo_action2'] == 'alert_log')
	{
		if (!bo_alert_log($show_all))
			return;
	}
	
	if ($show_all)
		$like = 'alert\_%';
	else
		$like = 'alert\_'.bo_user_get_id().'\_%';
	
	$Alerts = array();
	
	while ($row = BoData::get_all($like))
	{
		$data = unserialize($row['data']);
		
		if (preg_match('/alert_([0-9]+)_([0-9]+)/', $row['name'], $r) && is_array($data) && !empty($data))
		{
			$user_id = $r[1];
			$alert_cnt = $r[2];
			$Alerts[$user_id][$alert_cnt] = $data;
		}
	
	}
	
	echo '<div id="bo_alert_settings">';
	
	if (($level & BO_PERM_ADMIN))
	{
		echo '<form action="?" method="GET">';
		echo bo_insert_html_hidden(array('bo_all', 'bo_action2'));
		echo '<fieldset class="bo_alert_settings_fieldset">';
		echo '<legend>'._BL('alert_settings_legend_table').'</legend>';
		echo '<input type="checkbox" name="bo_all" value="1" '.($show_all ? 'checked="checked"' : '').' onchange="submit();" onclick="submit();" id="bo_show_all">';
		echo '<label for="bo_show_all"> '._BL('show other users').'</label> &nbsp; ';
		echo '</fieldset>';	
		echo '</form>';
	}
	
	if (!empty($Alerts))
	{
		echo '<table class="bo_table" id="bo_alert_table">';
		
		echo '<tr>
				<th>'._BL('User').'</th>
				<th>'._BL('Alert name').'</th>
				<th>'._BL('Send to').'</th>
				<th>'._BL('Lat/Lon').'</th>
				<th>'._BL('Max. Distance').'</th>
				<th>'._BL('Min. strike rate').'</th>
				<th>'._BL('Last alert').'</th>
				<th>'._BL('Alert count').'</th>
				</tr>';
		
		foreach($Alerts as $user_id => $user_alerts)
		{
			foreach($user_alerts as $alert_id => $d)
			{
				echo '<tr>';
				echo '<td>'.bo_user_get_name($user_id).'</td>';
				echo '<td>';
				echo '<a href="'.bo_insert_url('bo_action2', 'alert_form,'.$user_id.','.$alert_id).'">';
				echo _BC($d['name']);
				echo '</a>';
				echo '</td>';
				echo '<td>';
				
				
				switch ($d['type'])
				{
					default: 
					case 1: echo _BL('E-Mail'); break;
					case 2: echo _BL('SMS'); break;
					case 3: echo _BL('URL'); break;
				
				}
				
				echo ': ';
				
				if (strlen($d['address']) > 25)
					echo _BC(substr($d['address'], 0, 15).'...'.substr($d['address'], -3));
				else
					echo _BC($d['address']);
					
				echo '</td>';
				echo '<td>'._BN($d['lat'], 4).'&deg; / '._BN($d['lon'], 4).'&deg;</td>';
				echo '<td>'._BN($d['dist'], 0).'km</td>';
				echo '<td>'._BN($d['count'], 0).' / '.$d['interval'].'min</td>';
				
				echo '<td>'.($d['last_send'] ? _BDT($d['last_send']) : '-').'</td>';
				echo '<td>'._BN($d['send_count'], 0).'</td>';
				
				echo '</tr>';
			}
		}
		
		echo '</table>';
	}
	else
	{
		echo '<p>'._BL('No alerts available. Create one yourself!').'</p>';
	}
	
	$user_id = bo_user_get_id();
	
	echo '<p><a href="'.bo_insert_url(array('bo_action2'), 'alert_form,'.$user_id).'">'._BL('Create new alert').'</a></p>';
	echo '<p><a href="'.bo_insert_url(array('bo_action2'), 'alert_log').'">'._BL('Show log').'</a></p>';
	echo '</div>';
}

function bo_alert_settings_form()
{
	$level = bo_user_get_level();
	$alert_id = -1;
	
	if ( !($level&BO_PERM_ALERT))
	{
		echo '<div class="bo_info_fail">';
		echo _BL('No permission!');
		echo '</div>';
		return true;
	}

	$tmp = explode(',', $_GET['bo_action2']);

	if (count($tmp) > 2)
	{
		$user_id = intval($tmp[1]);
		$alert_id = intval($tmp[2]);
		$A = unserialize(BoData::get('alert_'.$user_id.'_'.$alert_id));
	}
	else
	{
		$A = array();
		$A['interval'] = BO_UP_INTVL_STRIKES;
		$A['address'] = bo_user_get_mail($user_id);
		$A['type'] = $A['address'] ? 1 : 0;
	}

	if (!$A['lat'] || !$A['lon'])
	{
		$A['lat'] = BO_LAT;
		$A['lon'] = BO_LON;
	}	

	if ($_POST['cancel'])
	{
		return true;
	}
	else if ($_POST['ok'] || $_POST['delete'])
	{
		
		$A['user_id'] = intval($_POST['bo_alert_user']);
		$A['name'] = bo_gpc_prepare($_POST['bo_alert_name']);
		$A['address'] = bo_gpc_prepare($_POST['bo_alert_address']);
		$A['type'] = intval($_POST['bo_alert_type']);
		$A['dist'] = intval($_POST['bo_alert_distance']);
		$A['count'] = intval($_POST['bo_alert_count']);
		$A['interval'] = intval($_POST['bo_alert_interval']);

		if ($_POST['bo_alert_lat'] && $_POST['bo_alert_lon'])
		{
			$A['lat'] = substr((double)$_POST['bo_alert_lat'],0,10);
			$A['lon'] = substr((double)$_POST['bo_alert_lon'],0,10);
		}

		
		if ($A['interval'] < BO_UP_INTVL_STRIKES)
			$A['interval'] = BO_UP_INTVL_STRIKES;
		
		if (!($level & BO_PERM_ADMIN) && $A['user_id'] != bo_user_get_id())
		{
			echo '<div class="bo_info_fail">';
			echo _BL('No permission!');
			echo '</div>';
			return true;
		}
		
		if ($_POST['delete'])
		{
			if ($alert_id >= 0)
				BoData::set('alert_'.$user_id.'_'.$alert_id, null);
				
			return true;
		}
		
		if (!($level&BO_PERM_ALERT_ALL))
			$dist = bo_latlon2dist($A['lat'], $A['lon']);
		
		if ( !($A['user_id'] && $A['name'] && $A['type']
				&& $A['address'] && $A['lat'] && $A['lon']
				&& $A['dist'] && $A['count'] && $A['interval']) )
		{
			echo '<div class="bo_info_fail">'._BL('You must fill all fields!').'</div>';
		}
		else if ($A['type'] == 1 && !preg_match("/[\.a-z0-9_-]+@[a-z0-9-\.]{3,}$/i",$A['address']))
		{
			echo '<div class="bo_info_fail">'._BL('Wrong format of E-Mail address').'</div>';
		}
		else if ($A['type'] == 2 && !preg_match("/^[0-9]+$/i",$A['address']))
		{
			echo '<div class="bo_info_fail">'._BL('Wrong format of telephone number').'</div>';
		}
		else if ($A['type'] == 3 && !preg_match("@^http://.{5}@i",$A['address']))
		{
			echo '<div class="bo_info_fail">'._BL('Enter URL like this').': http://domain.com/site...'.'</div>';
		}
		else if (!($level&BO_PERM_ALERT_ALL) && ($dist > BO_RADIUS || $A['dist'] > BO_RADIUS))
		{
			echo '<div class="bo_info_fail">'._BL('You have to stay in the area around the station!').'</div>';
		}
		else
		{
			$A['disarmed'] = false;
			$d['no_checking'] = false;
			$A['last_check'] = 0;

			if ($alert_id >= 0)
			{
				BoData::set('alert_'.$user_id.'_'.$alert_id, serialize($A));
			}
			else
			{
				$alert_id_new = 0;
				while($row = BoData::get_all('alert\_'.$user_id.'\_%'))
				{
					preg_match('/alert_([0-9]+)_([0-9]+)/', $row['name'], $r);
					$alert_id_new = max($alert_id_new, intval($r[2]) + 1);
				}
				
				BoData::set('alert_'.$user_id.'_'.$alert_id_new, serialize($A));
				
			}
			
			return true;
		}
	
	}
	
	if ( !($level&BO_PERM_ALERT_ALL))
		$disabled = ' disabled="disabled"';
	
	
	$adress_text = _BL('E-Mail');
		
	echo '<div id="bo_alert_settings_form">';
	
	if ($alert_id >= 0)
		echo '<h3>'._BL('Change settings').'</h3>';
	else
		echo '<h3>'._BL('New alert').'</h3>';
		
	if (($level & BO_PERM_ALERT))
	{
		echo '<h4>'._BL('Alert settings for user ').' '.bo_user_get_name($user_id).'</h4>';
	}
	
	echo '<form action="'.bo_insert_url('bo_all', $user_id != bo_user_get_id()).'" method="POST" class="bo_alert_set_form">';
	
	echo '<fieldset class="bo_alert_settings_fieldset">';
	echo '<legend>'._BL('alert_settings_legend1').'</legend>';
	
	echo '<input type="hidden" name="bo_alert_user" value="'.intval($user_id).'">';
	echo '<input type="hidden" name="bo_alert_id" value="'.intval($alert_id).'">';
	
	echo '<span class="bo_form_descr">'._BL('alert_name').':</span>';
	echo '<input type="text" name="bo_alert_name" value="'._BC($A['name']).'" id="bo_alert_name_input" class="bo_form_text bo_alert_input">';

	echo '<span class="bo_form_descr">'._BL('alert_type').':</span>';
	echo '<div class="bo_input_container">';
	echo '<input type="radio" name="bo_alert_type" value="1" '.($A['type'] == 1 ? 'checked="checked' : '').' id="bo_alert_type_mail" class="bo_form_radio bo_alert_input">';
	echo '<label for="bo_alert_type_mail">'._BL('alert_mail').'</label>';
	
	if (($level&BO_PERM_ALERT_SMS) && defined('BO_SMS_GATEWAY_URL') && BO_SMS_GATEWAY_URL)
	{
		echo '<input type="radio" name="bo_alert_type" value="2" '.($A['type'] == 2 ? 'checked="checked' : '').' id="bo_alert_type_sms" class="bo_form_radio bo_alert_input">';
		echo '<label for="bo_alert_type_sms">'._BL('alert_sms').'</label>';
		$adress_text .= ' / '._BL('Number');
	}

	if (($level&BO_PERM_ALERT_URL))
	{
		echo '<input type="radio" name="bo_alert_type" value="3" '.($A['type'] == 3 ? 'checked="checked' : '').' id="bo_alert_type_url" class="bo_form_radio bo_alert_input">';
		echo '<label for="bo_alert_type_url">'._BL('alert_url').'</label>';
		$adress_text .= ' / '._BL('URL');
	}
	
	echo '</div>';
	
	echo '<span class="bo_form_descr">'.$adress_text.':</span>';
	echo '<input type="text" name="bo_alert_address" value="'._BC($A['address']).'" style="width:95%" id="bo_alert_address_input" class="bo_form_text bo_alert_input">';
	
	
	echo '</fieldset>';

	

	echo '<fieldset class="bo_alert_settings_fieldset">';
	echo '<legend>'._BL('alert_settings_legend2').'</legend>';

	echo '<div id="bo_gmap" class="bo_map_alert" style="width: 300px; height: 250px;float: left;"></div>';

	echo '<span class="bo_form_descr">'._BL('alert_lat').':</span>';
	echo '<input type="text" name="bo_alert_lat" value="'.htmlentities($A['lat']).'" id="bo_alert_lat_input" class="bo_form_text bo_alert_input" '.$disabled.'>';

	echo '<span class="bo_form_descr">'._BL('alert_lon').':</span>';
	echo '<input type="text" name="bo_alert_lon" value="'.htmlentities($A['lon']).'" id="bo_alert_lon_input" class="bo_form_text bo_alert_input" '.$disabled.'>';
	
	echo '<span class="bo_form_descr">'._BL('alert_distance').':</span>';
	echo '<input type="text" name="bo_alert_distance" value="'.htmlentities($A['dist']).'" id="bo_alert_distance_input" class="bo_form_text bo_alert_input">';

	echo '<span class="bo_form_descr">'._BL('alert_count').':</span>';
	echo '<input type="text" name="bo_alert_count" value="'.htmlentities($A['count']).'" id="bo_alert_count_input" class="bo_form_text bo_alert_input">';

	echo '<span class="bo_form_descr">'._BL('alert_interval').' ('._BL('unit_minutes').'):</span>';
	echo '<input type="text" name="bo_alert_interval" value="'.htmlentities($A['interval']).'" id="bo_alert_interval_input" class="bo_form_text bo_alert_input">';

	
	echo '</fieldset>';

	echo '<input type="submit" name="ok" value="'._BL('Ok').'" id="bo_alert_ok" class="bo_form_submit">';
	echo '<input type="submit" name="cancel" value="'._BL('Cancel').'" id="bo_alert_cancel" class="bo_form_submit">';
	
	if ($alert_id >= 0)
		echo '<input type="submit" name="delete" value="'._BL('Delete').'" id="bo_alert_delete" class="bo_form_submit bo_form_delete">';
	
	echo '</form>';

	if (($level&BO_PERM_ALERT_URL))	
	{
		echo '<div id="bo_alert_form_descr">';
		
		echo '<p>'._BL('For usage with "URL"').':</p>';
		
		echo '<ul id="bo_alert_form_descr_ul">
				<li><span class="bo_descr">{name}:</span> <span class="bo_value">'._BL('Name of your alert').'</span></li>
				<li><span class="bo_descr">{strikes}:</span> <span class="bo_value">'._BL('Strike count').'</span></li>
				<li><span class="bo_descr">{time}:</span> <span class="bo_value">'._BL('Time of last strike').'</span></li>
				<li><span class="bo_descr">{first}:</span> <span class="bo_value">'._BL('Time of first strike in time range').'</span></li>
				<li><span class="bo_descr">{dist}:</span> <span class="bo_value">'._BL('Distance of last strike to selected position').'</span></li>
				<li><span class="bo_descr">{bear}:</span> <span class="bo_value">'._BL('Bearing of last strike to selected position').'</span></li>
				</ul>';
		echo '</div>';
	}
	
	echo '</div>';
	
	//Map
	$lat = $A['lat'] ? $A['lat'] : BO_LAT;
	$lon = $A['lon'] ? $A['lon'] : BO_LON;
	
?>
	<script type="text/javascript">

		var centerMarker;
		function bo_gmap_init2()
		{
		
			var myLatlng = new google.maps.LatLng(<?php echo "$lat,$lon" ?>);
			centerMarker = new google.maps.Marker({
				position: myLatlng,
				draggable: true,
				map: bo_map,
				icon: 'http://maps.google.com/mapfiles/ms/micons/blue-dot.png'
			});

			google.maps.event.addListener(centerMarker, 'dragend', function() {
				document.getElementById('bo_alert_lat_input').value=this.getPosition().lat();
				document.getElementById('bo_alert_lon_input').value=this.getPosition().lng();
			});

			<?php if ($A['dist']) { ?>

			var boDistCircle = {
				clickable: false,
				strokeColor: "#5555ff",
				strokeOpacity: 0.5,
				strokeWeight: 1,
				fillColor: "#5555ff",
				fillOpacity: 0.1,
				map: bo_map,
				center: new google.maps.LatLng(<?php echo "$lat,$lon" ?>),
				radius: <?php echo $A['dist']*1000 ?>
			};

			new google.maps.Circle(boDistCircle);

			<?php } ?>

			
		}

	</script>
<?php
	
	require_once 'functions_dynmap.inc.php';	
	bo_insert_map( (BO_PERM_NOLIMIT & $level) ? 0 : 2);

	
	return false;
}



function bo_alert_send()
{
	bo_echod(" ");
	bo_echod("=== Strike alerts ===");

	ini_set('default_socket_timeout', 2); // should be enough!
	
	$is_sending = BoData::get('is_sending_alerts');

	//Check if sth. went wrong on the last update (if older than 120sec continue)
	if ($is_sending && time() - $is_sending < 300)
	{
		bo_echod("Error: Another alert is running");
		return false;
	}

	BoData::set('is_sending_alerts', time());
	
	$Alerts = array();
	$log = array();

	//continues without further checks, if message was sent less than given minutes before
	if (defined('BO_ALERT_CHECK_INTERVAL') && intval(BO_ALERT_CHECK_INTERVAL))
		$check_continue = intval(BO_ALERT_CHECK_INTERVAL);
	else
		$check_continue = 15; 
	
	//waits given minutes and sends next message after negative check (minimum time without strikes)
	if (defined('BO_ALERT_SEND_INTERVAL') && intval(BO_ALERT_SEND_INTERVAL))
		$min_send_interval = intval(BO_ALERT_SEND_INTERVAL);
	else
		$min_send_interval = 45; 
	
	$max_time = BoData::get('uptime_strikes_modified');
	
	//strike data is to old --> do nothing
	if (time() - $max_time < 30 * 60)
	{
		//Warning! May cause very high Database load!
		while ($row = BoData::get_all('alert\_%'))
		{
			$d = unserialize($row['data']);
			
			if (preg_match('/alert_([0-9]+)_([0-9]+)/', $row['name'], $r) && is_array($d) && !empty($d))
			{
				if ($max_time - $d['last_check'] < $check_continue * 60)
					continue;
				
				$alert_dbname = $row['name'];
				
				$user_id = $r[1];
				$alert_cnt = $r[2];
				
				$search_time = $max_time - 60 * $d['interval'] - 60;
				$search_date = gmdate('Y-m-d H:i:s', $search_time);

				
				//Position is near Staion ==> use faster query
				if (BO_LAT - 0.001 < $d['lat'] && $d['lat'] < BO_LAT + 0.001 &&
					BO_LON - 0.001 < $d['lon'] && $d['lon'] < BO_LON + 0.001 	)
				{
					$sql_where = "	AND distance < ".($d['dist'] * 1000)."
									AND time >= '$search_date'
								";
				}
				else
				{
					//this calculation does search the strikes in a square, not in a circle but it is much faster for the database!
					
					list($str_lat_min, $str_lon_min) = bo_distbearing2latlong($d['dist'] * 1000 * sqrt(2), 225, $d['lat'], $d['lon']);
					list($str_lat_max, $str_lon_max) = bo_distbearing2latlong($d['dist'] * 1000 * sqrt(2), 45,  $d['lat'], $d['lon']);
					$sql_where = "	AND NOT (lat < $str_lat_min OR lat > $str_lat_max OR lon < $str_lon_min OR lon > $str_lon_max)
									AND time >= '$search_date'
									";
				}
				
				if ($d['count'] <= 2) // only confirmed strikes should count when min strike count is very low
					$sql_where .= " AND status > 0 ";
				
				$sql = "SELECT COUNT(id) cnt, MAX(time) maxtime, MIN(time) mintime
						FROM ".BO_DB_PREF."strikes
						WHERE 1	
							$sql_where";
				$res2 = BoDb::query($sql);
				$row2 = $res2->fetch_assoc();
				
				if ($row2['cnt'] >= $d['count'] && !$d['no_checking'])
				{
					if (!$d['disarmed']) //SEND IT
					{
						//only use the poition of the last strike in the interval to avoid to much calculating
						$sql = "SELECT lat, lon
								FROM ".BO_DB_PREF."strikes
								WHERE 1	
									$sql_where 
								ORDER BY time DESC
								LIMIT 1";
						$res3 = BoDb::query($sql);
						$row3 = $res3->fetch_assoc();

						$dist = round(bo_latlon2dist($d['lat'], $d['lon'], $row3['lat'], $row3['lon']) / 1000);
						$bear = _BL(bo_bearing2direction(bo_latlon2bearing($row3['lat'], $row3['lon'], $d['lat'], $d['lon'])), true);
						
						$replace = array(
									'{name}' 	=> $d['name'],
									'{strikes}' => $row2['cnt'],
									'{time}'	=> _BDT(strtotime($row2['maxtime'].' UTC')),
									'{first}'	=> _BDT(strtotime($row2['mintime'].' UTC')),
									'{dist}'	=> $dist,
									'{bear}'	=> $bear,
									'{userid}'	=> $user_id
										);
						
						$log[$alert_dbname] = array();

						switch($d['type'])
						{
							case 1: //E-Mail

								$text = _BL('alert_mail_description', true)."\n\n".
										_BL('alert_mail_time range', true).': '._BDT($search_time).' - '._BDT($max_time)."\n".
										_BL('alert_mail_strikes', true).': '.$row2['cnt']."\n".
										_BL('alert_mail_distance', true).': '.$dist." "._BL('unit_kilometers', true)." (".$bear.")\n".
										_BL('alert_mail_first_strike', true).': '.date('H:i:s', strtotime($row2['mintime'].' UTC'))."\n".
										_BL('alert_mail_last_strike', true).': '.date('H:i:s', strtotime($row2['maxtime'].' UTC'))."\n\n";
								
								$text = preg_replace("#(?<!\r)\n#si", "\r\n", $text); 								
								
								$ret = bo_mail($d['address'], 
											_BL('Strikes detected', true).' ('.$d['name'].')', 
											$text);
								
								$log[$alert_dbname]['text']   = $text;
								
								break;
							
							case 2: //SMS
								
								if (defined('BO_SMS_GATEWAY_URL') && BO_SMS_GATEWAY_URL)
								{
									$text = "*** ({name}) ***\n".
											"{strikes} "._BL('Strikes detected', true)."\n".
											_BL('alert_sms_last_strike', true).": {time}\n".
											_BL('alert_sms_distance', true).": {dist}km (".$bear.")\n".
											_BL('alert_sms_description', true);
									
									$text = strtr($text, $replace);
									$log[$alert_dbname]['text']   = $text;
									
									$url = strtr(BO_SMS_GATEWAY_URL, array(
										'{text}' 	=> urlencode($text), 
										'{tel}' 	=> urlencode($d['address']), 
										'{userid}' 	=> urlencode($user_id))
										);
									
									$ret = bo_get_file($url);
								}
								
								break;
								
							case 3: //URL
								
								foreach ($replace as $key => $val)
									$replace[$key] = urlencode($val);
								
								$url = strtr($d['address'], $replace);
								$ret = bo_get_file($url);
								$log[$alert_dbname]['text'] = $url;
								
								break;
						}
						
						$log[$alert_dbname]['type']   = $d['type'];
						$log[$alert_dbname]['return'] = $ret;
						$log[$alert_dbname]['address'] = $d['address'];
						$log[$alert_dbname]['name'] = $d['name'];

						$d['last_send'] = time();
						$d['send_count']++;
						$d['disarmed'] = true; //after sending!
					}
					
					$d['last_check'] = $max_time;
					
					BoData::set($alert_dbname, serialize($d));
				}
				elseif ($d['disarmed'] || $d['no_checking'])
				{

					if ($max_time - $d['last_check'] > $min_send_interval * 60)
					{
						//no strike detected for a long time
						//RESET
						$d['disarmed'] = false;
						$d['no_checking'] = false;
						BoData::set($alert_dbname, serialize($d));
					}
					else if (!$d['no_checking'])
					{
						//no strike detected and disarmed
						//do NOTHING during the next minutes
						$d['no_checking'] = true;
						BoData::set($alert_dbname, serialize($d));
					}
				}
			}

		}
		
		if (!empty($log))
		{
			$oldlog = unserialize(BoData::get('alerts_log'));
			$newlog = array();

			if (is_array($oldlog))
			{
				krsort($oldlog);
				$count = 0;
				foreach($oldlog as $logid => $logdata)
				{
					$newlog[$logid] = $logdata;
					
					if ($count++ > 100)
						break;
				}
			}
			
			if (is_array($log))
			{
				foreach($log as $logid => $logdata)
				{
					$newlog[$max_time.'_'.$logid] = $logdata;
				}
			}
			
			BoData::set('alerts_log', serialize($newlog));
		
			bo_echod("Sent ".count($log)." alerts.");
		}
		else
			bo_echod("No alerts sent.");
		
	}
	
	BoData::set('is_sending_alerts', 0);

}


function bo_alert_log($all = false)
{
	$log = unserialize(BoData::get('alerts_log'));
	$count = 0;
	
	if (is_array($log))
	{
		krsort($log);
		echo '<table class="bo_table" id="bo_alert_table">';
		echo '<tr>
				<th>'._BL('Time').'</th>
				<th>'._BL('User').'</th>
				<th>'._BL('Alert name').'</th>
				<th>'._BL('Type').'</th>
				<th>'._BL('To').'</th>
				<th>'._BL('Return value').'</th>
				<th>'._BL('Text').'</th>
				</tr>';

		foreach($log as $logid => $d)
		{
			if (!preg_match('/([0-9]+)_alert_([0-9]+)_([0-9]+)/', $logid, $r))
				continue;
			
			$time = $r[1];
			$user_id = $r[2];
			$alert_id = $r[3];
			
			if (!$all && $user_id != bo_user_get_id())
				continue;
			
			if (!$d['name'])
				$d['name'] = '?';
			
			echo '<tr>';
			echo '<td>'._BDT($time).'</td>';
			echo '<td>'.bo_user_get_name($user_id).'</td>';
			echo '<td><a href="'.bo_insert_url('bo_action2', 'alert_form,'.$user_id.','.$alert_id).'">'._BC($d['name']).'</a></td>';
			echo '<td>';
			switch ($d['type'])
			{
				default: echo '?'; break;
				case 1: echo _BL('E-Mail'); break;
				case 2: echo _BL('SMS'); break;
				case 3: echo _BL('URL'); break;
			}
			echo '</td>';
			echo '<td>'.($d['type'] != 3 ? htmlentities($d['address']) : '').'</td>';
			echo '<td>';
			echo $d['return'] ? $d['return'] : _BL('Error');
			echo '</td>';
			
			echo '<td>';
			echo htmlentities(strlen($d['text']) > 70 ? substr($d['text'], 0, 50).'...'.substr($d['text'], -10) : $d['text']);
			echo '</td>';
			echo '</tr>';
			
			$count++;
		}
				
		echo '</table>';
	}
	
	if (!$count)
		echo '<p>'._BL('No log entries').'</p>';
	
	echo '<p><a href="'.bo_insert_url(array('bo_action2')).'">'._BL('Back').'</a></p>';
	
	return false;
}

?>