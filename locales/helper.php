<?php

$in  = 'de';
$out = 'en';

include($in.'.php');
include($out.'.php');

$O = '';
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
		
		$translated = strlen($text) > 0;
		
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
		
		$O .= '<span style="'.($translated ? '' : 'color: red').'">';
		$O .= '$_BL[\''.$out.'\'][\''.$r[1].'\'] = ';
		
		if ($_BL[$out][$r[1]] === false)
			$O .= 'false';
		else
			$O .= '\''.$text.'\'';
		
		$O .= ';<br>';
		$O .= '</span>';
	}
	elseif (preg_match('/\$_BL\[\'locale\'\]/', $line))
	{
		$O .= '$_BL[\'locale\'] = \''.$out.'\';<br>';
	}
	elseif (!$line || $line2 == '* ' || $line2 == '**' || $line2 == '/*' || $line2 == '*/' || $line2 == '//')
	{
		$O .= htmlentities($line).'<br>';
	}
}

echo htmlentities("<?php\n").'<br>';
echo $O;

?>