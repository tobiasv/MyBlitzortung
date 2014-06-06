<?php

class BoSignalGraph 
{
	var $graph;
	var $fullscale = false;
	var $MaxTime = null;
	var $width  = 0;
	var $height = 0;
	var $big = false;
	
	function __construct($w, $h, $big=false)
	{
		if (!file_exists(BO_DIR.'includes/jpgraph/jpgraph.php'))
			bo_graph_error($w, $h);
	
		require_once BO_DIR.'includes/jpgraph/jpgraph.php';
		require_once BO_DIR.'includes/jpgraph/jpgraph_line.php';
		require_once BO_DIR.'includes/jpgraph/jpgraph_bar.php';
		require_once BO_DIR.'includes/jpgraph/jpgraph_plotline.php';
		
		$this->width = $w;
		$this->height = $h;
		$this->big = $big;
	}
	
	
	public function SetMaxTime($max_time)
	{
		$this->MaxTime = $max_time;
	}
	
	public function SetData($type, $data)
	{
		$tickLabels = array();
		$tickMajPositions = array();
		$tickPositions = array();	
	
		if ($type == 'xy') 
			$this->width = $this->height;

		$this->graph = new Graph($this->width, $this->height, "auto");
		$this->graph->ClearTheme();

		if (defined("BO_GRAPH_ANTIALIAS") && BO_GRAPH_ANTIALIAS)
			$this->graph->img->SetAntiAliasing();

		if ($this->big)
		{
			if (BO_GRAPH_RAW_COLOR_BACK_BIG)
				$this->graph->SetColor(BO_GRAPH_RAW_COLOR_BACK_BIG);
				
			if (BO_GRAPH_RAW_COLOR_MARGIN_BIG)
				$this->graph->SetMarginColor(BO_GRAPH_RAW_COLOR_MARGIN_BIG);
				
			if (BO_GRAPH_RAW_COLOR_FRAME_BIG)
				$this->graph->SetFrame(true, BO_GRAPH_RAW_COLOR_FRAME_BIG);
			else
				$this->graph->SetFrame(false);

			if (BO_GRAPH_RAW_COLOR_BOX_BIG)
				$this->graph->SetBox(true, BO_GRAPH_RAW_COLOR_BOX_BIG);
			else
				$this->graph->SetBox(false);
		}
		else 
		{
			if (BO_GRAPH_RAW_COLOR_BACK)
				$this->graph->SetColor(BO_GRAPH_RAW_COLOR_BACK);

			if (BO_GRAPH_RAW_COLOR_MARGIN)
				$this->graph->SetMarginColor(BO_GRAPH_RAW_COLOR_MARGIN);

			if (BO_GRAPH_RAW_COLOR_FRAME)
				$this->graph->SetFrame(true, BO_GRAPH_RAW_COLOR_FRAME);
			else
				$this->graph->SetFrame(false);

			if (BO_GRAPH_RAW_COLOR_BOX)
				$this->graph->SetBox(true, BO_GRAPH_RAW_COLOR_BOX);
			else
				$this->graph->SetBox(false);
		}
		
		$this->graph->SetMargin(24,1,1,1);
		bo_signal_parse($data, true);
	
		if ($type == 'spectrum')
		{
			//set labels
			$max_i = 0;
			foreach ($data['spec_freq'] as $i => $khz)
			{
				if ($khz > BO_GRAPH_RAW_SPEC_MAX_X)
				{
					$max_i = $i;
					break;
				}
				
				$tickLabels[$i] = (round($khz / 5) * 5).'kHz';
			}

			$max_ch = array();
			//parse data for each channel
			foreach($data['channel'] as $ch => $d)
			{

				
				foreach($d['spec'] as $i => $v)
				{
					if ($max_i && $i > $max_i)
						break;
						
					$D[$ch][$i] = $v;
				}

				$tmp = $D[$ch];
				rsort($tmp);
				$max_ch[$ch] = $tmp[2]; //the third highest maximum
			}
			
			$this->graph->SetScale("textlin", 0, max($max_ch), 0, BO_GRAPH_RAW_SPEC_MAX_X * $data['max_values'] * $ntime * 1e-6);

			if (isset($data['channel'][0]['spec']))
			{
				$plots[0]=new BarPlot($D[0]);
				$plots[0]->SetFillColor(BO_GRAPH_RAW_COLOR1);
			}

			if (isset($data['channel'][1]['spec']))
			{
				$plots[1]=new BarPlot($D[1]);
				$plots[1]->SetFillColor(BO_GRAPH_RAW_COLOR2);
			}

			if (isset($data['channel'][2]['spec']))
			{
				$plots[2]=new BarPlot($D[2]);
				$plots[2]->SetFillColor(BO_GRAPH_RAW_COLOR3);
			}

			if (isset($data['channel'][3]['spec']))
			{
				$plots[3]=new BarPlot($D[3]);
				$plots[3]->SetFillColor(BO_GRAPH_RAW_COLOR4);
			}

			if (isset($data['channel'][4]['spec']))
			{
				$plots[4]=new BarPlot($D[4]);
				$plots[4]->SetFillColor(BO_GRAPH_RAW_COLOR5);
			}

			if (isset($data['channel'][5]['spec']))
			{
				$plots[5]=new BarPlot($D[5]);
				$plots[5]->SetFillColor(BO_GRAPH_RAW_COLOR6);
			}
			
			foreach($plots as $p)
			{
				$p->SetColor('#fff@1');
				$p->SetAbsWidth(BO_GRAPH_RAW_SPEC_WIDTH / 2);
				
				if (count($plots) == 1)
					$this->graph->Add($p);
			}

			if (count($plots) > 1)
			{
				sort($plots);
				$plot = new GroupBarPlot($plots);
				$this->graph->Add($plot);
			}


			$grid = $this->big ? BO_GRAPH_RAW_COLOR_XGRID_BIG : BO_GRAPH_RAW_COLOR_XGRID;
			
			if ($grid)
			{
				$this->graph->xgrid->SetColor($grid);
				$this->graph->xgrid->Show(true,true);
			}
			else
				$this->graph->xgrid->Show(false);

				
			$grid = $this->big ? BO_GRAPH_RAW_COLOR_YGRID_BIG : BO_GRAPH_RAW_COLOR_YGRID;
				
			if ($grid)
			{
				$this->graph->ygrid->SetColor($grid);
				$this->graph->ygrid->Show(true,true);
			}
			else
				$this->graph->ygrid->Show(false,false);

			$this->graph->xaxis->SetColor($this->big ? BO_GRAPH_RAW_COLOR_XAXIS_BIG : BO_GRAPH_RAW_COLOR_XAXIS);
			$this->graph->yaxis->SetColor($this->big ? BO_GRAPH_RAW_COLOR_YAXIS_BIG : BO_GRAPH_RAW_COLOR_YAXIS);
			$this->graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
			$this->graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
			$this->graph->yaxis->HideLabels();

			$this->graph->xaxis->SetTickLabels($tickLabels);
			$this->graph->xaxis->SetTextLabelInterval(3);
			$this->graph->xaxis->SetTextTickInterval(20);

		}
		elseif ($type == 'xy')
		{
			$c = $data['channel'];
			
			if ($this->fullscale)
			{
				$max = 0;
				$max = max($max, @max($c[0]['data_volt']), @max($c[1]['data_volt']), abs(@min($c[0]['data_volt'])), abs(@min($c[1]['data_volt'])));
				$max = max($max, @max($c[3]['data_volt']), @max($c[4]['data_volt']), abs(@min($c[3]['data_volt'])), abs(@min($c[4]['data_volt'])));

				$xmax = $ymax = $max;
				$xmin = $ymin = -$max;
			}
			else
			{
				$xmin = -$data['max_volt'];
				$xmax = $data['max_volt'];
				$ymin = -$data['max_volt'];
				$ymax = $data['max_volt'];
			}

			$this->graph->SetScale("linlin",$ymin,$ymax,$xmin,$xmax);

			if (isset($c[0]) && isset($c[1]))
			{
				$plot=new LinePlot($c[0]['data_volt'], $c[1]['data_volt']);
				$plot->SetColor(BO_GRAPH_RAW_COLOR_XY1);
				$this->graph->Add($plot);
			}
			
			if (isset($c[3]) && isset($c[4]))
			{
				$plot=new LinePlot($c[3]['data_volt'], $c[4]['data_volt']);
				$plot->SetColor(BO_GRAPH_RAW_COLOR_XY2);
				$this->graph->Add($plot);
			}

			$this->graph->xaxis->SetColor($this->big ? BO_GRAPH_RAW_COLOR_XAXIS_BIG : BO_GRAPH_RAW_COLOR_XAXIS);
			$this->graph->yaxis->SetColor($this->big ? BO_GRAPH_RAW_COLOR_YAXIS_BIG : BO_GRAPH_RAW_COLOR_YAXIS);
			$this->graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,6);
			$this->graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,6);
			$this->graph->yaxis->SetTextTickInterval(0.5);
			
