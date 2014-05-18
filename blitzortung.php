<?php
/*
    MyBlitzortung - a tool for participants of blitzortung.org
	to display lightning data on their web sites.

	Copyright 2011-2012 by Tobias Volgnandt & Blitzortung.org Participants



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

if (!defined("BO_VER"))
{
	//show all errors/warnings by default
	error_reporting(E_ALL & ~E_NOTICE);
	ini_set('display_errors', 1);
	

	define("BO_DIR", dirname(__FILE__).'/');
	define("BO_VER", '1.4-dev2');

	define("BO_PERM_ADMIN", 		1);
	define("BO_PERM_SETTINGS", 		2);
	define("BO_PERM_NOLIMIT", 		4);
	define("BO_PERM_ALERT", 		8);
	define("BO_PERM_ALERT_ALL",		16);
	define("BO_PERM_ALERT_SMS",		32);
	define("BO_PERM_ALERT_URL",		64);
	define("BO_PERM_ARCHIVE", 		128);
	define("BO_PERM_ALERT_TWITTER",	256);
	define("BO_PERM_COUNT",	        9);



	//Some default PHP-Options
	ini_set('magic_quotes_runtime', 0);

	//Config var.
	global $_BO, $_BL;
	$_BO = array();
	$_BL = array();

	if (!file_exists(BO_DIR.'config.php'))
		die('Missing config.php! Please run installation first!');

	//Load Config
	require_once 'includes/const.inc.php';
	require_once 'includes/templates_gmap.inc.php';
	require_once 'includes/templates.inc.php';
	require_once 'config.php';
	require_once 'includes/default_settings.inc.php'; //after config.php!
	require_once 'includes/default_templates.inc.php'; //after config.php!

	//includes #1
	require_once 'includes/functions.inc.php';
	require_once 'includes/functions_image.inc.php';
	require_once 'includes/functions_station.inc.php';
	require_once 'includes/functions_lang.inc.php';
	require_once 'includes/functions_geo.inc.php';
	require_once 'includes/functions_sql.inc.php';
	require_once 'includes/functions_signal.inc.php';
	require_once 'includes/functions_strokes.inc.php';
	require_once 'includes/data.inc.php';
	require_once 'includes/user.inc.php';
	

	//Classes
	require_once 'includes/classes/Db.class.php';
	require_once 'includes/classes/DateTime.class.php';

	
	//Debug Mode?
	if (BO_DEBUG === true)
	{
		error_reporting(E_ALL & ~E_NOTICE);
		ini_set('display_errors', 1);
	}
	elseif (BO_DEBUG === "file")
	{
		error_reporting(E_ALL & ~E_NOTICE);
		ini_set('display_errors', 0);
		set_error_handler("bo_error_handler");
	}
	elseif (BO_DEBUG === "silent")
	{
		ini_set('display_errors', 0);
		error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
	}
	else
	{
		ini_set('display_errors', 0);
	}


	//timezone
	date_default_timezone_set(BO_TIMEZONE);
	
	//User init (session, cookie, etc...)
	bo_user_init();
	
	//Cookie login
	bo_user_cookie_login();

	//Station init
	bo_station_init();
	

	//creating tiles should be very fast, other include files not needed
	if (isset($_GET['tile']))
	{
		if (defined('BO_MAP_DISABLE') && BO_MAP_DISABLE && !(bo_user_get_level() & BO_PERM_NOLIMIT))
			exit('Google Maps disabled');

			
		require_once 'includes/tiles.inc.php';
			
		if (isset($_GET['tracks']))
			bo_tile_tracks();
		else
			bo_tile();

		exit;
	}
	//phpinfo for admin
	else if ((BO_PERM_ADMIN & bo_user_get_level()) && $_GET['bo_action'] == 'phpinfo')
	{
		phpinfo();
		exit;
	}

	// includes #2
	require_once 'includes/pages.inc.php';
	
	

	//Save info wether headers where sent
	$_BO['headers_sent'] = headers_sent();

	//Update with new data from blitzortung.org
	$bo_do_update = false;
	$bo_force_update = false;
	if (isset($_GET['update']))
	{
		if (defined('BO_UPDATE_SECRET') && BO_UPDATE_SECRET && $_GET['secret'] !== BO_UPDATE_SECRET)
			exit('Wrong secret: "<b>'.htmlentities($_GET['secret']).'</b>"  Look in your config.php for "<b>BO_UPDATE_SECRET</b>"');

		$bo_do_update = true;
		$bo_force_update = isset($_GET['force']);

		header("Content-Type: text/plain");
	}
	else if (isset($argv))
	{
		foreach ($argv as $a)
		{
			if ($a == 'update')
				$bo_do_update = true;
			elseif ($a == 'force')
				$bo_force_update = true;
		}
	}

	//load locale after tiles
	$bo_redir_lang = bo_load_locale();
	
	//decisions what to do begins...
	if ($bo_do_update)
	{
		require_once 'includes/import.inc.php';
		bo_update_all($bo_force_update, strtolower($_GET['only']));
		exit;
	}
	else if (isset($_POST['bo_do_login']))
	{
		//Login
		$bo_login_name   = BoDb::esc(bo_gpc_prepare($_POST['bo_user']));
		$bo_login_pass   = BoDb::esc(bo_gpc_prepare($_POST['bo_pass']));
		$bo_login_cookie = $_POST['bo_login_cookie'] ? true : false;

		if (!bo_user_do_login($bo_login_name, $bo_login_pass, $bo_login_cookie))
			$_BO['login_fail'] = true;
	}
	else if (isset($_GET['bo_logout']))
	{
		//Logout
		bo_user_do_logout();
	}
	else if (isset($_GET['bo_login']) && (!defined('BO_LOGIN_URL') || !BO_LOGIN_URL))
	{
		//login-screen: workaround when no special login-url is specified
		bo_show_login();
		exit;
	}
	else if (!headers_sent())
	{

		if (isset($_GET['bo_icon']))
		{
			require_once 'includes/image.inc.php';
			bo_icon($_GET['bo_icon']);
			exit;
		}
		else if (isset($_GET['bo_graph'])) 
		{
			require_once 'includes/graphs.inc.php';
			bo_graph_raw();
			exit;
		}
		else if (isset($_GET['image']))
		{
			require_once 'includes/image.inc.php';
			bo_get_image($_GET['image']);
			exit;
		}
		else if (isset($_GET['graph_statistics']))
		{
			require_once 'includes/graphs.inc.php';
			bo_graph_statistics();
			exit;
		}
		else if (isset($_GET['density']))
		{
			require_once 'includes/density.inc.php';
			bo_get_density_image();
			exit;
		}
		elseif (isset($_GET['map']))
		{
			require_once 'includes/image.inc.php';
			bo_get_map_image();
			exit;
		}
		elseif (isset($_GET['animation']))
		{
			require_once 'includes/image.inc.php';
			bo_get_map_image_ani();
			exit;
		}
	}

	//Order maps
	if (defined('BO_MAPS_ORDER') && strlen(BO_MAPS_ORDER))
	{
		$bo_order = explode(',',BO_MAPS_ORDER);
		$bo_tmp = array();
		ksort($_BO['mapimg']);

		foreach($bo_order as $id)
		{
			$bo_tmp[$id] = $_BO['mapimg'][$id];
		}

		foreach($_BO['mapimg'] as $id => $data)
		{
			if (!isset($bo_tmp[$id]))
				$bo_tmp[$id] = $_BO['mapimg'][$id];
		}

		$_BO['mapimg'] = $bo_tmp;
	}
	
	if (isset($_GET['kml']))
	{
		bo_output_kml();
		exit;
	}
	else if (isset($_GET['stations_json']))
	{
		echo bo_stations_json();
		exit;
	}
	
	if (BO_SEND_CACHE_HEADER_HTML > 0 && !headers_sent())
	{
		if (intval($_COOKIE['bo_select_stationid']) || bo_user_get_level() || bo_sess_parms_set())
		{
			$bo_max_age = 5;
			header("Cache-Control: private, max-age=".$bo_max_age);
		}
		else
		{
			$bo_max_age = BO_SEND_CACHE_HEADER_HTML;
			header("Cache-Control: public, max-age=".$bo_max_age);
		}

		$bo_data_time = intval(time()/60)*60 - $bo_max_age;
		header("Pragma: ");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $bo_data_time)." GMT");
		header("Expires: ".gmdate("D, d M Y H:i:s", time() + $bo_max_age - 1) ." GMT");
		
		
	}

	
	//Redirect to correct language (only on pages)
	if ($bo_redir_lang)
	{
		$url = bo_insert_url(BO_LANG_ARGUMENT, $bo_redir_lang, true);
		header("Location: http://".$_SERVER['HTTP_HOST'].$url);
		exit;
	}

	require_once 'includes/pages.inc.php';
	require_once 'includes/functions_html.inc.php';

	
}


?>