<?php


function bo_show_info_page1()
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
	echo '<img src="'.bo_bofile_url().'?image=logo" class="bo_bo_logo">';
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
	echo '<img src="'.bo_bofile_url().'?image=myblitzortung" class="bo_my_logo">';
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