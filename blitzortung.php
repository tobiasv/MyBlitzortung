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

if (!defined("BO_VER"))
{
	define("BO_DIR", dirname(__FILE__).'/');
	define("BO_VER", '0.2.3');

	define("BO_PERM_ADMIN", 		1);
	define("BO_PERM_SETTINGS", 		2);
	define("BO_PERM_NOLIMIT", 		4);
	define("BO_PERM_ALERT", 		8);
	define("BO_PERM_ALERT_ALL",		16);
	define("BO_PERM_ALERT_SMS",		32);
	define("BO_PERM_ALERT_URL",		64);
	define("BO_PERM_COUNT",	7);
	
	//Some default PHP-Options
	ini_set('magic_quotes_runtime', 0); 
	
	//Config var.
	global $_BO, $_BL;
	$_BO = array();
	$_BL = array();

	if (!file_exists(BO_DIR.'config.php'))
		die('Missing config.php! Please run installation first!');

	if (!file_exists(BO_DIR.'settings.php'))
		die('Missing settings.php!');
		
	//Load Config
	require_once 'config.php';
	require_once 'settings.php';

	date_default_timezone_set(BO_TIMEZONE);
	
	if (defined('BO_DEBUG') && BO_DEBUG)
	{
		error_reporting(E_ALL & ~E_NOTICE);
		ini_set('display_errors', 1);
	}
	else
	{
		ini_set('display_errors', 0);
	}

	//Session handling
	@session_start();


	//Very simple locale support
	$locdir = BO_DIR.'locales/';
	include $locdir.'en.php'; // always include this first
	if (file_exists($locdir.BO_LOCALE.'.php'))
		include $locdir.BO_LOCALE.'.php';
	
	if (file_exists($locdir.'own.php'))
		include $locdir.'own.php';

	//includes #1
	require_once 'includes/functions.inc.php';
	require_once 'includes/image.inc.php';
	require_once 'includes/user.inc.php';
	
	if (!class_exists('mysqli'))
		require_once 'includes/db_mysql.inc.php';
	else
		require_once 'includes/db_mysqli.inc.php';
		
	define("BO_TILE_SIZE", 256);
	$_BO['radius'] = (bo_user_get_level() & BO_PERM_NOLIMIT) ? 0 : BO_RADIUS;

	//creating tiles should be very fast
	if (isset($_GET['tile']))
	{
		if (defined('BO_MAP_DISABLE') && BO_MAP_DISABLE && !(bo_user_get_level() & BO_PERM_NOLIMIT))
			exit('Google Maps disabled');
			
		bo_tile();
		exit;
	}

	// includes #2
	require_once 'includes/statistics.inc.php';
	require_once 'includes/import.inc.php';
	require_once 'includes/graphs.inc.php';
	require_once 'includes/map.inc.php';
	require_once 'includes/archive.inc.php';
	require_once 'includes/info.inc.php';
	require_once 'includes/alert.inc.php';


	//Update with new data from blitzortung.org
	$do_update = false;
	$force_update = false;
	if (isset($_GET['update']))
	{
		if (defined('BO_UPDATE_SECRET') && BO_UPDATE_SECRET && $_GET['secret'] !== BO_UPDATE_SECRET)
			exit('Wrong secret: "<b>'.htmlentities($_GET['secret']).'</b>"  Look in your config.php for "<b>BO_UPDATE_SECRET</b>"');

		$do_update = true;
		$force_update = isset($_GET['force']);
	}
	else if (isset($argv))
	{
		foreach ($argv as $a)
		{
			if ($a == 'update')
				$do_update = true;
			elseif ($a == 'force')
				$force_update = true;
		}
	}
	
	if ($do_update)
	{
		ini_set('allow_url_fopen', 'on'); //doesnt work
		bo_update_all($force_update);
		exit;
	}

	//graphics, login...
	if (isset($_GET['map']))
	{
		bo_get_map_image();
		exit;
	}
	else if (isset($_GET['icon']))
	{
		bo_icon($_GET['icon']);
		exit;
	}
	else if (isset($_GET['graph']))
	{
		bo_graph_raw($_GET['graph']);
		exit;
	}
	else if (isset($_GET['image']))
	{
		bo_get_image($_GET['image']);
		exit;
	}

	// include extra language (after images with caching machanism!)
	$locale = '';
	if (preg_match('/^[a-zA-Z]{2}$/', $_GET['bo_lang']))
	{
		$locale = strtolower($_GET['bo_lang']);
		$_SESSION['bo_locale'] = $locale;
		setcookie("bo_locale", $locale, time()+3600*24*365*10);
	}
	else if (isset($_COOKIE['bo_locale']) && preg_match('/^[a-zA-Z]{2}$/', $_COOKIE['bo_locale']))
		$locale = $_COOKIE['bo_locale'];
	else if (isset($_SESSION['bo_locale']))
		$locale = $_SESSION['bo_locale'];

	if ($locale && file_exists($locdir.$locale.'.php') && $locale != BO_LOCALE)
		 include $locdir.$locale.'.php';

	// these graphs have a lot of text and no caching --> translate them individually
	if (isset($_GET['graph_statistics']))
	{
		bo_graph_statistics($_GET['graph_statistics'], intval($_GET['id']), intval($_GET['hours']));
		exit;
	}
		 
	//workaround when no special login-url is specified
	if (!defined('BO_LOGIN_FILE') || !BO_LOGIN_FILE)
	{
		if (isset($_GET['bo_login']))
		{
			bo_show_login();
			exit;
		}
		
		if (isset($_GET['bo_logout']))
			bo_user_do_logout();
	}

}

?>