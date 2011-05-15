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


function bo_show_info()
{

	echo '<div id="bo_infos">';
	
	echo '<h3>'._BL('h3_info_hint').'</h3>';
	echo '<p>';
	echo '<span style="color:red; font-weight: bold">';
	echo _BL('info_general_warning');
	echo '</span>';
	echo '</p>';
	
	echo '<h3>'._BL('h3_info_general').'</h3>';
	echo '<p>';
	echo '<img src="'.BO_FILE.'?image=logo" class="bo_bo_logo">';
	echo _BL('info_general_text');
	echo '</p>';
	
	echo '<h4>'._BL('h4_info_accuracy').'</h4>';
	echo '<p>';
	echo _BL('info_accuracy_text');
	echo '</p>';
	
	echo '<h4>'._BL('h4_info_participate').'</h4>';
	echo '<p>';
	echo _BL('info_participate_text');
	echo '</p>';

	echo '<h3>MyBlitzortung</h3>';
	echo '<p>';
	echo '<img src="'.BO_FILE.'?image=myblitzortung" class="bo_my_logo">';
	echo _BL('info_myblitzortung_text');
	echo '</p>';

	echo '<h3>'._BL('h3_info_usage').'</h3>';
	echo '<p>';
	echo _BL('info_usage_text');
	echo '</p>';
	
	echo '</div>';
	
	bo_copyright_footer();

}




?>