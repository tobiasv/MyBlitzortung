<?php

//workaround for myblitzortung.ORG
@define(BO_DB_PREF_USER, BO_DB_PREF);


function bo_user_show_admin()
{
	$show = $_GET['bo_action_admin'];
	$url = bo_insert_url(array('bo_*','login','id')).'&bo_action=admin&bo_action_admin=';

	switch($show)
	{
		case 'calibrate':
			bo_show_calibrate_antennas();
			break;

		case 'update':
			require_once 'import.inc.php';
			echo '<h4>'._BL('Importing data...').'</h4>';
			echo '<div style="display:none;">'.str_repeat('&nbsp;', 1000).'</div>'; //send some output, so that browsers display the page
			echo '<div style="border: 1px solid #999; padding: 10px; font-size:8pt;"><pre>';
			bo_update_all(true, $_GET['bo_only']);
			echo '</div></pre>';
			break;

		case 'cache_info':
			bo_cache_info();
			break;

		case 'strike_keys':
			bo_session_close();
			echo '<h4>'._BL('Updating database keys...').'</h4>';
			echo '<div style="border: 1px solid #999; padding: 10px; font-size:8pt;"><pre>';
			bo_db_recreate_strike_keys();
			echo '</div></pre>';
			break;

		case 'delete_stations':
			bo_session_close();
			echo '<h4>'._BL('Deleting Stations...').'</h4>';
			echo '<div style="border: 1px solid #999; padding: 10px; font-size:8pt;"><pre>';
			delete_stations(explode(',', $_GET['ids']));
			echo '</div></pre>';
			break;
			
		case 'delete_old_stations':
			bo_session_close();
			echo '<h4>'._BL('Purging deleted/old stations...').'</h4>';
			echo '<div style="border: 1px solid #999; padding: 10px; font-size:8pt;"><pre>';
			bo_purge_deleted_stations();
			echo '</div></pre>';
			break;
		
		case 'delete_duplicates':
			bo_session_close();
			echo '<h4>'._BL('Deleting duplicate strokes...').'</h4>';
			bo_strikes_delete_duplicate();
			echo '<p>'.$num.' '._BL('strokes deleted').'!</p>';
			break;
			
		case 'cities':
			bo_import_cities();
			break;

		default:

			echo '<h4>'._BL('Admin tools').'</h4>';

			echo '<ul>';
			echo '<li><a href="'.$url.'cache_info">'._BL('File cache info').'</a></li>';
			echo '<li><a href="'.$url.'cities">'._BL('Read cities.txt').'</a></li>';
			//echo '<li><a href="'.$url.'calibrate" class="bo_navi'.($show == 'calibrate' ? '_active' : '').'">'._BL('Calibrate Antennas').'</a>';
			echo '</ul>';

			echo '<h5>'._BL('Import/update data').'</h5>';
			echo '<ul>';
			echo '<li><strong><a href="'.$url.'update">'._BL('Do manual update').'</strong></a></li>';
			echo '<li><a href="'.$url.'update&bo_only=strikes">'._BL('Update only strikes').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=stations">'._BL('Update only stations').'</a></li>';
			//echo '<li><a href="'.$url.'update&bo_only=signals">'._BL('Update only signals').'</a></li>';
			//echo '<li><a href="'.$url.'update&bo_only=daily">'._BL('Update only daily statistics').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=density">'._BL('Update only densities').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=tracks">'._BL('Update only tracks').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=download">'._BL('Download only external files').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=purge">'._BL('Force data purge only').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=alerts">'._BL('Check alerts only').'</a></li>';
			echo '<li><a href="'.$url.'strike_keys">'._BL('Update database keys').'</a></li>';
			echo '<li><a href="'.$url.'delete_old_stations">'._BL('Delete old stations').'</a></li>';
			echo '</ul>';

			echo '<h5>'._BL('Specials').'</h5>';
			echo '<ul>';
			echo '<li><a href="'.bo_bofile_url().'?kml">Google Earth KML</a></li>';
			echo '</ul>';

			echo '<h5>'._BL('Documentation').'</h5>';
			echo '<ul>';
			echo '<li><a href="'.dirname(BO_FILE).'/README" target="_blank">README</a></li>';
			echo '<li><a href="http://www.blitzortung.org/Webpages/index.php?page=6" target="_blank">Blitzortung.org forum</a></li>';
			echo '<li><a href="http://www.lightningmaps.org/doc/intro" target="_blank">www.LightningMaps.org</a></li>';
			echo '<li><a href="http://www.faq-blitzortung.org/index.php?sid=267611&lang=de&action=show&cat=18" target="_blank">FAQ</a></li>';
			echo '</ul>';


			break;
	}


}

