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
$_BO['tpl_gmap']['1-10d']['upd_intv'] = 1;
for ($i=0;$i<20;$i++)
	$_BO['tpl_gmap']['1-10d']['col'][] = array(80 + 2*$i/20, 50   +200*$i/20, 230   -200*$i/20);
$_BO['tpl_gmap']['1-10d']['default_show'] = false;
$_BO['tpl_gmap']['1-10d']['sel_name'] = '1-10 days';
$_BO['tpl_gmap']['1-10d']['only_loggedin'] = true;


/**************************************/
/* Image Maps (PNG)                   */
/**************************************/

//Europe
$_BO['tpl_imgmap']['europe']['name'] = 'Europa';
$_BO['tpl_imgmap']['europe']['menu'] = true;
$_BO['tpl_imgmap']['europe']['archive'] = true;
$_BO['tpl_imgmap']['europe']['file'] = 'map_europe.png';
$_BO['tpl_imgmap']['europe']['coord'] = array(68, 50, 23, -20); //North, East, South, West (Degrees)
$_BO['tpl_imgmap']['europe']['trange'] = 2; //hours!
$_BO['tpl_imgmap']['europe']['upd_intv'] = 15; //minutes
$_BO['tpl_imgmap']['europe']['textcolor'] = array(255,255,255);
$_BO['tpl_imgmap']['europe']['textsize'] = 5;
$_BO['tpl_imgmap']['europe']['point_type'] = 2;
$_BO['tpl_imgmap']['europe']['point_size'] = 2;
$_BO['tpl_imgmap']['europe']['legend'] = array(5, 100, 80, 4, 4, 1);
$_BO['tpl_imgmap']['europe']['col'][] = array(255, 255, 0);
$_BO['tpl_imgmap']['europe']['col'][] = array(255, 200, 0);
$_BO['tpl_imgmap']['europe']['col'][] = array(255, 150, 0);
$_BO['tpl_imgmap']['europe']['col'][] = array(255, 100, 0);
$_BO['tpl_imgmap']['europe']['col'][] = array(255,   0, 0);
$_BO['tpl_imgmap']['europe']['col'][] = array(225,   0, 0);


//Germany (mini)
$_BO['tpl_imgmap']['germany_mini']['name'] = 'Deutschland (mini)';
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
$_BO['tpl_imgmap']['germany_mini']['col'][] = array(255, 255, 0);
$_BO['tpl_imgmap']['germany_mini']['col'][] = array(255, 200, 0);
$_BO['tpl_imgmap']['germany_mini']['col'][] = array(255, 150, 0);
$_BO['tpl_imgmap']['germany_mini']['col'][] = array(255, 100, 0);
$_BO['tpl_imgmap']['germany_mini']['col'][] = array(255,   0, 0);
$_BO['tpl_imgmap']['germany_mini']['col'][] = array(225,   0, 0);


//Germany (Landkreise)
$_BO['tpl_imgmap']['germany_lkr']['name'] = 'Deutschland';
$_BO['tpl_imgmap']['germany_lkr']['footer'] = '';
$_BO['tpl_imgmap']['germany_lkr']['menu'] = true;
$_BO['tpl_imgmap']['germany_lkr']['archive'] = true;
$_BO['tpl_imgmap']['germany_lkr']['file'] = 'map_deutschland_landkreise_grau.png';
$_BO['tpl_imgmap']['germany_lkr']['coord'] = array(55.044, 15.155, 47.249, 5.833); //North, East, South, West (Degrees)
$_BO['tpl_imgmap']['germany_lkr']['trange'] = 2; //hours!
$_BO['tpl_imgmap']['germany_lkr']['upd_intv'] = 5; //minutes
$_BO['tpl_imgmap']['germany_lkr']['textcolor'] = array(255,255,255);
$_BO['tpl_imgmap']['germany_lkr']['textsize'] = 5;
$_BO['tpl_imgmap']['germany_lkr']['point_type'] = 2;
$_BO['tpl_imgmap']['germany_lkr']['point_size'] = 2;
$_BO['tpl_imgmap']['germany_lkr']['legend'] = array(5, 100, 80, 4, 4, 1);
$_BO['tpl_imgmap']['germany_lkr']['col'][] = array(255, 255, 0);
$_BO['tpl_imgmap']['germany_lkr']['col'][] = array(255, 200, 0);
$_BO['tpl_imgmap']['germany_lkr']['col'][] = array(255, 150, 0);
$_BO['tpl_imgmap']['germany_lkr']['col'][] = array(255, 100, 0);
$_BO['tpl_imgmap']['germany_lkr']['col'][] = array(255,   0, 0);
$_BO['tpl_imgmap']['germany_lkr']['col'][] = array(225,   0, 0);


?>