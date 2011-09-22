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

//show all available statistics and menu
function bo_show_statistics()
{
	$show = $_GET['bo_show'] ? $_GET['bo_show'] : 'strikes';
	
	if (defined('BO_STATISTICS_ALL_STATIONS') && BO_STATISTICS_ALL_STATIONS || ((bo_user_get_level() & BO_PERM_NOLIMIT)))
	{
		$station_id = intval($_GET['bo_station_id']);

		if ($station_id && $station_id != bo_station_id())
		{
			$add_stid = '&bo_station_id='.$station_id;
			$add_graph = '&id='.$station_id;
			$own_station = false;
			$city = trim(bo_station_city($station_id));
		}
	}
	
	if (!$station_id || !$city)
	{
		$station_id = bo_station_id();
		$own_station = true;
		$city = trim(bo_station_city($station_id));
		$add_stid = '';
	}
	
	echo '<div id="bo_statistics">';

	echo '<ul id="bo_menu">';

	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'strikes').$add_stid.'" class="bo_navi'.($show == 'strikes' ? '_active' : '').'">'._BL('stat_navi_strikes').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'station').$add_stid.'" class="bo_navi'.($show == 'station' ? '_active' : '').'">'._BL('stat_navi_station').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'network').$add_stid.'" class="bo_navi'.($show == 'network' ? '_active' : '').'">'._BL('stat_navi_network').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'longtime').$add_stid.'" class="bo_navi'.($show == 'longtime' ? '_active' : '').'">'._BL('stat_navi_longtime').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'other').$add_stid.'" class="bo_navi'.($show == 'other' ? '_active' : '').'">'._BL('stat_navi_other').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'advanced').$add_stid.'" class="bo_navi'.($show == 'advanced' ? '_active' : '').'">'._BL('stat_navi_advanced').'</a></li>';

	echo '</ul>';

	if ($add_stid)
	{
		echo '<div id="bo_stat_other_station_info">';
		echo strtr(_BL('bo_stat_other_station_info'), array('{STATION_CITY}' => _BC($city)));
		echo ' <a href="'.bo_insert_url('bo_station_id').'">'._BL('bo_stat_other_station_info_back').'</a>';
		echo '</div>';
	}

	switch($show)
	{
		default:
		case 'strikes':
			bo_show_statistics_strikes($station_id, $own_station, $add_graph);
			break;

		case 'station':
			bo_show_statistics_station($station_id, $own_station, $add_graph);
			break;

		case 'longtime':
			echo '<h3>'._BL('h3_stat_longtime').'</h3>';
			bo_show_statistics_longtime($station_id, $own_station, $add_graph);
			break;

		case 'network':
			echo '<h3>'._BL('h3_stat_network').'</h3>';
			bo_show_statistics_network($station_id, $own_station, $add_graph);
			break;

		case 'other':
			echo '<h3>'._BL('h3_stat_other').'</h3>';
			bo_show_statistics_other($station_id, $own_station, $add_graph);
			break;
		
		case 'advanced':
			echo '<h3>'._BL('h3_stat_advanced').'</h3>';
			bo_show_statistics_advanced($station_id, $own_station, $add_graph);
			break;
	}

	echo '</div>';

	bo_copyright_footer();
}

