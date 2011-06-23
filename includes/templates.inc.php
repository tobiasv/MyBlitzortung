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


$_BO['mapcfg'] = array();
$_BO['mapovl'] = array();
$_BO['mapimg'] = array();
$_BO['density'] = array();

/**************************************/
/* Some predefined colors             */
/**************************************/

$_BO['tpl_color']['standard'][] = array(255, 255, 0);
$_BO['tpl_color']['standard'][] = array(255, 200, 0);
$_BO['tpl_color']['standard'][] = array(255, 150, 0);
$_BO['tpl_color']['standard'][] = array(255, 100, 0);
$_BO['tpl_color']['standard'][] = array(255,   0, 0);
$_BO['tpl_color']['standard'][] = array(225,   0, 0);

$_BO['tpl_density_colors'][] = array(150, 150, 250, 105);
$_BO['tpl_density_colors'][] = array(50, 150, 155, 15);
$_BO['tpl_density_colors'][] = array(205, 205, 50, 15);
$_BO['tpl_density_colors'][] = array(230, 0, 0, 15);
$_BO['tpl_density_colors'][] = array(255, 0, 255, 15);
$_BO['tpl_density_colors'][] = array(255, 190, 255, 15);


/**************************************/
/* Calculation settings for density   */
/**************************************/

//Europe
$_BO['tpl_density']['europe']['name'] 	= 'Europe';
$_BO['tpl_density']['europe']['coord'] 	= array(72, 52, 20, -22); //North, East, South, West (Degrees)
$_BO['tpl_density']['europe']['length']	= 10; // minimum width (W<->E) AND height (N<->S) of the box (kilometers)
$_BO['tpl_density']['europe']['bps'] 	= 2; // bytes per area 


//Germany
$_BO['tpl_density']['germany']['name'] 		= 'Germany';
$_BO['tpl_density']['germany']['coord'] 	= array(56, 16, 46, 5); //North, East, South, West (Degrees)
$_BO['tpl_density']['germany']['length']	= 2; // minimum width (W<->E) AND height (N<->S) of the box (kilometers)
$_BO['tpl_density']['germany']['bps'] 		= 2; // bytes per area 



/**************************************/
/* Dynamic map tiles                  */
/**************************************/

$_BO['tpl_gmap']['0-15']['tstart'] = 15;
$_BO['tpl_gmap']['0-15']['trange'] = 15;
$_BO['tpl_gmap']['0-15']['upd_intv'] = 5;
$_BO['tpl_gmap']['0-15']['col'][] = array(255, 255, 0);
$_BO['tpl_gmap']['0-15']['col'][] = array(255, 240, 0);
$_BO['tpl_gmap']['0-15']['col'][] = array(255, 225, 0);
$_BO['tpl_gmap']['0-15']['default_show'] = true;
$_BO['tpl_gmap']['0-15']['sel_name'] = '0-15 min';

$_BO['tpl_gmap']['15-120']['tstart'] = 120;
$_BO['tpl_gmap']['15-120']['trange'] = 105;
$_BO['tpl_gmap']['15-120']['upd_intv'] = 15;
$_BO['tpl_gmap']['15-120']['col'][] = array(250, 190, 0);
$_BO['tpl_gmap']['15-120']['col'][] = array(245, 170, 10);
$_BO['tpl_gmap']['15-120']['col'][] = array(240, 150, 10);
$_BO['tpl_gmap']['15-120']['col'][] = array(235, 130, 10);
$_BO['tpl_gmap']['15-120']['col'][] = array(230, 110, 10);
$_BO['tpl_gmap']['15-120']['col'][] = array(225,  90, 10);
$_BO['tpl_gmap']['15-120']['col'][] = array(220,  70, 10);
$_BO['tpl_gmap']['15-120']['default_show'] = true;
$_BO['tpl_gmap']['15-120']['sel_name'] = '15-120 min';

$_BO['tpl_gmap']['2-24h']['tstart'] = 60 * 24;
$_BO['tpl_gmap']['2-24h']['trange'] = 60 * 22;
$_BO['tpl_gmap']['2-24h']['upd_intv'] = 30;
for ($i=0;$i<20;$i++)
	$_BO['tpl_gmap']['2-24h']['col'][] = array(200-150*$i/20, 10+40*$i/20, 50+200*$i/20);
$_BO['tpl_gmap']['2-24h']['default_show'] = false;
$_BO['tpl_gmap']['2-24h']['sel_name'] = '2-24 h';


