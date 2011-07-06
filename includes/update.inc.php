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


function bo_check_for_update()
{
	
	$updates = array('0.2.2' => 202, '0.3' => 300, '0.3.1' => 301, '0.4.8' => 408, '0.5.2' => 502);
	$cur_version = bo_get_conf('version');

	preg_match('/([0-9]+)(\.([0-9]+)(\.([0-9]+))?)?/', $cur_version, $r);
	$cur_version_num = $r[1] * 10000 + $r[3] * 100 + $r[5];

	if ($cur_version_num < max($updates) && $_GET['bo_action'] != 'do_update')
	{
		echo '<h4>'._BL('Database version changed!').'</h4>';
		echo '<p>';
		echo ' <a href="'.bo_insert_url('bo_action', 'do_update').'">'._BL('Click to update').'</a>';
		echo '</p>';
		bo_copyright_footer();
		return true;
	}
	
	$updated = false;
	
	foreach($updates as $new_version => $number)
	{
		if ($cur_version_num >= $number)
			continue;
		
		echo '<h4>'._BL('Updating version').' '.$cur_version.' -&gt; '.$new_version.'</h4>';
		echo '<ul>';
		
		$ok = false;
		switch ($new_version)
		{
			case '0.2.2':
				bo_db('ALTER TABLE '.BO_DB_PREF.'raw DROP INDEX `time`', false); // to be sure the key is not added twice
				$sql = 'ALTER TABLE '.BO_DB_PREF.'raw ADD INDEX (`time`)';
				$ok = bo_db($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				$ok = true; //doesn't matter too much if this fails ;-)
				break;
			
			case '0.3':
				bo_db('ALTER TABLE '.BO_DB_PREF.'stations_stat DROP INDEX `stations_time`', false); // to be sure the key is not added twice
				$sql = 'CREATE INDEX stations_time ON '.BO_DB_PREF.'stations_stat (station_id, time)';
				$ok = bo_db($sql, false);
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
				
				$ok = bo_db($sql, false);
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
						$ok = bo_db($sql, false);
						echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
						break;
						
					case 'clear_dens_no':
						$ok = true;
						break;
				
				}
				break;
			
			case '0.5.2':

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` DROP INDEX `time_dist`';
				$ok = bo_db($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` ADD INDEX `time` (`time`)';
				$ok = bo_db($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();
				
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` ADD `lat2` TINYINT NOT NULL AFTER `lon`, ADD `lon2` TINYINT NOT NULL AFTER `lat2`';
				$ok = bo_db($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

				$sql = 'UPDATE `'.BO_DB_PREF.'strikes` SET lat2=FLOOR(lat), lon2=FLOOR(lon/180 * 128)';
				$ok = bo_db($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();
				
				$sql = 'ALTER TABLE `'.BO_DB_PREF.'strikes` ADD INDEX `latlon2` (`lat2`,`lon2`)';
				$ok = bo_db($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				flush();

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
	}
	
	if ($updated)
	{
		echo '<h4>'._BL('Update done!').'</h4>';
	}
	
	if ($cur_version != BO_VER)
	{
		bo_set_conf('version', BO_VER);
		echo '<h4>'._BL('Update-Info: Setting version number to').' '.BO_VER.'</h4>';
	}
	
	return $ok;
	
}


?>