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

function bo_alert_settings()
{
	$level = bo_user_get_level();
	$show_all = ($level & BO_PERM_SETTINGS) && isset($_GET['bo_all']);
	
	if (substr($_GET['bo_action2'],0,10) == 'alert_form')
	{
		if (!bo_alert_settings_form())
			return;
	}
	
	if ($show_all)
		$like = 'alert_%';
	else
		$like = 'alert_'.bo_user_get_id().'_%';
	
	$Alerts = array();
	
	$sql = "SELECT name, data, changed
			FROM ".BO_DB_PREF."conf
			WHERE name LIKE '$like'
			";
	$res = bo_db($sql);
	while ($row = $res->fetch_assoc())
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
	
	if (($level & BO_PERM_SETTINGS))
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
				echo $d['name'];
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
					echo substr($d['address'], 0, 15).'...'.substr($d['address'], -3);
				else
					echo $d['address'];
					
				echo '</td>';
				echo '<td>'.number_format($d['lat'], 4, _BL('.'), _BL(',')).'&deg; / '.number_format($d['lon'], 4, _BL('.'), _BL(',')).'&deg;</td>';
				echo '<td>'.number_format($d['dist'], 0, _BL('.'), _BL(',')).'km</td>';
				echo '<td>'.number_format($d['count'], 0, _BL('.'), _BL(',')).' / '.$d['interval'].'min</td>';
				
				echo '<td>'.($d['last_send'] ? date(_BL('_datetime'), $d['last_send']) : '-').'</td>';
				echo '<td>'.number_format($d['send_count'], 0, _BL('.'), _BL(',')).'</td>';
				
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
	
	echo '</div>';
}

