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

//show all available statistics and menu
function bo_show_statistics()
{
	$show = $_GET['bo_show'] ? $_GET['bo_show'] : 'strikes';

	echo '<div id="bo_statistics">';

	echo '<ul id="bo_menu">';

	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'strikes').'" class="bo_navi'.($show == 'strikes' ? '_active' : '').'">'._BL('stat_navi_strikes').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'station').'" class="bo_navi'.($show == 'station' ? '_active' : '').'">'._BL('stat_navi_station').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'network').'" class="bo_navi'.($show == 'network' ? '_active' : '').'">'._BL('stat_navi_network').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'longtime').'" class="bo_navi'.($show == 'longtime' ? '_active' : '').'">'._BL('stat_navi_longtime').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'other').'" class="bo_navi'.($show == 'other' ? '_active' : '').'">'._BL('stat_navi_other').'</a></li>';

	echo '</ul>';


	switch($show)
	{
		default:
		case 'strikes':
			echo '<h3>'._BL('h3_stat_strikes').'</h3>';
			bo_show_statistics_strikes();
			break;

		case 'station':
			bo_show_statistics_station();
			break;

		case 'longtime':
			echo '<h3>'._BL('h3_stat_longtime').'</h3>';
			bo_show_statistics_longtime();
			break;

		case 'network':
			echo '<h3>'._BL('h3_stat_network').'</h3>';
			bo_show_statistics_network();
			break;

		case 'other':
			echo '<h3>'._BL('h3_stat_other').'</h3>';
			bo_show_statistics_other();
			break;

	}

	echo '</div>';

	bo_copyright_footer();
}

//strike statistics 
function bo_show_statistics_strikes()
{
	$year = intval($_GET['bo_year']);
	$month = intval($_GET['bo_month']);
	
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
	
	if (!$years[(int)$year] || !$months[(int)$month])
	{
		$year = $y;
		$month = $m;
	}
	
	echo '<div id="bo_stat_strikes">';

	
	echo '<form action="?" method="GET" class="bo_stat_strikes_form">';
	echo bo_insert_html_hidden(array('bo_year', 'bo_month'));

	echo '<fieldset>';
	echo '<legend>'._BL('legend_stat_strikes').'</legend>';
	
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
	echo '<img src="'.BO_FILE.'?graph_statistics=strikes_time&year='.$year.'&month='.$month.'&bo_lang='._BL().'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	echo '<a name="graph_strikes"></a>';
	echo '<h4>'._BL('h4_graph_strikes_time_radius').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_strikes_time_radius">';
	echo strtr(_BL('bo_graph_descr_strikes_time_radius'), array('{RADIUS}' => BO_RADIUS));
	echo '</p>';
	echo '<img src="'.BO_FILE.'?graph_statistics=strikes_time&year='.$year.'&month='.$month.'&radius=1&bo_lang='._BL().'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	
	echo '</div>';

}

//show station-statistics
function bo_show_statistics_station()
{
	if (intval($_GET['bo_station_id']))
	{
		$station_id = intval($_GET['bo_station_id']);
		$add_graph = '&id='.$station_id;
	}
	else
	{
		$station_id = bo_station_id();
	}

	$tmp = bo_station_info($station_id);
	$city = _BC($tmp['city']);
	
	$row = bo_db("SELECT signalsh, strikesh, time FROM ".BO_DB_PREF."stations_stat WHERE station_id='$station_id' AND time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)")->fetch_assoc();
	$strikesh_own = $row['strikesh'];
	$signalsh_own = $row['signalsh'];
	$time_own = strtotime($row['time'].' UTC');

	$row = bo_db("SELECT strikesh, time FROM ".BO_DB_PREF."stations_stat WHERE station_id='0' AND time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)")->fetch_assoc();
	$strikesh = $row['strikesh'];
	$time = strtotime($row['time'].' UTC');

	$act_time = bo_get_conf('station_last_active');
	$inact_time = bo_get_conf('station_last_inactive');
	$active = $act_time > $inact_time;

	$last_update = round((time()-$time_own)/60);

	echo '<h3>'.strtr(_BL('h3_stat_station'), array('{STATION_CITY}' => $city)).'</h3>';
	
	echo '<div id="bo_stat_station">';

	echo '<p class="bo_stat_description" id="bo_stat_station_descr_lasth">';
	echo strtr(_BL('bo_stat_station_descr_lasth'), array('{STATION_CITY}' => $city));
	echo '</p>';
	

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Station active').': </span><span class="bo_value">'.($active ? _BL('yes') : _BL('no')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Last update').': </span><span class="bo_value">'._BL('_before')."$last_update ".($last_update == 1 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Strikes').': </span><span class="bo_value">'.number_format($strikesh_own, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Signals').': </span><span class="bo_value">'.number_format($signalsh_own, 0, _BL('.'), _BL(',')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Locating ratio').': </span><span class="bo_value">';
	echo $signalsh_own ? number_format($strikesh_own / $signalsh_own * 100, 1, _BL('.'), _BL(',')).'%' : '-';
	echo '</span></li>';
	echo '<li><span class="bo_descr">'._BL('Strike ratio').': </span><span class="bo_value">';
	echo $strikesh ? number_format($strikesh_own / $strikesh * 100, 1, _BL('.'), _BL(',')).'%' : '-';
	echo '</span></li>';
	echo '</ul>';

	echo '<a name="graph_strikes"></a>';
	echo '<h4>'._BL('h4_graph_strikes').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_strikes">';
	echo strtr(_BL('bo_graph_descr_strikes'), array('{STATION_CITY}' => $city));
	echo '</p>';
	echo '<img src="'.BO_FILE.'?graph_statistics=strikes&bo_lang='._BL().$add_graph.'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	echo '<a name="graph_signals"></a>';
	echo '<h4>'._BL('h4_graph_signals').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_signals">';
	echo strtr(_BL('bo_graph_descr_signals'), array('{STATION_CITY}' => $city));
	echo '</p>';
	echo '<img src="'.BO_FILE.'?graph_statistics=signals&bo_lang='._BL().$add_graph.'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	echo '<a name="graph_ratio"></a>';
	echo '<h4>'._BL('h4_graph_ratio').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_ratio">';
	echo strtr(_BL('bo_graph_descr_ratio'), array('{STATION_CITY}' => $city));
	echo '</p>';
	echo '<img src="'.BO_FILE.'?graph_statistics=ratio&bo_lang='._BL().$add_graph.'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	echo '<a name="graph_ratio_distance"></a>';
	echo '<h4>'._BL('h4_graph_ratio_distance').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_radi">';
	echo strtr(_BL('bo_graph_descr_radi'), array('{STATION_CITY}' => $city));
	echo '</p>';
	echo '<img src="'.BO_FILE.'?graph_statistics=ratio_distance&bo_lang='._BL().$add_graph.'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	echo '<a name="graph_ratio_bearing"></a>';
	echo '<h4>'._BL('h4_graph_ratio_bearing').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_bear">';
	echo strtr(_BL('bo_graph_descr_bear'), array('{STATION_CITY}' => $city));
	echo '</p>';
	echo '<img src="'.BO_FILE.'?graph_statistics=ratio_bearing&bo_lang='._BL().$add_graph.'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	echo '</div>';
}

