<?php



// latitude, longitude to distance in meters
function bo_latlon2dist($lat1, $lon1, $lat2 = BO_LAT, $lon2 = BO_LON)
{
	if ($lat1 == $lat2 && $lon1 == $lon2)
		return 0;

	return acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2))) * 6371000;
}


function bo_latlon2dist_rhumb($lat1, $lon1, $lat2 = BO_LAT, $lon2 = BO_LON)
{
	$R = 6371000;

	$lat1     = deg2rad($lat1);
	$lat2     = deg2rad($lat2);

	$dlat     = deg2rad($lat2 - $lat1);
	$dlon     = abs(deg2rad($lon2 - $lon1));

	$dPhi = log(tan($lat2/2 + M_PI/4) / tan($lat1/2 + M_PI/4));
	$q    = $dPhi != 0 ? $dlat / $dPhi : cos($lat1);  // E-W line gives dPhi=0

	// if dLon over 180° take shorter rhumb across 180° meridian:
	if ($dlon > M_PI) $dlon = 2*M_PI - $dlon;

	$dist = sqrt($dlat*$dlat + $q*$q*$dlon*$dlon) * $R;

	return $dist;
}

// latitude, longitude to bearing
function bo_latlon2bearing($lat1, $lon1, $lat2 = BO_LAT, $lon2 = BO_LON)
{
	if ($lat1 == $lat2 && $lon1 == $lon2)
		return false;
		
	$dlon = deg2rad($lon1-$lon2);
	$lat1 = deg2rad($lat1);
	$lat2 = deg2rad($lat2);
	
	$bear = atan2(sin($dlon)*cos($lat2), cos($lat1)*sin($lat2) - sin($lat1)*cos($lat2)*cos($dlon) );
	$bear = -rad2deg($bear) + 180;
	$bear = fmod($bear, 360);
	
	return $bear;
}


// latitude, longitude to bearing (Rhumb Line!)
function bo_latlon2bearing_rhumb($lat2, $lon2, $lat1 = BO_LAT, $lon1 = BO_LON)
{
     //difference in longitudinal coordinates
     $dLon = deg2rad($lon2) - deg2rad($lon1);

     //difference in the phi of latitudinal coordinates
     $dPhi = log(tan(deg2rad($lat2) / 2 + pi() / 4) / tan(deg2rad($lat1) / 2 + pi() / 4));

     //we need to recalculate $dLon if it is greater than pi
     if(abs($dLon) > pi()) {
          if($dLon > 0) {
               $dLon = (2 * pi() - $dLon) * -1;
          }
          else {
               $dLon = 2 * pi() + $dLon;
          }
     }

     //return the angle, normalized
	 $bear = rad2deg(atan2($dLon, $dPhi)) + 360;
	 $bear = fmod($bear, 360);

     return $bear;
}

function bo_bearing2direction($bearing)
{
     $tmp = round($bearing / 22.5);

     switch($tmp)
	 {
          case 1: $direction = "NNE"; break;
          case 2: $direction = "NE";  break;
          case 3: $direction = "ENE"; break;
          case 4: $direction = "E";   break;
          case 5: $direction = "ESE"; break;
          case 6: $direction = "SE";  break;
          case 7: $direction = "SSE"; break;
          case 8: $direction = "S";   break;
          case 9: $direction = "SSW"; break;
          case 10: $direction = "SW"; break;
          case 11: $direction = "WSW"; break;
          case 12: $direction = "W";   break;
          case 13: $direction = "WNW"; break;
          case 14: $direction = "NW";  break;
          case 15: $direction = "NNW"; break;
          default: $direction = "N";
	 }

     return $direction;
}

//return latitude from given position, distance and bearing
function bo_distbearing2latlong($dist, $bearing, $lat = BO_LAT, $lon = BO_LON)
{
	$R       = 6371000;
	$lat     = deg2rad($lat);
	$lon     = deg2rad($lon);
	$bearing = deg2rad($bearing);

	$lat2 = asin( sin($lat) * cos($dist/$R) + cos($lat) * sin($dist/$R) * cos($bearing) );
	$lon2 = $lon + atan2(sin($bearing) * sin($dist/$R) * cos($lat), cos($dist/$R) - sin($lat) * sin($lat2));

	$lat2 = rad2deg($lat2);
	$lon2 = rad2deg($lon2);

	return array($lat2, $lon2);
}

