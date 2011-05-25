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
	
	$updates = array('0.2.2' => 202, '0.3' => 300);
	$cur_version = bo_get_conf('version');

	preg_match('/([0-9]+)(\.([0-9]+)(\.([0-9]+))?)?/', $cur_version, $r);
	$cur_version_num = $r[1] * 10000 + $r[3] * 100 + $r[5];

	if ($cur_version_num < max($updates) && $_GET['bo_action'] != 'do_update')
	{
		echo _BL('Database version changed!');
		echo ' <a href="'.bo_insert_url('bo_action', 'do_update').'">'._BL('Click to update').'</a>';
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
			
			case '0.2.5':
				bo_db('ALTER TABLE '.BO_DB_PREF.'stations_stat DROP INDEX `stations_time`', false); // to be sure the key is not added twice
				$sql = 'CREATE INDEX stations_time ON '.BO_DB_PREF.'stations_stat (station_id, time)';
				$ok = bo_db($sql, false);
				echo '<li><em>'.$sql.'</em>: <b>'._BL($ok ? 'OK' : 'FAIL').'</b></li>';
				$ok = true; //doesn't matter too much if this fails ;-)
				break;
		}
		
		echo '</ul>';
		
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
	
		
	
}


?>