//strike statistics 
function bo_show_statistics_strikes($station_id = 0, $own_station = true, $add_graph = '')
{
	global $_BO;
	
	$year = intval($_GET['bo_year']);
	$month = intval($_GET['bo_month']);
	$region = $_GET['bo_region'];

	//Regions
	if (!preg_match('/[0-9a-z]+/i', $region) || !isset($_BO['region'][$region]['rect_add']))
		$region = 0;
	
	/*** Strikes NOW ***/
	$last_update = bo_get_conf('uptime_strikes');
	$last_update_minutes = round((time()-$last_update)/60,1);
	$group_minutes = BO_GRAPH_STAT_STRIKES_NOW_GROUP_MINUTES;
	
	
	if (1 || $region)
	{
		$last_strikes_region = unserialize(bo_get_conf('last_strikes_region'));
		$rate_strikes_region = unserialize(bo_get_conf('rate_strikes_region'));
		$strike_rate = $rate_strikes_region[$region];
		$last_strike = $last_strikes_region[$region];
	}
	else
	{
		$sql = "SELECT COUNT(*) cnt
				FROM ".BO_DB_PREF."strikes 
				WHERE time BETWEEN '".gmdate('Y-m-d H:i:s', $last_update - 60*$group_minutes*2 )."' AND '".gmdate('Y-m-d H:i:s', $last_update-60*$group_minutes*1)."'"; 
		$row = bo_db($sql)->fetch_assoc();
		$strike_rate = $row['cnt'] / $group_minutes;
		
		$sql = "SELECT MAX(time) mtime
				FROM ".BO_DB_PREF."strikes ";
		$row = bo_db($sql)->fetch_assoc();
		$last_strike = strtotime($row['mtime'].' UTC');
	}

	if (!$region && intval(BO_TRACKS_SCANTIME))
	{
		$num_cells = -1;
		$cells_data = unserialize(gzinflate(bo_get_conf('strike_cells')));
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
	
	$res = bo_db("SELECT DISTINCT SUBSTRING(name, 9, 6) time
					FROM ".BO_DB_PREF."conf
					WHERE name LIKE 'strikes_".$year."%'
					ORDER BY time");
	while($row = $res->fetch_assoc())
	{
		$y = (int)substr($row['time'], 0, 4);
		$m = (int)substr($row['time'], 4, 2);
		
		$years[$y] = $y;
		$months[$m] = _BL(date('M', strtotime("$y-$m-01")));
	}

	//Add current month
	$years[(int)date('Y')] = date('Y');
	$months[(int)date('m')] = _BL(date('M'));

	
	if (!$years[(int)$year] || !$months[(int)$month])
	{
		$year = $y;
		$month = $m;
	}

	
	echo '<div id="bo_stat_strikes">';

	echo '<a name="graph_strikes_now"></a>';
	echo '<h3>'._BL('h3_stat_strikes_now').'</h3>';

	echo '<p class="bo_stat_description" id="bo_stat_strikes_now_descr">';
	echo strtr(_BL('bo_descr_strikes_now'), array('{UPDATE_INTERVAL}' => _BLN(BO_UP_INTVL_STRIKES, 'every_minute'), '{RATE_INTERVAL}' => _BLN($group_minutes)));
	echo '</p>';


	echo '<form action="?" method="GET" class="bo_stat_strikes_form">';
	echo bo_insert_html_hidden(array('bo_year', 'bo_month', 'bo_region'));
	echo '<fieldset>';
	echo '<legend>'._BL('legend_stat_strikes_now').'</legend>';
	echo '<span class="bo_form_descr">'._BL('Region').': </span>';
	bo_show_select_region($region);
	echo '</fieldset>';
	echo '</form>';

	
	echo '<ul class="bo_stat_overview">';
	
	echo '<li><span class="bo_descr">'._BL('Last update').': </span>';
	echo '<span class="bo_value">'._BL('_before').number_format($last_update_minutes, 1, _BL('.'), _BL(',')).' '.($last_update_minutes == 1 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span></li>';

	echo '<li><span class="bo_descr">'._BL('Last detected strike').': </span>';
	echo '<span class="bo_value">'.($last_strike ? date(_BL('_datetime'), $last_strike) : '?').'</span></li>';
	
	echo '<li><span class="bo_descr">'._BL('Current strike rate').': </span>';
	echo '<span class="bo_value">';
	echo number_format($strike_rate, 1, _BL('.'), _BL(',')).' '.(0 && $strike_rate === 1.0 ? _BL('unit_strikesperminute_one') : _BL('unit_strikesperminute'));
	echo '</span></li>';

	if (!$region && intval(BO_TRACKS_SCANTIME) && $num_cells >= 0)
	{
		echo '<li><span class="bo_descr">'._BL('Thunder cells').': </span>';
		echo '<span class="bo_value">'.number_format($num_cells, 0, _BL('.'), _BL(',')).' ('._BL('experimental').')</span></li>';
	}
	
	echo '</ul>';

	bo_show_graph('strikes_now', $add_graph.'&region='.$region);
	
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
	echo '</select>';

	echo '<select name="bo_month" onchange="submit();" id="bo_stat_strikes_select_month">';
	foreach($months as $i => $m)
		echo '<option value="'.$i.'" '.($i == $month ? 'selected' : '').'>'.$m.'</option>';
	echo '</select>';
	
	echo '</fieldset>';
	
	echo '</form>';
	
	echo '<a name="graph_strikes"></a>';
	echo '<h4>'._BL('h4_graph_strikes_time').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_strikes_time">';
	echo _BL('bo_graph_descr_strikes_time');
	echo '</p>';
	bo_show_graph('strikes_time', '&year='.$year.'&month='.$month);

	echo '<a name="graph_strikes"></a>';
	echo '<h4>'._BL('h4_graph_strikes_time_radius').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_strikes_time_radius">';
	echo strtr(_BL('bo_graph_descr_strikes_time_radius'), array('{RADIUS}' => BO_RADIUS));
	echo '</p>';
	bo_show_graph('strikes_time', '&year='.$year.'&month='.$month.'&radius=1');
	
	echo '</div>';
}

//show station-statistics
function bo_show_statistics_station($station_id = 0, $own_station = true, $add_graph = '')
{
	$own_station_info = bo_station_info();
	$stInfo = bo_station_info($station_id);
	$city = _BC($stInfo['city']);
	
	if ($stInfo['country'] != $own_station_info['country'])
		$city .= ' ('._BL(strtr($stInfo['country'], array(chr(160) => ' '))).')';
	
	$sql = "SELECT signalsh, strikesh, time FROM ".BO_DB_PREF."stations_stat WHERE station_id='$station_id' AND time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat WHERE station_id='$station_id')";
	$row = bo_db($sql)->fetch_assoc();
	$strikesh_own = $row['strikesh'];
	$signalsh_own = $row['signalsh'];
	$time_own = $row['time'] ? strtotime($row['time'].' UTC') : false;

	$row = bo_db("SELECT strikesh, time FROM ".BO_DB_PREF."stations_stat WHERE station_id='0' AND time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)")->fetch_assoc();
	$strikesh = $row['strikesh'];
	$time = strtotime($row['time'].' UTC');

	$row = bo_db("SELECT COUNT(station_id) cnt FROM ".BO_DB_PREF."stations_stat a WHERE time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)")->fetch_assoc();
	$stations = $row['cnt'] - 1;
	
	if ($own_station)
	{
		$sql = "SELECT COUNT(*) cnt FROM ".BO_DB_PREF."strikes 
				WHERE time BETWEEN '".gmdate('Y-m-d H:i:s', $time - 3600)."' AND '".gmdate('Y-m-d H:i:s', $time)."'
						AND part>0 AND users='".BO_MIN_PARTICIPANTS."'";
		$row = bo_db($sql)->fetch_assoc();
		$strikes_part_min_own = $row['cnt'];
		
		$act_time = bo_get_conf('station_last_active');
		$inact_time = bo_get_conf('station_last_inactive');
		$active = $act_time > $inact_time;
	}
	else
	{
		$sql = "SELECT COUNT(*) cnt FROM ".BO_DB_PREF."strikes s
				JOIN ".BO_DB_PREF."stations_strikes ss
				ON s.id=ss.strike_id AND ss.station_id='$station_id'
				WHERE time BETWEEN '".gmdate('Y-m-d H:i:s', $time - 3600)."' AND '".gmdate('Y-m-d H:i:s', $time)."'
						AND users='".BO_MIN_PARTICIPANTS."'";
		$row = bo_db($sql)->fetch_assoc();
		$strikes_part_min_own = $row['cnt'];
		
		$active = $time_own + 3600 >= $time;
	}
	
	$tmp = @unserialize(bo_get_conf('last_strikes_stations'));
	$last_strike = $tmp[$station_id][0];
	$last_signal = strtotime($stInfo['last_time'].' UTC');
	
	$last_update = (time()-$time)/60;
	$last_active = $time_own ? (time()-$time_own)/60 : false;

	echo '<h3>'.strtr(_BL('h3_stat_station'), array('{STATION_CITY}' => $city)).'</h3>';
	
	echo '<div id="bo_stat_station">';

	echo '<p class="bo_stat_description" id="bo_stat_station_descr_lasth">';
	echo strtr(_BL('bo_stat_station_descr_lasth'), array('{STATION_CITY}' => $city, '{MIN_PARTICIPANTS}' => BO_MIN_PARTICIPANTS));
	echo '</p>';
	
	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Station active').': </span><span class="bo_value">'.($active ? _BL('yes') : _BL('no')).'</span>';

	if (!$active)
	{
		echo '<li><span class="bo_descr">'._BL('Last active').': </span><span class="bo_value">';
		
		if ($last_active === false)
		{
			echo _BL('Never before');
		}
		else
		{
			echo _BL('_before').number_format($last_active, 1, _BL('.'), _BL(','))." ";
			echo (0 && $last_active == 1 ? _BL('_minute_ago') : _BL('_minutes_ago'));
		}
		
		echo '</span>';
	}

	echo '<li><span class="bo_descr">'._BL('Last update').': </span><span class="bo_value">'._BL('_before').number_format($last_update, 1, _BL('.'), _BL(','))." ".(0 && $last_update == 1 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Last detected strike').': </span>';
	echo '<span class="bo_value">';
	echo $last_strike ? date(_BL('_datetime'), $last_strike) : _BL('no_strike_yet');
	echo '</span>';
	echo '</li>';
	
	if ($active) //don't display this part when inactive, there may be still some non-zero values
	{
		echo '<li><span class="bo_descr">'._BL('Strikes').': </span><span class="bo_value">'.number_format($strikesh_own, 0, _BL('.'), _BL(',')).'</span>';
		echo '<li><span class="bo_descr">'._BL('Signals').': </span><span class="bo_value">'.number_format($signalsh_own, 0, _BL('.'), _BL(',')).'</span>';

		echo '<li><span class="bo_descr">'._BL('Locating ratio').': </span><span class="bo_value">';
		echo $signalsh_own ? number_format($strikesh_own / $signalsh_own * 100, 1, _BL('.'), _BL(',')).'%' : '-';
		echo '</span></li>';
		echo '<li><span class="bo_descr">'._BL('Strike ratio').': </span><span class="bo_value">';
		echo $strikesh ? number_format($strikesh_own / $strikesh * 100, 1, _BL('.'), _BL(',')).'%' : '-';
		echo '</span></li>';
		
		echo '<li><span class="bo_descr">'._BL('Strikes station min participants').': </span>';
		echo '<span class="bo_value">';
		echo number_format($strikes_part_min_own, 0, _BL('.'), _BL(','));
		
		if ($strikesh)
		{
			$part_own_percent = $strikes_part_min_own / $strikesh * 100;
			
			echo ' (';
			echo number_format($part_own_percent, 1, _BL('.'), _BL(',')).'%';
			echo ' - '._BL('Score').': '.number_format($part_own_percent * $stations, 0, _BL('.'), _BL(',')).'%';
			echo ') ';
		}
			
		echo '</span></li>';
	}
	elseif ($last_signal)
	{
		echo '<li><span class="bo_descr">'._BL('Last signal').': </span><span class="bo_value">'.date(_BL('_datetime'), $last_signal).'</span>';
	}
	
	echo '</ul>';

	if (!$own_station && (double)($stInfo['lat']) && (double)($stInfo['lon']))
	{
		echo '<div id="bo_gmap" class="bo_map_station" style="width:560px;height:200px"></div>';
		
		?>
		<script type="text/javascript">
		function bo_gmap_init2()
		{
			//noting to do ;-)
		}
		</script>
		<?php

		bo_insert_map(0, round($stInfo['lat'],2), round($stInfo['lon'],2), 10, 'HYBRID');
	}
	
	echo '<a name="graph_strikes"></a>';
	echo '<h4>'._BL('h4_graph_strikes').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_strikes">';
	echo strtr(_BL('bo_graph_descr_strikes'), array('{STATION_CITY}' => $city));
	echo '</p>';
	bo_show_graph('strikes', $add_graph);
	
	echo '<a name="graph_signals"></a>';
	echo '<h4>'._BL('h4_graph_signals').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_signals">';
	echo strtr(_BL('bo_graph_descr_signals'), array('{STATION_CITY}' => $city));
	echo '</p>';
	bo_show_graph('signals', $add_graph);


	echo '<a name="graph_ratio"></a>';
	echo '<h4>'._BL('h4_graph_ratio').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_ratio">';
	echo strtr(_BL('bo_graph_descr_ratio'), array('{STATION_CITY}' => $city));
	echo '</p>';
	bo_show_graph('ratio', $add_graph);

	echo '<a name="graph_ratio_distance"></a>';
	echo '<h4>'._BL('h4_graph_ratio_distance').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_radi">';
	echo strtr(_BL('bo_graph_descr_radi'), array('{STATION_CITY}' => $city));
	echo '</p>';
	bo_show_graph('ratio_distance', $add_graph);

	echo '<a name="graph_ratio_bearing"></a>';
	echo '<h4>'._BL('h4_graph_ratio_bearing').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_bear">';
	echo strtr(_BL('bo_graph_descr_bear'), array('{STATION_CITY}' => $city));
	echo '</p>';
	
	if (BO_GRAPH_STAT_RATIO_BEAR_WINDROSE === true)
		bo_show_graph('ratio_bearing', $add_graph, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE);
	else
		bo_show_graph('ratio_bearing', $add_graph);

	echo '</div>';
}

//show network-statistics
function bo_show_statistics_network($station_id = 0, $own_station = true, $add_graph = '')
{
	$sort = $_GET['bo_sort'];


	$date_1h = gmdate('Y-m-d H:i:s', time() - 3600);

	$row = bo_db("SELECT MAX(users) max_users, AVG(users) avg_users FROM ".BO_DB_PREF."strikes WHERE time > '$date_1h'")->fetch_assoc();
	$max_part = $row['max_users'];
	$avg_part = $row['avg_users'];

	$row = bo_db("SELECT strikesh, time FROM ".BO_DB_PREF."stations_stat WHERE station_id='0' AND time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)")->fetch_assoc();
	$strikesh = $row['strikesh'];
	$time = strtotime($row['time'].' UTC');

	// currently available stations
	$sql = "SELECT COUNT(*) cnt
			FROM ".BO_DB_PREF."stations
			WHERE status != '-'";
	$res = bo_db($sql);
	$row = $res->fetch_assoc();
	$available = $row['cnt'];
	
	$last_update = (time()-$time)/60;

	$whole_sig_count = 0;
	$whole_sig_ratio = 0;
	$whole_sig_ratio_cnt = 0;
	$whole_strike_ratio = 0;
	$whole_strike_ratio_cnt = 0;

	$D = array();
	$res = bo_db("SELECT a.id sid, a.city city, a.country country, a.distance distance, a.user user,
							b.signalsh signalsh, b.strikesh strikesh
					FROM ".BO_DB_PREF."stations a, ".BO_DB_PREF."stations_stat b
					WHERE 1
							AND a.id=b.station_id
							AND b.time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)
							-- AND b.signalsh > 0");
	while($row = $res->fetch_assoc())
	{
		$D[$row['sid']] = $row;
		$D[$row['sid']]['country'] = _BL(strtr($row['country'], array(chr(160) => ' ')));

		if ($row['signalsh'])
		{
			$D[$row['sid']]['signalsh_ratio'] = $row['strikesh'] / $row['signalsh'];
			$whole_sig_ratio += $row['strikesh'] / $row['signalsh'];
			$whole_sig_ratio_cnt++;
		}
		else
		{
			$D[$row['sid']]['signalsh_ratio'] = null;
		}

		if ($strikesh)
		{
			$D[$row['sid']]['strikesh_ratio'] = $row['strikesh'] / $strikesh;
			$whole_strike_ratio += $row['strikesh'] / $strikesh;
			$whole_strike_ratio_cnt++;
		}
		else
		{
			$D[$row['sid']]['strikesh_ratio'] = null;
		}

		//ToDo: Perhaps better algorithm
		if ($row['strikesh'] == 0 && $row['signalsh'] && $strikesh)
			$D[$row['sid']]['efficiency'] = -$row['signalsh'] / $strikesh;
		else
			$D[$row['sid']]['efficiency'] = sqrt($D[$row['sid']]['strikesh_ratio'] * $D[$row['sid']]['signalsh_ratio']);

		$whole_sig_count += $row['signalsh'];

		switch($sort)
		{
			default: $sort = 'strikes';
			case 'strikes':
				$S[$row['sid']] = $row['strikesh'];
				break;

			case 'city':
				$S[$row['sid']] = $row['city'];
				break;

			case 'country':
				$S[$row['sid']] = $row['country'];
				break;

			case 'distance':
				$S[$row['sid']] = $row['distance'];
				break;

			case 'user':
				$S[$row['sid']] = $row['user'];
				break;

			case 'id':
				$S[$row['sid']] = $row['sid'];
				break;

			case 'signals':
				$S[$row['sid']] = $row['signalsh'];
				break;

			case 'signals_ratio':
				$S[$row['sid']] = $D[$row['sid']]['signalsh_ratio'];
				break;

			case 'efficiency':
				$S[$row['sid']] = $D[$row['sid']]['efficiency'];
				break;

		}

	}

	if ($whole_strike_ratio_cnt)
		$whole_strike_ratio /= $whole_strike_ratio_cnt;

	if ($whole_sig_ratio_cnt)
		$whole_sig_ratio /= $whole_sig_ratio_cnt;

	echo '<div id="bo_stat_network">';

	echo '<img src="'.BO_FILE.'?map=stations_mini&blank" id="bo_stat_network_stations_map">';
	
	echo '<p class="bo_stat_description" id="bo_stat_network_descr_lasth">';
	echo _BL('bo_stat_network_descr_lasth');
	echo '</p>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Last update').': </span><span class="bo_value">'._BL('_before').number_format($last_update, 1, _BL('.'), _BL(',')).' '.($last_update == 1 && 0 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Active Stations').': </span><span class="bo_value">'.number_format(count($D), 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Available stations').': </span><span class="bo_value">'.number_format($available, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Sum of Strikes').': </span><span class="bo_value">'.number_format($strikesh, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max participants per strike').': </span><span class="bo_value">'.number_format($max_part, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Mean participants per strike').': </span><span class="bo_value">'.number_format($avg_part, 1, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Mean locating ratio').': </span><span class="bo_value">';
	echo $whole_sig_ratio ? number_format($whole_sig_ratio * 100, 1, _BL('.'), _BL(',')).'%' : '-';
	echo '</span></li>';
	echo '<li><span class="bo_descr">'._BL('Mean strike ratio').': </span><span class="bo_value">';
	echo $whole_strike_ratio ? number_format($whole_strike_ratio * 100, 1, _BL('.'), _BL(',')).'%' : '-';
	echo '</span></li>';
	echo '<li><span class="bo_descr">'._BL('Sum of Signals').': </span><span class="bo_value">'.number_format($whole_sig_count, 0, _BL('.'), _BL(',')).'</span>';
	echo '</ul>';

	echo '<a name="table_network"></a>';
	echo '<h4>'._BL('h4_table_network').'</h4>';

	echo '<p class="bo_stat_description" id="bo_stat_network_descr_table">';
	echo _BL('bo_stat_network_descr_table');
	echo '</p>';

	echo '<div id="bo_network_stations_container">';
	echo '<table id="bo_network_stations">';
	echo '<tr>
			<th rowspan="2">'._BL('Pos.').'</th>';
	
	if (bo_user_get_id() == 1)
	{
		echo '
			<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'id').'#table_network" rel="nofollow">'._BL('Id').'</a></th>
			<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'user').'#table_network" rel="nofollow">'._BL('User').'</a></th>';
	}
	
	echo '
			<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'country').'#table_network" rel="nofollow">'._BL('Country').'</a></th>
			<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'city').'#table_network" rel="nofollow">'._BL('City').'</a></th>
			<th rowspan="2"><a href="'.bo_insert_url('bo_sort', 'distance').'#table_network" rel="nofollow">'._BL('Distance').'</a></th>
			<th colspan="2">'._BL('Strikes/h').'</th>
			<th colspan="2">'._BL('Signals/h').'</th>
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
		case 'city': case 'country': case 'distance': case 'user': case 'id':
			asort($S);
			break;
		default:
			arsort($S);
			break;
	}
	
	$urls = unserialize(bo_get_conf('mybo_stations'));

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
			echo '<a href="'.BO_STATISTICS_URL.'&bo_show=station&bo_station_id='.$id.'" rel="nofollow">'.$pos.'</a>';
		else
			echo $pos;
			
		echo '</td>';

		if (bo_user_get_id() == 1)
		{
			echo '<td class="bo_text '.($sort == 'id' ? 'bo_marked' : '').'">';
			echo $d['sid'];
			echo '</td>';

			echo '<td class="bo_text '.($sort == 'user' ? 'bo_marked' : '').'">';
			echo $d['user'];
			echo '</td>';
		}
		
		
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
		echo number_format($d['distance'] / 1000, 0, _BL('.'), _BL(',')).'km';
		echo '</td>';

		echo '<td class="bo_numbers '.($sort == 'strikes' ? 'bo_marked' : '').'">';
		echo $d['strikesh'];
		echo '</td>';

		echo '<td class="bo_numbers '.($sort == 'strikes' ? 'bo_marked' : '').'">';
		echo number_format($d['strikesh_ratio'] * 100, 1, _BL('.'), _BL(',')).'%';
		echo '</td>';

		echo '<td class="bo_numbers '.($sort == 'signals' ? 'bo_marked' : '').'">';
		echo $d['signalsh'];
		echo '</td>';

		echo '<td class="bo_numbers '.($sort == 'signals_ratio' ? 'bo_marked' : '').'">';
		echo number_format($d['signalsh_ratio'] * 100, 1, _BL('.'), _BL(',')).'%';
		echo '</td>';

		echo '<td class="bo_numbers '.($sort == 'efficiency' ? 'bo_marked' : '').'">';
		echo number_format($d['efficiency'] * 100, 1, _BL('.'), _BL(',')).'%';
		echo '</td>';

		echo '</tr>';
		
		$pos++;
	}

	echo '</table>';

	echo '</div>';

	echo '<a name="graph_stations"></a>';
	echo '<h4>'._BL('h4_graph_stations').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_stations">';
	echo strtr(_BL('bo_graph_stations'), array('{STATION_CITY}' => $city));
	echo '</p>';
	bo_show_graph('stations', $add_graph);

	if (intval(BO_STATISTICS_SHOW_NEW_STATIONS))
	{
		$user_stations = bo_stations('user');

		$data = unserialize(bo_get_conf('stations_new_date'));
		
		$new_stations = array();
		foreach($data as $user => $time)
		{
			if ($time)
			{
				$country = _BL(strtr($user_stations[$user]['country'], array(chr(160) => ' ')));
				$id = $user_stations[$user]['id'];
				$new_stations[$id] = array($time, $user_stations[$user]['city'].' ('.$country.')');
			}
		}
		
		arsort($new_stations);
		
		if (count($new_stations))
		{
			echo '<a name="new_stations"></a>';
			echo '<h4>'._BL('h4_new_stations').'</h4>';

			echo '<ul class="bo_stat_overview">';
			
			$i = 0;
			foreach($new_stations as $id => $d)
			{
				echo '<li><span class="bo_descr">';
				
				if ( (bo_user_get_level() & BO_PERM_NOLIMIT) || (BO_STATISTICS_ALL_STATIONS == 2) )
				{
					echo '<a href="'.BO_STATISTICS_URL.'&bo_show=station&bo_station_id='.$id.'" rel="nofollow">';
					echo _BC($d[1]);
					echo '</a>';
				}
				else
					echo _BC($d[1]);
					
				echo '</span>';
				echo '<span class="bo_value">';
				echo date(_BL('_datetime'), $d[0]);
				echo '</span>';
				$i++;
				
				if ($i >= BO_STATISTICS_SHOW_NEW_STATIONS)
					break;
			}
			
			echo '</ul>';

		}
		
	}

	echo '<a name="graph_signals_all"></a>';
	echo '<h4>'._BL('h4_graph_signals_all').'</h4>';
	bo_show_graph('signals_all', $add_graph);
	
	echo '</div>';

}