function bo_show_login_form($fail = false)
{
	global $_BO;

	echo '<div id="bo_login">';

	if ($fail)
		echo '<div class="bo_info_fail">'._BL('Login fail!').'</div>';

	echo '<form action="'.bo_insert_url('bo_logout').bo_add_sess_parms(true).'" method="POST" class="bo_login_form">';

	echo '<fieldset class="bo_login_fieldset">';
	echo '<legend>'._BL('login_legend').'</legend>';

	echo '<span class="bo_form_descr">'._BL('Login').':</span>';
	echo '<input type="text" name="bo_user" id="bo_login_user" class="bo_form_text bo_login_input">';

	echo '<span class="bo_form_descr">'._BL('Password').':</span>';
	echo '<input type="password" name="bo_pass" id="bo_login_pass" class="bo_form_text bo_login_input">';

	if (!$_BO['headers_sent'] && intval(BO_LOGIN_COOKIE_TIME))
	{
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" value="1" name="bo_login_cookie" id="bo_login_cookie_check" class="bo_form_checkbox">';
		echo '<label for="bo_login_cookie_check" class="bo_form_descr_checkbox">'._BL('stay logged in').'</label>';
		echo '</span>';
	}

	echo '<input type="submit" name="ok" value="'._BL('Login').'" id="bo_login_submit" class="bo_form_submit">';

	echo '<input type="hidden" name="bo_do_login" value="1">';

	echo '</fieldset>';

	echo '</form>';

	echo '</div>';

}


function bo_user_show_passw_change()
{
	if ($_POST['ok'])
	{
		$pass1 = bo_gpc_prepare($_POST['pass1']);
		$pass2 = bo_gpc_prepare($_POST['pass2']);

		if ($pass1 && $pass2 && $pass1 == $pass2)
		{
			$pass = md5($pass1);
			BoDb::query("UPDATE ".BO_DB_PREF_USER."user SET password='$pass' WHERE id='".bo_user_get_id()."'");
			echo '<div class="bo_info_ok">';
			echo _BL('Password changed!');
			echo '</div>';
		}
		else
		{
			echo '<div class="bo_info_fail">';
			echo _BL('Password was not changed!');
			echo '</div>';
		}
	}

	echo '<h3>'._BL('Change password').'</h3>';

	echo '<form action="'.bo_insert_url().'" method="POST" class="bo_admin_user_form">';

	echo '<fieldset class="bo_admin_user_fieldset">';
	echo '<legend>'._BL('user_change_passw_legend').'</legend>';

	echo '<span class="bo_form_descr">'._BL('New password').':</span>';
	echo '<input type="password" name="pass1" value="" id="bo_change_pass1" class="bo_form_text bo_form_input">';

	echo '<span class="bo_form_descr">'._BL('Repeat password').':</span>';
	echo '<input type="password" name="pass2" value="" id="bo_change_pass1" class="bo_form_text bo_form_input">';

	echo '<input type="submit" name="ok" value="'._BL('Change').'" id="bo_user_admin_submit" class="bo_form_submit">';
	echo '</fieldset>';

	echo '</form>';
}

