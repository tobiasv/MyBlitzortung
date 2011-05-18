<?php

/*
  Config file for MyBlitzortung
*/

//User / Login
define("BO_LOGIN_SHOW", true);
define("BO_LOGIN_ALLOW", 2); //0=nobody, 1=you, 2=all
define("BO_LOGIN_FILE", 'index.php?bo_page=login'); //the page where the function bo_show_login() is included; default: (index.php?bo_page=login)

//Map display
define("BO_RADIUS", 50);
define("BO_DEFAULT_ZOOM", 7);
define("BO_DEFAULT_MAP", 'TERRAIN');
define("BO_MAX_ZOOM_IN", 16);
define("BO_MAX_ZOOM_OUT", 8);

//Update intervals (Minutes!)
define("BO_UP_INTVL_STRIKES", 5);
define("BO_UP_INTVL_STATIONS", 15);
define("BO_UP_INTVL_RAW", 60);

//Experimental Polarity
define("BO_EXPERIMENTAL_POLARITY_CHECK", true);
define("BO_EXPERIMENTAL_POLARITY_ZOOM", 8);

//Show GPS Info
define("BO_SHOW_GPS_INFO", true);

/*** Graphs ***/
define("BO_GRAPH_ANTIALIAS", false); //true needs gd-php from php sources, better look but no transparency effects

// Raw Data Graph
define("BO_GRAPH_RAW_W", 200);
define("BO_GRAPH_RAW_H", 100);
define("BO_GRAPH_RAW_COLOR1", '#f00@0.5');  // first graph
define("BO_GRAPH_RAW_COLOR2", '#0f0@0.5');  // second graph
define("BO_GRAPH_RAW_COLOR3", '#800@0.6');  // 0.45V lines
define("BO_GRAPH_RAW_COLOR_BOX", '#d0d0d0');// box around axis (false disables)
define("BO_GRAPH_RAW_COLOR_BACK", '#fff');  // background color
define("BO_GRAPH_RAW_COLOR_MARGIN",'#fff'); // margin color
define("BO_GRAPH_RAW_COLOR_FRAME", '#fff'); // Outer frame of image (false disables also background!)
define("BO_GRAPH_RAW_COLOR_XGRID", '#eee');
define("BO_GRAPH_RAW_COLOR_YGRID", '#eee');
define("BO_GRAPH_RAW_COLOR_XAXIS", '#666');
define("BO_GRAPH_RAW_COLOR_YAXIS", '#666');

// Statistics Graph
define("BO_GRAPH_STAT_W", 550);
define("BO_GRAPH_STAT_H", 300);
define("BO_GRAPH_STAT_COLOR0", '#f00@0.5');  
define("BO_GRAPH_STAT_COLOR1", '#f00@0.5');  
define("BO_GRAPH_STAT_COLOR2", '#0f0@0.5');  
define("BO_GRAPH_STAT_COLOR3", '#800@0.6');  
define("BO_GRAPH_STAT_COLOR_BOX", '#d0d0d0');// box around axis (false disables)
define("BO_GRAPH_STAT_COLOR_BACK", '#fff');  // background color
define("BO_GRAPH_STAT_COLOR_MARGIN",'#fff'); // margin color
define("BO_GRAPH_STAT_COLOR_FRAME", '#fff'); // Outer frame of image (false disables also background!)
define("BO_GRAPH_STAT_COLOR_XGRID", '#eee');
define("BO_GRAPH_STAT_COLOR_YGRID", '#eee');
define("BO_GRAPH_STAT_COLOR_XAXIS", '#666');
define("BO_GRAPH_STAT_COLOR_YAXIS", '#666');
define("BO_GRAPH_STAT_COLOR_XAXIS_TITLE", '#666');
define("BO_GRAPH_STAT_COLOR_YAXIS_TITLE", '#666');

//Statistics-Graph: Strikes
define("BO_GRAPH_STAT_STR_COLOR_L1", '#99f@1'); 	// color: strikes all (line)
define("BO_GRAPH_STAT_STR_COLOR_F1", '#99f@0.7'); 	// color: strikes all (fill)
define("BO_GRAPH_STAT_STR_WIDTH_1",  1);			// line-width: strikes all 
define("BO_GRAPH_STAT_STR_COLOR_L2", '#00f@0.1'); 	// color: strikes own (line)
define("BO_GRAPH_STAT_STR_COLOR_F2", false); 	// color: strikes own (fill)
define("BO_GRAPH_STAT_STR_WIDTH_2",  2);			// line-width: strikes own
define("BO_GRAPH_STAT_STR_COLOR_L3", '#f00@0.5'); 	// color: strikes avg over stations (line)
define("BO_GRAPH_STAT_STR_COLOR_F3", false); 	// color: strikes avg over stations (fill)
define("BO_GRAPH_STAT_STR_WIDTH_3",  1);			// line-width: strikes avg over stations