// Example for 1 to 10 days. Be careful: can cause high database load!
$_BO['tpl_gmap']['1-10d']['tstart'] = 60 * 24 * 10;
$_BO['tpl_gmap']['1-10d']['trange'] = 60 * 24 * 9;
$_BO['tpl_gmap']['1-10d']['upd_intv'] = 60;
for ($i=0;$i<20;$i++)
	$_BO['tpl_gmap']['1-10d']['col'][] = array(80 + 2*$i/20, 50   +200*$i/20, 230   -200*$i/20);
$_BO['tpl_gmap']['1-10d']['default_show'] = false;
$_BO['tpl_gmap']['1-10d']['sel_name'] = '1-10 days';
$_BO['tpl_gmap']['1-10d']['only_loggedin'] = true;

/**************************************/
/* Image Maps (PNG)                   */
/**************************************/

//Europe
//MapTool URL: http://www.blitzortung.org/Webpages/index.php?lang=de&page=3&subpage_3=4&update=1&map_generator_north=71.3&map_generator_west=-22&map_generator_east=52&map_generator_south=24.3&map_generator_min_area=50&map_generator_shorelines=0.1p%2F30%2F30%2F30&map_generator_boundaries=a%2F0.1p%2F30%2F30%2F30&map_generator_rivers=-&map_generator_lakes=31%2F31%2F160&map_generator_dry_area=-&map_generator_wet_area=31%2F31%2F160&map_generator_color_map=-200+100+195+100+0+100+195+100+%0D%0A0+100+188+100+100+100+188+100+%0D%0A100+100+177+100+200+100+177+100+%0D%0A200+100+166+100+500+100+166+100+%0D%0A500+100+155+100+1000+100+155+100+%0D%0A1000+90+144+90+1500+90+144+90%0D%0A1500+80+140+80+2000+80+140+80%0D%0A2000+75+135+75+2500+75+135+75%0D%0A2500+70+130+70+3000+70+130+70%0D%0A3000+65+120+65+5000+65+120+65%0D%0A&map_generator_azimuth=315&map_generator_normalization=e0.01
$_BO['tpl_imgmap']['europe']['name'] = 'Europe';
$_BO['tpl_imgmap']['europe']['menu'] = true;
$_BO['tpl_imgmap']['europe']['archive'] = true;
$_BO['tpl_imgmap']['europe']['file'] = 'map_europe.png';
$_BO['tpl_imgmap']['europe']['borders'] = array('map_europe_borders.png', 60);
$_BO['tpl_imgmap']['europe']['coord'] = array(71.3, 52, 24.3, -22); //North, East, South, West (Degrees)
$_BO['tpl_imgmap']['europe']['trange'] = 2; //hours!
$_BO['tpl_imgmap']['europe']['upd_intv'] = 15; //minutes
$_BO['tpl_imgmap']['europe']['textcolor'] = array(255,255,255);
$_BO['tpl_imgmap']['europe']['textsize'] = 5;
$_BO['tpl_imgmap']['europe']['point_type'] = 2;
$_BO['tpl_imgmap']['europe']['point_size'] = 1;
$_BO['tpl_imgmap']['europe']['legend'] = array(5, 100, 80, 4, 4, 1);
$_BO['tpl_imgmap']['europe']['col'] = $_BO['tpl_color']['standard'];
$_BO['tpl_imgmap']['europe']['density'] = true;
$_BO['tpl_imgmap']['europe']['density_blocksize'] = 10;
$_BO['tpl_imgmap']['europe']['density_colors'] = $_BO['tpl_density_colors'];
$_BO['tpl_imgmap']['europe']['density_darken'] = 40;

//Europe (mini)
$_BO['tpl_imgmap']['europe_mini']['name'] = 'Europe (mini)';
$_BO['tpl_imgmap']['europe_mini']['menu'] = false;
$_BO['tpl_imgmap']['europe_mini']['file'] = 'map_europe_mini.png';
$_BO['tpl_imgmap']['europe_mini']['coord'] = array(71.1, 44.5, 31.8, -14.0); //North, East, South, West (Degrees)
$_BO['tpl_imgmap']['europe_mini']['trange'] = 2; //hours!
$_BO['tpl_imgmap']['europe_mini']['upd_intv'] = 15; //minutes
$_BO['tpl_imgmap']['europe_mini']['textcolor'] = array(255,255,255);
$_BO['tpl_imgmap']['europe_mini']['textsize'] = 1;
$_BO['tpl_imgmap']['europe_mini']['point_type'] = 1;
$_BO['tpl_imgmap']['europe_mini']['point_size'] = 2;
$_BO['tpl_imgmap']['europe_mini']['legend'] = array(0, 54, 26, 0, 0, 0);
$_BO['tpl_imgmap']['europe_mini']['col'] = $_BO['tpl_color']['standard'];