//show longtime statistics
function bo_show_statistics_longtime($station_id = 0, $own_station = true, $add_graph = '')
{
	//Own
	$str_own	 		= bo_get_conf('count_strikes_own');
	$active_days 		= bo_get_conf('longtime_station_active_time') / 3600 / 24;
	$inactive_days 		= bo_get_conf('longtime_station_inactive_time') / 3600 / 24;
	$min_dist_own 		= bo_get_conf('longtime_min_dist_own') / 1000;
	$max_dist_own 		= bo_get_conf('longtime_max_dist_own') / 1000;
	$max_str_own 		= (double)bo_get_conf('longtime_max_strikesh_own');
	$max_sig_own 		= (double)bo_get_conf('longtime_max_signalsh_own');
	$max_str_day_own	= unserialize(bo_get_conf('longtime_max_strikes_day_own'));
	$max_str_dayrad_own	= unserialize(bo_get_conf('longtime_max_strikes_day_own_rad'));
	$signals 			= bo_get_conf('count_raw_signals');
	
	//Global
	$min_dist_all 		= bo_get_conf('longtime_min_dist_all') / 1000;
	$max_dist_all 		= bo_get_conf('longtime_max_dist_all') / 1000;
	$max_str_all 		= (double)bo_get_conf('longtime_max_strikesh');
	$max_sig_all 		= (double)bo_get_conf('longtime_max_signalsh');
	$max_str_day_all	= unserialize(bo_get_conf('longtime_max_strikes_day_all'));
	$max_str_dayrad_all	= unserialize(bo_get_conf('longtime_max_strikes_day_all_rad'));
	$max_active 		= (double)bo_get_conf('longtime_count_max_active_stations');
	$max_active_sig		= (double)bo_get_conf('longtime_count_max_active_stations_sig');
	$max_available		= (double)bo_get_conf('longtime_count_max_avail_stations');
	$max_part			= (double)bo_get_conf('longtime_max_participants');

	//MyBO
	$first_update		= bo_get_conf('first_update_time');
	$download_count     = bo_get_conf('upcount_strikes');
	$download_stat      = unserialize(bo_get_conf('download_statistics'));

	$kb_per_day = array();
	$kb_today = 0;
	$kb_traffic = 0;
	foreach($download_stat as $type => $d)
	{
		$kb_traffic += $d['traffic']  / 1024;
		$kb_today += $d['traffic_today']  / 1024;
		if ($d['time_first'])
		{
			$kb_per_day[$type] = $d['traffic']  / 1024 / (time() - $d['time_first']) * 3600 * 24;
		}
	}
	
	if (!$max_str_day_all[0])
		$max_str_day_all[1] = 0;
	if (!$max_str_dayrad_all[0])
		$max_str_dayrad_all[1] = 0;
	if (!$max_str_day_own[0])
		$max_str_day_own[1] = 0;
	if (!$max_str_dayrad_own[0])
		$max_str_dayrad_own[1] = 0;
	
	if (intval($signals))
		$signal_ratio = number_format($str_own / $signals * 100, 1, _BL('.'), _BL(',')).'%';
	else
		$signal_ratio = '-';
	
	echo '<div id="bo_stat_longtime">';

	echo '<p class="bo_stat_description" id="bo_stat_longtime_descr">';
	echo _BL('bo_stat_longtime_descr');
	echo '</p>';

	echo '<a name="longtime_station"></a>';
	echo '<h4>'._BL('h4_stat_longtime_station').'</h4>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Strikes detected').': </span><span class="bo_value">'.number_format($str_own, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Active').': </span><span class="bo_value">'.number_format($active_days, 1, _BL('.'), _BL(',')).' '._BL('days').'</span>';
	echo '<li><span class="bo_descr">'._BL('Inactive').': </span><span class="bo_value">'.number_format($inactive_days, 1, _BL('.'), _BL(',')).' '._BL('days').'</span>';
	echo '<li><span class="bo_descr">'._BL('Max strikes per hour').': </span><span class="bo_value">'.number_format($max_str_own, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max strikes per day').': </span><span class="bo_value">'.number_format($max_str_day_own[0], 0, _BL('.'), _BL(',')).($max_str_day_own[1] ? ' ('.date(_BL('_date'), strtotime($max_str_day_own[1])).')' : '' ).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max strikes per day').' (< '.BO_RADIUS.'km) : </span><span class="bo_value">'.number_format($max_str_dayrad_own[0], 0, _BL('.'), _BL(',')).($max_str_dayrad_own[1] ? ' ('.date(_BL('_date'), strtotime($max_str_dayrad_own[1])).')' : '').'</span>';
	echo '<li><span class="bo_descr">'._BL('Min dist').': </span><span class="bo_value">'.number_format($min_dist_own, 1, _BL('.'), _BL(',')).' '._BL('unit_kilometers').'</span>';
	echo '<li><span class="bo_descr">'._BL('Max dist').': </span><span class="bo_value">'.number_format($max_dist_own, 1, _BL('.'), _BL(',')).' '._BL('unit_kilometers').'</span>';
	echo '<li><span class="bo_descr">'._BL('Signals detected').': </span><span class="bo_value">'.number_format($signals, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Signal ratio').': </span><span class="bo_value">'.$signal_ratio.'</span>';
	echo '<li><span class="bo_descr">'._BL('Max signals per hour').': </span><span class="bo_value">'.number_format($max_sig_own, 0, _BL('.'), _BL(',')).'</span>';
	echo '</ul>';

	echo '<a name="longtime_network"></a>';
	echo '<h4>'._BL('h4_stat_longtime_network').'</h4>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Max strikes per hour').': </span><span class="bo_value">'.number_format($max_str_all, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max strikes per day').': </span><span class="bo_value">'.number_format($max_str_day_all[0], 0, _BL('.'), _BL(',')).($max_str_day_all[1] ? ' ('.date(_BL('_date'), strtotime($max_str_day_all[1])).')' : '').'</span>';
	echo '<li><span class="bo_descr">'._BL('Max strikes per day').' (< '.BO_RADIUS.'km) : </span><span class="bo_value">'.number_format($max_str_dayrad_all[0], 0, _BL('.'), _BL(',')).($max_str_dayrad_all[1] ? ' ('.date(_BL('_date'), strtotime($max_str_dayrad_all[1])).')' : '').'</span>';
	echo '<li><span class="bo_descr">'._BL('Min dist').': </span><span class="bo_value">'.number_format($min_dist_all, 1, _BL('.'), _BL(',')).' '._BL('unit_kilometers').'</span>';
	echo '<li><span class="bo_descr">'._BL('Max dist').': </span><span class="bo_value">'.number_format($max_dist_all, 1, _BL('.'), _BL(',')).' '._BL('unit_kilometers').'</span>';
	echo '<li><span class="bo_descr">'._BL('Max signals per hour').': </span><span class="bo_value">'.number_format($max_sig_all, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max participants per strike').': </span><span class="bo_value">'.number_format($max_part, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max active stations').': </span><span class="bo_value">'.number_format($max_active_sig, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Max available stations').': </span><span class="bo_value">'.number_format($max_available, 0, _BL('.'), _BL(',')).'</span>';
	echo '</ul>';

	echo '<a name="longtime_network"></a>';
	echo '<h4>'._BL('MyBlitzortung_notags').'</h4>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('First data').': </span><span class="bo_value">'.date(_BL('_datetime'), $first_update).'</span>';
	echo '<li><span class="bo_descr">'._BL('Lightning data imports').': </span><span class="bo_value">'.number_format($download_count, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Traffic to Blitzortung.org').': </span><span class="bo_value">'.number_format(array_sum($kb_per_day), 0, _BL('.'), _BL(',')).' kB/'._BL('day').'</span>';
	
	//print detailed stat
	if (bo_user_get_level() & BO_PERM_NOLIMIT)
	{
		$traffic_show = array('strikes' => 'Strikes', 'stations' => 'Stations', 'archive' => 'Signals');

		foreach($traffic_show as $type => $name)
		{
			$kb_per_day_single = $kb_per_day[$type];
			unset($kb_per_day[$type]);

			echo '<li><span class="bo_descr">'._BL('Traffic').' - '._BL($name).': </span><span class="bo_value">'.number_format($kb_per_day_single, 0, _BL('.'), _BL(',')).' kB/'._BL('day').'</span>';
		}
		
		echo '<li><span class="bo_descr">'._BL('Traffic').' - '._BL('Other').': </span><span class="bo_value">'.number_format(array_sum($kb_per_day), 0, _BL('.'), _BL(',')).' kB/'._BL('day').'</span>';
		echo '<li><span class="bo_descr">'._BL('Traffic').' - '._BL('Total').': </span><span class="bo_value">'.number_format($kb_traffic, 0, _BL('.'), _BL(',')).' kB</span>';		
		
	}
	
	echo '</ul>';

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
		bo_show_graph('ratio_bearing_longtime', $add_graph, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE);
	else
		bo_show_graph('ratio_bearing_longtime', $add_graph);

	echo '</div>';

}


//show own other statistics
function bo_show_statistics_other($station_id = 0, $own_station = true, $add_graph = '')
{
	$D = array();
	$tables = array('conf', 'raw', 'stations', 'stations_stat', 'stations_strikes', 'strikes', 'user', 'densities', 'cities');

	$res = bo_db("SHOW TABLE STATUS WHERE Name LIKE '".BO_DB_PREF."%'");
	while($row = $res->fetch_assoc())
	{
		$name = substr($row['Name'], strlen(BO_DB_PREF));

		if (array_search($name, $tables) !== false)
		{
			$D['rows'][$name] = $row['Rows'];
			$D['data'][$name] = $row['Data_length'];
			$D['keys'][$name] = $row['Index_length'];
		}
	}

	$last_str = bo_get_conf('uptime_strikes');
	$last_net = bo_get_conf('uptime_stations');
	$last_sig = bo_get_conf('uptime_raw');

	$mem_all = (array_sum($D['data']) + array_sum($D['keys'])) / 1024 / 1024;
	$mem_keys = array_sum($D['keys']) / (array_sum($D['data']) + array_sum($D['keys'])) * 100;
	$entries_all = array_sum($D['rows']);

	$download_stat      = unserialize(bo_get_conf('download_statistics'));
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
					.date(_BL('_datetime'), $last_str)
					.' ('._BL('update every').' '.intval(BO_UP_INTVL_STRIKES).' '._BL('unit_minutes').')'
				.'</span>';
	echo '<li><span class="bo_descr">'
				._BL('Last update stations').': </span><span class="bo_value">'
					.date(_BL('_datetime'), $last_net)
					.' ('._BL('update every').' '.intval(BO_UP_INTVL_STATIONS).' '._BL('unit_minutes').')'
				.'</span>';
	echo '<li><span class="bo_descr">'
				._BL('Last update signals').': </span><span class="bo_value">'
					.date(_BL('_datetime'), $last_sig)
					.' ('._BL('update every').' '.intval(BO_UP_INTVL_RAW).' '._BL('unit_minutes').')'
					.'</span>';

	echo '<li><span class="bo_descr">'._BL('Traffic to Blitzortung.org').': </span><span class="bo_value">'.number_format($kb_today, 0, _BL('.'), _BL(',')).' kB ('._BL('Today').')</span>';
					
	echo '</ul>';


	echo '<h4>'._BL('h4_stat_other_database').'</h4>';
	echo '<p class="bo_stat_description" id="bo_stat_other_descr_database">';
	echo _BL('bo_stat_other_database_descr');
	echo '</p>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Strikes').': </span><span class="bo_value">'.number_format($D['rows']['strikes'], 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Signals').': </span><span class="bo_value">'.number_format($D['rows']['raw'], 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Entries (all data)').': </span><span class="bo_value">'.number_format($entries_all, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li>
			<span class="bo_descr">'._BL('Memory usage').':
			</span><span class="bo_value">'.number_format($mem_all, 1, _BL('.'), _BL(',')).'MB
					('.number_format($mem_keys, 1, _BL('.'), _BL(',')).'% '._BL('for keys').')
			</span>';
	
	
	if (bo_user_get_level() & BO_PERM_NOLIMIT)
	{
		foreach($D['rows'] as $type => $rows)
		{
			echo '<li><span class="bo_descr">'._BL('Usage').' "'.$type.'": </span><span class="bo_value">';
			echo number_format($D['rows'][$type], 0, _BL('.'), _BL(',')).' rows / ';
			echo number_format( ($D['data'][$type]+$D['keys'][$type])  / 1024 / 1024, 1, _BL('.'), _BL(',')).' MB / ';
			echo number_format( $D['keys'][$type]/($D['data'][$type]+$D['keys'][$type])*100, 1, _BL('.'), _BL(',')).'% '._BL('for keys');
			echo '</span>';		
		
		}
	}
	
	echo '</ul>';
	
	if (BO_ANTENNAS == 2)
	{
		$ant1 = bo_get_conf('antenna1_bearing');
		$ant2 = bo_get_conf('antenna2_bearing');
		$show_ant = false;
		
		if ($ant1 !== '' && $ant1 !== null && $ant2 !== '' && $ant2 !== null)
		{
			$show_ant = true;
			$ant1 = round($ant1);
			$ant2 = round($ant2);
			
			echo '<h4>'._BL('h4_stat_other_antennas').'</h4>';
			echo '<p class="bo_stat_description" id="bo_stat_other_descr_antennas">';
			echo _BL('bo_stat_other_antennas_descr');
			echo '</p>';

			echo '<ul class="bo_stat_overview">';
			echo '<li><span class="bo_descr">'._BL('Direction antenna 1').': </span><span class="bo_value">'.$ant1.'&deg; - '.($ant1+180).'&deg ('.(_BL(bo_bearing2direction($ant1)).'-'._BL(bo_bearing2direction($ant1+180))).')</span>';
			echo '<li><span class="bo_descr">'._BL('Direction antenna 2').': </span><span class="bo_value">'.$ant2.'&deg; - '.($ant2+180).'&deg ('.(_BL(bo_bearing2direction($ant2)).'-'._BL(bo_bearing2direction($ant2+180))).')</span>';
			echo '</ul>';
			
		}
	}
	
	//Show GPS Info
	if ( (defined("BO_SHOW_GPS_INFO") && BO_SHOW_GPS_INFO) || (bo_user_get_level() & BO_PERM_SETTINGS) )
	{
		echo '<h4>'._BL('h4_stat_other_gps').'</h4>';
		echo '<p class="bo_stat_description" id="bo_stat_other_descr_gps">';
		echo _BL('bo_stat_other_gps_descr');
		echo '</p>';

		$js_data = '';
		$height = array();
		$lat = array();
		$lon = array();
		
		$res = bo_db("SELECT lat, lon, height
						FROM ".BO_DB_PREF."raw
						WHERE time > '".gmdate('Y-m-d H:i:s', time() - 24 * 3600)."'
						GROUP BY DAYOFMONTH(time), HOUR(time), FLOOR(MINUTE(time) / 5)
						ORDER BY time DESC");
		while($row = $res->fetch_assoc())
		{
			$js_data .= ($js_data ? ',' : '').'new google.maps.LatLng('.$row['lat'].','.$row['lon'].')';
			$height[] = $row['height'];
			$lat[] = $row['lat'];
			$lon[] = $row['lon'];
		}

		if (count($lat))
		{
			$st_lat = array_sum($lat) / count($lat);
			$st_lon = array_sum($lon) / count($lon);
			$st_height = round(array_sum($height) / count($height));
			
			//distance: mean deviation
			$dist_dev = 0;
			foreach($lat as $id => $val)
			{
				$dist_dev += bo_latlon2dist($st_lat, $st_lon, $lat[$id], $lon[$id]);
			}  
			$dist_dev /= count($lat); 

			//height: standard deviation
			$height_dev = 0;
			if (count($height) > 1)
			{
				foreach($height as $val)
				{
					$height_dev += pow($val-$st_height,2);
				}  
				$height_dev = sqrt($height_dev/(count($height)-1)); 
			}
						
		}
		else
		{
			$st_lat = BO_LAT;
			$st_lon = BO_LON;
		}
			
		if ($show_ant)
		{
			$dist = 50;
			list($lat1a, $lon1a) = bo_distbearing2latlong($dist, $ant1, $st_lat, $st_lon);
			list($lat1b, $lon1b) = bo_distbearing2latlong($dist, $ant1+180, $st_lat, $st_lon);
			list($lat2a, $lon2a) = bo_distbearing2latlong($dist, $ant2, $st_lat, $st_lon);
			list($lat2b, $lon2b) = bo_distbearing2latlong($dist, $ant2+180, $st_lat, $st_lon);
			
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
		
		echo '<ul class="bo_stat_overview">';
		
		if ($lat[0] && $lon[0])
		{
			
			echo '<li><span class="bo_descr">'._BL('Coordinates').': </span><span class="bo_value">'.number_format($st_lat,6,_BL('.'), _BL(',')).'&deg; / '.number_format($st_lon,6,_BL('.'), _BL(',')).'&deg (&plusmn;'.number_format($dist_dev, 1, _BL('.'), _BL(',')).'m)</span>';
			echo '<li><span class="bo_descr">'._BL('Height').': </span><span class="bo_value">'.$st_height.'m (&plusmn;'.number_format($height_dev, 1, _BL('.'), _BL(',')).'m)</span>';
			
		}
		else
			echo '<li><span class="bo_descr">'._BL('Currently no GPS coordinates available!').'</span>';
		
		echo '</ul>';
		
		echo '<div id="bo_gmap" class="bo_map_gps" style="width:250px;height:200px"></div>';
		


		?>
		<script type="text/javascript">

		function bo_gmap_init2()
		{
			var coordinates;
			coordinates = [ <?php echo $js_data ?> ];

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

				var bounds = new google.maps.LatLngBounds();
				for (var i = 0; i < coordinates.length; i++) {
					bounds.extend(coordinates[i]);
				}
				bo_map.fitBounds(bounds);
			}
			
			<?php echo $js_data_ant ?>
		}

		</script>

		<?php

		bo_insert_map(0, $st_lat, $st_lon, 19, 'ROADMAP');
	}
	
	

}

//show own other statistics
function bo_show_statistics_advanced($station_id = 0, $own_station = true, $add_graph = '')
{
	global $_BO;
	
	$show_options = array('strikes', 'strike_ratios', 'signals');
	
	$show = $_GET['bo_show2'];
	$region = $_GET['bo_region'];
	$channel = intval($_GET['bo_channel']);

	//Regions
	if (!preg_match('/[0-9a-z]+/i', $region) || !isset($_BO['region'][$region]['rect_add']))
		$region = '';
	else
		$add_graph .= '&region='.$region;
		
	if (!$own_station)
	{
		$show = '';
	}
	else
	{
		if ($channel)
			$add_graph .= '&channel='.$channel;
	}
	
	$channels = BO_ANTENNAS;
	$bpv      = bo_get_conf('raw_bitspervalue');
	$values   = bo_get_conf('raw_values');
	$utime    = bo_get_conf('raw_ntime') / 1000;

	$last_update = bo_get_conf('uptime_raw');
	$last_update_minutes = round((time()-$last_update)/60,1);
	
	echo '<div id="bo_stat_advanced">';

	echo '<p class="bo_stat_description" id="bo_stat_advanced_info">';
	echo _BL('bo_stat_advanced_info');
	echo '</p>';
	
	echo '<form action="?" method="GET" class="bo_stat_advanced_form">';
	
	echo bo_insert_html_hidden(array('bo_show2', 'bo_region', 'bo_channel'));
	echo '<fieldset>';
	echo '<legend>'._BL('legend_stat_advanced_options').'</legend>';
	
	echo '<span class="bo_form_group">';
	echo '<span class="bo_form_descr">'._BL('Show').': </span>';
	echo '<select name="bo_show2" onchange="submit();" id="bo_stat_advanced_select_show" '.($own_station ? '' : 'disabled').'>';
	foreach($show_options as $opt_name)
	{
		echo '<option value="'.$opt_name.'" '.($show == $opt_name ? 'selected' : '').'>'._BL('stat_advanced_show_'.$opt_name).'</option>';
	}
	echo '</select> ';
	echo '</span>&nbsp;';
	
	echo '<span class="bo_form_group">';
	echo '<span class="bo_form_descr">'._BL('Region').': </span>';
	bo_show_select_region($region);
	echo '</span>&nbsp;';
	
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

	echo '<script type="text/javascript">
	function bo_change_value (val,tid,name) {
		if (!name) name = "value";
		var regex = new RegExp("/&"+name+"=[^&]*&?/g");
		var img = document.getElementById("bo_graph_" + tid + "_img");
		img.src = img.src.replace(regex, "") + "&"+name+"=" + val;
	}
	function bo_change_radio (val, type) {
		var dis = val == 1;
		document.getElementById("bo_stat_"+type+"_time_min").disabled=dis;
		document.getElementById("bo_stat_"+type+"_time_max").disabled=dis;

		var img = document.getElementById("bo_graph_"+type+"_time_img");
		img.src = img.src.replace(/&average/g, "");
		img.src += val == 1 ? "&average" : "";
	}
	</script>
	';
	
	switch($show)
	{
		default: //Strikes
	
	
			/*** PARTICIPANTS ***/
			
			echo '<a name="graph_participants"></a>';
			echo '<h4>'._BL('h4_graph_participants').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_participants">';
			echo strtr(_BL('bo_graph_participants'), array('{MIN_PARTICIPANTS}' => BO_MIN_PARTICIPANTS));
			echo '</p>';
			if (BO_GRAPH_STAT_PARTICIPANTS_LOG === true)
				echo '<p class="bo_graph_description bo_graph_log_warn" ><strong>'._BL('bo_graph_log_warn').'</strong></p>';

			bo_show_graph('participants', $add_graph);

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
			echo '<select name="bo_participants_min" onchange="bo_change_value(this.value, \'participants_time\');" id="bo_stat_participants_time_min" disabled>';
			//echo '<option value="0">'._BL('Average').'</option>';
			for($i=BO_MIN_PARTICIPANTS;$i<150;$i+=$i<20?1:10)
				echo '<option value="'.$i.'">'.$i.'</option>';
			echo '</select> ';
			echo '</span>';
			echo '<span class="bo_form_group">';
			echo '<span class="bo_form_descr">'._BL('Max').':</span>';
			echo '<select name="bo_participants_max" onchange="bo_change_value(this.value, \'participants_time\', \'value_max\');" id="bo_stat_participants_time_max" disabled>';
			for($i=BO_MIN_PARTICIPANTS;$i<150;$i+=$i<20?1:10)
				echo '<option value="'.$i.'">'.$i.'</option>';
			echo '</select> ';
			echo '</span>';
			echo '</fieldset>';

			bo_show_graph('participants_time', $add_graph.'&average');
			
			/*** DEVIATIONS ***/
			
			echo '<a name="graph_deviations"></a>';
			echo '<h4>'._BL('h4_graph_deviations').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_deviations">';
			echo _BL('bo_graph_deviations');
			echo '</p>';
			if (BO_GRAPH_STAT_DEVIATIONS_LOG === true)
				echo '<p class="bo_graph_description bo_graph_log_warn" ><strong>'._BL('bo_graph_log_warn').'</strong></p>';

			bo_show_graph('deviations', $add_graph);
			

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
			echo '<select name="bo_region" onchange="bo_change_value(this.value, \'deviations_time\');" id="bo_stat_deviations_time_min" disabled>';
			for($i=0;$i<6000;$i+=100)
				echo '<option value="'.$i.'">'.number_format($i / 1000, 1, _BL('.'), _BL(',')).'</option>';
			echo '</select> ';
			echo '</span>';
			echo '<span class="bo_form_group">';
			echo '<span class="bo_form_descr">'._BL('Max').':</span>';
			echo '<select onchange="bo_change_value(this.value, \'deviations_time\', \'value_max\');" id="bo_stat_deviations_time_max" disabled>';
			for($i=0;$i<6000;$i+=100)
				echo '<option value="'.$i.'" '.($i == 1000 ? 'selected' : '').'>'.number_format($i / 1000, 1, _BL('.'), _BL(',')).'</option>';
			echo '</select> ';
			echo '</span>';
			echo '</fieldset>';
			
			bo_show_graph('deviations_time', $add_graph.'&average');
			
			
			/*** DISTANCE ***/
			if ($own_station)
			{
				echo '<a name="graph_distance"></a>';
				echo '<h4>'._BL('h4_graph_distance').'</h4>';
				echo '<p class="bo_graph_description" id="bo_graph_distance">';
				echo _BL('bo_graph_distance');
				echo '</p>';
				bo_show_graph('distance', $add_graph);
			}
			
			break;
	
		
		case 'strike_ratios':

			/*** RATIO DISTANCE ***/
			echo '<a name="graph_ratio_distance"></a>';
			echo '<h4>'._BL('h4_graph_ratio_distance').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_descr_radi">';
			echo _BL('bo_graph_descr_radi_adv');
			echo '</p>';
			bo_show_graph('ratio_distance', $add_graph);

			/*** RATIO BEARING ***/
			echo '<a name="graph_ratio_bearing"></a>';
			echo '<h4>'._BL('h4_graph_ratio_bearing').'</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_descr_bear">';
			echo _BL('bo_graph_descr_bear_adv');
			echo '</p>';
			
			if (BO_GRAPH_STAT_RATIO_BEAR_WINDROSE === true)
				bo_show_graph('ratio_bearing', $add_graph, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE);
			else
				bo_show_graph('ratio_bearing', $add_graph);
			
			/*** EVALUATED RATIO ***/
			echo '<a name="graph_evaluated_signals"></a>';
			echo '<h4>'._BL('h4_graph_evaluated_signals').' ('._BL('experimental').')</h4>';
			echo '<p class="bo_graph_description" id="bo_graph_evaluated_signals">';
			echo _BL('bo_graph_evaluated_signals');
			echo '</p>';
			bo_show_graph('evaluated_signals', $add_graph);
			
			break;
			
		case 'signals':
			
			echo '<h4>'._BL('h4_stat_signals').'</h4>';
			echo '<ul class="bo_stat_overview">';
			echo '<li><span class="bo_descr">'._BL('Last update').': </span>';
			echo '<span class="bo_value">'._BL('_before').number_format($last_update_minutes, 1, _BL('.'), _BL(',')).' '.($last_update_minutes == 1 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span></li>';
			echo '<li><span class="bo_descr">'._BL('Channels').': </span>';
			echo '<span class="bo_value">'.$channels.'</span></li>';
			echo '<li><span class="bo_descr">'._BL('Samples per Channel').': </span>';
			echo '<span class="bo_value">'.$values.'</span></li>';
			echo '<li><span class="bo_descr">'._BL('Recording time').': </span>';
			echo '<span class="bo_value">'.number_format($utime * $values, 0, _BL('.'), _BL(','))._BL('unit_us_short').'</span></li>';
			echo '<li><span class="bo_descr">'._BL('Bits per Sample').': </span>';
			echo '<span class="bo_value">'.$bpv.'</span></li>';
			echo '<li><span class="bo_descr">'._BL('Sample rate').': </span>';
			echo '<span class="bo_value">'.number_format(1 / $utime * 1000, 0, _BL('.'), _BL(',')).' '._BL('unit_ksps').'</span></li>';
			echo '</ul>';

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
			bo_show_graph('spectrum', $add_graph.'&type2=amp');

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
			echo '<select name="bo_participants_min" onchange="bo_change_value(this.value, \'frequencies_time\');" id="bo_stat_frequencies_time_min" disabled>';
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
			bo_show_graph('frequencies_time', $add_graph.'&average&value_max=30');
			
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
			bo_show_graph('amplitudes', $add_graph);
			
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
			echo '<select name="bo_participants_min" onchange="bo_change_value(this.value, \'amplitudes_time\');" id="bo_stat_amplitudes_time_min" disabled>';
			for($i=0;$i<=26;$i++)
				echo '<option value="'.round(($i/26)*BO_MAX_VOLTAGE*10).'">'.number_format(($i/26)*BO_MAX_VOLTAGE, 1, _BL('.'), _BL(',')).'V</option>';
			echo '</select> ';
			echo '</span>';
			echo '<span class="bo_form_group">';
			echo '<span class="bo_form_descr">'._BL('Max').':</span>';
			echo '<select name="bo_participants_max" onchange="bo_change_value(this.value, \'amplitudes_time\', \'value_max\');" id="bo_stat_amplitudes_time_max" disabled>';
			for($i=0;$i<=26;$i++)
				echo '<option value="'.round(($i/26)*BO_MAX_VOLTAGE*10).'" '.($i == 10 ? 'selected' : '').'>'.number_format(($i/26)*BO_MAX_VOLTAGE, 1, _BL('.'), _BL(',')).'V</option>';
			echo '</select> ';
			echo '</span>';
			echo '</div>';
			echo '</fieldset>';
			bo_show_graph('amplitudes_time', $add_graph.'&average&value_max=10');
			
			break;
	
		break;
	
	}
	
	echo '</div>';

}

function bo_show_graph($type, $add_graph='', $width=BO_GRAPH_STAT_W, $height=BO_GRAPH_STAT_H)
{
	$alt = _BL('graph_stat_title_'.$type);
	
	if ($alt == 'graph_stat_title_'.$type)
		$alt = '';
	else
		$alt = ': '.$alt;
	
	$alt = _BL('h3_graphs').$alt;
	
	echo '<img src="'.BO_FILE.'?graph_statistics='.$type.'&bo_lang='._BL().$add_graph.'" 
			class="bo_graph_img" 
			style="width:'.$width.'px;height:'.$height.'px;background-image:url(\''.BO_FILE.'?image=wait\');"
			id="bo_graph_'.$type.'_img"
			alt="'.htmlspecialchars($alt).'"
			>';
}

function bo_show_select_region($region)
{
	global $_BO;
	
	$regions = array();
	if (isset($_BO['region']) && is_array($_BO['region']))
	{
		foreach ($_BO['region'] as $reg_id => $d)
		{
			if ($d['visible'] && isset($d['rect_add']))
				$regions[$reg_id] = _BL($d['name']);
		}
		
		$regions[''] = _BL('No limit');
		ksort($regions);
	}
	

	if (count($regions) > 1)
	{
		echo '<select name="bo_region" onchange="submit();" id="bo_stat_strikes_select_now">';
		foreach($regions as $i => $y)
			echo '<option value="'.$i.'" '.($i === $region ? 'selected' : '').'>'.$y.'</option>';
		echo '</select>';
	}
	

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

?>