//return latitude from given position, distance and bearing (Rhumb line = constant bearing = not the shortest way)
function bo_distbearing2latlong_rhumb($dist, $bearing, $lat = BO_LAT, $lon = BO_LON)
{
	$lat     = deg2rad($lat);
	$lon     = deg2rad($lon);

	$d    = $dist / 6371000;
	$lat2 = $lat + $d * cos($bearing);
	$dLat = $lat2-$lat;
	$dPhi = log(tan($lat2/2 + M_PI/4) / tan($lat/2 + M_PI/4));
	$q    = $dPhi != 0 ? $dLat/$dPhi : cos($lat);  // E-W line gives dPhi=0
	$dLon = $dist * sin($bearing) / $q;

	// check for some daft bugger going past the pole, normalise latitude if so
	if (abs($lat2) > M_PI/2)
		$lat2 = $lat2 > 0 ? M_PI-$lat2 : -(M_PI-$lat2);

	$lon2 = fmod($lon + $dLon + M_PI, 2 * M_PI) - M_PI;

	$lat2 = rad2deg($lat2);
	$lon2 = rad2deg($lon2);

	return array($lat2, $lon2);
}



function bo_latlon2mercator($lat, $lon)
{
	$lon /= 360;
	$lat = log(tan(M_PI/4 + deg2rad($lat)/2))/M_PI/2;
	return array($lon, $lat);
}


function bo_latlon2miller($lat, $lon)
{
	$lon /= 360;
	$lat = (5/4)*log(tan(M_PI/4 + 2/5*deg2rad($lat)))/M_PI/2;
	return array($lon, $lat);
}

function bo_latlon2geos($lat, $lon, $sub_lon = 0)
{

	//  REFERENCE:                                            
	//  [1] LRIT/HRIT Global Specification                     
	//      (CGMS 03, Issue 2.6, 12.08.1999)                  
	//      for the parameters used in the program.

	if ($lon > 180)
		$lon -= 360;

	$SUB_LON     = $sub_lon;   /* longitude of sub-satellite point in radiant */
	$R_POL       = 6356.5838;  /* radius from Earth centre to pol             */
	$R_EQ        = 6378.169;   /* radius from Earth centre to equator         */
	$SAT_HEIGHT  = 42164.0;    /* distance from Earth centre to satellite     */
		
	$lat = deg2rad($lat);
	$lon = deg2rad($lon);
	
	/* calculate the geocentric latitude from the          */
	/* geograhpic one using equations on page 24, Ref. [1] */
	$c_lat = atan( 0.993243 * tan($lat) );
	
	/* using c_lat calculate the length form the Earth */
	/* centre to the surface of the Earth ellipsoid    */
	/* equations on page 23, Ref. [1]                  */
	$re = $R_POL / sqrt( (1.0 - 0.00675701 * cos($c_lat) * cos($c_lat) ) );
	
	/* calculate the forward projection using equations on */
	/* page 24, Ref. [1]                                   */
	$rl = $re; 
	$r1 = $SAT_HEIGHT - $rl * cos($c_lat) * cos($lon - $SUB_LON);
	$r2 = -$rl *  cos($c_lat) * sin($lon - $SUB_LON);
	$r3 = $rl * sin($c_lat);
	$rn = sqrt( $r1*$r1 + $r2*$r2 +$r3*$r3 );
	

	/* check for visibility, whether the point on the Earth given by the */
	/* latitude/longitude pair is visible from the satellte or not. This */ 
	/* is given by the dot product between the vectors of:               */
	/* 1) the point to the spacecraft,			               */
	/* 2) the point to the centre of the Earth.			       */
	/* If the dot product is positive the point is visible otherwise it  */
	/* is invisible.						       */
	$dotprod = $r1*($rl * cos($c_lat) * cos($lon - $SUB_LON)) - $r2*$r2 - $r3*$r3*(pow(($R_EQ/$R_POL),2));

	if ($dotprod <= 0 )
		return false;
	
	/* the forward projection is x and y */
	$x = atan(-$r2/$r1);
	$y = asin(-$r3/$rn);
	
	return array($x, $y);
}




?>