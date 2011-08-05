<?php

$in  = 'de';
$out = 'en';

include($in.'.php');
include($out.'.php');

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
		$T .= '$_BL[\''.$out.'\'][\''.$r[1].'\'] = ';
		
		if ($_BL[$out][$r[1]] === false)
			$T .= 'false';
		else
			$T .= '\''.$text.'\'';
		
		$T .= ';<br>';
		$T .= '</span>';
		
		if ($translated)
			$O .= $T;
		else
		{
			$U .= '<br>//'.$in.': '.htmlentities($_BL[$in][$r[1]]).'<br>';
			$U .= $T;
		}
		
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

echo htmlentities("<?php\n").'<br>';
echo $O;

if ($U)
{
	echo "<br><br>/********************/<br>/*&nbsp;&nbsp;NOT TRANSLATED&nbsp;&nbsp;*/<br>/********************/<br><br>";
	echo $U;
}

echo '<br><br>';

?>