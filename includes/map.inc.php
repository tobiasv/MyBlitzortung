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


function bo_show_map()
{
	bo_show_lightning_map();
	
	bo_copyright_footer();
}

function bo_insert_map($show_station=3, $lat=BO_LAT, $lon=BO_LON, $zoom=BO_DEFAULT_ZOOM, $type=BO_DEFAULT_MAP)
{
	global $_BO;

	$radius = $_BO['radius'] * 1000;

	$info = bo_station_info();
	
	$station_lat = BO_LAT;
	$station_lon = BO_LON;
	$station_text = $info['city'].' '.$info['height'].'m';
	
?>

	<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
	<script type="text/javascript">

	var bo_map;
	var boOvlMap = new Array();
	var bo_home;
	var bo_home_zoom;
	var bo_infobox;
	var bo_loggedin = <?php echo intval(bo_user_get_level()) ?>;
	
	function bo_gmap_init() 
	{
		bo_home = new google.maps.LatLng(<?php echo  $lat ?>, <?php echo  $lon ?>);
		bo_home_zoom = <?php echo  $zoom ?>;
		
		var mapOptions = {
		  zoom: bo_home_zoom,
		  center: bo_home,
		  mapTypeId: google.maps.MapTypeId.<?php echo $type ?>,
		  scaleControl: true,
		  streetViewControl: false,
		  scrollwheel: false
		};

		bo_map = new google.maps.Map(document.getElementById("bo_gmap"), mapOptions);
<?php
	if ($show_station & 1)
	{
?>
		var myLatlng = new google.maps.LatLng(<?php echo  "$station_lat,$station_lon" ?>);
		var marker = new google.maps.Marker({
		  position: myLatlng, 
		  map: bo_map, 
		  title:"<?php echo $station_text ?>",
		  icon: 'http://labs.google.com/ridefinder/images/mm_20_red.png'
		});
		
<?php
	}
	
	if (($show_station & 2) && $radius)
	{
?>

		var boDistCircle = {
			clickable: false,
			strokeColor: "<?php echo  BO_MAP_CIRCLE_COLOR_LINE ?>",
			strokeOpacity: <?php echo  BO_MAP_CIRCLE_OPAC_LINE ?>,
			strokeWeight: 1,
			fillColor: "<?php echo  BO_MAP_CIRCLE_COLOR_FILL ?>",
			fillOpacity: <?php echo  BO_MAP_CIRCLE_OPAC_FILL ?>,
			map: bo_map,
			center: new google.maps.LatLng(<?php echo  "$station_lat,$station_lon" ?>),
			radius: <?php echo  $radius + 1000 ?>
		};

		new google.maps.Circle(boDistCircle);
<?php
	}
?>

		bo_gmap_init2();
	}

	function bo_init() 
	{
		if (arguments.callee.done) return; 
		arguments.callee.done = true;   
		if (_timer) clearInterval(_timer);

		bo_gmap_init();
		
		if (bo_load_body)
			bo_load_body();

		
		
	};

	function bo_setcookie(name, value)
	{
		var now = new Date();
		now = new Date(now.getTime()+ 3600*24*365);
		document.cookie = name+'='+value+'; expires='+now.toGMTString()+';';
	}

	function bo_getcookie(name)
	{
		var value = '';
		var c;
		if(document.cookie)
		{
			c = document.cookie;
			if (c.indexOf(name+'=') >= 0)
			{
				c = c.substring(c.indexOf(name+'='));
				c = c.substring(name.length);
				if (c.indexOf(';'))
					value = c.substring(1, c.indexOf(';'));
				else
					value = c;
			}
		}
		return value;
	}
	
	/*** emulate <body onload="..."> ***/
	
	// Mozilla/Opera9
	if (document.addEventListener) {
	  document.addEventListener("DOMContentLoaded", bo_init, false);
	}

	// IE
	/*@cc_on @*/
	/*@if (@_win32)
	  document.write("<script id=__ie_onload defer src=javascript:void(0)><\/script>");
	  var script = document.getElementById("__ie_onload");
	  script.onreadystatechange = function() {
		if (this.readyState == "complete") {
		  bo_init(); 
		}
	  };
	/*@end @*/

	//Safari
	if (/WebKit/i.test(navigator.userAgent)) {
	  var _timer = setInterval(function() {
		if (/loaded|complete/.test(document.readyState)) {
		  bo_init();
		}
	  }, 10);
	}

	//other browsers
	var bo_load_body = window.onload;
	window.onload = bo_init();

</script>

<?php

}



