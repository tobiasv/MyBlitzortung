<?php


//loads the needed locales
function bo_load_locale($locale = '')
{
	global $_BL;
	$locdir = BO_DIR.'locales/';
	$load = array();
	$langs = array_flip(explode(',', BO_LANGUAGES));

	
	if (BO_LOCALE2 && file_exists($locdir.BO_LOCALE2.'.php')) // 2nd locale -> include first
	{
		$load[] = BO_LOCALE2;
	}

	if (file_exists($locdir.BO_LOCALE.'.php')) //main locale -> overwrites 2nd
	{
		$load[] = BO_LOCALE;
	}
	elseif (BO_LOCALE2 != 'en')
	{
		$load[] = 'en';
	}

	if (file_exists($locdir.'own.php')) //own translation (language independent)
		$load[] = 'own';


	//individual locale for user (link, session, cookie)
	if ($locale == '')
	{
		if (isset($_GET[BO_LANG_ARGUMENT]) && preg_match('/^[a-zA-Z]{2}$/', $_GET[BO_LANG_ARGUMENT]))
		{
			$locale = strtolower($_GET[BO_LANG_ARGUMENT]);
			$_SESSION['bo_locale'] = $locale;
			
			if ($_COOKIE['bo_locale'] != $locale)
				bo_setcookie("bo_locale", $locale, time()+3600*24*365*10, '/');
		}
		else if (isset($_SESSION['bo_locale']) && preg_match('/^[a-zA-Z]{2}$/', $_SESSION['bo_locale']))
		{
			$locale = $_SESSION['bo_locale'];
		}
		else if (isset($_COOKIE['bo_locale']) && preg_match('/^[a-zA-Z]{2}$/', $_COOKIE['bo_locale']))
		{
			$_SESSION['bo_locale'] = $_COOKIE['bo_locale'];
			$locale = $_COOKIE['bo_locale'];
		}
		else
		{
			$acc_langs = bo_get_accepted_langs();
			foreach($acc_langs as $lang => $q)
			{
				$lang = substr($lang,0,2);
				if (isset($langs[$lang]))
				{
					$locale = $lang;
					break;
				}
			}
		
		}

		if ($locale && file_exists($locdir.$locale.'.php') && $locale != BO_LOCALE)
		{
			$load[] = $locale;

			if (file_exists($locdir.'own.php')) //include this 2nd time (must overwrite the manual specified language!)
				$load[] = 'own';
		}
	}
	elseif ($locale !== false)
	{
		if (file_exists($locdir.$locale.'.php'))
			$load[] = $locale;
	}

	
	foreach ($load as $lang)
	{
		if (file_exists($locdir.$lang.'.php'))
		{
			if (BO_FORCE_LANGS === true && $lang != 'own' && !isset($langs[$lang]))
				continue;
			
			include $locdir.$lang.'.php';
			
			if ($lang != 'own')
				$main_lang = $lang;
		}
	}
	
	if (BO_LANG_REDIRECT === true 
		&& empty($_POST) 
		&& !headers_sent()
		&& php_sapi_name() != 'cli'
		&& $main_lang 
		&& (!isset($_GET[BO_LANG_ARGUMENT]) || $_GET[BO_LANG_ARGUMENT] != $main_lang) 
		)
	{
		return $main_lang;
	}
	
	//Send the language
	if (!headers_sent())
		header("Content-Language: $locale");
	
	return false;
}

function bo_get_accepted_langs()
{
	$langs = array();

	if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) 
	{
		preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

		if (count($lang_parse[1])) 
		{
			$langs = array_combine($lang_parse[1], $lang_parse[4]);
			
			foreach ($langs as $lang => $val) 
			{
				if ($val === '') 
					$langs[$lang] = 1;
			}

			arsort($langs, SORT_NUMERIC);
		}
	}
	
	return $langs;
}

function bo_lang_arg($type = false)
{
	if (BO_FORCE_MAP_LANG && $type == 'map')
		return;

	if (BO_FORCE_MAP_LANG == 'tiles' && $type == 'tile')
		return;

	return '&'.BO_LANG_ARGUMENT.'='._BL();
}


