<?php



//error output
function bo_image_error($text, $w=400, $h=300, $size=2)
{
	$I = imagecreate($w, $h);
	imagefill($I, 0, 0, imagecolorallocate($I, 255, 150, 150));
	$black = imagecolorallocate($I, 0, 0, 0);
	bo_imagestring_max($I, $size, 10, $h/2-25, $text, $black, $w-20);
	imagerectangle($I, 0,0,$w-1,$h-1,$black);

	$expire = time() + 30;
	Header("Content-type: image/png");
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", time())." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $expire)." GMT");
	header("Cache-Control: public, max-age=".($expire - time()));
	Imagepng($I);
	exit;
}

function bo_image_cache_error($w=400, $h=300)
{
	bo_image_error('Creating image failed! Please check if your cache-dirs are writeable!', $w, $h, 3);
}

function bo_imagestring(&$I, $size, $x, $y, $text, $tcolor = false, $bold = false, $angle = 0, $bordercolor = false, $px = 0)
{
	$font = bo_imagestring_font($size, $bold);

	if (is_string($tcolor))
	{
		$color = bo_hex2color($I, $tcolor);
	}
	elseif (is_array($tcolor))
	{
		$color = bo_hex2color($I, $tcolor[0]);
		$bordercolor = bo_hex2color($I, $tcolor[2]);
		$px = $tcolor[1];
	}
	else
		$color = $tcolor;

	if ($size <= 5 && $angle == 90)
	{
		return imagestringup($I, $size, $x, $y, $text, $color);
	}
	else
	{
		$h = $angle ? 0 : $size;
		$w = $angle ? $size : 0;

		return bo_imagettftextborder($I, $size, $angle, $x+$w, $y+$h, $color, $font, $text, $bordercolor, $px);
	}
}

function bo_imagestringright($I, $size, $x, $y, $text, $color = false, $bold = false, $angle = 0)
{
	$x -= bo_imagetextwidth($size, $bold, $text);
	return bo_imagestring($I, $size, $x, $y, $text, $color, $bold);
}

function bo_imagestringcenter($I, $size, $x, $y, $text, $color = false, $bold = false, $angle = 0)
{
	$x -= bo_imagetextwidth($size, $bold, $text) / 2;
	return bo_imagestring($I, $size, $x, $y, $text, $color, $bold);
}


//writes text with automatic line brakes into an image
function bo_imagestring_max(&$I, $size, $x, $y, $text, $color, $maxwidth, $bold = false)
{
	$text = strtr($text, array(chr(160) => ' '));
	$line_height = bo_imagetextheight($size, $bold) * 1.2;
	$breaks = explode("\n", $text);
	$blankwidth = bo_imagetextwidth($size, $bold, " ");

	foreach($breaks as $text2)
	{
		$width = 0;
		$lines = explode(" ", $text2);
		$x2 = $x;

		foreach($lines as $i=>$line)
		{
			$width = bo_imagetextwidth($size, $bold, $line) + $blankwidth;

			if ($x2+$width+9 > $x+$maxwidth)
			{
				$y += $line_height;
				$x2 = $x;
			}

			bo_imagestring($I, $size, $x2, $y, $line, $color, $bold);

			$x2 += $width;
		}

		$y += $line_height;
	}
	return $y;
}

function bo_imagetextheight($size, $bold = false, $string = false)
{
	if ($size <= 5)
	{
		return imagefontheight($size);
	}

	$font = bo_imagestring_font($size, $bold);

	$string = $string === false ? 'Ag' : $string;

	if (BO_FONT_USE_FREETYPE2)
		$tmp = imageftbbox($size, 0, $font, $string);
	else
		$tmp = imagettfbbox($size, 0, $font, $string);

	$height = $tmp[1] - $tmp[5];

	return $height;
}