//show network-statistics
function bo_show_statistics_network()
{
	$sort = $_GET['bo_sort'];
	$station_id = bo_station_id();

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
	
	$last_update = round((time()-$time)/60);

	$whole_sig_count = 0;
	$whole_sig_ratio = 0;
	$whole_sig_ratio_cnt = 0;
	$whole_strike_ratio = 0;
	$whole_strike_ratio_cnt = 0;

	$D = array();
	$res = bo_db("SELECT a.id sid, a.city city, a.country country, a.distance distance,
							b.signalsh signalsh, b.strikesh strikesh
					FROM ".BO_DB_PREF."stations a, ".BO_DB_PREF."stations_stat b
					WHERE 1
							AND a.id=b.station_id
							AND b.time=(SELECT MAX(time) FROM ".BO_DB_PREF."stations_stat)
							-- AND b.signalsh > 0");
	while($row = $res->fetch_assoc())
	{
		$D[$row['sid']] = $row;

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

	echo '<p class="bo_stat_description" id="bo_stat_network_descr_lasth">';
	echo _BL('bo_stat_network_descr_lasth');
	echo '</p>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Last update').': </span><span class="bo_value">'._BL('_before')."$last_update ".($last_update == 1 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span>';
	echo '<li><span class="bo_descr">'._BL('Active Stations').': </span><span class="bo_value">'.number_format(count($D), 0, _BL('.'), _BL(',')).(' ('._BL('available_of').' '.number_format($available, 0, _BL('.'), _BL(',')).' '._BL('available_stations').')').'</span>';
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
			<th rowspan="2">'._BL('Pos.').'</th>
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
		case 'city': case 'country': case 'distance':
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
		echo $pos++;
		echo '</td>';

		echo '<td class="bo_text '.($sort == 'country' ? 'bo_marked' : '').'">';
		echo _BC($d['country']);
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
	}

	echo '</table>';

	echo '</div>';

	echo '<a name="graph_stations"></a>';
	echo '<h4>'._BL('h4_graph_stations').'</h4>';
	echo '<img src="'.BO_FILE.'?graph_statistics=stations&bo_lang='._BL().'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	echo '</div>';

}

//show longtime statistics
function bo_show_statistics_longtime()
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
	
	echo '<div id="bo_stat_network">';

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
	echo '<h4>'._BL('h4_stat_longtime_myblitzortung').'</h4>';

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('First data').': </span><span class="bo_value">'.date(_BL('_datetime'), $first_update).'</span>';

	echo '</ul>';

	echo '<a name="graph_ratio_distance"></a>';
	echo '<h4>'._BL('h4_graph_ratio_distance_longtime').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_radi_longtime">';
	echo _BL('bo_graph_descr_radi_longtime');
	echo '</p>';
	echo '<img src="'.BO_FILE.'?graph_statistics=ratio_distance_longtime&bo_lang='._BL().'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	echo '<a name="graph_ratio_bearing"></a>';
	echo '<h4>'._BL('h4_graph_ratio_bearing_longtime').'</h4>';
	echo '<p class="bo_graph_description" id="bo_graph_descr_bear_longtime">';
	echo _BL('bo_graph_descr_bear_longtime');
	echo '</p>';
	echo '<img src="'.BO_FILE.'?graph_statistics=ratio_bearing_longtime&bo_lang='._BL().'" class="bo_graph_img" style="width:'.BO_GRAPH_STAT_W.'px;height:'.BO_GRAPH_STAT_H.'px;">';

	
	echo '</div>';

}


//show own other statistics
function bo_show_statistics_other()
{
	$D = array();
	$tables = array('conf', 'raw', 'stations', 'stations_stat', 'stations_strikes', 'strikes', 'user', 'densities');

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
	echo '</ul>';
	
	
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



?>