// GoogleMap with Markers
function bo_show_lightning_map()
{
	global $_BO;
	
	//show strike archive for lat/lon instead of map (for known users only!)
	$lat = $_GET['lat'];
	$lon = $_GET['lon'];
	$zoom = intval($_GET['zoom']);
	if (bo_user_get_level() && $lat && $lon)
	{
		if ($zoom)
			$fuzzy = 1/$zoom/7;
			
		bo_show_archive_table($lat, $lon, $fuzzy);
		return;
	}
	
	$disabled = (defined('BO_MAP_DISABLE') && BO_MAP_DISABLE && !bo_user_get_level());
	$no_google = isset($_GET['bo_showmap']) || $disabled;
	
	$static_map_id = intval($_GET['bo_showmap']);
	$menu_text = '';
	foreach($_BO['mapimg'] as $id => $d)
	{
		if (!$d['name'] || !$d['menu'])
			continue;
		
		$menu_text .= '<li><a href="'.bo_insert_url('bo_showmap', "$id").'" class="bo_navi'.($no_google && $static_map_id == $id ? '_active' : '').'">'.htmlentities($d['name']).'</a></li>';
	}
	
	if ($menu_text)
	{
		echo '<ul id="bo_menu">';
		
		if (!$disabled)
			echo '<li><a href="'.bo_insert_url('bo_showmap').'" class="bo_navi'.(!$no_google ? '_active' : '').'">'._BL('Dynamic map').'</a></li>';
		
		echo $menu_text;
		echo '</ul>';
	}

	if ($no_google)
	{	
		$footer= $_BO['mapimg'][$static_map_id]['footer'];
		
		echo '<h3>'._BL('Lightning map').'</h3>';
		echo '<img src="'.BO_FILE.'?map='.$static_map_id.'">';
		echo '<div class="bo_map_footer">'.$footer.'</div>';
		
		return;
	}

	
	
	$radius = $_BO['radius'] * 1000;
	$zoom = 9;
	$lat = BO_LAT;
	$lon = BO_LON;


	echo '<fieldset class="bo_map_options">';
	echo '<legend>'._BL("map_options").'</legend>';

	foreach ($_BO['mapcfg'] as $mapid => $cfg)
	{
		if ($cfg['only_loggedin'] && !bo_user_get_level())
			continue;

		$name = strtr($cfg['sel_name'], array('min' => _BL('unit_minutes'), 'h' => _BL('unit_hours')));
		
		echo '<input type="checkbox" onclick="bo_map_toggle_overlay(this.checked, '.$mapid.');" ';
		echo $cfg['default_show'] ? ' checked="checked" ' : '';
		echo ' id="bo_map_opt'.$mapid.'"> ';
		echo '<label for="bo_map_opt'.$mapid.'">'.$name.'</label> &nbsp ';
	}
	
	echo '<input type="checkbox" onclick="bo_map_toggle_own(this.checked);" id="bo_map_opt_own"> ';
	echo '<label for="bo_map_opt_own">'._BL("only own strikes").'</label>';
	
	echo '<input type="submit" value="'._BL('update map').'" onclick="bo_map_reload_overlays(); return false;" id="bo_map_reload">';
	
	echo '</fieldset>';

	echo '<div id="bo_gmap" class="bo_map" style="width:500px; height:400px;"></div>';
	

	?>
	
	<script type="text/javascript">
	
	var bo_show_only_own = 0;
	
	function bo_map_toggle_overlay(checked, type)
	{
		bo_setcookie('bo_show_ovl'+type, checked ? 1 : -1);
		boOvlMap[type].bo_show = checked;
		bo_map_reload_overlays();
	}
	
	function bo_map_toggle_own(show_only_own)
	{
		bo_setcookie('bo_show_only_own', show_only_own ? 1 : -1);
		bo_show_only_own = show_only_own ? 1 : 0;
		bo_map_reload_overlays();
	}
	
	function bo_map_reload_overlays()
	{
		var i;
		
		while (bo_map.overlayMapTypes.length)
			bo_map.overlayMapTypes.pop();
		
		for (i=boOvlMap.length-1; i>=0;i--)
		{
			if (boOvlMap[i].bo_show)
			{
				bo_map.overlayMapTypes.push(new google.maps.ImageMapType(boOvlMap[i]));
			}
		}
		
		bo_reload_mapinfo();
	}
	
	function bo_get_tile(zoom, coord, type, interval)
	{
		var now = new Date();
		var add = now.getDate() + '_' + now.getHours() + '_' + Math.floor(now.getMinutes() / interval) + (bo_loggedin ? '_1' : '');
		
		return "<?php echo BO_FILE ?>?tile&type="+type+"&own="+bo_show_only_own+"&zoom="+zoom+"&x="+coord.x+"&y="+coord.y+"&"+add;
	}
	
	function bo_reload_mapinfo() 
	{
		bo_infobox.innerHTML = '';
		

		var infoUI = document.createElement('DIV');
		infoUI.style.backgroundColor = '#f9f9f9';
		infoUI.style.borderStyle = 'solid';
		infoUI.style.borderWidth = '1px';
		infoUI.style.borderColor = '#bbe';
		infoUI.style.cursor = 'pointer';
		infoUI.style.textAlign = 'center';
		infoUI.style.width = '79px';
		infoUI.style.margin = '0 0 15px 0';
		infoUI.title = '<?php echo _BL('Click to set the map to Home') ?>';

		var infoText = document.createElement('DIV');
		infoText.style.fontFamily = 'Arial,sans-serif';
		infoText.style.fontSize = '12px';
		infoText.style.paddingLeft = '7px';
		infoText.style.paddingRight = '7px';
		infoText.innerHTML = '<?php echo _BL('Home') ?>';
		infoUI.appendChild(infoText);
		
		bo_infobox.style.width = '80px';
		bo_infobox.style.marginTop = '20px';
		bo_infobox.style.padding = '5px';
		bo_infobox.style.textAlign = 'right';
		bo_infobox.appendChild(infoUI);
		
		for (i=boOvlMap.length-1; i>=0;i--)
		{
			if (boOvlMap[i].bo_show)
			{
				var now = new Date();
				var add = now.getDate() + '_' + now.getHours() + '_' + Math.floor(now.getMinutes() / boOvlMap[i].bo_interval);
				var infoImg = document.createElement('IMG');
				infoImg.src = "<?php echo BO_FILE ?>?tile&info&type="+i+"&"+add+now.getTime();
				infoImg.style.paddingTop = '5px';
				infoImg.style.display = 'block';
				infoImg.style.opacity = 0.7;
				bo_infobox.appendChild(infoImg);
			}
		}
		
		var infoText = document.createElement('DIV');
		infoText.style.fontFamily = 'Arial,sans-serif';
		infoText.style.fontSize = '10px';
		infoText.style.padding = '1px 1px';
		infoText.style.background = '#ddd';
		infoText.style.margin = '20px 0 0 0';
		infoText.style.textAlign = 'center';
		infoText.style.lineHeight = '12px';
		infoText.style.opacity = 0.9;

		infoText.innerHTML = '<?php echo _BL('lightning data') ?>:<br>&copy;&nbsp;Blitzortung.org';
		bo_infobox.appendChild(infoText);

		google.maps.event.addDomListener(infoUI, 'click', function() {
			var mapOptions = {
			  zoom: bo_home_zoom,
			  center: bo_home
			}
			bo_map.setOptions(mapOptions);
		});

	}
	
	function bo_gmap_init2()
	{
<?php
		
		foreach($_BO['mapcfg'] as $mapid => $cfg)
		{
			if ($cfg['only_loggedin'] && !bo_user_get_level())
				continue;
				
			echo '
			boOvlMap['.$mapid.'] = {
				getTileUrl: function (coord, zoom) { return bo_get_tile(zoom, coord, '.$mapid.', '.$cfg['upd_intv'].'); },
				tileSize: new google.maps.Size(256,256), 
				isPng:true, 
				bo_show:'.($cfg['default_show'] ? 'true' : 'false').',
				bo_interval:'.$cfg['upd_intv'].'
			};
			';
		}
		
?>		

		var c = bo_getcookie('bo_show_only_own');
		if (c)
		{
			bo_show_only_own = c == -1 ? 0 : 1;
			document.getElementById('bo_map_opt_own').checked = c == -1 ? false : true;
		}

		
		for (i=0;i<boOvlMap.length;i++)
		{
			var c = bo_getcookie('bo_show_ovl'+i);
			if (c)
			{
				boOvlMap[i].bo_show = c == -1 ? false : true;
				document.getElementById('bo_map_opt' + i).checked = c == -1 ? false : true;
			}
		}

		bo_infobox = document.createElement('DIV');
		bo_infobox.index = 1;
		bo_map.controls[google.maps.ControlPosition.RIGHT_TOP].push(bo_infobox);
		
		bo_map_reload_overlays();
		
		google.maps.event.addListener(bo_map, 'dragend', function() {
			bo_setcookie('bo_map_lat', bo_map.getCenter().lat());
			bo_setcookie('bo_map_lon', bo_map.getCenter().lng());
			bo_setcookie('bo_map_zoom', bo_map.getZoom());
		});

		google.maps.event.addListener(bo_map, 'zoom_changed', function() {
			if (this.getZoom() < 4)
				 this.setZoom(4);
			else
				bo_setcookie('bo_map_zoom', bo_map.getZoom());
		});
		
		

		var map_lat = bo_getcookie('bo_map_lat');
		var map_lon = bo_getcookie('bo_map_lon');
		var map_zoom = bo_getcookie('bo_map_zoom');
		
		if (map_lat > 0 && map_lon > 0 && map_zoom > 0)
		{
			var mapOptions = {
			  zoom: parseInt(map_zoom),
			  center: new google.maps.LatLng(map_lat,map_lon)
			}
			bo_map.setOptions(mapOptions);
		}

<?php  if (bo_user_get_level()) { ?>
		google.maps.event.addListener(bo_map, 'rightclick', function(event) {
		if (bo_map.getZoom() > 7)
		{
			var newWindow = window.open("<?php echo bo_insert_url() ?>&lat="+event.latLng.lat()+"&lon="+event.latLng.lng()+"&zoom="+bo_map.getZoom(), '_blank');
			newWindow.focus();
		}
});


<?php  } ?>

	}
	
	</script>
	
	<?php

	
	bo_insert_map(3, $lat, $lon, $zoom);
}


?>