<?php

//not done yet!

class BoMapProjection 
{
	var $Method;
	var $ImageWidth;
	var $ImageHeight;
	var $ImageCalibrationX;
	var $ImageCalibrationY;
	var $ImageOffsetX;
	var $ImageOffsetY;
	var $ImageCoor;
	
	function __construct($proj = '', $w, $h, $coord)
	{
		$this->ImageWidth  = $w;
		$this->ImageHeight = $h;
		$this->Method      = $proj;
		$this->ImageCoor   = $coord;
		
		//calibration points in image (default: top/right and bottom/left <-> N/E and S/W points)
		$cN = isset($coord[4]) ? $coord[4] : 0;
		$cE = isset($coord[5]) ? $coord[5] : $this->ImageWidth;
		$cS = isset($coord[6]) ? $coord[6] : $this->ImageHeight;
		$cW = isset($coord[7]) ? $coord[7] : 0;
		
		list($x1, $y1) = $this->Calculate($coord[2], $coord[3]); //South, West
		list($x2, $y2) = $this->Calculate($coord[0], $coord[1]); //North, East
		
		$this->ImageCalibrationX = ($cE - $cW) / ($x2 - $x1);
		$this->ImageCalibrationY = ($cS - $cN) / ($y2 - $y1);
		
		$this->ImageOffsetX = $x1 * $this->ImageCalibrationX - $cW;
		$this->ImageOffsetY = $y1 * $this->ImageCalibrationY + $cS;
		
	}
	
	
	function LatLon2Image($lat, $lon)
	{
		list($px, $py) = $this->Calculate($lat, $lon);
		$x =  $px * $this->ImageCalibrationX - $this->ImageOffsetX;
		$y = -$py * $this->ImageCalibrationY + $this->ImageOffsetY;

		return array($x, $y);
	}

	
	function Calculate($lat, $lon)
	{
		switch ($this->Method)
		{

			default:
				return bo_latlon2mercator($lat, $lon);

				
			case 'plate':
				return array($lon, $lat);
			
			
			//Normalized Geostationary Projection
			//by EUMETSAT
			case 'geos':
				return bo_latlon2geos($lat, $lon);
		}
	}
	
	//ToDo: We need calculation image-pixel -> lat/lon for that (or extra cfg-setting)
	function GetBounds()
	{
		switch ($this->Method)
		{
			case 'geos':
				return array(90, 180, -90, -180);
				
			default:
				return array($this->ImageCoor[0], $this->ImageCoor[1], $this->ImageCoor[2], $this->ImageCoor[3]);
	
		}
		
	}
	
}


?>