//Statistics-Graph: Signals
define("BO_GRAPH_STAT_SIG_COLOR_L1", '#fa0@1'); 	// color: signals avg over stations (line)
define("BO_GRAPH_STAT_SIG_COLOR_F1", '#fa0@0.8'); 	// color: signals avg over stations (fill)
define("BO_GRAPH_STAT_SIG_WIDTH_1",  1);			// line-width: signals avg over stations
define("BO_GRAPH_STAT_SIG_COLOR_L2", '#fc3@0.2'); 	// color: signals own (line)
define("BO_GRAPH_STAT_SIG_COLOR_F2", false); 	    // color: signals own (fill)
define("BO_GRAPH_STAT_SIG_WIDTH_2",  2);			// line-width: signals own 

//Statistics-Graph: Ratio
define("BO_GRAPH_STAT_RAT_COLOR_L1", '#fa0@0.9'); 	// color: signal-ratio avg over stations (line)
define("BO_GRAPH_STAT_RAT_COLOR_F1", '#fa0@0.7'); 	// color: signal-ratio avg over stations (fill)
define("BO_GRAPH_STAT_RAT_WIDTH_1",  1);			// line-width: signal-ratio avg over stations
define("BO_GRAPH_STAT_RAT_COLOR_L2", '#fa0@0.5'); 	// color: signal-ratio own (line)
define("BO_GRAPH_STAT_RAT_COLOR_F2", false); 	    // color: signal-ratio own (fill)
define("BO_GRAPH_STAT_RAT_WIDTH_2",  2);			// line-width: signal-ratio own 
define("BO_GRAPH_STAT_RAT_COLOR_L3", '#88f@0.9'); 	// color: strike-ratio avg over stations (line)
define("BO_GRAPH_STAT_RAT_COLOR_F3", '#88f@0.7'); 	// color: strike-ratio avg over stations (fill)
define("BO_GRAPH_STAT_RAT_WIDTH_3",  1);			// line-width: strike-ratio avg over stations
define("BO_GRAPH_STAT_RAT_COLOR_L4", '#00f@0.5'); 	// color: strike-ratio own (line)
define("BO_GRAPH_STAT_RAT_COLOR_F4", false); 	    // color: strike-ratio own (fill)
define("BO_GRAPH_STAT_RAT_WIDTH_4",  2);			// line-width: strike-ratio own 

//Statistics-Graph: Stations
define("BO_GRAPH_STAT_STA_COLOR_L1", '#c00@0.98'); 	// color: active stations (line)
define("BO_GRAPH_STAT_STA_COLOR_F1", '#c00@0.85'); 	// color: active stations (fill)
define("BO_GRAPH_STAT_STA_WIDTH_1",  1);			// line-width: active stations
define("BO_GRAPH_STAT_STA_COLOR_L2", '#fa0@0.1'); 	// color: max active stations (line)
define("BO_GRAPH_STAT_STA_WIDTH_2",  1);			// line-width: max active stations

//Statistics-Graph: Distance-Ratio
define("BO_GRAPH_STAT_RATIO_DIST_DIV", 20);			 		// distance-interval
define("BO_GRAPH_STAT_RATIO_DIST_LINE", false);			 	// false: barplot true: lineplot
define("BO_GRAPH_STAT_RATIO_DIST_COLOR_L1", '#00f@1'); 	// color: 
define("BO_GRAPH_STAT_RATIO_DIST_COLOR_F1", '#ada@0'); 	// color: 
define("BO_GRAPH_STAT_RATIO_DIST_WIDTH1",  2);				// line-width: 
define("BO_GRAPH_STAT_RATIO_DIST_COLOR_L2", '#22f@0.8'); 	// color: 
define("BO_GRAPH_STAT_RATIO_DIST_COLOR_F2", '#22f@0.95'); 	// color: 
define("BO_GRAPH_STAT_RATIO_DIST_WIDTH2",  1);				// line-width: 