function bo_alert_settings_form()
{
	$level = bo_user_get_level();
	
	if ( !($level&BO_PERM_ALERT))
	{
		echo '<div class="bo_info_fail">';
		echo _BL('No permission!');
		echo '</div>';
		return true;
	}

	$tmp = explode(',', $_GET['bo_action2']);
	$user_id = intval($tmp[1]);
	$alert_id = intval($tmp[2]);

	if ($alert_id)
	{
		$A = unserialize(bo_get_conf('alert_'.$user_id.'_'.$alert_id));
		
	}
	else
	{
		$A = array();
		$A['interval'] = BO_UP_INTVL_STRIKES;
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
		$A['name'] = trim($_POST['bo_alert_name']);
		$A['type'] = intval($_POST['bo_alert_type']);
		$A['address'] = trim($_POST['bo_alert_address']);
		$A['lat'] = (double)$_POST['bo_alert_lat'];
		$A['lon'] = (double)$_POST['bo_alert_lon'];
		$A['dist'] = intval($_POST['bo_alert_distance']);
		$A['count'] = intval($_POST['bo_alert_count']);
		$A['interval'] = intval($_POST['bo_alert_interval']);
		
		if ($A['interval'] < BO_UP_INTVL_STRIKES)
			$A['interval'] = BO_UP_INTVL_STRIKES;
		
		if (!($level & BO_PERM_SETTINGS) && $A['user_id'] != bo_user_get_id())
		{
			echo '<div class="bo_info_fail">';
			echo _BL('No permission!');
			echo '</div>';
			return true;
		}
		
		if ($_POST['delete'])
		{
			if ($alert_id)
				bo_set_conf('alert_'.$user_id.'_'.$alert_id, null);
				
			return true;
		}
		
		if ( !($A['user_id'] && $A['name'] && $A['type']
				&& $A['address'] && $A['lat'] && $A['lon']
				&& $A['dist'] && $A['count'] && $A['interval']) )
		{
			echo '<div class="bo_info_fail">'._BL('You must fill all fields!').'</div>';
		}
		else if ($A['type'] == 1 && !preg_match("/[\.a-z0-9_-]+@[a-z0-9-]{2,}\.[a-z]{2,6}$/i",$A['address']))
		{
			echo '<div class="bo_info_fail">'._BL('Wrong format of E-Mail address').'</div>';
		}
		else if ($A['type'] == 2 && !preg_match("/^[0-9]+$/i",$A['address']))
		{
			echo '<div class="bo_info_fail">'._BL('Wrong format of telephone number').'</div>';
		}
		else if ($A['type'] == 3 && !preg_match("@^http://.{5}@i",$A['address']))
		{
			echo '<div class="bo_info_fail">'._BL('Enter URL like this: http://domain.com/site...').'</div>';
		}
		else
		{
			$A['address'] = BoDb::esc($A['address']);
			$A['name'] = BoDb::esc($A['name']);
			
			$A['disarmed'] = false;
			$A['last_check'] = 0;
			
			if ($alert_id)
			{
				bo_set_conf('alert_'.$user_id.'_'.$alert_id, serialize($A));
			}
			else
			{
				$sql = "SELECT name FROM ".BO_DB_PREF."conf WHERE name LIKE 'alert_".$user_id."_%' ORDER BY name DESC LIMIT 1";
				$res = bo_db($sql);
				$row = $res->fetch_assoc();

				preg_match('/alert_([0-9]+)_([0-9]+)/', $row['name'], $r);
				$alert_id = intval($r[2]) + 1;

				bo_set_conf('alert_'.$user_id.'_'.$alert_id, serialize($A));
				
			}
			
			return true;
		}
	
	}
	
	if ( !($level&BO_PERM_ALERT_ALL))
		$disabled = ' disabled="disabled"';
	
	
	$adress_text = _BL('E-Mail');
		
	echo '<div id="bo_alert_settings_form">';
	
	if ($alert_id)
		echo '<h3>'._BL('Change settings').'</h3>';
	else
		echo '<h3>'._BL('New alert').'</h3>';
		
	if (($level & BO_PERM_SETTINGS))
	{
		echo '<h4>'._BL('Alert settings for user ').' '.bo_user_get_name($user_id).'</h4>';
	}
	
	echo '<form action="'.bo_insert_url('bo_all', $user_id != bo_user_get_id()).'" method="POST" class="bo_alert_set_form">';
	
	echo '<fieldset class="bo_alert_settings_fieldset">';
	echo '<legend>'._BL('alert_settings_legend1').'</legend>';
	
	echo '<input type="hidden" name="bo_alert_user" value="'.intval($user_id).'">';
	echo '<input type="hidden" name="bo_alert_id" value="'.intval($alert_id).'">';
	
	echo '<span class="bo_form_descr">'._BL('alert_name').':</span>';
	echo '<input type="text" name="bo_alert_name" value="'.htmlentities($A['name']).'" id="bo_alert_name_input" class="bo_form_text bo_alert_input">';

	echo '<span class="bo_form_descr">'._BL('alert_type').':</span>';
	echo '<input type="radio" name="bo_alert_type" value="1" '.($A['type'] == 1 ? 'checked="checked' : '').' id="bo_alert_type_mail" class="bo_form_radio bo_alert_input">';
	echo '<label for="bo_alert_type_mail">'._BL('alert_mail').'</label>';
	
	if (($level&BO_PERM_ALERT_SMS) && defined('BO_SMS_GATEWAY_URL') && BO_SMS_GATEWAY_URL)
	{
		echo '<input type="radio" name="bo_alert_type" value="2" '.($A['type'] == 2 ? 'checked="checked' : '').' id="bo_alert_type_sms" class="bo_form_radio bo_alert_input">';
		echo '<label for="bo_alert_type_sms">'._BL('alert_sms').'</label>';
		$adress_text .= _BL(' / Number');
	}

	if (($level&BO_PERM_ALERT_URL))
	{
		echo '<input type="radio" name="bo_alert_type" value="3" '.($A['type'] == 3 ? 'checked="checked' : '').' id="bo_alert_type_url" class="bo_form_radio bo_alert_input">';
		echo '<label for="bo_alert_type_url">'._BL('alert_url').'</label>';
		$adress_text .= _BL(' / URL');
	}

	echo '<span class="bo_form_descr">'.$adress_text.':</span>';
	echo '<input type="text" name="bo_alert_address" value="'.htmlentities($A['address']).'" id="bo_alert_address_input" class="bo_form_text bo_alert_input">';
	
	
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

	echo '<span class="bo_form_descr">'._BL('alert_interval').':</span>';
	echo '<input type="text" name="bo_alert_interval" value="'.htmlentities($A['interval']).'" id="bo_alert_interval_input" class="bo_form_text bo_alert_input">';

	
	echo '</fieldset>';

	echo '<input type="submit" name="ok" value="'._BL('Ok').'" id="bo_alert_ok" class="bo_form_submit">';
	echo '<input type="submit" name="cancel" value="'._BL('Cancel').'" id="bo_alert_cancel" class="bo_form_submit">';
	
	if ($alert_id)
		echo '<input type="submit" name="delete" value="'._BL('Delete').'" id="bo_alert_delete" class="bo_form_submit bo_form_delete">';
	
	echo '</form>';
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
	
	bo_insert_map( (BO_PERM_NOLIMIT & $level) ? 0 : 2);

	
	return false;
}