function bo_imagetextwidth($size, $bold = false, $string = false)
{
	if ($size <= 5)
	{
		return imagefontwidth($size) * strlen($string);
	}

	$font = bo_imagestring_font($size, $bold);

	$string = $string === false ? 'A' : $string;

	if (BO_FONT_USE_FREETYPE2)
		$tmp = imageftbbox($size, 0, $font, $string);
	else
		$tmp = imagettfbbox($size, 0, $font, $string);

	$width = $tmp[2] - $tmp[0];

	return $width;
}


function bo_imagestring_font(&$size, &$type)
{
	if ($type === true) // bold
		$font = BO_DIR.BO_FONT_TTF_BOLD;
	else if ((int)$type && $type == 1)
		$font = BO_DIR.BO_FONT_TTF_MONO;
	else
		$font = BO_DIR.BO_FONT_TTF_NORMAL;


	return $font;

}


function bo_imagettftextborder(&$I, $size, $angle, $x, $y, $textcolor, $font, $text, $bordercolor = false, $px = 0)
{
	if ($px && $bordercolor !== false)
	{
		for($c1 = ($x-abs($px)); $c1 <= ($x+abs($px)); $c1++)
			for($c2 = ($y-abs($px)); $c2 <= ($y+abs($px)); $c2++)
				$bg = bo_imagefttext($I, $size, $angle, $c1, $c2, $bordercolor, $font, $text);
	}

   return bo_imagefttext($I, $size, $angle, $x, $y, $textcolor, $font, $text);
}

function bo_imagefttext(&$I, $size, $angle, $x, $y, $color, $font, $text)
{
	if ($size <= 5)
		return imagestring($I, $size, $x, $y, $text, $color);
	else if (BO_FONT_USE_FREETYPE2)
		return imagefttext($I, $size, $angle, $x, $y, $color, $font, $text);
	else
		return imagettftext($I, $size, $angle, $x, $y, $color, $font, $text);
}


function bo_image_reduce_colors(&$I, $density_map=false, $transparent=false)
{
	if ($transparent)
		$colors = intval(BO_IMAGE_PALETTE_COLORS_TRANSPARENT);
	elseif ($density_map)
		$colors = intval(BO_IMAGE_PALETTE_COLORS_DENSITIES);
	else
		$colors = intval(BO_IMAGE_PALETTE_COLORS_MAPS);
	
	
	if ($colors)
	{
		//colorstotal works only for palette images
		$total = imagecolorstotal($I);
		if ($total && $total <= 256)
			return;


		$width = imagesx($I);
		$height = imagesy($I);

		if (BO_IMAGE_PALETTE_AUTO)
		{
			$Itmp = ImageCreateTrueColor($width, $height);
			
			if ($transparent)
			{
				$back = imagecolorallocate($Itmp, 140, 142, 144);
				imagefilledrectangle($Itmp, 0, 0, $width, $height, $back);
				imagecolortransparent($Itmp, $back);
			}

			ImageCopy($Itmp, $I, 0, 0, 0, 0, $width, $height);
		}
		
		//reduce colors: imagecolormatch doesn't exist in some PHP-GD modules (i.e. Ubuntu)
		if (!$transparent && function_exists('imagecolormatch'))
		{
			$colors_handle = ImageCreateTrueColor($width, $height);
			ImageCopyMerge($colors_handle, $I, 0, 0, 0, 0, $width, $height, 100 );
			ImageTrueColorToPalette($I, false, $colors);
			ImageColorMatch($colors_handle, $I);
			ImageDestroy($colors_handle);
		}
		else
		{
			imagetruecolortopalette($I, false, $colors);
		}

		if (BO_IMAGE_PALETTE_AUTO)
		{
			if (imagecolorstotal($I) == 256) //too much colors ==> back to truecolor
			{
				imagedestroy($I);
				$I = $Itmp;
			}
			else
			{
				imagedestroy($Itmp);
			}
		}
		
	}
}


