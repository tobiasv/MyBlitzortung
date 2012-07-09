<?php


function bo_show_map($var1=null,$var2=null)
{
	require_once 'map.inc.php';
	bo_show_lightning_map($var1,$var2);
	bo_copyright_footer();
}


function bo_show_archive()
{
	if (BO_DISABLE_ARCHIVE === true)
		return;

	require_once 'archive.inc.php';
	require_once 'density.inc.php';	
		
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
	
	echo '<div id="bo_archives">';
	
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
			echo '<h3 class="bo_main_title">'._BL('h3_arch_maps').' </h3>';
			bo_show_archive_map();
			break;

		case 'density':
			echo '<h3 class="bo_main_title">'._BL('h3_arch_density').' </h3>';
			bo_show_archive_density();
			break;
		
		default:
		case 'search':
			echo '<h3 class="bo_main_title">'._BL('h3_arch_search').' </h3>';
			bo_show_archive_search();
			break;

		case 'signals':
			echo '<h3 class="bo_main_title">'._BL('h3_arch_last_signals').'</h3>';
			bo_show_archive_table();
			break;
		
		case 'strikes':
			echo '<h3 class="bo_main_title">'._BL('h3_arch_last_strikes').'</h3>';
			bo_show_archive_table(true);
			break;
	}

	echo '</div>';


	bo_copyright_footer();

}




//show all available statistics and menu
function bo_show_statistics()
{
	require_once 'statistics.inc.php';
	
	$show = $_GET['bo_show'] ? $_GET['bo_show'] : 'strikes';

	if (defined('BO_STATISTICS_ALL_STATIONS') && BO_STATISTICS_ALL_STATIONS || ((bo_user_get_level() & BO_PERM_NOLIMIT)))
	{
		$station_id = intval($_GET['bo_station_id']);

		if ($station_id && $station_id != bo_station_id())
		{
			$add_stid = 'bo_station_id='.$station_id;
			$add_graph = '&bo_station_id='.$station_id;
			$own_station = false;
			$city = trim(bo_station_city($station_id));
		}
	}

	if ( (!$station_id || !$city) && bo_station_id() > 0)
	{
		$station_id = bo_station_id();
		$own_station = true;
		$city = trim(bo_station_city($station_id));
		$add_stid = '';
	}

	if (!($station_id == bo_station_id() || BO_ENABLE_LONGTIME_ALL === true) && $show == 'longtime')
		$show = 'station';

	echo '<div id="bo_statistics">';

	echo '<ul id="bo_menu">';

	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'strikes').$add_stid.'" class="bo_navi'.($show == 'strikes' ? '_active' : '').'">'._BL('stat_navi_strikes').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'station').$add_stid.'" class="bo_navi'.($show == 'station' ? '_active' : '').'">'._BL('stat_navi_station').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'network').$add_stid.'" class="bo_navi'.($show == 'network' ? '_active' : '').'">'._BL('stat_navi_network').'</a></li>';

	if ($station_id == bo_station_id() || BO_ENABLE_LONGTIME_ALL === true)
		echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'longtime').$add_stid.'" class="bo_navi'.($show == 'longtime' ? '_active' : '').'">'._BL('stat_navi_longtime').'</a></li>';

	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'other').$add_stid.'" class="bo_navi'.($show == 'other' ? '_active' : '').'">'._BL('stat_navi_other').'</a></li>';
	echo '<li><a href="'.bo_insert_url(array('bo_show', 'bo_*'), 'advanced').$add_stid.'" class="bo_navi'.($show == 'advanced' ? '_active' : '').'">'._BL('stat_navi_advanced').'</a></li>';

	echo '</ul>';

	if (bo_station_id() < 0)
	{
		echo '<div id="bo_stat_station_select">';
		echo '<fieldset>';
		echo '<form>';
		echo _BL('Select station').': ';
		echo bo_insert_html_hidden(array('bo_station_id'));
		echo bo_get_stations_html_select($station_id);
		echo '</form>';
		echo '</fieldset>';
		echo '</div>';
	}

	if (bo_station_id() >= 0 || $station_id || !$show || $show == 'strikes' || $show == 'network' || $show == 'other')
	{

		if ($add_stid && bo_station_id() >= 0)
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
	}
	echo '</div>';

?>
<script type="text/javascript">
function bo_change_value (val,tid,name) {
	var regex = new RegExp("&"+name+"=[^&]*&?", "g");
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
<?php
	
	bo_copyright_footer();
}



