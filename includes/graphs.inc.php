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


// Graph from raw dataset
function bo_graph_raw($id)
{
	if (!file_exists(BO_DIR.'includes/jpgraph/jpgraph.php'))
		bo_graph_error(BO_GRAPH_RAW_W, BO_GRAPH_RAW_H);

	$id = intval($id);

	$sql = "SELECT id, time, time_ns, lat, lon, height, data
			FROM ".BO_DB_PREF."raw
			WHERE id='$id'";
	$erg = bo_db($sql);
	$row = $erg->fetch_assoc();

	$data[0] = array();
	$data[1] = array();
	$tickLabels = array();
	$tickMajPositions = array();
	$tickPositions = array();

	for ($i=0;$i<strlen($row['data']);$i++)
	{
		if (!($i%2))
		{
			$x = floor($i / 0.357000 / 2 / 50) * 50;

			if (!($i%4))
			{
				if (!($i%36))
				{
					$tickMajPositions[] = $i/2;
					$tickLabels[] = $x.'µs';
				}
				elseif (!($i%6))
				{
					$tickPositions[] = $i/2;
				}
			}

			$datax[] = $i/2;
		}

		$datay[$i%2][] = (ord(substr($row['data'],$i,1)) - 128) / 128 * 2.5;
	}

	$n = count($datax);
	$xmin = $datax[0];
	$xmax = $datax[$n-1];

	require_once 'jpgraph/jpgraph.php';
	require_once 'jpgraph/jpgraph_line.php';
	require_once 'jpgraph/jpgraph_plotline.php';

	$graph = new Graph(BO_GRAPH_RAW_W,BO_GRAPH_RAW_H,"auto");
	$graph->ClearTheme();

	if (defined("BO_GRAPH_ANTIALIAS") && BO_GRAPH_ANTIALIAS)
		$graph->img->SetAntiAliasing();

	$graph->SetScale("linlin",-2.5,2.5,$xmin,$xmax);

	if (BO_GRAPH_RAW_COLOR_BACK)
		$graph->SetColor(BO_GRAPH_RAW_COLOR_BACK);

	if (BO_GRAPH_RAW_COLOR_BACK)
		$graph->SetMarginColor(BO_GRAPH_RAW_COLOR_MARGIN);

	if (BO_GRAPH_RAW_COLOR_FRAME)
		$graph->SetFrame(true, BO_GRAPH_RAW_COLOR_FRAME);
	else
		$graph->SetFrame(false);

	if (BO_GRAPH_RAW_COLOR_BOX)
		$graph->SetBox(true, BO_GRAPH_RAW_COLOR_BOX);
	else
		$graph->SetBox(false);

	$plot=new LinePlot($datay[0], $datax);
	$plot->SetColor(BO_GRAPH_RAW_COLOR1);
	$graph->Add($plot);

	$plot=new LinePlot($datay[1], $datax);
	$plot->SetColor(BO_GRAPH_RAW_COLOR2);
	$graph->Add($plot);

	$graph->xaxis->SetPos('min');
	$graph->xaxis->SetTickPositions($tickMajPositions,$tickPositions,$tickLabels);

	$graph->xaxis->SetColor(BO_GRAPH_RAW_COLOR_XAXIS);
	$graph->yaxis->SetColor(BO_GRAPH_RAW_COLOR_YAXIS);

	if (BO_GRAPH_RAW_COLOR_XGRID)
	{
		$graph->xgrid->SetColor(BO_GRAPH_RAW_COLOR_XGRID);
		$graph->xgrid->Show(true,true);
	}
	else
		$graph->xgrid->Show(false);

	if (BO_GRAPH_RAW_COLOR_YGRID)
	{
		$graph->ygrid->SetColor(BO_GRAPH_RAW_COLOR_YGRID);
		$graph->ygrid->Show(true,true);
	}
	else
		$graph->ygrid->Show(false,false);


	$graph->yaxis->SetTextTickInterval(0.5);

	for($i=-2.5;$i<=2.5;$i+=0.5)
	{
		if (abs($i) != 0.5)
			$yt[] = $i;
	}

	$graph->yaxis->SetTickPositions(array(-2,-1,0,1,2),$yt,array('-2V','-1V','0V','1V','2V'));

	$sline  = new PlotLine(HORIZONTAL,  0.45, BO_GRAPH_RAW_COLOR3, 1);
	$graph->AddLine($sline);

	$sline  = new PlotLine(HORIZONTAL, -0.45, BO_GRAPH_RAW_COLOR3, 1);
	$graph->AddLine($sline);

	$graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
	$graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);

	$graph->SetMargin(24,1,1,1);

	$graph->Stroke();
	exit;
}

