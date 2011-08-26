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

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
ini_set('magic_quotes_runtime', 0); 


$path = realpath(dirname(__FILE__).'/../').'/';

if (file_exists($path.'config.php'))
	require_once $path.'config.php';

require_once $path.'includes/default_settings.inc.php';

if (!class_exists('mysqli'))
	require_once $path.'includes/db_mysql.inc.php';
else
	require_once $path.'includes/db_mysqli.inc.php';

if ($_SERVER['HTTP_HOST'])
{
	$tmp = parse_url($_SERVER['REQUEST_URI']);
	$url_path = substr($tmp['path'], 0, -8);
}

$config_example = '<?php

/****************************************/
/*  Main Config file for MyBlitzortung  */
/****************************************/


/*** Database settings  ***/

define("BO_DB_USER", "###Database: Username###");
define("BO_DB_PASS", "###Database: Password###");
define("BO_DB_NAME", "###Database: Name of database###");
define("BO_DB_HOST", "localhost"); // should work in most cases
define("BO_DB_PREF", "mybo_"); // you can change this individually


/*** blitzortung.org Login ***/

define("BO_USER", "###Your Blitzortung.org Login###");
define("BO_PASS", "###Your Blitzortung.org Password###");


/*** Station info ***/

define("BO_LAT", "###Latitude of your Station###");
define("BO_LON", "###Longitude of your Station###");
define("BO_STATION_NAME", "###Name of your station (i.e. city name)###");


/*** Update secret  ***/
/*   For importing the strike data. You can leave it blank,   */
/*   but then everybody can trigger a data import!            */

define("BO_UPDATE_SECRET", "'.uniqid().'");


/*** Default language ***/

define("BO_LOCALE", "de");


/*** Time Zone setting ***/

define("BO_TIMEZONE", "Europe/Berlin");


?>';

$step = intval($_GET['step']);
$msg = 0;

if (!file_exists($path.'config.php'))
{
	if ($step)
		$msg = 1;

	$step = 0;
}
else
{
	$contents = file_get_contents($path.'config.php');
	
	if (!defined('BO_DB_HOST') || !defined('BO_DB_USER') || !defined('BO_DB_PASS') || !defined('BO_DB_NAME'))
	{
		$step = 0;
		$msg = 2;
	}
	else if (headers_sent())
	{
		$step = 0;
		$msg = 3;
	}
	elseif (!strpos(BO_LAT, '.') || !strpos(BO_LON, '.'))
	{
		$step = 0;
		$msg = 4;
	}
	elseif ($step != 1)
	{
		$connid = BoDb::connect(false);

		if ($connid === false)
		{
			$step = 1;
			$msg = 1;
		}
		elseif(!BoDb::select_db(false))
		{
			$step = 1;
			$msg = 2;
		}

		$erg = BoDb::query("SHOW TABLES");

		if (!$erg)
		{
			$msg = 3;
			$step = 1;
		}
		else
		{
			$tables = array('conf', 'raw', 'stations', 'stations_stat', 'stations_strikes', 'strikes', 'user', 'densities', 'cities');
			$rows = 0;
			$res = BoDb::query("SHOW TABLE STATUS WHERE Name LIKE '".BO_DB_PREF."%'");

			while($row = $res->fetch_assoc())
			{
				$name = substr($row['Name'], strlen(BO_DB_PREF));
				$id = array_search($name, $tables);

				if ($id !== false)
				{
					unset($tables[$id]);

					if ($name != 'user')
						$rows += $row['Rows'];
				}
			}

			if (count($tables)) // one or more tables missing
				$step = 2;
			else if (!count($tables) && $step == 2 && $rows < 10) //already installed --> no reinstall
				$step = 3;
			else if ($rows) // there's already sth in the database --> last step
				$step = 4;
		}
	}
}