//Germany (mini)
$_BO['tpl_imgmap']['germany_mini']['name'] = 'Germany (mini)';
$_BO['tpl_imgmap']['germany_mini']['menu'] = false;
$_BO['tpl_imgmap']['germany_mini']['file'] = 'map_germany.png';
$_BO['tpl_imgmap']['germany_mini']['coord'] = array(56, 18.3, 46.3, 1.8); //North, East, South, West (Degrees)
$_BO['tpl_imgmap']['germany_mini']['trange'] = 2; //hours!
$_BO['tpl_imgmap']['germany_mini']['upd_intv'] = 5; //minutes
$_BO['tpl_imgmap']['germany_mini']['textcolor'] = array(255,255,255);
$_BO['tpl_imgmap']['germany_mini']['textsize'] = 1;
$_BO['tpl_imgmap']['germany_mini']['point_type'] = 2;
$_BO['tpl_imgmap']['germany_mini']['point_size'] = 1;
$_BO['tpl_imgmap']['germany_mini']['legend'] = array(0, 54, 26, 0, 0, 0);
$_BO['tpl_imgmap']['germany_mini']['col'] = $_BO['tpl_color']['standard'];

//Germany (Landkreise) no exact merator prjection!
$_BO['tpl_imgmap']['germany_lkr']['name'] = 'Germany';
$_BO['tpl_imgmap']['germany_lkr']['footer'] = 'Kartenmaterial: <a href="http://de.wikipedia.org/w/index.php?title=Datei:Landkreise,_Kreise_und_kreisfreie_St%C3%A4dte_in_Deutschland.svg" target="_blank">Wikipedia</a> &bull; Achtung: Positionen sind nicht exakt (nur bei dieser Karte!)';
$_BO['tpl_imgmap']['germany_lkr']['menu'] = true;
$_BO['tpl_imgmap']['germany_lkr']['archive'] = true;
$_BO['tpl_imgmap']['germany_lkr']['file'] = 'map_germany_lkr.png';
$_BO['tpl_imgmap']['germany_lkr']['coord'] = array(55.065, 15.11, 47.380, 5.85); //North, East, South, West (Degrees)
$_BO['tpl_imgmap']['germany_lkr']['trange'] = 2; //hours!
$_BO['tpl_imgmap']['germany_lkr']['upd_intv'] = 5; //minutes
$_BO['tpl_imgmap']['germany_lkr']['textcolor'] = array(255,255,255);
$_BO['tpl_imgmap']['germany_lkr']['textsize'] = 5;
$_BO['tpl_imgmap']['germany_lkr']['point_type'] = 2;
$_BO['tpl_imgmap']['germany_lkr']['point_size'] = 2;
$_BO['tpl_imgmap']['germany_lkr']['legend'] = array(5, 100, 80, 4, 4, 1);
$_BO['tpl_imgmap']['germany_lkr']['col'] = $_BO['tpl_color']['standard'];
$_BO['tpl_imgmap']['germany_lkr']['show_station'] = array(5, 155,80,155,true);
$_BO['tpl_imgmap']['germany_lkr']['density'] = true;
$_BO['tpl_imgmap']['germany_lkr']['density_blocksize'] = 8;
$_BO['tpl_imgmap']['germany_lkr']['density_colors'] = $_BO['tpl_density_colors'];
$_BO['tpl_imgmap']['germany_lkr']['density_darken'] = 10;

//Germany (Landkreise) Geodatenzentrum
//see http://www.geodatenzentrum.de/geodaten/gdz_rahmen.gdz_div?gdz_spr=deu&gdz_akt_zeile=5&gdz_anz_zeile=4&gdz_user_id=0
$_BO['tpl_imgmap']['germany_lkr_gdz'] = $_BO['tpl_imgmap']['germany_lkr'];
$_BO['tpl_imgmap']['germany_lkr_gdz']['file'] = 'map_germany_lkr_gdz.png';
$_BO['tpl_imgmap']['germany_lkr_gdz']['borders'] = array('map_germany_lkr_gdz_borders.png', 60);
$_BO['tpl_imgmap']['germany_lkr_gdz']['coord'] = array(55.22, 15.64, 47.05, 5.21); //North, East, South, West (Degrees)
$_BO['tpl_imgmap']['germany_lkr_gdz']['footer'] = '© Bundesamt für Kartographie und Geodäsie, Frankfurt am Main, 2011<br>
	Vervielfältigung, Verbreitung und öffentliche Zugänglichmachung, auch auszugsweise, mit Quellenangabe gestattet.';

