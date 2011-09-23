<?php


//Input language
$in  = 'en';

//Ouput language
$out = 'fr';


/*
	HELPER FILE FOR TRANSLATING MyBlitzortung
	-----------------------------------------
	
	Howto:
	
	1. Above, change the $in and $out values according to your translation.
	2. Create a new file for your language code like 'xx.php' in the locales directory.
	   --> Read the warning below if the file already exists.
	3. Call helper.php in your browser, i.e. http://www.example.com/blitzortung/locales/helper.php
	   --> You will get a sorted output of translations
	4. Copy the output to your desired editor and change/add the translations.
	5. Save the whole edited text in the file created in step 1 --> "xx.php"
	   --> Check the translations in MyBlitzortung
	   --> Be careful: No spaces/newlines before "<?php" and after "?>"
	       at beginning or end of file!
	6. Again, call the helper.php URL, as described in step 3.
	   --> Your new translations will be ordered as in the input file.
	7. You can now repeat steps 4-6 and add more and more translations. 
	
	
	WARNING!
	--------
	BE CAREFUL IF YOU UPDATE MYBLITZORTUNG! YOUR OWN LANGUAGE FILE COULD BE OVERWITTEN!
	
	The above description is for creating new translations. If you want to change or add existing 
	translations, you should use the "own.php" file. You can put all your changes/additions in there. 
	Leave the $out value (don't insert "own"). The output of helper.php will be the same,
	as if you would edit the original language file. Everything in "own.php" will overwrite the
	original files.
	
	

	NOTES
	-----
	
	Be careful: Currently you can not use UTF-8 in language files!

	
	Even if you didn't have finished translating, you can send the new translation file
	to the maintainer at <mail@myblitzortung.de> 

	
	You may change one or more of the following settings in config.php:
	
	define('BO_LOCALE', 'en'); // <-- enter your new language-code here
	define('BO_LOCALE2', '');  // <-- second lang. if no translation of 1st locale is present (default: en)
	define('BO_LANGUAGES', 'de,en,fr'); 
	define('BO_SHOW_LANGUAGES', true); 
	define('BO_SHOW_LANG_FLAGS', true);
	define('BO_FORCE_MAP_LANG', true); // <-- set to false if languages in images can be changed too
	
	//For changing texts in images/graphs (change back to false after you've finished!)
	define('BO_CACHE_DISABLE', true); 
	

	
*/






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