echo '
<html>
<head>
<title>MyBlitzortung installation</title>
<link rel="stylesheet" href="../style.css" type="text/css">
<style>
	body { font-size: 100.01%; font-family: Arial,Helvetica,sans-serif; margin: 0;  padding: 0 0 10px 0; background: #f6f6f9; font-size: 12px; width: 800px;}
	#mybo_head h2 { display: block; margin-left:0 }
	h3 { margin-top: 40px; text-decoration:underline;}
</style>
</head>
<body>
<div id="mybo_head">
<h1><span class="bo_my">My</span>Blitzortung installation</h1> 

<div style="margin-left: 20px">

';

switch($step)
{

	default:
	case 0:

		$code = highlight_string($config_example, true);
		$code = preg_replace('/###(.*)###/U', '<strong><span style="color: green; text-decoration: underline; ">\1</span></strong>', $code);

		echo '<h2>Step 1: Create config file</h2>';

		echo '<p>Copy the following code, enter your own settings an save it as config.php in the directory,
				where you\'ve installed MyBlitzortung ';
		echo ' <em>('.$path.'config.php)</em>.';
		echo '</p>';
		echo '<p><b>Hints:</b> ';
		echo ' Use correct linebreak-format, i.e. Windows users should convert to Linux format because most webservers are running with Linux.';
		echo ' Don\'t forget the &lt;?php and ?&gt tag!';
		echo '</p>';


		if ($msg)
		{
			echo '<p style="color:red; font-weight: bold">';

			switch($msg)
			{
				case 1:
					echo "No config.php found!";
					break;
				case 2:
					echo "Please check your settings in config.php!";
					break;
				case 3:
					echo "You must remove all blank lines and spaces before &lt;?php and after ?&gt in config.php !!!";
					break;
				case 4:
					echo "Enter your correct latitude/longitude like 12.3456 (note: dot '.' not ',')!";
					break;
			}

			echo '</p>';
		}

		echo '<p><a href="?step=1">Continue to next step &gt;</a></p>';


		echo '<div style="width: 900px; font-family: Courier; border: 1px solid #999; padding: 1px 10px; font-size: 10pt; background: #fff;">';
		echo nl2br($code);
		echo '</div>';

		break;


	case 1:

		echo '<h2>Step 2: Check database</h2>';

		if ($msg)
		{
			echo '<p style="color:red; font-weight: bold">';

			switch($msg)
			{
				case 1:
					echo "Couldn't connect to database. Check your database Settings!";
					break;
				case 2:
					echo "Couldn't select the database. Check your database Settings!";
					break;
				default:
				case 3:
					echo "Database error. Check your database Settings!";
					break;

			}

			echo '</p>';

			echo '<p><a href="?step=1">Reload</a></p>';
		}
		else
		{
			echo '<p>Settings OK!</p>';
			echo '<p><a href="?step=2">Install database &gt;</a></p>';
		}

		break;

	case 2:

		echo '<h2>Step 3: Install database</h2>';

		$err = false;
		$contents = file_get_contents('db.sql');
		$contents = stripslashes($contents); // some php settings cause slashes being added ==> remove them
		$contents = strtr($contents, array('{BO_DB_PREF}' => BO_DB_PREF));
		$queries = explode(';', $contents);

		$queries[] = "INSERT IGNORE INTO ".BO_DB_PREF."user SET id=1, login='', password='', level=1, mail=''";

		foreach($queries as $query)
		{
			if (trim($query) && !BoDb::query($query))
			{
				echo 'Error: <p>'.htmlspecialchars($connid->error).'</p> at <p>'.htmlspecialchars($query).'</p>';
				$err = true;
			}
		}

		if ($err)
		{
			echo '<p style="color:red; font-weight: bold">';
			echo "Some errors occured! Please check if the tables exists.";
			echo '</p>';
			echo '<p><a href="?step=1">Try again.</a></p>';
		}
		else
		{
			echo '<p>Database installation done!</p>';
			if (!$rows)
				echo '<p><a href="?step=3">Test data collection</a> (This may take a while!)</p>';
			else
				echo '<p><a href="?step=4">Finish</a></p>';
		}

		break;

	case 3:

		include $path.'blitzortung.php';

		echo '<h2>Testing data collection</h2>';

		echo '<p>You should see some output and no error messages:</p>';

		flush();

		echo '<div style="font-family: Courier; font-size: 0.8em; border: 1px solid #999; padding: 10px; ">';
		bo_update_all(true);
		echo '</div>';

		echo '<p><a href="?step=4">Finish installation &gt;</a></p>';

		bo_set_conf('install_show_secret', time());
		bo_set_conf('version', BO_VER);
		
		break;

	case 4:
	
		include '../blitzortung.php';

		echo '<h2>Almost finished!</h2>';

		$file = BO_FILE;
		if (substr($file, 0, 1) != '/')
			$file = '../'.$file;

		$update_url = $file.'?update';
		
		if (defined('BO_UPDATE_SECRET') && BO_UPDATE_SECRET)
		{
			$secret_time = bo_get_conf('install_show_secret');
			if (time() - 60 * 30 < $secret_time)
				$update_url .= '&secret='.BO_UPDATE_SECRET;
			else
				$update_url .= '&secret=&lt;Insert BO_UPDATE_SECRET here&gt;';
		}
		
		echo '<p>Now it\'s on your own for the next steps:</p>';

		
		
		echo '<h3>Make sure, the following directories are writeable for the web-server:</h3>
				<ul>';
				
		echo '<li><em>cache</em>: <span style="color:';
		if (is_writeable('../cache/')) echo 'green;">OK.'; else echo 'red;">Not writeable! Please correct.';
		echo '</span></li><li><em>cache/icons</em>: <span style="color:';
		if (is_writeable('../cache/icons')) echo 'green;">OK.'; else echo 'red;">Not writeable! Please correct.';
		echo '</span></li><li><em>cache/maps</em>: <span style="color:';
		if (is_writeable('../cache/maps')) echo 'green;">OK.'; else echo 'red;">Not writeable! Please correct.';
		echo '</span></li><li><em>cache/tiles</em>: <span style="color:';
		if (is_writeable('../cache/tiles')) echo 'green;">OK.'; else echo 'red;">Not writeable! Please correct.';
		echo '</span></li>
				</ul>
				<p>
				For linux users:<ul><li> <em>chmod -R 777 '.$path.'cache</em></li></ul>
				</p>';
		echo '<h3>Set up the automatic data collection</h3>
					<p>This is the automatic update-url:
					<ul><li>
					<a href="'.$update_url.'" target="_blank">'.$update_url.'</a> </em>(the link may not work if you use your own helper-file)</em>
					<br>
					For example, you can use www.cronjob.de to automattically retrieve the URL. Use the link above by right-clicking it and "copy URL" ...
					<br>&nbsp;
					</li>
					<li>
					
					Use "'.$update_url.'&force" if you want to manually force an update (only for testing!).
					With the force-option, there is no internal timer! Do not use it periodically.
					It will cause high load on the Blitzortung-server!
					</li>
					</ul>
			';
		echo '<h3>Change your individual configuration</h3>
				<p>Perhaps you want to add some settings to config.php. See README or <em>includes/default_settings.inc.php</em> for details.
				German users should try these links:</p>
				<ul>
				<li><a target="_blank" href="http://www.myblitzortung.de/myblitzortung_config">Konfiguration (Allgemein)</a></li>
				<li><a target="_blank" href="http://www.myblitzortung.de/myblitzortung_config_maps">Konfiguration (Karten)</a></li>
				<li><a target="_blank" href="http://www.myblitzortung.de/myblitzortung_config_example">Konfiguration (Beispiel)</a></li>
				</ul>
				';
				
		echo '<h3>Copy JpGraph files.</h3>
			<p>JpGraph is used for creating the graphs.
			You can get it at <a href="http://jpgraph.net/download/">http://jpgraph.net/download/</a>. 
			Copy the files from the JpGraph-<em>src</em> direcory to includes/jpgraph. 
			You can omit the directories <em>src/barcode</em>, <em>src/Examples</em>, <em>src/themes</em> 
			and all files beginnig width <em>flag</em>.
			No further installation needed!
			</p>';

		echo '<h3>Finished!</h3>
				<p>Click <a href="../">here</a> to view your MyBlitzortung installation!</p>
				<p>You can login with your Blitzortung.org username/password.</p>';



		break;


}

echo '
</div>
</div>
</div>
</body>
</html>
';


?>