function bo_user_show_useradmin()
{
	$user_id = intval($_GET['id']);
	$failure = false;

	if (isset($_POST['bo_admin_user']) && (bo_user_get_level() & BO_PERM_ADMIN) )
	{
		$user_id = intval($_POST['user_id']);

		$new_user_login = BoDb::esc(bo_gpc_prepare($_POST['bo_user_login']));
		$new_user_pass = BoDb::esc(bo_gpc_prepare($_POST['bo_user_pass']));
		$new_user_mail = BoDb::esc(bo_gpc_prepare($_POST['bo_user_mail']));

		if ($user_id == 1 || $new_user_login)
		{
			$new_user_level = 0;
			if (is_array($_POST['bo_user_perm']))
			{
				foreach($_POST['bo_user_perm'] as $perm => $checked)
				{
					if ($checked)
						$new_user_level += $perm;
				}
			}

			$sql = " ".BO_DB_PREF_USER."user SET mail='$new_user_mail' ";

			if ($user_id != 1)
			{
				$sql .= ", login='$new_user_login', level='$new_user_level' ";

				if (strlen(trim($new_user_pass)))
				{
					$new_user_pass = md5($new_user_pass);
					$sql .= ", password='$new_user_pass'";
				}
			}

			//To be sure, if creation of main user during install failed
			BoDb::query("INSERT IGNORE INTO ".BO_DB_PREF_USER."user SET id=1", false);

			if ($user_id)
				$ok = BoDb::query("UPDATE $sql WHERE id='$user_id'", false);
			else
				$ok = BoDb::query("INSERT INTO $sql", false);

			if (!$ok)
				$failure = true;

			$user_id = 0;
		}
		else
		{
			$user_login = $new_user_login;
			$user_pass = $new_user_pass;
			$user_mail = $new_user_mail;
		}
	}

	if ($_GET['bo_action2'] == 'delete' && $user_id > 1 && (bo_user_get_level() & BO_PERM_ADMIN) )
	{
		BoDb::query("DELETE FROM ".BO_DB_PREF_USER."user WHERE id='$user_id'");
		BoData::delete_all('alert_'.$user_id.'%');
		$user_id = 0;
	}


	echo '<div id="bo_user_admin">';

	if (!$user_id)
	{
	
		echo '<h3>'._BL('User list').'</h3>';
		echo '<table class="bo_table" id="bo_user_table">';
		echo '<tr>
				<th rowspan="2">ID</th>
				<th rowspan="2">'._BL('Login').'</th>
				<th rowspan="2">'._BL('E-Mail').'</th>
				<th colspan="'.BO_PERM_COUNT.'">'._BL('Level').'</th>
				<th rowspan="2">'._BL('Delete').'</th>
				<th rowspan="2">'._BL('Alert').'</th>
				</tr>';

		for ($i=0; $i<BO_PERM_COUNT;$i++)
		{
			echo '<th>'.($i+1).'</th>';
		}

		$sql = "SELECT id, login, password, level, mail
				FROM ".BO_DB_PREF_USER."user
				ORDER BY id
				";
		$res = BoDb::query($sql);
		while ($row = $res->fetch_assoc())
		{
			if ($row['id'] == 1)
			{
				$row['login'] = BO_USER;
				$row['pass'] = BO_PASS;
				$row['level'] = pow(2, BO_PERM_COUNT) - 1;
			}

			echo '<tr>
				<td><a href="'.bo_insert_url(array('bo_action2', 'id')).'&id='.$row['id'].'">'.$row['id'].'</a></td>
				<td>'._BC($row['login']).'</td>
				<td>'._BC($row['mail']).'</td>';

			for ($i=0; $i<BO_PERM_COUNT;$i++)
			{
				$l = pow(2, $i);
				echo '<td>'.(($row['level'] & $l) ? 'X' : '-').'</td>';
			}

			echo '<td>';

			if ($row['id'] > 1)
				echo '<a href="'.bo_insert_url(array('bo_action2')).'&bo_action2=delete&id='.$row['id'].'" style="color:red" onclick="return confirm(\''._BL('Sure?').'\');">X</a>';

			echo '</td>';

			echo '<td><a href="'.bo_insert_url(array('bo_action', 'bo_action2')).'&bo_action=alert&bo_action2=alert_form%2C'.$row['id'].'">'._BL('new').'</a></td>';

			echo '</tr>';

			if ($user_id == $row['id'])
			{
				$user_mail = $row['mail'];
				$user_level = $row['level'];
				$user_login = $row['login'];
			}

		}

		echo '</table>';
	}
	
	if ($user_id == 1)
	{
		$disabled = ' disabled="disabled"';
	}

	if ($failure)
		echo '<div class="bo_info_fail">'._BL('Failure!').'</div>';

		
		
	$sql = "SELECT id, login, password, level, mail
		FROM ".BO_DB_PREF_USER."user WHERE id='$user_id'";
	$res = BoDb::query($sql);
	$row = $res->fetch_assoc();

	if ($row['id'] == 1)
	{
		$row['login'] = BO_USER;
		$row['pass'] = BO_PASS;
		$row['level'] = pow(2, BO_PERM_COUNT) - 1;
	}
	
	$user_mail = $row['mail'];
	$user_level = $row['level'];
	$user_login = $row['login'];
	
	
	echo '<form action="'.bo_insert_url(array('bo_logout', 'id', 'bo_action2')).'" method="POST" class="bo_admin_user_form">';

	echo '<fieldset class="bo_admin_user_fieldset">';
	echo '<legend>'._BL('admin_user_legend').'</legend>';

	echo '<span class="bo_form_descr">'._BL('Login').':</span>';
	echo '<input type="text" name="bo_user_login" value="'._BC($user_login).'" id="bo_user_login" class="bo_form_text bo_admin_input" '.$disabled.'>';

	echo '<span class="bo_form_descr">'._BL('Password').':</span>';
	echo '<input type="password" name="bo_user_pass" value="'._BC($user_pass).'" id="bo_user_login" class="bo_form_text bo_admin_input" '.$disabled.'>';

	echo '<span class="bo_form_descr">'._BL('E-Mail').':</span>';
	echo '<input type="text" name="bo_user_mail"  value="'._BC($user_mail).'" id="bo_user_mail" class="bo_form_text bo_login_input">';

	echo '<span class="bo_form_descr">'._BL('Level').':</span>';
	echo '<div class="bo_input_container">';
	for ($i=0; $i<BO_PERM_COUNT;$i++)
	{
		$l = pow(2, $i);

		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" value="1" name="bo_user_perm['.$l.']" id="bo_user_perm'.$i.'" class="bo_form_checkbox" '.$disabled.(($user_level & $l) ? ' checked="checked"' : '').'>';
		echo '<label for="bo_user_perm'.$i.'" class="bo_form_descr_checkbox">'._BL('user_perm'.$i).'&nbsp;('.($i+1).')</label>';
		echo '</span>';
	}
	echo '</div>';
	echo '<input type="hidden" name="bo_admin_user" value="1">';
	echo '<input type="hidden" name="user_id" value="'.$user_id.'">';

	echo '<input type="submit" name="ok" value="'._BL('Add/Edit').'" id="bo_user_admin_submit" class="bo_form_submit">';


	echo '</fieldset>';

	echo '</form>';


	echo '</div>';

}