//Statistics-Graph: Bearing-Ratio
define("BO_GRAPH_STAT_RATIO_BEAR_DIV", 10);			 		// bearing-interval
define("BO_GRAPH_STAT_RATIO_BEAR_LINE", false);			 	// false: barplot true: lineplot
define("BO_GRAPH_STAT_RATIO_BEAR_COLOR_L1", '#00f@1'); 	// color: 
define("BO_GRAPH_STAT_RATIO_BEAR_COLOR_F1", '#ada@0'); 	// color: 
define("BO_GRAPH_STAT_RATIO_BEAR_WIDTH1",  14);				// line-width: 
define("BO_GRAPH_STAT_RATIO_BEAR_COLOR_L2", '#22f@0.8'); 	// color: 
define("BO_GRAPH_STAT_RATIO_BEAR_COLOR_F2", '#22f@0.95'); 	// color: 
define("BO_GRAPH_STAT_RATIO_BEAR_WIDTH2",  1);				// line-width: 


/*** Automatic Data-Purging ***/
// min-age in hours, 0 disables
define("BO_PURGE_SIG_NS", 300);            //purge: raw-signals, where no strike assigned
define("BO_PURGE_SIG_ALL", 0);             //purge: all raw-signals
define("BO_PURGE_STR_NP", 0);              //purge: strikes not-participated
define("BO_PURGE_STR_ALL", 0);             //purge: strikes all
define("BO_PURGE_STR_DIST", 0);            //purge: strikes greater than BO_PURGE_STR_DIST_KM kilometers
define("BO_PURGE_STRSTA_ALL", 100);        //purge: strike <-> station table 
define("BO_PURGE_STA_OTHER",  300);        //purge: station statistics (not yours)
define("BO_PURGE_STA_ALL", 0);             //purge: all station statistics
define("BO_PURGE_STR_DIST_KM", 2000);      //purge: strikes kilometers (for BO_PURGE_STR_DIST)

//global purge settings
define("BO_PURGE_ENABLE", true);   //enable/disable purging
define("BO_PURGE_MAIN_INTVL", 6);   //purge: main interval in hours, following times should be equal or greater


/*** Google Map ***/
define('BO_MAP_DISABLE', false); //Disables google maps for non logged in users
define('BO_MAP_CIRCLE_COLOR_LINE', '#FF0000');
define('BO_MAP_CIRCLE_OPAC_LINE', '0.8');
define('BO_MAP_CIRCLE_COLOR_FILL', '#FF0000');
define('BO_MAP_CIRCLE_OPAC_FILL', '0.05');
define('BO_MAP_STRIKE_SHOW_CIRCLE_ZOOM', 9);
define('BO_MAP_STRIKE_SHOW_DEVIATION_ZOOM', 12);

$_BO['mapcfg'][0]['tstart'] = 15;
$_BO['mapcfg'][0]['trange'] = 15;
$_BO['mapcfg'][0]['upd_intv'] = 5;
$_BO['mapcfg'][0]['col'][] = array(255, 255, 0);
$_BO['mapcfg'][0]['col'][] = array(255, 240, 0);
$_BO['mapcfg'][0]['col'][] = array(255, 225, 0);
$_BO['mapcfg'][0]['default_show'] = true;
$_BO['mapcfg'][0]['sel_name'] = '0-15 min';

$_BO['mapcfg'][1]['tstart'] = 120;
$_BO['mapcfg'][1]['trange'] = 105;
$_BO['mapcfg'][1]['upd_intv'] = 15;
$_BO['mapcfg'][1]['col'][] = array(250, 190, 0);
$_BO['mapcfg'][1]['col'][] = array(245, 170, 10);
$_BO['mapcfg'][1]['col'][] = array(240, 150, 10);
$_BO['mapcfg'][1]['col'][] = array(235, 130, 10);
$_BO['mapcfg'][1]['col'][] = array(230, 110, 10);
$_BO['mapcfg'][1]['col'][] = array(225,  90, 10);
$_BO['mapcfg'][1]['col'][] = array(220,  70, 10);
$_BO['mapcfg'][1]['default_show'] = true;
$_BO['mapcfg'][1]['sel_name'] = '15-120 min';

$_BO['mapcfg'][2]['tstart'] = 60 * 24;
$_BO['mapcfg'][2]['trange'] = 60 * 22;
$_BO['mapcfg'][2]['upd_intv'] = 30;
for ($i=0;$i<20;$i++)
	$_BO['mapcfg'][2]['col'][] = array(200-150*$i/20, 10+40*$i/20, 50+200*$i/20);