function bo_imagecreatefromfile($file)
{
	$extension = strtolower(substr($file, strrpos($file, '.')+1));
	
	if (!file_exists($file) || is_dir($file))
		bo_image_error("Couldn't find image file:\n$file");
	
	if ($extension == 'jpg' || $extension == 'jpeg')
		$I = imagecreatefromjpeg($file);
	elseif ($extension == 'gif')
		$I = imagecreatefromgif($file);
	else // PNG is default
		$I = imagecreatefrompng($file);
	
	if ($I === false)
		bo_image_error("Couldn't read image file:\n$file\nUnknown file format/Wrong image data");
	
	return $I;
}



function bo_circle($I, $px, $py, $s, $col, $filled = false)
{
	//imagefilledarc draws much nicer circles, but has a bug in older php versions
	//https://bugs.php.net/bug.php?id=43547
	//imagefilledarc: not nice when size is even
	$arc = !(!($s%2) || $s >= 8 || BO_NICE_CIRCLES == 0 || (BO_NICE_CIRCLES == 2 && $py >= imagesy($I)-$s+3));
	
	if ($filled)
	{
		if ($arc)
			imagefilledarc($I, $px, $py, $s, $s, 0, 360, $col, IMG_ARC_PIE);
		else
			imagefilledellipse($I, $px, $py, $s, $s, $col);
	}
	else
	{
		if ($arc)
			imagearc($I, $px, $py, $s, $s, 0, 360, $col);
		else
			imageellipse($I, $px, $py, $s, $s, $col);
	}
}

function bo_drawpoint($I, $x, $y, &$style, $color = null, $use_alpha = true, $strikedata = null)
{

	if ($color == null && $style[2]) //fillcolor
		$color = bo_hex2color($I, $style[2], $use_alpha);

	$bordercolor = null;
		
	if ($style[3]) 
	{
		$bordercolor = bo_hex2color($I, $style[4], $use_alpha);
		imagesetthickness($I, $style[3]);
	}

	$s = $style[1]; //size
		
		
	switch ($style[0])
	{
		case 1: //Circle
			
			if ($s == 1)
			{
				imagesetpixel($I, $x, $y, $color);
			}
			else if ($s == 2)
			{
				imagerectangle($I, $x, $y, $x+1, $y+1, $color);
			}
			else
			{
				imagefilledellipse($I, $x, $y, $s, $s, $color);
			}
			
			if ($bordercolor !== null)
				imageellipse($I, $x, $y, $s+1, $s+1, $bordercolor);
				
			break;
		
		
		case 2: //Plus
		
			$s /= 2;
			$x = (int)$x;
			$y = (int)$y;
			
			if ($bordercolor !== null)
			{
				imagesetthickness($I, $style[3]+2);
				imageline($I, $x-$s-1, $y, $x+$s+1, $y, $bordercolor);
				imageline($I, $x, $y-$s-1, $x, $y+$s+1, $bordercolor);
			}
			
			if ($style[3])
				imagesetthickness($I, $style[3]);
				
			imageline($I, $x-$s, $y, $x+$s, $y, $color);
			imageline($I, $x, $y-$s, $x, $y+$s, $color);
			
	
			break;
		
		
		case 3: // Square
		
			$s /= 2;
			
			if ($style[2])
				imagefilledrectangle($I, $x-$s, $y-$s, $x+$s, $y+$s, $color);
			
			if ($bordercolor !== null)
				imagerectangle($I, $x-$s-1, $y-$s-1, $x+$s+1, $y+$s+1, $bordercolor);
				
			break;
		
		case 10: // Station sign *g*
		
			imageline($I, $x-$s*0.6, $y+$s*0.9, $x+$s*0.6, $y+$s*0.9, $color);
			imageline($I, $x, $y-$s, $x, $y+$s*0.9, $color);
			
			imagefilledellipse($I, $x, $y-$s, $s-1, $s-1, $color);
			
			imagearc($I, $x-$s, $y-$s, $s*4, $s*3, -30, +30, $bordercolor);
			imagearc($I, $x+$s, $y-$s, $s*4, $s*3, -30+180, +30+180, $bordercolor);
			
			break;


			
		case 20: // Strike sign
		
			$points = array(
					$x-$s*0.3, $y+$s*0.1, 
					$x-$s*0.1, $y+$s*0.1,
					$x-$s*0.3, $y+$s,
					$x+$s*0.4, $y-$s*0.1, 
					$x+$s*0.1, $y-$s*0.1,
					$x+$s*0.7, $y-$s, 
					$x+$s*0.1, $y-$s,
					$x-$s*0.3, $y+$s*0.1);

			if ($style[2])					
				imagefilledpolygon($I, $points, count($points)/2, $color);
			
			if ($bordercolor !== null)
				imagepolygon($I, $points, count($points)/2, $bordercolor);
			
			
			break;
			
		
		default:
		
			if (function_exists($style[0]))
				call_user_func($style[0], $I, $x, $y, $color, $style, $strikedata);
				
			break;
			
	}
	
	imagesetthickness($I, 1);

}



