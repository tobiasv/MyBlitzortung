<?php

function bo_archive_select_map(&$map)
{
	global $_BO;
	
	$map_ok = false;
	$map_default = false;
	
	$ret = '<span class="bo_form_descr">'._BL('Map').':';
	$ret .= ' <select name="bo_map" id="bo_arch_strikes_select_map" onchange="submit();">';
	foreach($_BO['mapimg'] as $id => $d)
	{
		if (!$d['name'] || !$d['archive'])
			continue;
		
		if ($map_default === false)
			$map_default = $id;
			
		$ret .= '<option value="'.$id.'" '.((string)$id === (string)$map ? 'selected' : '').'>'._BL($d['name'], false, BO_CONFIG_IS_UTF8).'</option>';
		
		if ($map < 0)
			$map = $id;
		
		if ((string)$id === (string)$map)
			$map_ok = true;
	}
	$ret .= '</select></span> ';
	
	$map = $map_ok ? $map : $map_default;
	
	return $ret;
}


function bo_archive_get_dim($map)
{
	global $_BO;
	
	$cfg = $_BO['mapimg'][$map];
	
	if ($cfg['dim'][0] && $cfg['dim'][1])
	{
		$x = $cfg['dim'][0];
		$y = $cfg['dim'][1];
	}
	else
	{
		$file = BO_DIR.'images/'.$cfg['file'];
		if (file_exists($file) && !is_dir($file))
		{
			list($x,$y) = getimagesize($file);
			
			if (isset($cfg['resize']) && $cfg['resize'] > 0)
			{
				$y = $y * ($cfg['resize'] / $y);
				$x = $cfg['resize'];
			}
			
		}
	}
	
	return array($x, $y);

}

function bo_archive_get_dim_html($map, $addx=0)
{
	list($x, $y) = bo_archive_get_dim($map);
	
	if ($x && $y)
		$img_dim = ' width="'.($x+$addx).'" height="'.$y.'" ';	
	else
		$img_dim = '';
		
	return $img_dim;
}


function bo_archive_get_dim_css($map, $addx=0)
{
	list($x, $y) = bo_archive_get_dim($map);
	
	if ($x && $y)
		$img_dim = 'width:'.($x+$addx).'px;height:'.$y.'px;';	
	else
		$img_dim = '';
		
	return $img_dim;
}