$_BO['mapcfg'][2]['default_show'] = false;
$_BO['mapcfg'][2]['sel_name'] = '2-24 h';

/*
// Example for 1 to 10 days. Be carful: can cause high database load!
$_BO['mapcfg'][3]['tstart'] = 60 * 24 * 10;
$_BO['mapcfg'][3]['trange'] = 60 * 24 * 9;
$_BO['mapcfg'][3]['upd_intv'] = 1;
for ($i=0;$i<20;$i++)
	$_BO['mapcfg'][3]['col'][] = array(80 + 2*$i/20, 50   +200*$i/20, 230   -200*$i/20);
$_BO['mapcfg'][3]['default_show'] = false;
$_BO['mapcfg'][3]['sel_name'] = '1-10 days';
$_BO['mapcfg'][3]['only_loggedin'] = true;
*/

/*** Image Map ***/

//Europe
$_BO['mapimg'][0]['name'] = 'Europa';
$_BO['mapimg'][0]['menu'] = true;
$_BO['mapimg'][0]['file'] = 'map_europe.png';
$_BO['mapimg'][0]['coord'] = array(68, 50, 23, -20); //North, East, South, West (Degrees)
$_BO['mapimg'][0]['trange'] = 2; //hours!
$_BO['mapimg'][0]['upd_intv'] = 15; //minutes
$_BO['mapimg'][0]['textcolor'] = array(255,255,255);
$_BO['mapimg'][0]['textsize'] = 5;
$_BO['mapimg'][0]['point_type'] = 2;
$_BO['mapimg'][0]['point_size'] = 2;
$_BO['mapimg'][0]['legend'] = array(5, 100, 80, 4, 4, 1);
$_BO['mapimg'][0]['col'][] = array(255, 255, 0);
$_BO['mapimg'][0]['col'][] = array(255, 200, 0);
$_BO['mapimg'][0]['col'][] = array(255, 150, 0);
$_BO['mapimg'][0]['col'][] = array(255, 100, 0);
$_BO['mapimg'][0]['col'][] = array(255,   0, 0);
$_BO['mapimg'][0]['col'][] = array(225,   0, 0);

//Germany (mini)
$_BO['mapimg'][1]['name'] = 'Deutschland (mini)';
$_BO['mapimg'][1]['menu'] = false;
$_BO['mapimg'][1]['file'] = 'map_germany.png';
//$_BO['mapimg'][1]['dim'] = array(160, 200); // increase/decrease canvas size
$_BO['mapimg'][1]['coord'] = array(56, 18.3, 46.3, 1.8); //North, East, South, West (Degrees)
$_BO['mapimg'][1]['trange'] = 2; //hours!
$_BO['mapimg'][1]['upd_intv'] = 5; //minutes
$_BO['mapimg'][1]['textcolor'] = array(255,255,255);
$_BO['mapimg'][1]['textsize'] = 1;
$_BO['mapimg'][1]['point_type'] = 2;
$_BO['mapimg'][1]['point_size'] = 1;
$_BO['mapimg'][1]['legend'] = array(0, 54, 26, 0, 0, 0);
$_BO['mapimg'][1]['col'][] = array(255, 255, 0);
$_BO['mapimg'][1]['col'][] = array(255, 200, 0);
$_BO['mapimg'][1]['col'][] = array(255, 150, 0);
$_BO['mapimg'][1]['col'][] = array(255, 100, 0);
$_BO['mapimg'][1]['col'][] = array(255,   0, 0);
$_BO['mapimg'][1]['col'][] = array(225,   0, 0);


//Settings for Developers
define("BO_DEBUG", false);
define("BO_LANG_AUTO_ADD", true);


//Enable/disable alarms
define("BO_ALERTS", true);

// SMS-Gateway URL
// Leave it blank, if you don't want to use that feature.
// {text} will be replaced by the message text
// {tel}  will be replaced by the telephone number
/* Some examples. Of course you have to register yourself at the gateway provider and change USER/PASS to your values!         */
/*  http://gateway.smstrade.de/?key=PASS&to={tel}&message={text}&from=MyBO&route=gold                         */
/*  http://gateway.sms77.de/?u=USER&p=PASS&to={tel}&text={text}&type=quality&from=MyBO                        */
/*  http://www.innosend.de/gateway/sms.php?id=USER&pw=PASS&absender=MyBO&empfaenger={tel}&text={text}&type=4  */
 
define("BO_SMS_GATEWAY_URL", '');

?>