//Germany (Bavaria) * don't know wether mercator, but border overlap really good in google earth
$_BO['tpl_imgmap']['germany_bavaria']['name'] = 'Bavaria';
$_BO['tpl_imgmap']['germany_bavaria']['footer'] = 'Kartenmaterial: <a href="http://commons.wikimedia.org/wiki/File:Bavaria_relief_location_map.jpg?uselang=de">Wikimedia Commons</a> &bull; GNU-Lizenz für freie Dokumentation ';
$_BO['tpl_imgmap']['germany_bavaria']['menu'] = true;
$_BO['tpl_imgmap']['germany_bavaria']['archive'] = true;
$_BO['tpl_imgmap']['germany_bavaria']['file'] = 'map_germany_bavaria.png';
$_BO['tpl_imgmap']['germany_bavaria']['coord'] = array(50.62, 14.059753, 47.25, 8.897552); //North, East, South, West (Degrees)
$_BO['tpl_imgmap']['germany_bavaria']['trange'] = 2; //hours!
$_BO['tpl_imgmap']['germany_bavaria']['upd_intv'] = 5; //minutes
$_BO['tpl_imgmap']['germany_bavaria']['textcolor'] = array(255,255,255);
$_BO['tpl_imgmap']['germany_bavaria']['textsize'] = 5;
$_BO['tpl_imgmap']['germany_bavaria']['point_type'] = 2;
$_BO['tpl_imgmap']['germany_bavaria']['point_size'] = 2;
$_BO['tpl_imgmap']['germany_bavaria']['legend'] = array(5, 100, 80, 4, 4, 1);
$_BO['tpl_imgmap']['germany_bavaria']['col'] = $_BO['tpl_color']['standard'];
$_BO['tpl_imgmap']['germany_bavaria']['show_station'] = array(5, 155,80,155,true);
$_BO['tpl_imgmap']['germany_bavaria']['density'] = true;
$_BO['tpl_imgmap']['germany_bavaria']['density_blocksize'] = 8;
$_BO['tpl_imgmap']['germany_bavaria']['density_colors'] = $_BO['tpl_density_colors'];
$_BO['tpl_imgmap']['germany_bavaria']['density_darken'] = 20;
	

//Germany NRW: not exact, maximum deviation about 2km
$_BO['tpl_imgmap']['germany_nrw']['name'] = 'NRW';
$_BO['tpl_imgmap']['germany_nrw']['footer'] = 'Kartenmaterial: <a href="http://commons.wikimedia.org/wiki/File:North_Rhine-Westphalia_location_map_03.svg" target="_blank">Wikipedia</a>';
$_BO['tpl_imgmap']['germany_nrw']['menu'] = true;
$_BO['tpl_imgmap']['germany_nrw']['archive'] = true;
$_BO['tpl_imgmap']['germany_nrw']['file'] = 'map_germany_nrw_color.png';
$_BO['tpl_imgmap']['germany_nrw']['coord'] = array(52.552, 5.84 , 50.295 , 9.481); //North, East, South, West (Degrees)
$_BO['tpl_imgmap']['germany_nrw']['trange'] = 2; //hours!
$_BO['tpl_imgmap']['germany_nrw']['upd_intv'] = 3; //minutes
$_BO['tpl_imgmap']['germany_nrw']['textcolor'] = array(255,255,255);
$_BO['tpl_imgmap']['germany_nrw']['textsize'] = 5;
$_BO['tpl_imgmap']['germany_nrw']['point_type'] = 2;
$_BO['tpl_imgmap']['germany_nrw']['point_size'] = 2;
$_BO['tpl_imgmap']['germany_nrw']['legend'] = array(5, 100, 80, 4, 4, 1);
$_BO['tpl_imgmap']['germany_nrw']['col'] = $_BO['tpl_color']['standard'];
$_BO['tpl_imgmap']['germany_nrw']['density'] = true;
$_BO['tpl_imgmap']['germany_nrw']['density_blocksize'] = 10;
$_BO['tpl_imgmap']['germany_nrw']['density_colors'] = $_BO['tpl_density_colors'];
$_BO['tpl_imgmap']['germany_nrw']['density_darken'] = 40;

//Germany NRW black&white
$_BO['tpl_imgmap']['germany_nrw_dark'] = $_BO['tpl_imgmap']['germany_nrw'];
$_BO['tpl_imgmap']['germany_nrw_dark']['file'] = 'map_germany_nrw_dark.png';