// translate text
function _BL($msgid=null, $noutf = false, $utf_in = false)
{
	global $_BL;

	$locale  = $_BL['locale'];
	$utf_out = BO_UTF8 && !$noutf;
	
	if ($msgid === null)
		return $locale;

	if (isset($_BL[$locale][$msgid]))
	{
		$msg     = $_BL[$locale][$msgid];
		$utf_in  = $_BL[$locale]['is_utf8'] == true;
	}
	
	if ($msg === false)
	{
		return '';
	}
	else if (!$msg)
	{
		if (defined('BO_LANG_AUTO_ADD') && BO_LANG_AUTO_ADD)
		{
			bo_add_locale_msgid($locale, $msgid);
			bo_add_locale_msgid('en', $msgid);
		}

		if (isset($_BL['en'][$msgid]))
		{
			$msg    = $_BL['en'][$msgid];
			$utf_in = $_BL['en']['is_utf8'] == true;
		}
		
	}

	if (!$msg)
	{
		$msg = $msgid;
		
		//Try to find some known words in short strings, i.e. country names
		if (BO_TRANSLATE_SINGLE_WORDS > 0 && strlen($msg) < 200 && strlen($msg) > BO_TRANSLATE_SINGLE_WORDS)
		{
			$words = preg_split("@[,;:/\(\)\<\> ]@", $msg, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

			if (count($words) > 1)
			{
				$offset_diff = 0;
				foreach ($words as $d)
				{
					$word   = $d[0];
					$offset = $d[1];
					$len    = strlen(trim($word));
					
					if ($len > 2)
					{
						$word_new = _BL($word, true);
						$len_new  = strlen($word_new);
						
						if ($word_new && $word_new != $word)
						{
							$msg_new  = substr($msg, 0, $offset + $offset_diff);
							$msg_new .= $word_new;
							$msg_new .= substr($msg, $offset + $offset_diff + $len);
							
							$offset_diff = $len_new - $len;
							
							$msg = $msg_new;
							//$msg = strtr($msg, array($word => $word_new));
						}
					}
				}
			}
		}
		
		
	}
	else
	{
		if (strpos($msg, "{STATION}") !== false)
		{
			$station_name = bo_station_city(); //always returning utf8
			if (!$utf_in)
				$station_name = utf8_decode($station_name);
			$msg = strtr($msg, array('{STATION}' => $station_name)); //needs a database lookup
		}

		$replace = array(
					'{USER}' => bo_user_get_name(),
					'{MYBO}' => $_BL[$locale]['MyBlitzortung'],
					'{MYBO_NOTAGS}' => $_BL[$locale]['MyBlitzortung_notags'],
					'{MYBO_ORIG}' => $_BL[$locale]['MyBlitzortung_original']
				);

		$msg = strtr($msg, $replace);
	}

	if ($utf_out && !$utf_in)
		$msg = utf8_encode($msg);
	elseif (!$utf_out && $utf_in)
		$msg = utf8_decode($msg);
	else
		$msg = $msg;
		
	return $msg;
}

function _BLN($number, $unit = 'minute')
{

	if ($number == 1)
		return _BL('number_1'.$unit.'');
	else
		return strtr(_BL('number_'.$unit.'s'), array('{NUMBER}' => $number));

}

//charset
function _BC($text, $nospecialchars=false, $is_utf8 = true)
{
	if ($is_utf8 && BO_UTF8 === false)
		return utf8_decode($text);
	else if (!$is_utf8 && BO_UTF8 === true)
		return utf8_encode($text);
	else if ($nospecialchars)
		return $text;
	else
		return htmlspecialchars($text);
}

// helper function for developers
function bo_add_locale_msgid($locale, $msgid)
{
	global $_BL;

	$file = BO_DIR.'locales/'.$locale.'.php';

	if (!isset($_BL[$locale][$msgid]) && is_writeable($file))
	{
		$msgid = strtr($msgid, array("'" => "\\'"));
		file_put_contents($file, '$_BL[\''.$locale.'\'][\''.$msgid.'\'] = \'\';'."\n", FILE_APPEND);
		$_BL[$locale][$msgid] = '';
	}

}



function _BDT($time, $show_tz = true)
{
	if ($time && $time > 0)
		return date(_BL('_datetime'), $time).($show_tz ? _BZ($time) : '');
	else
		return '-';
}

function _BD($time)
{
	return date(_BL('_date'), $time);
}

function _BZ($time)
{
	return BO_SHOW_TIMEZONE === true ? ' '._BL(date('T', $time)) : '';
}

function _BN($number, $decs = 0)
{
	return number_format($number, $decs, _BL('.'), _BL(','));
}


function _BK($km = false, $decs = 0)
{
	if ($km === false)
	{
		return BO_IMPERIAL === true ? _BL('unit_miles') : _BL('unit_kilometers');
	}
	
	if (BO_IMPERIAL === true)
		return _BN(km2mi($km), $decs)._BL('unit_miles');
	else
		return _BN($km, $decs)._BL('unit_kilometers');
}

function _BM($m = false, $decs = 0)
{
	if ($m === false)
	{
		return BO_IMPERIAL === true ? _BL('unit_yards') : _BL('unit_meters');
	}

	if (BO_IMPERIAL === true)
		return _BN(m2yd($m), $decs)._BL('unit_yards');
	else
		return _BN($m, $decs)._BL('unit_meters');
}


function bo_km($km)
{
	return BO_IMPERIAL === true ? km2mi($km) : $km;
}

function bo_m($m)
{
	return BO_IMPERIAL === true ? m2yd($m) : $m;
}


function m2yd($val)
{
	return $val / 0.9144;
}

function yd2m($val)
{
	return $val * 0.9144;
}

function km2mi($val)
{
	return $val / 1.609;
}

function mi2km($val)
{
	return $val * 1.609;
}


?>