function bo_cache_info()
{
	$dirs['Tiles'] = array('tiles/', 8);
	$dirs['Icons'] = array('icons/', 8);
	$dirs['Maps']  = array('maps/',  8);

	if (BO_CACHE_SUBDIRS === true)
		$dirs['Density maps'] = array('densitymap/', 8);

	$dirs['Graphs'] = array('graphs/', 0);
		
	$dirs['Other'] = array('', 0);

	
	echo '<h3>'._BL('File cache info').'</h3>';
	echo '<p><a href="'.bo_insert_url().'&bo_action2=unlink">'._BL('Click here to delete all files').'</a></p>';
	flush();
	
	echo '<h3>'._BL('Folders').'</h3>';
	foreach($dirs as $name => $d)
	{
		$dir = BO_CACHE_DIR.'/'.$d[0];
		$depth = $d[1];

		echo '<h4>'.$name.': <em>'.$dir.'</em></h4>';

		$dir = BO_DIR.$dir;

		if ($_GET['bo_action2'] == 'unlink')
		{
			bo_delete_files($dir, 0, $depth);
			flush();
			clearstatcache();
		}



		$files = glob($dir.'*');

		if ($depth && is_array($files))
		{
			for ($i = 0; $i < count($files); $i++)
			{
				if (is_dir($files[$i]))
				{
					$add = glob($files[$i].'/*');

					if ($add && is_array($add))
						$files = array_merge($files, $add);
				}
			}
		}

		if (is_array($files))
		{
			$size = 0;
			$count = 0;
			foreach($files as $file)
			{
				$file = $file;
				if (!is_dir($file))
				{
					$size += filesize($file);
					$count++;
				}
			}
		}

		echo '<p>'._BL('Files').': <strong>'.$count.'</strong> ('._BN($size / 1024, 1).' kB)</p>';

		flush();
	}


}