			$this->graph->xaxis->SetTickPositions(array(-$xmax,$xmax), array(-$xmax/2,$xmax/2));
			$this->graph->yaxis->SetTickPositions(array(-$xmax,$xmax), array(-$xmax/2,$xmax/2));
			
			$this->graph->xaxis->HideLabels();
			$this->graph->yaxis->HideLabels();
			$this->graph->xgrid->Show(true,false);
			$this->graph->ygrid->Show(true,false);
			$this->graph->SetMargin(1,1,1,1);
		}
		else
		{
			$ustepdisplay = 50;
			$data_tmp = array();

			foreach ($data['signal_time'] as $i => $time_us)
			{
				$data_tmp[$i] = $time_us;
			}
			
			if ($time_us < $this->MaxTime && $ntime > 0.0)
			{
				for ($us = $time_us+$ntime/1000; $us < $this->MaxTime; $us += $ntime/1000)
					$data_tmp[] = $us;
			}
			
			foreach($data_tmp as $i => $time_us)
			{
				if ($this->MaxTime !== null && $time_us > $this->MaxTime)
					break;
				
				$datax[] = $i;
				
				foreach($data['channel'] as $ch => $d)
					$datay[$ch][] = $d['data_volt'][$i];
				
				$tickLabels[] = round($time_us / $ustepdisplay, 1) * $ustepdisplay.'µs';
				
				if (!($i%12))
				{
					if (!($i%18))
					{
						$tickMajPositions[] = $i;
						
					}
					elseif (!($i%6))
					{
						$tickPositions[] = $i;
					}
				}
			}


			$n = count($datax);
			$xmin = $datax[0];
			$xmax = $datax[$n-1];

			if ($this->fullscale)
			{
				$ymax = $ymin = null;
			}
			else
			{
				$ymin = -$data['max_volt'];
				$ymax = $data['max_volt'];
			}

			$this->graph->SetScale("linlin",$ymin,$ymax,$xmin,$xmax);

			$plot = array();
			
			for ($i=0; $i<6; $i++)
			{
				if (is_array($datay[$i]))
				{
					if (max($datay[$i]) || min($datay[$i]))
					{
						$plot[$i]=new LinePlot($datay[$i], $datax);
						$plot[$i]->SetColor($this->GetColorChannel($i));
					}
				}
			}
			
			foreach ($plot as $p)
				$this->graph->Add($p);
			
			$this->graph->xaxis->SetPos('min');
			$this->graph->xaxis->SetTickLabels($tickLabels);
			//$this->graph->xaxis->scale->ticks->Set(28);
			$this->graph->xaxis->SetColor($this->big ? BO_GRAPH_RAW_COLOR_XAXIS_BIG : BO_GRAPH_RAW_COLOR_XAXIS);
			$this->graph->yaxis->SetColor($this->big ? BO_GRAPH_RAW_COLOR_YAXIS_BIG : BO_GRAPH_RAW_COLOR_YAXIS);

			
			
			$grid = $this->big ? BO_GRAPH_RAW_COLOR_XGRID_BIG : BO_GRAPH_RAW_COLOR_XGRID;
			
			if ($grid)
			{
				$this->graph->xgrid->SetColor($grid);
				$this->graph->xgrid->Show(true,true);
			}
			else
				$this->graph->xgrid->Show(false);

				
			$grid = $this->big ? BO_GRAPH_RAW_COLOR_YGRID_BIG : BO_GRAPH_RAW_COLOR_YGRID;
				
			if ($grid)
			{
				$this->graph->ygrid->SetColor($grid);
				$this->graph->ygrid->Show(true,true);
			}
			else
				$this->graph->ygrid->Show(false,false);

			
			$cline = $this->big ? BO_GRAPH_RAW_COLOR_LINES_BIG : BO_GRAPH_RAW_COLOR_LINES;

			$sline  = new PlotLine(HORIZONTAL,  0, $cline, 1);
			$this->graph->AddLine($sline);

			$sline  = new PlotLine(HORIZONTAL,  BO_TRIGGER_VOLTAGE, $cline, 1);
			$this->graph->AddLine($sline);

			$sline  = new PlotLine(HORIZONTAL, -BO_TRIGGER_VOLTAGE, $cline, 1);
			$this->graph->AddLine($sline);
			
			if ($data['start'])
			{
				$sline  = new PlotLine(VERTICAL, $data['start'], $cline, 1);
				$this->graph->AddLine($sline);
			}

			$this->graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
			$this->graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
			
			$this->graph->SetMargin(32,4,4,3);

			$sig = null;
			$y = $this->height-73;
			foreach($data['channel'] as $ch => $d)
			{
				$sig = $d;
				
				if ((int)$d['gain'] <= 0)
					continue;
					
				$caption = new Text($d['gain'], $this->width - 30, $y);
				$caption->SetFont(FF_DV_SANSSERIF,FS_NORMAL, 6);
				$caption->SetColor($this->GetColorChannel($ch));
				$this->graph->AddText($caption);
				
				
				$y += 10;
			}
			
			if (is_array($sig))
			{
				$ksps = round(1E6 / $sig['conv_gap']);
				
				$caption = new Text("PCB ".$sig['pcb']."\n ".$sig['values']." Values\n $ksps kSps", $this->width - 60, 5);
				$caption->SetFont(FF_DV_SANSSERIF,FS_NORMAL, 6);
				$caption->SetColor($this->big ? BO_GRAPH_STAT_COLOR_CAPTION_BIG : BO_GRAPH_STAT_COLOR_CAPTION);
				$this->graph->AddText($caption);
			}
		}
	
	}
	
	public function AddText($text)
	{
		$caption = new Text($text,35,5);
		$caption->SetFont(FF_DV_SANSSERIF,FS_NORMAL, 6);
		$caption->SetColor($this->big ? BO_GRAPH_STAT_COLOR_CAPTION_BIG : BO_GRAPH_STAT_COLOR_CAPTION);
		$this->graph->AddText($caption);
	}
	
	public function Display()
	{
		if (!is_object($this->graph))
		{
			bo_graph_error($this->width, $this->height);
		}
		else
		{
			$time = strtotime($row['time'].' UTC');

			header("Content-Type: image/png");
			header("Pragma: ");
			header("Cache-Control: public, max-age=".(3600 * 24));
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $time)." GMT");
			header("Expires: ".gmdate("D, d M Y H:i:s", $time + 3600 * 24)." GMT");

			$I = $this->graph->Stroke(_IMG_HANDLER);
			imagepng($I);
		}
	}
	
	public function DisplayEmpty($do_exit = false, $text = '')
	{
		$I = imagecreate(BO_GRAPH_RAW_W,BO_GRAPH_RAW_H);
		$color = imagecolorallocate($I, 255, 150, 150);
		imagefill($I, 0, 0, $color);
		imagecolortransparent($I, $color);
		
		bo_imagestring_max($I, 8, 5, 5, $text, BO_GRAPH_STAT_COLOR_CAPTION, BO_GRAPH_RAW_W);
		
		
		Header("Content-type: image/png");
		Imagepng($I);
		
		if ($do_exit)
			exit;
	}
	
	private function GetColorChannel($channel)
	{
		switch($channel)
		{
			case 0: return BO_GRAPH_RAW_COLOR1;
			case 1: return BO_GRAPH_RAW_COLOR2;
			case 2: return BO_GRAPH_RAW_COLOR3;
			case 3: return BO_GRAPH_RAW_COLOR4;
			case 4: return BO_GRAPH_RAW_COLOR5;
			case 5: return BO_GRAPH_RAW_COLOR6;
		}
	}
}

?>