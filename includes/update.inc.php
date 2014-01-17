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
						'0.7.5a',
						'0.7.5b',
						'0.7.6',
						'0.7.9a',
						'0.7.9b',
						'0.7.9c',
						'0.7.9e',
						'0.8.1',
						'1.1',
						'1.2',
						);

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
			$cur_version = BoData::get('version');
			
			if (!$cur_version) //if no or wrong version is saved
			{
				BoData::set('version', BO_VER);
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

		BoData::set('is_updating', time());
		register_shutdown_function('BoData::set', 'is_updating', 0);

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

				break;
			
			case '0.7.5b':

				//silent update, because of wrong install in 0.7.5(a)
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'raw`	ADD `ntime` SMALLINT UNSIGNED NOT NULL AFTER `channels`';
				BoDb::query($sql, false);
				$ok = true;
				
				$channels = BoData::get('raw_channels');
				$ntime    = BoData::get('raw_ntime');
				$sql = "UPDATE `".BO_DB_PREF."raw` SET channels='$channels', ntime='$ntime' WHERE channels=0 AND ntime=0";
				echo '<li><em>'.$sql.'</em>: <b>';
				flush();
				$no = BoDb::query($sql, false);
				echo $no.' rows affected</b></li>';

				break;

			case '0.7.6':

				$sql = 'ALTER TABLE '.BO_DB_PREF.'stations CHANGE `country` `country` VARCHAR(50) NOT NULL';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				$ok = true; //doesn't matter too much if this fails ;-)
				flush();

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` ADD `tracker` VARCHAR(50) NOT NULL';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();
				
				break;
				
			case '0.7.9a':
			
				$sql = 'ALTER TABLE '.BO_DB_PREF.'densities ADD INDEX status_station (status, station_id)';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'Already exists.').'</b></li>';
				flush();

				$sql = 'ALTER TABLE '.BO_DB_PREF.'densities ADD INDEX date_station_position (date_start, date_end , station_id, lat_min, lon_min, lat_max, lon_max)';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'Already exists.').'</b></li>';
				flush();

				$sql = 'ALTER TABLE '.BO_DB_PREF.'densities ADD INDEX date_end (date_end)';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'Already exists.').'</b></li>';

				$ok = true; //doesn't matter too much if this fails ;-)
				break;

			case '0.7.9b':
			
				BoDb::query('DELETE FROM '.BO_DB_PREF.'densities WHERE station_id=32767', false);
				$sql = 'ALTER TABLE '.BO_DB_PREF.'densities CHANGE `station_id` `station_id` INT NOT NULL';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				break;

			case '0.7.9c':
				BoDb::query('DELETE FROM '.BO_DB_PREF.'stations WHERE user="28002900"', false);
				$sql = 'ALTER TABLE '.BO_DB_PREF.'stations CHANGE `user` `user` VARCHAR(30) NOT NULL';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				break;
				
				
			case '0.7.9e':
				$ok = true;
				break;
			
			case '0.8.1':
				
			
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` 
							ADD `show_mybo` varchar(3) NOT NULL AFTER `tracker`,
							ADD `bo_station_id` SMALLINT UNSIGNED NOT NULL AFTER `id`,
							ADD `bo_user_id` SMALLINT UNSIGNED NOT NULL AFTER `bo_station_id`,
							ADD `alt` decimal(5,1) NOT NULL AFTER `lon`,
							ADD `controller_pcb` VARCHAR(30) NOT NULL AFTER `status`,
							ADD `amp_gains` VARCHAR(40) NOT NULL AFTER `status`,
							ADD `amp_antennas` VARCHAR(30) NOT NULL AFTER `amp_gains`,
							ADD `amp_firmwares` VARCHAR(100) NOT NULL AFTER `amp_antennas`,
							ADD `url` VARCHAR(200) NOT NULL AFTER `show_mybo`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` 
							ADD INDEX ( `bo_station_id` ),
							ADD INDEX ( `bo_user_id` ),
							ADD INDEX ( `country` ),
							ADD INDEX ( `first_seen` ),
							ADD INDEX ( `status` ),
							ADD INDEX ( `show_mybo` )';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();
				
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` 
							CHANGE `tracker` `firmware` varchar(50) NOT NULL,
							CHANGE `status` `status` varchar(3) NOT NULL,
							CHANGE `last_time` `last_time` TIMESTAMP NULL DEFAULT NULL,
							CHANGE `first_seen` `first_seen` TIMESTAMP NULL DEFAULT NULL';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` ADD `tmp_id` SMALLINT UNSIGNED NOT NULL AFTER `id`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';

				$sql = 'UPDATE `'.BO_DB_PREF.'stations` SET tmp_id=id, status=0';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` DROP `id`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` ADD PRIMARY KEY(`tmp_id`)';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` CHANGE `tmp_id` `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();
				
				//strikes table
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` 
						CHANGE `polarity` `type` tinyint(1) NOT NULL,
						CHANGE `users` `stations` smallint(5) NOT NULL,
						CHANGE `time` `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
						ADD `stations_calc` smallint(5) NOT NULL AFTER `stations`,
						ADD `part_pos` tinyint(5) NOT NULL AFTER `part`,
						ADD `alt` decimal(5,1) NOT NULL AFTER `lon`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';

				echo '</ul>';
				flush();
				
				echo '<h4>Upgrading station data to new format</h4>';
				echo '<ul>';		
				
				//upgrade data
				bo_upgrade_to_red();

				$sql = 'SELECT bo_station_id FROM `'.BO_DB_PREF.'stations` WHERE user="'.BO_USER.'"';
				$row = BoDb::query($sql, false)->fetch_assoc();
				
				if ($row['bo_station_id'])
					BoData::set('bo_station_id', $row['bo_station_id']);
				
				
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` DROP `user`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations_stat` 
					CHANGE `time` `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				
				break;
			
			
			case '1.1':
				
			
				$sql = "ALTER TABLE `".BO_DB_PREF."stations` 
							CHANGE `id` `id` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT, 
							CHANGE `bo_station_id` `bo_station_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0', 
							CHANGE `bo_user_id` `bo_user_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0', 
							CHANGE `city` `city` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', 
							CHANGE `country` `country` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', 
							CHANGE `lat` `lat` DECIMAL(9,6) NOT NULL DEFAULT '0', 
							CHANGE `lon` `lon` DECIMAL(9,6) NOT NULL DEFAULT '0', 
							CHANGE `alt` `alt` DECIMAL(5,1) NOT NULL DEFAULT '0', 
							CHANGE `distance` `distance` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0', 
							CHANGE `last_time` `last_time` TIMESTAMP NULL DEFAULT NULL, 
							CHANGE `last_time_ns` `last_time_ns` INT(11) NOT NULL DEFAULT '0', 
							CHANGE `status` `status` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0', 
							CHANGE `amp_gains` `amp_gains` VARCHAR(40) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', 
							CHANGE `amp_antennas` `amp_antennas` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', 
							CHANGE `amp_firmwares` `amp_firmwares` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', 
							CHANGE `controller_pcb` `controller_pcb` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', 
							CHANGE `changed` `changed` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, 
							CHANGE `first_seen` `first_seen` TIMESTAMP NULL DEFAULT NULL, 
							CHANGE `firmware` `firmware` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', 
							CHANGE `show_mybo` `show_mybo` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', 
							CHANGE `url` `url` VARCHAR(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';

			case '1.2':
			
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` ADD `comment` varchar(128) NOT NULL AFTER `country`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'stations` DROP `show_mybo`';
				$ok = BoDb::query($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
			
			default:
				$ok = true;
				break;

		}

		echo '</ul>';

		flush();

		if ($ok)
		{
			BoData::set('version', $new_version);
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
	
	bo_update_db_compression();
	

	if ($updated)
	{
		echo '<h4>'._BL('Update done!').'</h4>';
	}

	if ($cur_version != $bo_version && (!$db_update || $updated) || $_GET['bo_update_from'])
	{
		BoData::set('version', $bo_version);
		echo '<h4>'._BL('Update-Info: Setting version number to').' '.$bo_version.'</h4>';
	}

	echo '</div>';

	return $ok;

}




function bo_update_db_compression()
{
	
	//for compression, the data type has to be changed to longblob
	//no need to change back, even if compression gets disabled again
	if (BO_DB_COMPRESSION === true)
	{
		$row = BoDb::query("SHOW COLUMNS FROM `".BO_DB_PREF."conf` WHERE Field='data' AND Type != 'mediumblob'")->fetch_assoc();
		
		if ($row['Type'] && strtolower($row['Type']) != 'mediumblob')
		{
			$sql = 'ALTER TABLE `'.BO_DB_PREF.'conf` CHANGE  `data`  `data` MEDIUMBLOB NOT NULL';
			$ok = BoDb::query($sql, false);
			echo '<ul><li>Converting data column to enable compression (EXPERIMENTAL!)</li>';
			echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
			echo '</ul>';
			flush();
		}
	}
}

function bo_upgrade_to_red()
{
	require_once 'import.inc.php';

	$file = bo_get_file('http://'.trim(BO_USER).':'.trim(BO_PASS).'@blitzortung.net/Data_1/Protected/stations_map.php');
	$lines = explode("\n", $file);
	
	echo "<li>Fetching map table";
	
	if (count($lines) < 300)
	{
		echo ' FAILURE!</li>';
		return 0;
	}
	
	$i=0;
	foreach($lines as $line)
	{
		list($id, $user) = explode(" ", $line);
		
		if (!trim($user))
			continue;
		
		$sql = "UPDATE ".BO_DB_PREF."stations SET bo_station_id='".$id."' WHERE user='".BoDb::esc($user)."' AND bo_station_id=0 LIMIT 1";
		
		if (!BoDb::query($sql, false))
			echo ", $user not found";
		else	
			$i++;
	}
	
	echo ', '.(count($lines)-1).' lines, '.$i.' stations OK</li>';
	
	echo '<li>Depending on your region the user not found messages are no problem!</li>';
	
	flush();

	
	return 1;
}


?>