function bo_my_station_update_form()
{
	require_once 'import.inc.php';
	
	if ($_POST['ok'])
	{
		$url = trim($_POST['bo_url']);

		echo '<pre>';
		$ret = bo_my_station_update($url);
		echo '</pre>';

		if ($ret && $url)
			BoData::set('mybo_stations_autoupdate', $_POST['bo_auto'] ? 1 : 0);
		else
			BoData::set('mybo_stations_autoupdate', 0);
	}
	else
	{

		$st_urls = unserialize(BoData::get('mybo_stations'));

		if (is_array($st_urls) && $st_urls[bo_station_id()])
			$url = $st_urls[bo_station_id()];
		else
			$url = 'http://'.$_SERVER["HTTP_HOST"].dirname($_SERVER["REQUEST_URI"]);


		echo '<h3>'._BL('Link with the MyBlitzortung network').'</h3>';
		echo strtr(_BL('mybo_station_update_info'), array('{LINK_HOST}' => BO_LINK_HOST));
		echo '<form action="'.bo_insert_url().'" method="POST" class="bo_login_form">';
		echo '<fieldset class="bo_mylink_fieldset">';
		echo '<span class="bo_form_descr">'._BL('URL of your website').' ('._BL('Leave blank to remove your station from the list').'):</span>';
		echo '<input type="text" name="bo_url" id="bo_mylink_url" value="'._BC($url).'" class="bo_form_text" style="width:100%">';
		echo '<div class="bo_input_container">';
		echo '<span class="bo_form_descr">'.' '._BL('Do an auto update every 24h to retrieve new stations').':</span>';
		echo '<input type="checkbox" value="1" name="bo_auto" '.(BoData::get('mybo_stations_autoupdate') == 1 ? ' checked ' : '').'>';
		echo '</div>';
		echo '<input type="submit" name="ok" value="'._BL('Agree and Send').'" id="bo_login_submit" class="bo_form_submit" onclick="return confirm(\''._BL('Really continue?').'\');">';
		echo '</fieldset>';
		echo '</form>';

	}

}

function bo_import_cities()
{
	$fp = @fopen(BO_DIR."cities.txt", "r");

	echo '<h3>'._BL('Importing cities').'</h3>';

	if ($fp)
	{
		$cities = array();
		while (($line = fgets($fp, 4096)) !== false)
		{
			$p = explode(',', $line);
			$cities[] = $p;
		}

		fclose($fp);

		echo '<p>'._BL('Cities read').': '.count($cities).'</p>';
		flush();

		if (count($cities))
		{
			echo '<p>'._BL('Deleting existing cities from DB').'</p>';
			flush();
			BoDb::query("DELETE FROM ".BO_DB_PREF."cities");

			echo '<p>'._BL('Cities imported').': ';
			flush();

			$i = 0;
			foreach($cities as $city)
			{
				if (count($city) > 4) //cities with borders --> big cities
					$city[3] += 4;

				$ok = BoDb::query("INSERT INTO ".BO_DB_PREF."cities
						SET name='".BoDb::esc($city[0])."',
							lat ='".BoDb::esc($city[1])."',
							lon ='".BoDb::esc($city[2])."',
							type='".BoDb::esc($city[3])."'");
				if ($ok) $i++;
			}

			echo $i.'</p>';
		}
	}
	else
	{
		echo _BL('Error');
	}

}




?>