function bo_graph_statistics($type = 'strikes', $station_id = 0, $hours_back = 24)
{

	if (!file_exists(BO_DIR.'includes/jpgraph/jpgraph.php'))
		bo_graph_error(BO_GRAPH_STAT_W, BO_GRAPH_STAT_H);

	session_write_close();

	$hours_back = intval($hours_back) ? intval($hours_back) : 24;
	$hours_back = !bo_user_get_level() && $hours_back > 96 ? 96 : $hours_back;

	$interval = BO_UP_INTVL_STATIONS;
	$stId = bo_station_id();

	$date_end = gmdate('Y-m-d H:i:s', bo_get_conf('uptime_stations'));
	$time_end = strtotime($date_end." UTC");
	$date_start = gmdate('Y-m-d H:i:s', time() - 3600 * $hours_back);
	$time_start = strtotime($date_start." UTC");

	$X = $Y = array(); //data array
	$tickLabels = array();
	$tickMajPositions = array();
	$tickPositions = array();
	$xmin = null;
	$xmax = null;
	
	if ($type == 'strikes_time')
	{
		$station_id = 0;
		
		$year = intval($_GET['year']);
		$month = intval($_GET['month']);
		$radius = intval($_GET['radius']);
		
		$add_title .= '';
		
		if ($radius)
		{
			$rad = 2;
			$add_title .= ' '.strtr(_BL('_in_radius'), array('{RADIUS}' => BO_RADIUS));
		}

		if ($month == -1)
		{
			$like = 'strikes_'.$year.'%';
			$time_begin = strtotime("$year-01-01");
			$days = date('L', $time_begin) ? 366 : 365;

			$xtitle = 'Month';
			$add_title .= ' '.$year;
		}
		else
		{
			$like = 'strikes_'.$year.sprintf('%02d', $month).'%';
			$time_begin = strtotime("$year-$month-01");
			$days = date('t', $time_begin);

			$xtitle = 'Day';
			$add_title .= ' '._BL(date('F', $time_begin)).' '.$year;
		}

		$day_offset = date('z', $time_begin);

		for ($i=0;$i<$days;$i++)
		{
			$time = mktime(0,0,0,$month == -1 ? 1 : $month, $i+1, $year);

			if ($month == -1 && date('d', $time) == 1)
			{
				$tickLabels[] = _BL(date('M', $time));
				$tickMajPositions[] = $i;
			}
			else if ($month != -1 && !($i%5))
			{
				$tickLabels[] = date('d.m', $time);
				$tickMajPositions[] = $i;
			}
			
			$Y1[$i] = 0;
			$Y2[$i] = 0;
		}

		$sql = "SELECT DISTINCT SUBSTRING(name, 9) time, data, changed
				FROM ".BO_DB_PREF."conf
				WHERE name LIKE '$like'
				ORDER BY time";
		$res = bo_db($sql);
		while($row = $res->fetch_assoc())
		{

			$y = substr($row['time'], 0, 4);
			$m = substr($row['time'], 4, 2);
			$d = substr($row['time'], 6, 2);
			$time = strtotime("$y-$m-$d");

			$i = date('z', $time) - $day_offset;

			$d = unserialize($row['data']);

			$Y2[$i] = $d[0 + $rad] - $d[1 + $rad];
			$Y1[$i] = $d[1 + $rad];
		}

		$caption  = (array_sum($Y1) + array_sum($Y2)).' '._BL('total strikes');
		$caption .= "\n";
		$caption .= array_sum($Y1).' '._BL('total strikes station');
		
		$graph_type = 'textlin';
	}
	else if ($type == 'ratio_distance_longtime')
	{
		$station_id = 0;
		
		$own = unserialize(bo_get_conf('longtime_dist_own'));
		$all = unserialize(bo_get_conf('longtime_dist'));

		if (is_array($own) && is_array($all))
		{
			foreach($own as $dist => $cnt)
			{
				$X[$dist] = $dist * 10;

				if ($all[$dist])
					$Y[$dist] = $cnt / $all[$dist] * 100;
				else
					$Y[$dist] = null;

				$max_dist = max($max_dist, $dist);
			}

			foreach($all as $dist => $cnt)
			{
				$Y2[$dist] = $cnt;
				$max_dist = max($max_dist, $dist);
			}

			for ($i=0;$i<=$max_dist;$i++)
			{
				$Y[$i] = isset($Y[$i]) ? $Y[$i] : null;
				$Y2[$i] = isset($Y2[$i]) ? $Y2[$i] : null;
				
				if ( !($i%5))
					$tickPositions[] = $i;

				if ( !($i%50))
				{
					$tickLabels[] = $i * 10;
					$tickMajPositions[] = $i;
				}
				
				
			}
			
		}

		$graph_type = 'textlin';
		$add_title = ' '._BL('since begin of data logging');
	}
	else if($type == 'ratio_bearing_longtime')
	{
		$station_id = 0;
		
		$own = unserialize(bo_get_conf('longtime_bear_own'));
		$all = unserialize(bo_get_conf('longtime_bear'));
		
		if (is_array($own) && is_array($all))
		{
			foreach($own as $bear => $cnt)
			{
				$X[$bear] = $bear * 10;

				if ($all[$bear])
					$Y[$bear] = $cnt / $all[$bear] * 100;
				else
					$Y[$bear] = null;
			}

			foreach($all as $bear => $cnt)
			{
				$Y2[$bear] = $cnt;
			}

			for ($i=0;$i<360;$i++)
			{
				$Y[$i] = isset($Y[$i]) ? $Y[$i] : null;
				$Y2[$i] = isset($Y2[$i]) ? $Y2[$i] : null;
				
				if ( !($i%5))
					$tickPositions[] = $i;

				if ( !($i%45))
				{
					$tickLabels[] = $i;
					$tickMajPositions[] = $i;
				}
				
			}

		}

		$graph_type = 'linlin';
		$add_title = ' '._BL('since begin of data logging');
		$xmin = 0;
		$xmax = 360;
	}
	else if ($type == 'ratio_distance' || $type == 'ratio_bearing')
	{
		$dist_div = BO_GRAPH_STAT_RATIO_DIST_DIV; //interval in km
		$bear_div = BO_GRAPH_STAT_RATIO_BEAR_DIV;

		$xmin = 0;
		if ($type == 'ratio_bearing')
			$xmax = 360 / $bear_div;
		
		$tmp = array();
		$ticks = 0;
		if ($station_id) //Special Query for own "ratio strikes by distance" - may be slow!
		{
			$station_info = bo_station_info($station_id);
			$stLat = $station_info['lat'];
			$stLon = $station_info['lon'];

			$sql = "SELECT s.lat lat, s.lon lon, ss.station_id stid
					FROM ".BO_DB_PREF."strikes s
					LEFT JOIN ".BO_DB_PREF."stations_strikes ss
						ON s.id=ss.strike_id AND ss.station_id='$station_id'
					WHERE s.time BETWEEN '$date_start' AND '$date_end'";
			$res = bo_db($sql);
			while($row = $res->fetch_assoc())
			{
				if ($type == 'ratio_bearing')
					$val = ceil(bo_latlon2bearing($row['lat'], $row['lon'], $stLat, $stLon) / $bear_div);
				else
					$val = ceil(bo_latlon2dist($row['lat'], $row['lon'], $stLat, $stLon) / $dist_div / 1000);

				$part = $row['stid'] ? 1 : 0;
				$tmp[$part][$val] += 1;
				$ticks = max($ticks, $val);
				$x++;
			}
		}
		else
		{
			if ($type == 'ratio_bearing')
				$sql = " CEIL(bearing/$bear_div) val ";
			else
				$sql = " CEIL(distance/$dist_div/1000) val ";

			//strike ratio for own station
			$sql = "SELECT COUNT(id) cnt, part, $sql
					FROM ".BO_DB_PREF."strikes
					WHERE time BETWEEN '$date_start' AND '$date_end'
					GROUP BY part, val";
			$res = bo_db($sql);
			while($row = $res->fetch_assoc())
			{
				$tmp[$row['part']][$row['val']] = $row['cnt'];
				$x += $row['cnt'];
				$ticks = max($ticks, $row['val']);
			}
		}

		for($i=0;$i<$ticks;$i++)
		{
			if ($type == 'ratio_bearing')
			{
				if ( !(($i*$bear_div)%45))
				{
					$tickLabels[] = $i*$bear_div;
					$tickMajPositions[] = $i;
				}
			}
			else
			{
				if ( !(($i*$dist_div)%500))
				{
					$tickLabels[] = $i*$dist_div;
					$tickMajPositions[] = $i;
				}
			}

			if ($tmp[0][$i])
				$Y[$i] = $tmp[1][$i] / ($tmp[0][$i]+$tmp[1][$i]) * 100;
			else
				$Y[$i] = 0;

			$Y2[$i] = intval($tmp[0][$i]+$tmp[1][$i]);
		}

		$graph_type = 'textlin';

	}
	else
	{
		$ticks = ($time_end - $time_start) / 60 / $interval;

		$stId = $station_id ? $station_id : $stId;

		$sql_where[0] = " station_id  = 0 "; // first!
		$sql_where[1] = " station_id  = '$stId' ";
		$sql_where[2] = " station_id != 0 ";


		foreach($sql_where as $data_id => $sqlw)
		{

			//one SQL-Query for all graphs -> Query Cache should improve performance (if enabled)
			$sql = "SELECT time, AVG(signalsh) sig, AVG(strikesh) astr, MAX(strikesh) mstr, COUNT(time) / COUNT(DISTINCT time) cnt
					FROM ".BO_DB_PREF."stations_stat
					WHERE time BETWEEN '$date_start' AND '$date_end' AND $sqlw
							-- AND (signalsh > 0 OR strikesh > 0)
					GROUP BY DAYOFMONTH(time), HOUR(time), FLOOR(MINUTE(time) / ".$interval.")";
			$res = bo_db($sql);
			while($row = $res->fetch_assoc())
			{
				$time = strtotime($row['time'].' UTC');

				$index = floor( ($time - time() + 3600 * $hours_back) / 60 / $interval);

				if ($index < 0)
					continue;

				$Y[$data_id]['sig'][$index]  = $row['sig'];  //average signals
				$Y[$data_id]['astr'][$index] = $row['astr']; //average strikes
				$Y[$data_id]['mstr'][$index] = $row['mstr']; //maximum strikes
				$Y[$data_id]['cnt'][$index] = $row['cnt']; //count

				if ($data_id > 0)
				{
					//Strike Ratio
					if (intval($Y[0]['astr'][$index]))
						$Y[$data_id]['str_ratio'][$index] = $row['astr'] / intval($Y[0]['astr'][$index]) * 100;

					//Signal Ratio
					if (intval($row['sig']))
						$Y[$data_id]['sig_ratio'][$index] = $row['astr'] / $row['sig'] * 100;
				}

				//Active stations
				$Y[$data_id]['ratio'][$index] = $row['mstr'];
			}

			for($i=0;$i<$ticks;$i++)
			{
				$X[$i] = $time_start + $i * $interval * 60;

				//JpGraph wants equal number of x and y data points
				if (!isset($Y[$data_id]['sig'][$i])) $Y[$data_id]['sig'][$i] = $Y[$data_id]['sig'][$i-1];
				if (!isset($Y[$data_id]['astr'][$i])) $Y[$data_id]['astr'][$i] = $Y[$data_id]['astr'][$i-1];
				if (!isset($Y[$data_id]['mstr'][$i])) $Y[$data_id]['mstr'][$i] = $Y[$data_id]['mstr'][$i-1];
				if (!isset($Y[$data_id]['cnt'][$i])) $Y[$data_id]['cnt'][$i] = $Y[$data_id]['cnt'][$i-1];
				if (!isset($Y[$data_id]['sig_ratio'][$i])) $Y[$data_id]['sig_ratio'][$i] = $Y[$data_id]['sig_ratio'][$i-1];
				if (!isset($Y[$data_id]['str_ratio'][$i])) $Y[$data_id]['str_ratio'][$i] = $Y[$data_id]['str_ratio'][$i-1];
			}
		}

		$graph_type = 'datlin';
	}

	$info_station_id = $station_id ? $station_id : $stId;

	if (!$add_title)
		$add_title = ' '._BL('of the last').' '.$hours_back.'h';

	if ($station_id)
	{
		$stInfo = bo_station_info($station_id);
		$add_title .= ' '._BL('for_station').': '.$stInfo['city'];
		bo_station_city($stInfo['city'], false);
	}

	require_once 'jpgraph/jpgraph.php';
	require_once 'jpgraph/jpgraph_line.php';
	require_once 'jpgraph/jpgraph_bar.php';
	require_once 'jpgraph/jpgraph_plotline.php';
	require_once 'jpgraph/jpgraph_date.php';

	$graph = new Graph(BO_GRAPH_STAT_W,BO_GRAPH_STAT_H,"auto");
	$graph->ClearTheme();
	$graph->SetScale($graph_type, null, null, $xmin, $xmax);

	if (defined("BO_GRAPH_ANTIALIAS") && BO_GRAPH_ANTIALIAS)
		$graph->img->SetAntiAliasing();

	if (BO_GRAPH_STAT_COLOR_BACK)
		$graph->SetColor(BO_GRAPH_STAT_COLOR_BACK);

	if (BO_GRAPH_STAT_COLOR_BACK)
		$graph->SetMarginColor(BO_GRAPH_STAT_COLOR_MARGIN);

	if (BO_GRAPH_STAT_COLOR_FRAME)
		$graph->SetFrame(true, BO_GRAPH_STAT_COLOR_FRAME);
	else
		$graph->SetFrame(false);

	if (BO_GRAPH_STAT_COLOR_BOX)
		$graph->SetBox(true, BO_GRAPH_STAT_COLOR_BOX);
	else
		$graph->SetBox(false);

	$graph->SetMargin(50,50,20,70);
	
	$graph->legend->SetPos(0.5,0.99,"center","bottom");
	$graph->legend->SetColumns(2);
	$graph->legend->SetFillColor(BO_GRAPH_STAT_COLOR_LEGEND_FILL);
	$graph->legend->SetColor(BO_GRAPH_STAT_COLOR_LEGEND_TEXT, BO_GRAPH_STAT_COLOR_LEGEND_FRAME);
	$graph->legend->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
	
	if ($caption)
	{
		$caption=new Text($caption,60,30); 
		$caption->SetFont(FF_DV_SANSSERIF,FS_NORMAL, 7);
		$caption->SetColor(BO_GRAPH_STAT_COLOR_CAPTION);
		$graph->AddText($caption);

	}
	
	switch($type)
	{


		case 'strikes_time':

			$plot1=new BarPlot($Y1);
			$plot1->SetColor(BO_GRAPH_STAT_STRIKES_TIME_COLOR_L1);
			if (BO_GRAPH_STAT_STRIKES_TIME_COLOR_F1)
				$plot1->SetFillColor(BO_GRAPH_STAT_STRIKES_TIME_COLOR_F1);
			$plot1->SetLegend(_BL('graph_legend_strikes_time_own'));

			$plot2=new BarPlot($Y2);
			$plot2->SetColor(BO_GRAPH_STAT_STRIKES_TIME_COLOR_L2);
			if (BO_GRAPH_STAT_STRIKES_TIME_COLOR_F2)
				$plot2->SetFillColor(BO_GRAPH_STAT_STRIKES_TIME_COLOR_F2);
			$plot2->SetLegend(_BL('graph_legend_strikes_time_all'));

			$plot = new AccBarPlot(array($plot1,$plot2), $X);
			if (BO_GRAPH_STAT_STRIKES_TIME_WIDTH)
				$plot->SetWidth(BO_GRAPH_STAT_STRIKES_TIME_WIDTH);

			$graph->Add($plot);
			$graph->xaxis->SetTickPositions($tickMajPositions,$tickPositions,$tickLabels);
			$graph->yaxis->title->Set(_BL('Count'));
			$graph->xaxis->title->Set(_BL($xtitle));
			$graph->title->Set(_BL('graph_stat_title_strikes_time').$add_title);
			
			break;

		case 'strikes':

			$graph->title->Set(_BL('graph_stat_title_strikes').$add_title);

			$plot=new LinePlot($Y[0]['astr'], $X);
			$plot->SetColor(BO_GRAPH_STAT_STR_COLOR_L1);
			if (BO_GRAPH_STAT_STR_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_STR_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_STR_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_strikes_sum'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[2]['astr'], $X);
			$plot->SetColor(BO_GRAPH_STAT_STR_COLOR_L3);
			if (BO_GRAPH_STAT_STR_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_STR_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_STR_WIDTH_3);
			$plot->SetLegend(_BL('graph_legend_strikes_avg_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[1]['astr'], $X);
			$plot->SetColor(BO_GRAPH_STAT_STR_COLOR_L2);
			if (BO_GRAPH_STAT_STR_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_STR_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_STR_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_strikes_own'));
			$graph->Add($plot);

			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Count per hour'));

			break;

		case 'signals':

			$graph->title->Set(_BL('graph_stat_title_signals').$add_title);

			$plot=new LinePlot($Y[2]['sig'], $X);
			$plot->SetColor(BO_GRAPH_STAT_SIG_COLOR_L1);
			if (BO_GRAPH_STAT_SIG_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_SIG_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_SIG_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_signals_avg_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[1]['sig'], $X);
			$plot->SetColor(BO_GRAPH_STAT_SIG_COLOR_L2);
			if (BO_GRAPH_STAT_SIG_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_SIG_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_SIG_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_signals_own'));
			$graph->Add($plot);

			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Count per hour'));

			break;

		case 'ratio':
			$graph->title->Set(_BL('graph_stat_title_ratio').$add_title);

			$plot=new LinePlot($Y[2]['sig_ratio'], $X);
			$plot->SetColor(BO_GRAPH_STAT_RAT_COLOR_L1);
			if (BO_GRAPH_STAT_RAT_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_RAT_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_RAT_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_ratio_sig_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[1]['sig_ratio'], $X);
			$plot->SetColor(BO_GRAPH_STAT_RAT_COLOR_L2);
			if (BO_GRAPH_STAT_RAT_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_RAT_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_RAT_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_ratio_sig_own'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[2]['str_ratio'], $X);
			$plot->SetColor(BO_GRAPH_STAT_RAT_COLOR_L3);
			if (BO_GRAPH_STAT_RAT_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_RAT_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_RAT_WIDTH_3);
			$plot->SetLegend(_BL('graph_legend_ratio_str_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[1]['str_ratio'], $X);
			$plot->SetColor(BO_GRAPH_STAT_RAT_COLOR_L4);
			if (BO_GRAPH_STAT_RAT_COLOR_F4)
				$plot->SetFillColor(BO_GRAPH_STAT_RAT_COLOR_F4);
			$plot->SetWeight(BO_GRAPH_STAT_RAT_WIDTH_4);
			$plot->SetLegend(_BL('graph_legend_ratio_str_own'));
			$graph->Add($plot);

			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');


			break;

		case 'stations':

			$graph->title->Set(_BL('graph_stat_title_stations').$add_title);

			$plot=new LinePlot($Y[2]['cnt'], $X);
			$plot->SetColor(BO_GRAPH_STAT_STA_COLOR_L1);
			if (BO_GRAPH_STAT_STA_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_STA_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_STA_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_stations_active'));
			$graph->Add($plot);


			$max_stations = bo_get_conf('longtime_count_max_active_stations');
			if ($max_stations)
			{
				$sline  = new PlotLine(HORIZONTAL, $max_stations, BO_GRAPH_STAT_STA_COLOR_L2, 1);
				$sline->SetWeight(BO_GRAPH_STAT_STA_WIDTH_2);
				$sline->SetLegend(_BL('graph_legend_stations_max_active'));
				$graph->AddLine($sline);

				$graph->yscale->SetAutoMax($max_stations + 1);
			}

			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Count'));

			break;

		case 'ratio_distance':
		case 'ratio_distance_longtime':

			$graph->title->Set(_BL('graph_stat_title_ratio_distance').$add_title);

			if (BO_GRAPH_STAT_RATIO_DIST_LINE)
			{
				$plot=new LinePlot($Y);
				$plot->SetWeight(BO_GRAPH_STAT_RATIO_DIST_WIDTH1);
			}
			else
			{
				$plot=new BarPlot($Y);
				if (BO_GRAPH_STAT_RATIO_DIST_WIDTH1)
					$plot->SetWidth(BO_GRAPH_STAT_RATIO_DIST_WIDTH1);
			}



			$plot->SetColor(BO_GRAPH_STAT_RATIO_DIST_COLOR_L1);
			if (BO_GRAPH_STAT_RATIO_DIST_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_RATIO_DIST_COLOR_F1);
			$plot->SetLegend(_BL('graph_legend_ratio_distance'));
			$graph->Add($plot);


			$plot=new LinePlot($Y2);
			$plot->SetColor(BO_GRAPH_STAT_RATIO_DIST_COLOR_L2);
			if (BO_GRAPH_STAT_RATIO_DIST_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_RATIO_DIST_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_RATIO_DIST_WIDTH2);
			$plot->SetLegend(_BL('graph_legend_count_distance'));
			$graph->SetYScale(0,'lin');
			$graph->AddY(0,$plot);

			$graph->xaxis->SetTickPositions($tickMajPositions,$tickPositions,$tickLabels);
			$graph->xaxis->title->Set(_BL('Distance').'   [km]');
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');

			if ($type == 'ratio_distance_longtime')
				$graph->ynaxis[0]->title->Set(_BL('Count'));
			else
				$graph->ynaxis[0]->title->Set(_BL('Count per hour'));

			break;

		case 'ratio_bearing':
		case 'ratio_bearing_longtime':

			$graph->title->Set(_BL('graph_stat_title_ratio_bearing').$add_title);

			if (BO_GRAPH_STAT_RATIO_BEAR_LINE)
			{
				$plot=new LinePlot($Y);
				$plot->SetWeight(BO_GRAPH_STAT_RATIO_BEAR_WIDTH1);
			}
			else
			{
				$plot=new BarPlot($Y);
				if (BO_GRAPH_STAT_RATIO_BEAR_WIDTH1)
					$plot->SetWidth(BO_GRAPH_STAT_RATIO_BEAR_WIDTH1);
			}

			$plot->SetColor(BO_GRAPH_STAT_RATIO_BEAR_COLOR_L1);
			if (BO_GRAPH_STAT_RATIO_BEAR_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_RATIO_BEAR_COLOR_F1);
			$plot->SetLegend(_BL('graph_legend_ratio_bearing'));
			$graph->Add($plot);


			$plot=new LinePlot($Y2);
			$plot->SetColor(BO_GRAPH_STAT_RATIO_BEAR_COLOR_L2);
			if (BO_GRAPH_STAT_RATIO_BEAR_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_RATIO_BEAR_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_RATIO_BEAR_WIDTH2);
			$plot->SetLegend(_BL('graph_legend_count_bearing'));
			$graph->SetYScale(0,'lin');
			$graph->AddY(0,$plot);

			$graph->xaxis->SetTickPositions($tickMajPositions,$tickPositions,$tickLabels);
			$graph->xaxis->title->Set(_BL('Bearing').'   [°]');
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');

			if ($type == 'ratio_bearing_longtime')
				$graph->ynaxis[0]->title->Set(_BL('Count'));
			else
				$graph->ynaxis[0]->title->Set(_BL('Count per hour'));

			break;


	}


	$graph->xaxis->title->SetColor(BO_GRAPH_STAT_COLOR_YAXIS_TITLE);
	$graph->yaxis->title->SetColor(BO_GRAPH_STAT_COLOR_YAXIS_TITLE);
	$graph->yaxis->SetLabelMargin(1);
	$graph->yaxis->SetTitleMargin(35);
	//$graph->xaxis->SetLabelAngle(45);


	if ($graph_type == 'datlin')
	{
		if ($X[count($X)-1] - $X[0] > 3600 * 24 * 3)
		{
			$graph->xaxis->title->Set(_BL('day'));
			$graph->xaxis->scale->SetDateFormat('d.m');
		}
		else
		{
			$graph->xaxis->title->Set(_BL('timeclock'));
			$graph->xaxis->scale->SetDateFormat('H:i');
		}
	}

	$graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
	$graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);

	if (is_object($graph->ynaxis[0]))
	{
		$graph->ynaxis[0]->title->SetColor(BO_GRAPH_STAT_COLOR_YAXIS_TITLE);
		$graph->ynaxis[0]->SetLabelMargin(3);
		$graph->ynaxis[0]->SetTitleMargin(30);
		$graph->ynaxis[0]->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
		$graph->ynaxis[0]->SetTitleMargin(45);
	}

	header("Content-Type: image/png");
	header("Pragma: ");
	header("Cache-Control: public, max-age=".($time_end + BO_UP_INTVL_STATIONS * 60 - time()));
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $time_end)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $time_end + BO_UP_INTVL_STATIONS * 60)." GMT");


	$I = $graph->Stroke(_IMG_HANDLER);
	imagepng($I);


}

function bo_graph_error($w, $h)
{
	$I = imagecreate($w, $h);
	$back  = imagecolorallocate($I, 255, 150, 150);
	$black = imagecolorallocate($I, 0, 0, 0);

	imagestring($I, 2, $w / 2 - 90, $h/2-25, 'File', $black);
	imagestring($I, 2, $w / 2 - 90, $h/2-10, '"includes/jpgraph/jpgraph.php"', $black);
	imagestring($I, 2, $w / 2 - 90, $h/2+5, 'not found!', $black);

	imagerectangle($I, 0,0,$w-1,$h-1,$black);
	Header("Content-type: image/png");
	Imagepng($I);
	exit;
}

?>