function bo_alert_send()
{
	echo '<h3>Strike alerts</h3>';

	ini_set('default_socket_timeout', 2); // should be enough!
	
	$is_sending = bo_get_conf('is_sending_alerts');

	//Check if sth. went wrong on the last update (if older than 120sec continue)
	if ($is_sending && time() - $is_sending < 120)
	{
		echo '<p>Error: Another update is running</p>';
		return false;
	}

	bo_set_conf('is_sending_alerts', time());
	
	$Alerts = array();
	$log = array();

	$check_continue = 1; //continues without further checks, if message was sent less than given minutes before
	$min_send_interval = 15; //waits given minutes and sends next message after negative check
	
	$sql = "SELECT MAX(time) mtime FROM ".BO_DB_PREF."strikes";
	$res = bo_db($sql);
	$row = $res->fetch_assoc();
	$max_time = strtotime($row['mtime'].' UTC');
	
	//strike data is to old --> do nothing
	if (time() - $max_time < 30 * 60)
	{
		
		//Warning! May cause very high Database load!
		$sql = "SELECT name, data, changed
				FROM ".BO_DB_PREF."conf
				WHERE name LIKE 'alert%'";
		$res = bo_db($sql);
		while ($row = $res->fetch_assoc())
		{
			$d = unserialize($row['data']);
			
			if (preg_match('/alert_([0-9]+)_([0-9]+)/', $row['name'], $r) && is_array($d) && !empty($d))
			{
				print_r($d);
				if ($max_time - $d['last_check'] < $check_continue * 60)
				{
					continue;
				}
				
				
				
				$alert_dbname = $row['name'];
				
				$user_id = $r[1];
				$alert_cnt = $r[2];
				
				list($str_lat_min, $str_lon_min) = bo_distbearing2latlong($d['dist'] * 1000 * sqrt(2), 225, $d['lat'], $d['lon']);
				list($str_lat_max, $str_lon_max) = bo_distbearing2latlong($d['dist'] * 1000 * sqrt(2), 45, $d['lat'], $d['lon']);

				$search_time = $max_time - 60 * $d['interval'] - 60;
				$search_date = gmdate('Y-m-d H:i:s', $search_time);
				
				$sql = "SELECT COUNT(id) cnt, MAX(time) maxtime, MIN(time) mintime
						FROM ".BO_DB_PREF."strikes
						WHERE 1
							AND NOT (lat < $str_lat_min OR lat > $str_lat_max OR lon < $str_lon_min OR lon > $str_lon_max)
							AND time >= '$search_date' ";
				$res2 = bo_db($sql);
				$row2 = $res2->fetch_assoc();
				
				if ($row2['cnt'] >= $d['count'])
				{
					$d['last_check'] = $max_time;
					
					if (!$d['disarmed']) //SEND IT
					{
						$replace = array(
									'{name}' 	=> $d['name'],
									'{strikes}' => $row2['cnt'],
									'{time}'	=> date(_BL('_datetime'), strtotime($row2['maxtime'].' UTC')),
									'{first}'	=> date(_BL('_datetime'), strtotime($row2['mintime'].' UTC')),
										);
						
						
						switch($d['type'])
						{
							case 1: //E-Mail
								
								$ret = mail(	$d['address'], 
										'MyBlitzortung: '._BL('Strikes detected').' ('.$d['name'].')', 
										_BL('alert_mail_description')."\n\n".
										_BL('alert_mail_time range').': '.date(_BL('_datetime'), $search_time).' - '.date(_BL('_datetime'), $max_time)."\n".
										_BL('alert_mail_strikes').': '.$row2['cnt']."\n".
										_BL('alert_mail_last_strike').': '.date(_BL('_datetime'), strtotime($row2['maxtime'].' UTC'))."\n".
										_BL('alert_mail_first_strike').': '.date(_BL('_datetime'), strtotime($row2['mintime'].' UTC'))."\n\n"
										);
								
								break;
							
							case 2: //SMS
								
								if (defined('BO_SMS_GATEWAY_URL') && BO_SMS_GATEWAY_URL)
								{
									$text = "*** MyBlitzortung ({name}) ***\n".
											"{strikes} "._BL('Strikes detected')."\n".
											_BL('alert_sms_last_strike').": {time}\n".
											_BL('alert_sms_description');
									
									$text = strtr($text, $replace);
									$text = urlencode($text);
									
									$url = BO_SMS_GATEWAY_URL;
									$url = strtr($url, array('{text}' => $text, '{tel}' => $d['address']));
									
									$ret = file_get_contents($url);
								}
							
							case 3: //URL
								
								$url = strtr($d['address'], $replace);
								$ret = file_get_contents($url);
								
								break;
						}
						
						$log[$alert_dbname] = array($d['type'], $text, $ret, $d['address']);
						
						$d['last_send'] = time();
						$d['disarmed'] = true;
						$d['send_count']++;
					}
					
					bo_set_conf($alert_dbname, serialize($d));
				}
				elseif ($d['disarmed'] && $max_time - $d['last_check'] > $min_send_interval * 60)
				{
					$d['disarmed'] = false;
					bo_set_conf($alert_dbname, serialize($d));
				}
			}

		}
		
		if (!empty($log))
		{
		
		
		}
		else
			echo '<p>No alerts sent.</p>';
		
	}
	
	bo_set_conf('is_sending_alerts', 0);

}




?>