/**************************************/
/* Extra Overlays for dynamic Map     */
/**************************************/

//Meteox Radar Overlay
//WARNING: Copyright! Only internal private usage!
$_BO['tpl_overlay']['meteox_radar']['img'] = 'http://www2.meteox.com/radareu.php';
$_BO['tpl_overlay']['meteox_radar']['coord'] = array(59.9934, 20.4106, 41.4389, -14.9515); //North, East, South, West (Degrees)
$_BO['tpl_overlay']['meteox_radar']['default_show'] = false;
$_BO['tpl_overlay']['meteox_radar']['sel_name'] = 'Meteox rain radar';
$_BO['tpl_overlay']['meteox_radar']['only_loggedin'] = true;
$_BO['tpl_overlay']['meteox_radar']['to_mercator'] = true;

//Meteox Radar Overlay (Scandinavia)
//WARNING: Copyright! Only internal private usage!
$_BO['tpl_overlay']['meteox_radar_scandinavia']['img'] = 'http://www.vaderradar.se/image.ashx';
$_BO['tpl_overlay']['meteox_radar_scandinavia']['coord'] = array(71.546744,35.110422,53.879462,2.865478); //North, East, South, West (Degrees)
$_BO['tpl_overlay']['meteox_radar_scandinavia']['default_show'] = false;
$_BO['tpl_overlay']['meteox_radar_scandinavia']['sel_name'] = 'Meteox rain radar (Scandinavia)';
$_BO['tpl_overlay']['meteox_radar_scandinavia']['only_loggedin'] = true;
$_BO['tpl_overlay']['meteox_radar_scandinavia']['to_mercator'] = true;

//EUCLID (you can check strike polarity with this map)
//WARNING: Copyright! Only internal private usage!
//It's calibrated only for germany!
$_BO['tpl_overlay']['euclid']['img'] = 'http://www.meteorage.fr/euclid/euclid_last_lightnings.gif';
$_BO['tpl_overlay']['euclid']['coord'] = array(68, 43, 33.7, -22); //North, East, South, West (Degrees)
$_BO['tpl_overlay']['euclid']['default_show'] = false;
$_BO['tpl_overlay']['euclid']['sel_name'] = 'EUCLID';
$_BO['tpl_overlay']['euclid']['only_loggedin'] = true;
$_BO['tpl_overlay']['euclid']['to_mercator'] = false;
$_BO['tpl_overlay']['euclid']['opacity'] = 50;

//BLIDS Lightning Overlay
//WARNING: Copyright! Only internal private usage!
$_BO['tpl_overlay']['blids_de']['img'] = 'http://www.blids.de/spion/bilder/aktkartegergrau.jpg';
$_BO['tpl_overlay']['blids_de']['coord'] = array(55.11, 15.53, 47.13, 4.97);  //North, East, South, West (Degrees)
$_BO['tpl_overlay']['blids_de']['default_show'] = false;
$_BO['tpl_overlay']['blids_de']['sel_name'] = 'BLIDS Germany';
$_BO['tpl_overlay']['blids_de']['only_loggedin'] = true;
$_BO['tpl_overlay']['blids_de']['to_mercator'] = false;
$_BO['tpl_overlay']['blids_de']['opacity'] = 50;

//T-Online/wetter.info Radar
//WARNING: Copyright! Only internal private usage!
$time = time() - 300;
$i = sprintf('%02d', (string)intval(date('i', $time) / 15) * 15);
$time = strtotime(gmdate('Y-m-d H:'.$i.':00', $time).' UTC');
$date = gmdate('YmdHi', $time);

$_BO['tpl_overlay']['tonline_radar']['img'] = "http://data.wetter.info//data/layers/xxlradar-de/xxlradar-de_radar_$date.gif"; //'http://data.wetter.info//data/maps/basemaps/xxlradar-de.jpg';
$_BO['tpl_overlay']['tonline_radar']['coord'] = array(55.5, 16.6, 46.2, 4.5);  //North, East, South, West (Degrees)
$_BO['tpl_overlay']['tonline_radar']['default_show'] = false;
$_BO['tpl_overlay']['tonline_radar']['sel_name'] = 'T-Online Radar ('.date('H:i', $time).')';
$_BO['tpl_overlay']['tonline_radar']['only_loggedin'] = true;
$_BO['tpl_overlay']['tonline_radar']['to_mercator'] = true;
$_BO['tpl_overlay']['tonline_radar']['opacity'] = 40;


?>