function bo_imageout($I, $extension = 'png', $file = null, $mtime = null, $quality = BO_IMAGE_JPEG_QUALITY)
{
	$extension = strtr($extension, array('.' => ''));

	if (!headers_sent() && $file === null)
	{
		header("Content-Type: ".extension2mime($extension));
	}
	
	if (!$quality)
		$quality = BO_IMAGE_JPEG_QUALITY;
	
	//there seems to be an error in very rare cases
	//we retry to save the image if it didn't work
	$i=0;
		
	do
	{
		if ($i)
			usleep(100000);

		if ($extension == 'png')
			$ret = imagepng($I, $file, BO_IMAGE_PNG_COMPRESSION, BO_IMAGE_PNG_FILTERS);
		else if ($extension == 'gif')
			$ret = imagegif($I, $file);
		else if ($extension == 'jpeg')
			$ret = imagejpeg($I, $file, $quality);
		else if (imageistruecolor($I) === false)
			$ret = imagepng($I, $file, BO_IMAGE_PNG_COMPRESSION, BO_IMAGE_PNG_FILTERS);
		else
			$ret = imagejpeg($I, $file, $quality);
	}
	while ($i++ < 3 && !$ret && imagesx($I));

	
	if ($file)
	{
		if (filesize($file) == 0)
		{
			unlink($file);
		}
		else if ($mtime !== null)
		{
			touch($file, $mtime);
		}
		
		if (BO_OPTIPNG_LEVEL > 0 && $extension == 'png')
			exec("/usr/bin/optipng -o ".BO_OPTIPNG_LEVEL." '$file'");
	}
	
	return $ret;
}



function bo_hex2color(&$I, $str, $use_alpha = true)
{
	$rgb = bo_hex2rgb($str);

	if (count($rgb) == 4 && imageistruecolor($I) && $use_alpha)
		return imagecolorallocatealpha($I, $rgb[0], $rgb[1], $rgb[2], $rgb[3]);
	else
	{
		$col = imagecolorexact($I, $rgb[0], $rgb[1], $rgb[2]);

		if ($col === -1)
			return imagecolorallocate($I, $rgb[0], $rgb[1], $rgb[2]);
		else
			return $col;
	}
}

function bo_hex2rgb($str)
{
    $hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $str);
    $rgb = array();

	if (strlen($hexStr) == 3 || strlen($hexStr) == 4)
	{
        $rgb[0] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
        $rgb[1] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
        $rgb[2] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));

		if (strlen($hexStr) == 4)
			$rgb[3] = hexdec(str_repeat(substr($hexStr, 3, 1), 2)) / 2;
		else
			$rgb[3] = 0;
    }
	elseif (strlen($hexStr) == 6 || strlen($hexStr) == 8)
	{
        $rgb[0] = hexdec(substr($hexStr, 0, 2));
        $rgb[1] = hexdec(substr($hexStr, 2, 2));
        $rgb[2] = hexdec(substr($hexStr, 4, 2));

		if (strlen($hexStr) == 8)
			$rgb[3] = hexdec(substr($hexStr, 6, 2)) / 2;
		else
			$rgb[3] = 0;
    }

    return $rgb;
}


?>