function bo_insert_animation_js($images, $bo_file_url, $img_file, $ani_delay=BO_ANIMATIONS_WAITTIME, $ani_delay_end=BO_ANIMATIONS_WAITTIME_END, $img_dim='', $alt="")
{
	echo '<img style="position:relative;background-image:url(\''.bo_bofile_url().'?image=wait\');" '.$img_dim.' id="bo_arch_map_img" src="'.$img_file.'" alt="'.htmlspecialchars($alt).'">';
	echo '<img style="position:absolute;top:1px;left:1px;" '.$img_dim.' id="bo_arch_map_img_ani" src="'.$bo_file_url.$images[0].'" alt="'.htmlspecialchars($alt).'">';

	echo '<div id="bo_ani_loading_container" style="display:none;">';
	echo '<div id="bo_ani_loading_white" style="position:absolute;top:0px;left:0px;"></div>';
	echo '<div id="bo_ani_loading_text"  style="position:absolute;top:0px;left:0px">';
	echo '<p id="bo_ani_loading_text_percent" >'._BL('Loading...').'</p>';
	echo '</div>';
	echo '</div>';
	
	$js_img = '';
	foreach($images as $image)
		$js_img .= ($js_img ? ',' : '').'"'.$image.'"';
	
?>
	
<script type="text/javascript">

var bo_maps_pics   = new Array(<?php echo $js_img ?>);
var bo_maps_img    = new Array();
var bo_maps_loaded = 0;
var bo_maps_playing = false;
var bo_maps_position = 0;

function bo_maps_animation(nr)
{
	if (bo_maps_playing)
	{
		document.getElementById('bo_arch_map_img_ani').src=bo_maps_img[nr].src;
		var timeout = <?php echo intval($ani_delay); ?>;
		if (nr >= bo_maps_pics.length-1) { nr=-1; timeout += <?php echo intval($ani_delay_end); ?>; }
		window.setTimeout("bo_maps_animation("+(nr+1)+");",timeout);
		bo_maps_position=nr;
	}
}

function bo_maps_load()
{
	document.getElementById('bo_ani_loading_container').style.display='block';
	for (var i=0; i<bo_maps_pics.length; i++)
	{
		bo_maps_img[i] = new Image();
		bo_maps_img[i].onload=bo_maps_animation_start;
		bo_maps_img[i].onerror=bo_maps_animation_start;
		bo_maps_img[i].src = "<?php echo $bo_file_url ?>" + bo_maps_pics[i];
	}
	
}

function bo_maps_animation_start()
{
	if (bo_maps_loaded >= 0)
		bo_maps_loaded++;
	
	if (bo_maps_loaded+1 >= bo_maps_pics.length && bo_maps_loaded >= 0)
	{
		bo_maps_loaded = -1;
		bo_maps_playing = true;
		document.getElementById('bo_ani_loading_container').style.display='none';
		bo_maps_animation(0);
	}
	else if (bo_maps_loaded > 0)
	{
		if (bo_maps_pics.length > 0)
			document.getElementById('bo_ani_loading_text_percent').innerHTML="<?php echo _BL('Loading...') ?> " + Math.round(bo_maps_loaded / bo_maps_pics.length * 100) + "%";
		
		document.getElementById('bo_arch_map_img_ani').src = bo_maps_img[bo_maps_loaded-1].src;
	}
}

function bo_animation_pause()
{
	if (bo_maps_loaded == -1)
	{
		if (bo_maps_playing)
		{
			bo_maps_playing = false;
			document.getElementById('bo_animation_dopause').innerHTML="<?php echo _BL('ani_play') ?>";
		}
		else
		{
			bo_maps_playing = true;
			document.getElementById('bo_animation_dopause').innerHTML="<?php echo _BL('ani_pause') ?>";
			bo_maps_animation(bo_maps_position);
		}
	}

}

function bo_animation_next()
{
	if (bo_maps_loaded == -1)
	{
		if (bo_maps_playing)
			bo_animation_pause();
		
		if (++bo_maps_position >= bo_maps_img.length) bo_maps_position=0;
		
		document.getElementById('bo_arch_map_img_ani').src=bo_maps_img[bo_maps_position].src;
		
	}
}

function bo_animation_prev()
{
	if (bo_maps_loaded == -1)
	{
		if (bo_maps_playing)
			bo_animation_pause();
		
		if (--bo_maps_position < 0) bo_maps_position=bo_maps_img.length-1;
		document.getElementById('bo_arch_map_img_ani').src=bo_maps_img[bo_maps_position].src;
		
		
	}
}

window.setTimeout("bo_maps_load();", 500);
</script>
<?php

}



function bo_signal_info_list()
{

	$channels = BO_ANTENNAS;
	$bpv      = BoData::get('raw_bitspervalue');
	$values   = BoData::get('raw_values');
 	$utime    = BoData::get('raw_ntime') / 1000;
	$last_update = BoData::get('uptime_raw');
	$last_update_minutes = round((time()-$last_update)/60,1);

	echo '<ul class="bo_stat_overview">';
	echo '<li><span class="bo_descr">'._BL('Last update').': </span>';
	echo '<span class="bo_value">'._BL('_before')._BN($last_update_minutes, 1).' '.($last_update_minutes == 1 ? _BL('_minute_ago') : _BL('_minutes_ago')).'</span></li>';
	echo '<li><span class="bo_descr">'._BL('Channels').': </span>';
	echo '<span class="bo_value">'.$channels.'</span></li>';
	echo '<li><span class="bo_descr">'._BL('Samples per Channel').': </span>';
	echo '<span class="bo_value">'.$values.'</span></li>';
	echo '<li><span class="bo_descr">'._BL('Recording time').': </span>';
	echo '<span class="bo_value">'._BN($utime * $values, 0)._BL('unit_us_short').'</span></li>';
	echo '<li><span class="bo_descr">'._BL('Bits per Sample').': </span>';
	echo '<span class="bo_value">'.$bpv.'</span></li>';
	echo '<li><span class="bo_descr">'._BL('Sample rate').': </span>';
	echo '<span class="bo_value">'._BN(1 / $utime * 1000, 0).' '._BL('unit_ksps').'</span></li>';
	echo '</ul>';
}

?>