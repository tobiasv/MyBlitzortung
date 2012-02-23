<?php

class BoSignalGraph 
{
	var $graph;
	var $fullscale = false;
	
	function __construct()
	{
		if (!file_exists(BO_DIR.'includes/jpgraph/jpgraph.php'))
			bo_graph_error(BO_GRAPH_RAW_W, BO_GRAPH_RAW_H);
	
		require_once BO_DIR.'includes/jpgraph/jpgraph.php';
		require_once BO_DIR.'includes/jpgraph/jpgraph_line.php';
		require_once BO_DIR.'includes/jpgraph/jpgraph_bar.php';
		require_once BO_DIR.'includes/jpgraph/jpgraph_plotline.php';
	}
	
	function SetData($type, $data, $channels, $ntime)
	{
		$data = raw2array($data, true, $channels, $ntime);
		
		$tickLabels = array();
		$tickMajPositions = array();
		$tickPositions = array();	
	
		if ($type == 'xy') {
			$width = BO_GRAPH_RAW_H;
		} else {
			$width = BO_GRAPH_RAW_W;
		}

		$this->graph = new Graph($width, BO_GRAPH_RAW_H, "auto");
		$this->graph->ClearTheme();

		if (defined("BO_GRAPH_ANTIALIAS") && BO_GRAPH_ANTIALIAS)
			$this->graph->img->SetAntiAliasing();

		if (BO_GRAPH_RAW_COLOR_BACK)
			$this->graph->SetColor(BO_GRAPH_RAW_COLOR_BACK);

		if (BO_GRAPH_RAW_COLOR_BACK)
			$this->graph->SetMarginColor(BO_GRAPH_RAW_COLOR_MARGIN);

		if (BO_GRAPH_RAW_COLOR_FRAME)
			$this->graph->SetFrame(true, BO_GRAPH_RAW_COLOR_FRAME);
		else
			$this->graph->SetFrame(false);

		if (BO_GRAPH_RAW_COLOR_BOX)
			$this->graph->SetBox(true, BO_GRAPH_RAW_COLOR_BOX);
		else
			$this->graph->SetBox(false);

		$this->graph->SetMargin(24,1,1,1);

		if ($type == 'spectrum')
		{
			$step = 5;

			foreach ($data['spec_freq'] as $i => $khz)
			{
				$tickLabels[$i] = (round($khz / 5) * 5).'kHz';
			}

			$values   = count($data['signal'][0]);

			$this->graph->SetScale("textlin", 0, $this->fullscale ? null : BO_GRAPH_RAW_SPEC_MAX_Y, 0, BO_GRAPH_RAW_SPEC_MAX_X * $values * $ntime * 1e-6);

			$plot1=new BarPlot($data['spec'][0]);
			$plot1->SetFillColor(BO_GRAPH_RAW_COLOR1);
			$plot1->SetColor('#fff@1');
			$plot1->SetAbsWidth(BO_GRAPH_RAW_SPEC_WIDTH / 2);

			$plot2=new BarPlot($data['spec'][1]);
			$plot2->SetFillColor(BO_GRAPH_RAW_COLOR2);
			$plot2->SetColor('#fff@1');
			$plot2->SetAbsWidth(BO_GRAPH_RAW_SPEC_WIDTH / 2);

			$plot = new GroupBarPlot(array($plot1, $plot2));
			$this->graph->Add($plot);


			if (BO_GRAPH_RAW_COLOR_XGRID)
			{
				$this->graph->xgrid->SetColor(BO_GRAPH_RAW_COLOR_XGRID);
				$this->graph->xgrid->Show(true,true);
			}
			else
				$this->graph->xgrid->Show(false);

			if (BO_GRAPH_RAW_COLOR_YGRID)
			{
				$this->graph->ygrid->SetColor(BO_GRAPH_RAW_COLOR_YGRID);
				$this->graph->ygrid->Show(true,true);
			}
			else
				$this->graph->ygrid->Show(false,false);

			$this->graph->xaxis->SetColor(BO_GRAPH_RAW_COLOR_XAXIS);
			$this->graph->yaxis->SetColor(BO_GRAPH_RAW_COLOR_YAXIS);
			$this->graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
			$this->graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
			$this->graph->yaxis->HideLabels();

			$this->graph->xaxis->SetTickLabels($tickLabels);
			$this->graph->xaxis->SetTextLabelInterval(2);
			$this->graph->xaxis->SetTextTickInterval(2);

		}
		elseif ($type == 'xy')
		{
			if ($this->fullscale)
			{
				$max = max(max($data['signal'][0]), max($data['signal'][1]), abs(min($data['signal'][0])), abs(min($data['signal'][1])));
				$xmax = $ymax = $max;
				$xmin = $ymin = -$max;
			}
			else
			{
				$xmin = -BO_MAX_VOLTAGE;
				$xmax = BO_MAX_VOLTAGE;
				$ymin = -BO_MAX_VOLTAGE;
				$ymax = BO_MAX_VOLTAGE;
			}
			
			$this->graph->SetScale("linlin",$ymin,$ymax,$xmin,$xmax);

			
			$plot=new LinePlot($data['signal'][0], $data['signal'][1]);
			$plot->SetColor(BO_GRAPH_RAW_COLOR_XY);
			$this->graph->Add($plot);

			$this->graph->xaxis->SetColor(BO_GRAPH_RAW_COLOR_XAXIS);
			$this->graph->yaxis->SetColor(BO_GRAPH_RAW_COLOR_YAXIS);
			$this->graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,6);
			$this->graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,6);
			$this->graph->yaxis->SetTextTickInterval(0.5);
			
