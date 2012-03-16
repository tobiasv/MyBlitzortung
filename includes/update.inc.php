<?php


function bo_check_for_update()
{
	$updated = false;
	$db_update = false;
	$do_update = $_GET['bo_action'] == 'do_update';

	$updates = array(	'0.2.2',
						'0.3',
						'0.3.1',
						'0.4.8',
						'0.5.2',
						'0.5.5',
						'0.6.1',
						'0.6.2',
						'0.7.2',
						'0.7.3',
						'0.7.4',
						'0.7.5',
						'0.7.5a');

	$max_update_num = bo_version2number($updates[count($updates)-1]);
	
	if ($_GET['bo_update_from'] && $_GET['bo_update_to'])
	{
		$cur_version = $_GET['bo_update_from'];
		$cur_version_num = bo_version2number($_GET['bo_update_from']);
		$bo_version = $_GET['bo_update_to'];
		$updated = true;
	}
	else
	{
		if ($_GET['bo_update_from'])
		{
			$cur_version = $_GET['bo_update_from'];
		}
		else
		{
			$cur_version = bo_get_conf('version');
			
			if (!$cur_version) //if no or wrong version is saved
			{
				bo_set_conf('version', BO_VER);
				$cur_version = BO_VER;
			}
				
		}
		
		$cur_version_num = bo_version2number($cur_version);
		$bo_version = BO_VER;

		//Workaround for developer Versions
		if (BO_VER != $cur_version && (strpos($cur_version, 'dev') || strpos($cur_version, '-')))
			$cur_version_num--;

		//Warning when updating from dev-Version
		if ($do_update && (strpos($cur_version, 'dev') || strpos($cur_version, '-')))
		{
			echo '<div id="bo_update_info">Hint: ';
			echo 'You are updating from a developer Version. Database updates may fail, because they could already have been occured before! In most cases this should be no problem.';
			echo '</div>';
		}

		if ($cur_version_num < $max_update_num && !$do_update)
		{
			echo '<div id="bo_update_info">';
			echo '<h4>'._BL('Database version changed!').'</h4>';
			echo '<p>';
			echo ' <a href="'.bo_insert_url('bo_action', 'do_update').'">'._BL('Click to update').'</a>';
			echo '</p>';
			echo '</div>';
			return true;
		}
	}

	echo '<div id="bo_update_info">';

	foreach($updates as $new_version)
	{
		$number = bo_version2number($new_version);
				
		if ($cur_version_num >= $number)
			continue;

		bo_set_conf('is_updating', time());
		register_shutdown_function('bo_set_conf', 'is_updating', 0);

		$db_update = true;

		echo '<h4>'._BL('Updating version').' '.$cur_version.' -&gt; '.$new_version.'</h4>';
		echo '<ul>';
		flush();

		$ok = false;
		switch ($new_version)
		{
			case '0.2.2':
				BoDb::query('ALTER TABLE '.BO_DB_PREF.'raw DROP INDEX `time`', false); // to be sure the key is not added twice
				$sql = 'ALTER TABLE '.BO_DB_PREF.'raw ADD INDEX (`time`)';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				$ok = true; //doesn't matter too much if this fails ;-)
				break;

			case '0.3':
				BoDb::query('ALTER TABLE '.BO_DB_PREF.'stations_stat DROP INDEX `stations_time`', false); // to be sure the key is not added twice
				$sql = 'CREATE INDEX stations_time ON '.BO_DB_PREF.'stations_stat (station_id, time)';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				$ok = true; //doesn't matter too much if this fails ;-)
				break;

			case '0.3.1':

				$sql = ' CREATE TABLE IF NOT EXISTS `'.BO_DB_PREF.'densities` (
						  `id` int(10) unsigned NOT NULL auto_increment,
						  `date_start` date default NULL,
						  `date_end` date default NULL,
						  `status` tinyint(4) NOT NULL,
						  `station_id` smallint(5) unsigned NOT NULL,
						  `length` decimal(4,1) NOT NULL,
						  `lat_min` decimal(5,2) NOT NULL,
						  `lon_min` decimal(5,2) NOT NULL,
						  `lat_max` decimal(5,2) NOT NULL,
						  `lon_max` decimal(5,2) NOT NULL,
						  `type` smallint(5) unsigned NOT NULL,
						  `info` varchar(500) NOT NULL,
						  `data` longblob NOT NULL,
						  `changed` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
						  PRIMARY KEY  (`id`),
						  UNIQUE KEY `unique_dataset` (`date_start`,`date_end`,`station_id`,`type`),
						  KEY `date_start` (`date_start`,`date_end`),
						  KEY `status` (`status`),
						  KEY `type` (`type`)
						) ENGINE=MyISAM  DEFAULT CHARSET=utf8';

				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				break;

			case '0.4.8':

				switch ($_GET['bo_action2'])
				{
					default:
						echo '<li>Should the densities be cleaned? Due to some major changes, the old data cannot be used any more.
							<a href="'.bo_insert_url(array('bo_action', 'bo_action2')).'&bo_action=do_update&bo_action2=clear_dens_yes">Yes, clear!</a>
							<a href="'.bo_insert_url(array('bo_action', 'bo_action2')).'&bo_action=do_update&bo_action2=clear_dens_no">No, do not clear!</a>
							</li></ul>';
						return true;
						break;

					case 'clear_dens_yes':
						$sql = 'TRUNCATE TABLE `'.BO_DB_PREF.'densities`';
						$ok = BoDb::query($sql, false);
						echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
						break;

					case 'clear_dens_no':
						$ok = true;
						break;

				}
				break;

			case '0.5.2':

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` DROP INDEX `time_dist`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` ADD INDEX `time` (`time`)';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` ADD `lat2` TINYINT NOT NULL AFTER `lon`, ADD `lon2` TINYINT NOT NULL AFTER `lat2`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				$sql = 'UPDATE `'.BO_DB_PREF.'strikes` SET lat2=FLOOR(lat), lon2=FLOOR(lon/180 * 128)';
				$ok = BoDb::query($sql);
				echo '<li><em>'.$sql.'</em>: <b>'.$ok.' rows affected</b></li>';
				flush();

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` ADD INDEX `latlon2` (`lat2`,`lon2`)';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				break;

			case '0.5.5':

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'raw`
							ADD `amp1` TINYINT UNSIGNED NOT NULL AFTER `strike_id`,
							ADD `amp2` TINYINT UNSIGNED NOT NULL AFTER `amp1`,
							ADD `amp1_max` TINYINT UNSIGNED NOT NULL AFTER `amp2`,
							ADD `amp2_max` TINYINT UNSIGNED NOT NULL AFTER `amp1_max`,
							ADD `freq1` SMALLINT UNSIGNED NOT NULL AFTER `amp2_max`,
							ADD `freq1_amp` TINYINT UNSIGNED NOT NULL AFTER `freq1`,
							ADD `freq2` SMALLINT UNSIGNED NOT NULL AFTER `freq1_amp`,
							ADD `freq2_amp` TINYINT UNSIGNED NOT NULL AFTER `freq2`
							';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				// to assign new signals
				BoDb::query('UPDATE `'.BO_DB_PREF.'raw` SET strike_id=0 WHERE time > NOW() - INTERVAL 72 HOUR', false);
				BoDb::query('UPDATE `'.BO_DB_PREF.'strikes` SET raw_id=NULL WHERE time > NOW() - INTERVAL 72 HOUR', false);


				echo '<li><strong>You can update some signals by clicking <a href="'.bo_insert_url('bo_action', 'do_update').'&bo_update_signals">here</a>';

				break;

			case '0.6.1':

				$sql = 'CREATE TABLE IF NOT EXISTS `'.BO_DB_PREF.'cities` (
						  `id` int(11) unsigned NOT NULL auto_increment,
						  `name` varchar(50) NOT NULL,
						  `lat` decimal(9,6) NOT NULL,
						  `lon` decimal(9,6) NOT NULL,
						  `type` tinyint(4) NOT NULL,
						  PRIMARY KEY  (`id`),
						  KEY `latlon` (`lat`,`lon`)
						) ENGINE=MyISAM  DEFAULT CHARSET=utf8
						';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				break;


			case '0.6.2':

				$res = BoDb::query("SHOW COLUMNS FROM `".BO_DB_PREF."strikes` WHERE Field='time_key'");
				$sql = "ALTER TABLE `".BO_DB_PREF."strikes` ADD `time_key` SMALLINT UNSIGNED NOT NULL AFTER `time_ns`";
				echo '<li><em>'.$sql.'</em>: <b>';
				if (!$res->num_rows)
				{
					$ok = BoDb::query($sql, false);
					echo _BL($ok ? 'OK' : 'FAIL');
				}
				else
				{
					echo _BL('Already DONE BEFORE');
					$ok = true;
				}
				echo '</b></li>';
				flush();


				$sql = "UPDATE `".BO_DB_PREF."strikes` SET time_key=FLOOR(UNIX_TIMESTAMP(time) / (3600*12) )";
				$no = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'.$no.' rows affected</b></li>';
				flush();


				$res = BoDb::query("SHOW INDEX FROM `".BO_DB_PREF."strikes` WHERE Key_name='time_latlon'");
				$sql = "ALTER TABLE `".BO_DB_PREF."strikes` ADD INDEX `time_latlon` (`time_key`,`lat2`,`lon2`)";
				echo '<li><em>'.$sql.'</em>: <b>';
				if (!$res->num_rows)
				{
					$ok = BoDb::query($sql, false);
					echo _BL($ok ? 'OK' : 'FAIL');
				}
				else
				{
					echo _BL('Already DONE BEFORE');
				}
				echo '</b></li>';
				flush();

				break;

			case '0.7.2':


				$res = BoDb::query("SHOW COLUMNS FROM `".BO_DB_PREF."strikes` WHERE Field='status'");
				$sql = "ALTER TABLE `".BO_DB_PREF."strikes` ADD `status` tinyint(4) NOT NULL";
				echo '<li><em>'.$sql.'</em>: <b>';
				flush();
				if (!$res->num_rows)
				{
					$ok = BoDb::query($sql, false);
					echo _BL($ok ? 'OK' : 'FAIL');
					echo '</b></li>';
					flush();

					if ($ok)
					{
						$sql = "UPDATE `".BO_DB_PREF."strikes` SET status=3 WHERE status=0";
						echo '<li><em>'.$sql.'</em>: <b>';
						flush();
						$no = BoDb::query($sql, false);
						echo $no.' rows affected';
						echo '</b></li>';
					}
				}
				else
				{
					echo _BL('Already DONE BEFORE');
					echo '</b></li>';
					$ok = true;
				}

				flush();

				break;

			case '0.7.3':

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` DROP INDEX `latlon2`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` DROP INDEX `time_latlon`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				$res = BoDb::query("SHOW INDEX FROM `".BO_DB_PREF."strikes` WHERE Key_name='timelatlon'");
				$sql = "ALTER TABLE `".BO_DB_PREF."strikes` ADD INDEX `timelatlon` (`time`,`lat`,`lon`)";
				echo '<li><em>'.$sql.'</em>: <b>';
				if (!$res->num_rows)
				{
					$ok = BoDb::query($sql, false);
					echo _BL($ok ? 'OK' : 'FAIL');
				}
				else
				{
					$ok = true;
					echo _BL('Already DONE BEFORE');
				}
				echo '</b></li>';
				flush();

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` DROP `time_key`, DROP `lat2`, DROP `lon2`';
				$ok2 = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok2 ? 'OK' : 'FAIL').'</b></li>';
				flush();

				break;

			case '0.7.4':

				switch ($_GET['bo_action2'])
				{
					default:
						echo '<li>Should the densities be cleaned? Due to some major changes, the old data cannot be used any more.
								If you didn\'t purge your strike data, the densities will be rebuild during the next imports.<br>
							<a href="'.bo_insert_url(array('bo_action', 'bo_action2')).'&bo_action=do_update&bo_action2=clear_dens_yes">Yes, clear!</a>
							<a href="'.bo_insert_url(array('bo_action', 'bo_action2')).'&bo_action=do_update&bo_action2=clear_dens_no">No, do not clear!</a>
							</li></ul>';
						return true;
						break 2;

					case 'clear_dens_yes':
						$sql = 'TRUNCATE TABLE `'.BO_DB_PREF.'densities`';
						$ok = BoDb::query($sql, false);
						echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
						break;

					case 'clear_dens_no':
						$ok = true;
						break;
				}

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'densities` CHANGE  `station_id`  `station_id` SMALLINT( 5 ) NOT NULL';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				$res = BoDb::query("SHOW COLUMNS FROM `".BO_DB_PREF."stations` WHERE Field='first_seen'");
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` ADD `first_seen` datetime NOT NULL ';
				echo '<li><em>'.$sql.'</em>: <b>';
				flush();
				if (!$res->num_rows)
				{
					$ok = BoDb::query($sql, false);
					echo _BL($ok ? 'OK' : 'FAIL');
					echo '</b></li>';
					flush();

					if ($ok)
					{
						$sql = "UPDATE `".BO_DB_PREF."stations` SET first_seen=changed";
						echo '<li><em>'.$sql.'</em>: <b>';
						flush();
						$no = BoDb::query($sql, false);
						echo $no.' rows affected';
						echo '</b></li>';
					}

				}
				else
				{
					echo _BL('Already DONE BEFORE');
					echo '</b></li>';
					$ok = true;
				}

				break;


			case '0.7.5':
				$sql = 'ALTER TABLE '.BO_DB_PREF.'strikes DROP INDEX `timelatlon`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'Already done.').'</b></li>';
				$ok = true; //doesn't matter too much if this fails ;-)
				flush();

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'raw`
							ADD `channels` TINYINT UNSIGNED NOT NULL AFTER `height`,
							ADD `ntime` SMALLINT UNSIGNED NOT NULL AFTER `channels`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();


				//if ($ok)
				{
					$channels = bo_get_conf('raw_channels');
					$ntime    = bo_get_conf('raw_ntime');

					$sql = "UPDATE `".BO_DB_PREF."raw` SET channels='$channels', ntime='$ntime' WHERE channels=0 AND ntime=0";
					echo '<li><em>'.$sql.'</em>: <b>';
					flush();
					$no = BoDb::query($sql, false);
					echo $no.' rows affected</b></li>';
				}

				break;

			default:
				$ok = true;
				break;

		}

		echo '</ul>';

		flush();

		if ($ok)
		{
			bo_set_conf('version', $new_version);
			$cur_version = $new_version;
			$cur_version_num = $number;
			$updated = true;
		}
		else
		{
			echo '<p>';
			echo _BL('Update failed!');
			echo ' <a href="'.bo_insert_url('bo_action', 'do_update').'">'._BL('Retry').'</a>';
			echo ' <a href="'.bo_insert_url(array('bo_action','bo_update_from','bo_update_to'), 'do_update').'&bo_update_from='.$new_version.'">'._BL('Continue').'</a>';
			echo '</p>';

			$ok = true;

			break;
		}
	}

	if ($updated)
	{
		echo '<h4>'._BL('Update done!').'</h4>';
	}

	if ($cur_version != $bo_version && (!$db_update || $updated) || $_GET['bo_update_from'])
	{
		bo_set_conf('version', $bo_version);
		echo '<h4>'._BL('Update-Info: Setting version number to').' '.$bo_version.'</h4>';
	}

	if (isset($_GET['bo_update_signals']))
	{
		session_write_close();
		$limit = intval($_GET['bo_update_signals']);
		if (!$limit)
			$limit = 5000;

		echo '<p>Updating '.$limit.' signals!</p>';
		flush();

		$res = BoDb::query("SELECT id, data FROM ".BO_DB_PREF."raw WHERE amp1=0 AND freq1=0 ORDER BY id DESC LIMIT $limit");
		$i = 0;
		while ($row = $res->fetch_assoc())
		{
			$sql = bo_examine_signal($row['data']);
			BoDb::query("UPDATE ".BO_DB_PREF."raw SET $sql WHERE id='".$row['id']."'");
			$i++;
			$last_id = $row['id'];
		}

		echo '<p>Examined '.$i.' signals. Last ID was '.$last_id.'. <a href="'.bo_insert_url(array('bo_action','bo_update_from','bo_update_signals'), 'do_update').'&bo_update_signals='.$limit.'">'._BL('Update more...').'</a></p>';
	}

	echo '</div>';

	return $ok;

}

function bo_version2number($version)
{
	preg_match('/([0-9]+)(\.([0-9]+)(\.([0-9]+))?)?([a-z])?/', $version, $r);
	$num = $r[1] * 10000 + $r[3] * 100 + $r[5];
	
	if ($r[6])
		$num += (abs(ord($r[6]) - ord('a')) + 1) * 0.01;
	
	return $num;
}


?>