function bo_show_login()
{
	global $_BO;

	if (!defined('BO_LOGIN_ALLOW') || (BO_LOGIN_ALLOW != 1 && BO_LOGIN_ALLOW != 2))
	{
		echo _BL('Login not allowed');
		return;
	}

	$login_fail = false;

	$remove_vars = array('bo_*','login','id');

	if (bo_user_get_id())
	{
		$level = bo_user_get_level();
		$show = $_GET['bo_action'];

		echo '<ul id="bo_menu">';

		echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=" class="bo_navi'.($show == '' ? '_active' : '').'">'._BL('Start').'</a>';
		if (bo_user_get_id() > 1 && $_SESSION['bo_external_login'] !== true)
			echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=password" class="bo_navi'.($show == 'password' ? '_active' : '').'">'._BL('Password').'</a>';

		if (BO_PERM_ADMIN & $level)
			echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=user_settings" class="bo_navi'.($show == 'user_settings' ? '_active' : '').'">'._BL('Add/Remove User').'</a>';

		if (BO_PERM_ADMIN & $level)
			echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=admin" class="bo_navi'.($show == 'admin' ? '_active' : '').'">'._BL('Administration').'</a>';

		if (defined('BO_ALERTS') && BO_ALERTS && ($level & BO_PERM_ALERT))
			echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=alert" class="bo_navi'.($show == 'alert' ? '_active' : '').'" class="bo_navi'.($show == 'alert' ? '_active' : '').'">'._BL('Strike alert').'</a></li>';

		echo '</ul>';

		if (bo_user_get_id() == 1)
		{
			require_once 'update.inc.php';

			if (bo_check_for_update() == true)
			{
				bo_copyright_footer();
				return;
			}
		}

		switch($show)
		{

			case 'admin':
				if (BO_PERM_ADMIN & $level)
					bo_user_show_admin();
				break;

			case 'user_settings':
				if (BO_PERM_ADMIN & $level)
					bo_user_show_useradmin();
				break;

			case 'password':
				if (bo_user_get_id() > 1)
					bo_user_show_passw_change();
				break;

			case 'alert':
				if (BO_PERM_ALERT & $level)
				{
					require_once 'alert.inc.php';
					bo_alert_settings();
				}
				break;



			default:

				$lastlogin = bo_get_conf_user('lastlogin');
				$sessiontime = time() - $_SESSION['bo_login_time'];

				echo '<h3>'._BL('Welcome to MyBlitzortung user area').'!</h3>';
				echo '<ul class="bo_login_info">';
				echo '<li>'._BL('user_welcome_text').': <strong>'._BC(bo_user_get_name()).'</strong></li>';
 
				if ($lastlogin && $_SESSION['bo_external_login'] !== true)
					echo '<li>'._BL('user_lastlogin_text').': <strong>'._BDT($lastlogin).'</strong></li>';

				echo '<li>'._BL('user_sessiontime_text').': <strong>'._BN($sessiontime / 60, 1).' '._BL('unit_minutes').'</strong></li>';
				echo '</ul>';

				if (BO_PERM_ADMIN & $level)
				{
					if (file_exists(BO_DIR.'settings.php'))
						echo '<p style="color:red"><strong>Warning: File <u>settings.php</u> found!</strong><br>Since version 0.3.1 standard values and settings are saved internally. For individual setting edit config.php and enter your individual settings there. Delete settings.php to hide this message.</p>';
				}

				echo '<h4>'._BL('Version information').'</h4>';
				echo '<ul>';
				echo '<li>'._BL('MyBlitzortung version').': <strong>'.bo_get_conf('version').'</strong></li>';
				if (BO_PERM_ADMIN & $level)
				{
					$res = BoDb::query("SHOW VARIABLES LIKE 'version'");
					$row = $res->fetch_assoc();
					$mysql_ver = $row['Value'];

					echo '<li>'._BL('PHP version').': '.phpversion().' (<a href="'.bo_insert_url($remove_vars).'&bo_action=phpinfo" target="_blank">'._BL('Show PHP info').'</a>)</li>';
					echo '<li>'._BL('MySQL version').': '.$mysql_ver.'</li>';
				}
				echo '</ul>';

				break;
		}



	}
	else
	{
		bo_show_login_form($_BO['login_fail']);
	}

	bo_copyright_footer();

}



function bo_show_info()
{
	require_once 'info.inc.php';
	bo_show_info_page1();
}

?>