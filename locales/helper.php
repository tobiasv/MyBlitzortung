<?php


//Input language
$in  = 'en';

//Ouput language
$out = 'de';




error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);

include($in.'.php');
include($out.'.php');

if (file_exists('own.php'))
	include('own.php');

$O = ''; $U = '';

//find first "$_BL"
$tmp = file_get_contents($out.'.php');
$lines = explode("\n", $tmp);
foreach($lines as $line)
{
	if (preg_match('/\$_BL\[/', trim($line)))
		break;

	$O .= strtr(htmlentities($line), array(' ' => '&nbsp;')).'<br>';
}


//get input file
$I = file_get_contents($in.'.php');
$lines = explode("\n", $I);
$first_lines = true;

$in_utf  = $_BL[$in]['is_utf8'] == true;
$out_utf = $_BL[$out]['is_utf8'] == true;

foreach($lines as $line)
{
	$line = trim($line);
	$line1 = substr($line,0,1);
	$line2 = substr($line,0,2);

	if (preg_match('/\$_BL\[\'([a-z]+)\'\]\[\'([^\]]+)\'\]/', $line, $r))
	{
		$id = $r[2];
		$first_lines = false;

		if ($r[1] != $in)
		{
			continue;
		}
		elseif ($id == 'is_utf8')
		{
			$O .= '$_BL[\''.$out.'\'][\'is_utf8\'] = '.($_BL[$out]['is_utf8'] ? 'true' : 'false').';<br>';
			continue;
		}
			

		$text = $_BL[$out][$id];
		$translated = $text === false || strlen($text) > 0;

		if (!strlen($text) && $out == 'en' && strpos($id, '_') === false)
			$text = strtr($id, array("\\'" => "'"));

		if (!$out_utf)
			$text = htmlentities($text);
			
		$text = nl2br(strtr($text, array("'" => "\\'")));


		$T  = '<span style="'.($translated ? '' : 'color: red').'">';
		$T .= '$_BL[\''.$out.'\'][\'<strong>'.$id.'</strong>\'] = ';

		if ($_BL[$out][$id] === false)
			$T .= 'false';
		else
			$T .= '\''.$text.'\'';

		$T .= ';<br>';
		$T .= '</span>';

		if ($translated)
		{
			$O .= $T;
		}
		else
		{
			if ($out_utf && !$in_utf)
				$text_in = utf8_encode($_BL[$in][$id]);
			else if (!$out_utf && $in_utf)
				$text_in = utf8_decode($_BL[$in][$id]);
			else if (!$out_utf && !$in_utf)
				$text_in = htmlentities($_BL[$in][$id]);
			
			$comment = strtr($text_in, array("'" => "\\'"));
			$comment = strtr($comment, array("\n" => "<br>//      "));

			$U .= '<span style="color:#080">';
			$U .= '<br>//'.$in.': \''.$comment.'\'<br>';
			$U .= '</span>';
			$U .= $T;
		}

		if (isset($_BL[$out][$id]))
			unset($_BL[$out][$id]);
	}
	elseif (preg_match('/\$_BL\[\'locale\'\]/', $line))
	{
		$first_lines = false;
		$O .= '$_BL[\'locale\'] = \''.$out.'\';<br>';
	}
	elseif ($first_lines == false && (!$line || $line1 == '$' || $line2 == '* ' || $line2 == '**' || $line2 == '/*' || $line2 == '*/' || $line2 == '//') && substr($line,0,5) != '//'.$in.':')
	{
		$O .= strtr(htmlentities($line), array(' ' => '&nbsp;')).'<br>';
	}
}

$X = '';
if (!empty($_BL[$out]))
{
	foreach($_BL[$out] as $id => $text)
	{
		if ($id == 'is_utf8')
			continue;
			
		$X .= '$_BL[\''.$out.'\'][\''.$id.'\'] = ';

		if ($text === false)
			$X .= 'false';
		else
		{
			if (!$out_utf)
				$text = htmlentities($text);
			
			$X .= '\''.strtr($text, array("'" => "\\'")).'\'';
		}

		$X .= ';<br>';
	}
}


if ($out_utf)
	header("Content-Type: text/html; charset=UTF-8");
else
	header("Content-Type: text/html; charset=ISO-8859-1");


?><!DOCTYPE html>
<html>
<head>
<style>
body {
font-family: courier;
font-size: 11px;
}
</style>
</head>

<body>
<?php
//echo htmlentities("<?php\n").'<br>';
echo $O;

if ($U)
{
	echo "<br><br>/********************/<br>/*&nbsp;&nbsp;NOT TRANSLATED&nbsp;&nbsp;*/<br>/********************/<br><br>";
	echo $U;
}

if ($X)
{
	echo "<br><br>/*******************************/<br>/*&nbsp;&nbsp;NOT AVAILABLE IN ORIGINAL&nbsp;&nbsp;*/<br>/*******************************/<br><br>";
	echo $X;
}

echo '<br><br>';
echo htmlentities("?>").'<br>';

?>
</body>
</html>