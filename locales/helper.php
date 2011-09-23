<?php

$in  = 'de';
$out = 'fr';

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);

include($in.'.php');
include($out.'.php');
if (file_exists('own.php'))
	include('own.php');

$O = ''; $U = '';
$I = file_get_contents($in.'.php');
$lines = explode("\n", $I);

foreach($lines as $line)
{
	$line = trim($line);
	$line1 = substr($line,0,1);
	$line2 = substr($line,0,2);
	
	if (preg_match('/\$_BL\[\'[a-z]+\'\]\[\'([^\]]+)\'\]/', $line, $r))
	{
		$text = $_BL[$out][$r[1]];
		
		$translated = $text === false || strlen($text) > 0;
		
		if (!strlen($text) && $out == 'en' && strpos($r[1], '_') === false)
			$text = strtr($r[1], array("\\'" => "'"));

		$text = strtr(htmlentities($text), array("'" => "\\'"));
		
		if (0 && !$translated)
		{
			$text = '<input type="text" value="'.$text.'">';
		}
		else
		{
			$text = nl2br($text);
		}
		
		$T  = '<span style="'.($translated ? '' : 'color: red').'">';
		$T .= '$_BL[\''.$out.'\'][\'<strong>'.$r[1].'</strong>\'] = ';
		
		if ($_BL[$out][$r[1]] === false)
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
			$comment = htmlentities(strtr($_BL[$in][$r[1]], array("'" => "\\'")));
			$comment = strtr($comment, array("\n" => "<br>//      "));
			
			$U .= '<span style="color:#080">';
			$U .= '<br>//'.$in.': \''.$comment.'\'<br>';
			$U .= '</span>';
			$U .= $T;
		}
		
		if (isset($_BL[$out][$r[1]]))
			unset($_BL[$out][$r[1]]);
	}
	elseif (preg_match('/\$_BL\[\'locale\'\]/', $line))
	{
		$O .= '$_BL[\'locale\'] = \''.$out.'\';<br>';
	}
	elseif ((!$line || $line1 == '$' || $line2 == '* ' || $line2 == '**' || $line2 == '/*' || $line2 == '*/' || $line2 == '//') && substr($line,0,5) != '//'.$in.':')
	{
		$O .= strtr(htmlentities($line), array(' ' => '&nbsp;')).'<br>';
	}
}

$X = '';
if (!empty($_BL[$out]))
{
	foreach($_BL[$out] as $id => $text)
	{
		$X .= '$_BL[\''.$out.'\'][\''.$id.'\'] = ';
		
		if ($text === false)
			$X .= 'false';
		else
			$X .= '\''.strtr(htmlentities($text), array("'" => "\\'")).'\'';
		
		$X .= ';<br>';
	}
}

?>

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
echo htmlentities("<?php\n").'<br>';
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

?>
</body>
</html>