			if ($xmax >= 1)
			{
				$this->graph->xaxis->SetTickPositions(array(-2,-1,0,1,2), array(-1.5,-0.5,0.5,1.5));
				$this->graph->yaxis->SetTickPositions(array(-2,-1,0,1,2), array(-1.5,-0.5,0.5,1.5));
			}
			else
			{
				$this->graph->xaxis->SetTickPositions(array(-$xmax,$xmax), array(-$xmax/2,$xmax/2));
				$this->graph->yaxis->SetTickPositions(array(-$xmax,$xmax), array(-$xmax/2,$xmax/2));
			}
			
			$this->graph->xaxis->HideLabels();
			$this->graph->yaxis->HideLabels();
			$this->graph->xgrid->Show(true,false);
			$this->graph->ygrid->Show(true,false);
			$this->graph->SetMargin(1,1,1,1);
		}
		else
		{
			$ustep = 50;
			foreach ($data['signal_time'] as $i => $time_us)
			{
				$datax[] = $i;
				$time_us = round($time_us / $ustep, 1) * $ustep;

				if (!($i%12))
				{
					if (!($i%18))
					{
						$tickMajPositions[] = $i;
						$tickLabels[] = $time_us.'µs';
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
				$ymin = -BO_MAX_VOLTAGE;
				$ymax = BO_MAX_VOLTAGE;
			}

			$this->graph->SetScale("linlin",$ymin,$ymax,$xmin,$xmax);

			if (max($data['signal'][0]) || min($data['signal'][0]))
			{
				$plot=new LinePlot($data['signal'][0], $datax);
				$plot->SetColor(BO_GRAPH_RAW_COLOR1);
				$this->graph->Add($plot);
			}

			if (max($data['signal'][1]) || min($data['signal'][1]))
			{
				$plot=new LinePlot($data['signal'][1], $datax);
				$plot->SetColor(BO_GRAPH_RAW_COLOR2);
				$this->graph->Add($plot);
			}

			$this->graph->xaxis->SetPos('min');
			$this->graph->xaxis->SetTickPositions($tickMajPositions,$tickPositions,$tickLabels);

			$this->graph->xaxis->SetColor(BO_GRAPH_RAW_COLOR_XAXIS);
			$this->graph->yaxis->SetColor(BO_GRAPH_RAW_COLOR_YAXIS);

			if (BO_GRAPH_RAW_COLOR_XGRID)
			{
				$this->graph->xgrid->SetColor(BO_GRAPH_RAW_COLOR_XGRID);
				$this->graph->xgrid->Show(true,true);
			}
			else
				$this->graph->xgrid->Show(false);

			if (BO_GRAPH_RAW_COLOR_YGRID)
			{
				$this->graph->ygrid->SetColor(BO_GRAPH_RAW_COLOR_YGRID);
				$this->graph->ygrid->Show(true,true);
			}
			else
				$this->graph->ygrid->Show(false,false);

			if (!$this->fullscale)
			{
				$this->graph->yaxis->SetTextTickInterval(0.5);

				for($i=-BO_MAX_VOLTAGE;$i<=BO_MAX_VOLTAGE;$i+=0.5)
				{
					if (abs($i) != 0.5)
						$yt[] = $i;
				}

				$this->graph->yaxis->SetTickPositions(array(-2,-1,0,1,2),$yt,array('-2V','-1V','0V','1V','2V'));
			}

			$sline  = new PlotLine(HORIZONTAL,  BO_TRIGGER_VOLTAGE, BO_GRAPH_RAW_COLOR3, 1);
			$this->graph->AddLine($sline);

			$sline  = new PlotLine(HORIZONTAL, -BO_TRIGGER_VOLTAGE, BO_GRAPH_RAW_COLOR3, 1);
			$this->graph->AddLine($sline);

			$this->graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
			$this->graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
		}
	
	}
	
	public function AddText($text)
	{
			$caption = new Text($text,35,3);
			$caption->SetFont(FF_DV_SANSSERIF,FS_NORMAL, 6);
			$caption->SetColor(BO_GRAPH_STAT_COLOR_CAPTION);
			$this->graph->AddText($caption);
	}
	
	public function Display()
	{
		if (!is_object($this->graph))
		{
			bo_graph_error(BO_GRAPH_RAW_W, BO_GRAPH_RAW_H);
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
	
	public function DisplayEmpty($do_exit = false)
	{
		$I = imagecreate(BO_GRAPH_RAW_W,BO_GRAPH_RAW_H);
		$color = imagecolorallocate($I, 255, 150, 150);
		imagefill($I, 0, 0, $color);
		imagecolortransparent($I, $color);
		Header("Content-type: image/png");
		Imagepng($I);
		
		if ($do_exit)
			exit;
	}
	
}

?>