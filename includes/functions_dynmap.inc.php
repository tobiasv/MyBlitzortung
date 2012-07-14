<?php


function bo_insert_map($show_station=3, $lat=BO_LAT, $lon=BO_LON, $zoom=BO_DEFAULT_ZOOM, $type=BO_DEFAULT_MAP)
{
	global $_BO;

	$radius = $_BO['radius'] * 1000;

	$info = bo_station_info();
	
	if ($info)
		$station_text = $info['city'];
		
	$station_lat = BO_LAT;
	$station_lon = BO_LON;
	$center_lat = BO_MAP_LAT ? BO_MAP_LAT : BO_LAT;
	$center_lon = BO_MAP_LON ? BO_MAP_LON : BO_LON;
	
?>


	<script type="text/javascript" id="bo_script_map">
	
	
	
	//bo_ProjectedOverlay
	//Source: http://www.usnaviguide.com/v3maps/js/bo_ProjectedOverlay.js
	var bo_ProjectedOverlay = function(map, imageUrl, bounds, opts)
	{
	 google.maps.OverlayView.call(this);
	 this.url_ = imageUrl ;
	 this.bounds_ = bounds ;
	 this.addZ_ = opts.addZoom || '' ;				// Add the zoom to the image as a parameter
	 this.id_ = opts.id || this.url_ ;				// Added to allow for multiple images
	 this.percentOpacity_ = opts.opacity || 50 ;
	 this.layer_ = opts.layer || 0;
	 this.map_ = map;
	}

	
	var bo_map;
	var bo_home;
	var bo_home_zoom;
	var bo_infobox;
	var bo_loggedin = <?php echo intval(bo_user_get_level()) ?>;
	var boDistCircle;
	
	function bo_gmap_init() 
	{ 
		bo_home = new google.maps.LatLng(<?php echo  $lat ?>, <?php echo  $lon ?>);
		bo_home_zoom = <?php echo $zoom ?>;

		var mapOptions = {
		  zoom: bo_home_zoom,
		  center: bo_home,
		  mapTypeId: google.maps.MapTypeId.<?php echo $type ?>,
		  scaleControl: true,
		  streetViewControl: false,
		  scrollwheel: false
		};

		bo_map = new google.maps.Map(document.getElementById("bo_gmap"), mapOptions);


<?php if (($show_station & 1)) { ?>

		var myLatlng = new google.maps.LatLng(<?php echo "$station_lat,$station_lon" ?>);
		
<?php if (BO_MAP_STATION_ICON) { ?>

		var marker = new google.maps.Marker({
		  position: myLatlng, 
		  map: bo_map, 
		  title:"<?php echo _BC($station_text) ?>",
		  icon: '<?php echo BO_MAP_STATION_ICON ?>' 
		});
		
<?php } ?>

<?php } ?>


<?php if (($show_station & 2) && $radius) { ?>

		boDistCircle = new google.maps.Circle( {
			clickable: false,
			strokeColor: "<?php echo BO_MAP_CIRCLE_COLOR_LINE ?>",
			strokeOpacity: <?php echo BO_MAP_CIRCLE_OPAC_LINE ?>,
			strokeWeight: "<?php echo BO_MAP_CIRCLE_STROKE_LINE ?>",
			fillColor: "<?php echo BO_MAP_CIRCLE_COLOR_FILL ?>",
			fillOpacity: <?php echo BO_MAP_CIRCLE_OPAC_FILL ?>,
			map: bo_map,
			center: new google.maps.LatLng(<?php echo "$center_lat,$center_lon" ?>),
			radius: <?php echo $radius + 1000 ?>
		} );

		bo_show_circle(bo_home_zoom);
		//google.maps.event.addListener(bo_map, 'zoom_changed', bo_show_circle());
		
<?php } ?>







		bo_ProjectedOverlay.prototype = new google.maps.OverlayView();	
		
		// Remove the main DIV from the map pane
		bo_ProjectedOverlay.prototype.remove = function()
		{
			 if (this.div_) 
			 {
			  this.div_.parentNode.removeChild(this.div_);
			  this.div_ = null;
			 }
		}

		bo_ProjectedOverlay.prototype.onAdd = function() 
		{
			  // Note: an overlay's receipt of onAdd() indicates that
			  // the map's panes are now available for attaching
			  // the overlay to the map via the DOM.

			  // Create the DIV and set some basic attributes.
			  var div = document.createElement('DIV');
			  div.style.border = "none";
			  div.style.borderWidth = "0px";
			  div.style.position = "absolute";

			  // Create an IMG element and attach it to the DIV.
			  var img = document.createElement("img");
			  img.src = this.url_;
			  img.style.width = "100%";
			  img.style.height = "100%";
			  div.appendChild(img);

			  // Set the overlay's div_ property to this DIV
			  this.div_ = div;
				  
			  if( this.percentOpacity_ )
			  {
			   this.setOpacity(this.percentOpacity_) ;
			  }
			  
			  // We add an overlay to a map via one of the map's panes.
			  // We'll add this overlay to the overlayImage pane.
			  var panes = this.getPanes();
			  
			  if (this.layer_ == 1)
				panes.mapPane.appendChild(div); //map pane = same as strikes
			  else
			    panes.overlayLayer.appendChild(div);
				
		}
		
		// Redraw based on the current projection and zoom level...
		bo_ProjectedOverlay.prototype.draw = function(firstTime)
		{
			if (!this.div_)
			{
				return ;
			}

			var c1 = this.getProjection().fromLatLngToDivPixel(this.bounds_.getSouthWest());
			var c2 = this.getProjection().fromLatLngToDivPixel(this.bounds_.getNorthEast());

			if (!c1 || !c2) return;

			if (c1.x > c2.x)
				c2.x += this.getProjection().getWorldWidth();
				
			 // Now position our DIV based on the DIV coordinates of our bounds
			 this.div_.style.width = Math.abs(c2.x - c1.x) + "px";
			 this.div_.style.height = Math.abs(c2.y - c1.y) + "px";
			 this.div_.style.left = Math.min(c2.x, c1.x) + "px";
			 this.div_.style.top = Math.min(c2.y, c1.y) + "px";

			 // Do the rest only if the zoom has changed...
			 if ( this.lastZoom_ == this.map_.getZoom() )
			 {
			  return ;
			 }

			 this.lastZoom_ = this.map_.getZoom() ;

			 var url = this.url_ ;

			 if ( this.addZ_ )
			 {
			  url += this.addZ_ + this.map_.getZoom() ;
			 }

			 this.div_.innerHTML = '<img src="' + url + '"  width=' + this.div_.style.width + ' height=' + this.div_.style.height + ' >' ;
		}

		bo_ProjectedOverlay.prototype.setOpacity=function(opacity)
		{
			 if (opacity < 0)
			 {
			  opacity = 0 ;
			 }
			 if(opacity > 100)
			 {
			  opacity = 100 ;
			 }
			 var c = opacity/100 ;

			 if (typeof(this.div_.style.filter) =='string')
			 {
			  this.div_.style.filter = 'alpha(opacity:' + opacity + ')' ;
			 }
			 if (typeof(this.div_.style.KHTMLOpacity) == 'string' )
			 {
			  this.div_.style.KHTMLOpacity = c ;
			 }
			 if (typeof(this.div_.style.MozOpacity) == 'string')
			 {
			  this.div_.style.MozOpacity = c ;
			 }
			 if (typeof(this.div_.style.opacity) == 'string')
			 {
			  this.div_.style.opacity = c ;
			 }
		}



		bo_gmap_init2();
	}
	
	function bo_setcookie(name, value)
	{
		var now = new Date();
		now = new Date(now.getTime()+ 3600*24*365);
		document.cookie = name+'='+value+'; expires='+now.toGMTString()+';';
	}

	
	function bo_getcookie( check_name ) 
	{
		// first we'll split this cookie up into name/value pairs
		// note: document.cookie only returns name=value, not the other components
		var a_all_cookies = document.cookie.split( ';' );
		var a_temp_cookie = '';
		var cookie_name = '';
		var cookie_value = '';
		var b_cookie_found = false; // set boolean t/f default f

		for ( i = 0; i < a_all_cookies.length; i++ )
		{
			// now we'll split apart each name=value pair
			a_temp_cookie = a_all_cookies[i].split( '=' );

			// and trim left/right whitespace while we're at it
			cookie_name = a_temp_cookie[0].replace(/^\s+|\s+$/g, '');

			// if the extracted name matches passed check_name
			if ( cookie_name == check_name )
			{
				b_cookie_found = true;
				// we need to handle case where cookie has no value but exists (no = sign, that is):
				if ( a_temp_cookie.length > 1 )
				{
					cookie_value = unescape( a_temp_cookie[1].replace(/^\s+|\s+$/g, '') );
				}
				// note that in cases where cookie is initialized but no value, null is returned
				return cookie_value;
				break;
			}
			a_temp_cookie = null;
			cookie_name = '';
		}
		if ( !b_cookie_found )
		{
			return '';
		}
	}


	function bo_show_circle(zoom)
	{
		if (typeof(boDistCircle) == 'undefined')
			return;
			
		if (zoom < <?php echo intval(BO_MAP_CIRCLE_SHOW_ZOOM); ?>)
		{
			boDistCircle.setMap(null);
		}
		else
		{
			boDistCircle.setMap(bo_map);
		}
	}
		


	</script>

    <script type="text/javascript" id="bo_script_google" src="http://maps.googleapis.com/maps/api/js?callback=bo_gmap_init&v=<?php echo BO_GMAP_API_VERSION.'&'.BO_GMAP_PARAM ?>">
    </script>



<?php

}



?>