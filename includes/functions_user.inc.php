<?php 


function bo_user_do_login($user, $pass, $cookie, $md5pass = false)
{
	$pass = trim($pass);

	if (!$user || !$pass)
		return false;

	if (BO_LOGIN_ALLOW > 0 && $user == BO_USER && defined('BO_USER') && strlen(BO_USER))
	{
		if ( ($pass == BO_PASS || ($md5pass && $pass == md5(BO_PASS))) && defined('BO_PASS') && strlen(BO_PASS))
		{
			if (!$md5pass)
				$pass = md5($pass);

			bo_user_set_session(1, pow(2, BO_PERM_COUNT) - 1, $cookie, $pass);
			return true;
		}
	}

	if (BO_LOGIN_ALLOW == 2)
	{
		if ($md5pass == false)
			$pass = md5($pass);

		$res = BoDb::query("SELECT id, login, level FROM ".BO_DB_PREF_USER."user WHERE login='$user' AND password='$pass'");

		if ($res->num_rows == 1)
		{
			$row = $res->fetch_assoc();
			if ($row['id'] > 1)
			{
				bo_user_set_session($row['id'], $row['level'], $cookie, $pass);
				return true;
			}
		}

	}

	return false;
}

function bo_user_do_login_byid($id, $pass)
{
	$id = intval($id);

	if ($id == 1)
	{
		$user = BO_USER;
	}
	elseif ($id > 1)
	{
		$row = BoDb::query("SELECT login FROM ".BO_DB_PREF_USER."user WHERE id='$id'")->fetch_assoc();
		$user = $row['login'];
	}

	bo_user_do_login($user, $pass, false, true);
}

function bo_user_do_logout()
{
	if ($_COOKIE['bo_login'] && !$_BO['headers_sent'])
		setcookie("bo_login", '', time()+3600*24*9999,'/');

	bo_set_conf_user('cookie', '');

	$_SESSION['bo_user'] = 0;
	$_SESSION['bo_user_level'] = 0;
	$_SESSION['bo_logged_out'] = true;
	$_SESSION['bo_login_time'] = 0;
	
	unset($_SESSION['bo_external_login']);
	unset($_SESSION['bo_external_name']);
	
}

function bo_user_set_session($id, $level, $cookie, $pass='')
{
	bo_user_init(true);
	
	$_SESSION['bo_user'] = $id;
	$_SESSION['bo_user_level'] = $level;
	$_SESSION['bo_logged_out'] = false;
	$_SESSION['bo_login_time'] = time();

	$cookie_days = intval(BO_LOGIN_COOKIE_TIME);

	if ($cookie && !$_BO['headers_sent'] && $cookie_days)
	{
		$data = unserialize(bo_get_conf_user('cookie', $id));

		if (!$data['uid'])
			$data['uid'] = md5(uniqid('', true));

		$data['pass'] = $pass;
		bo_set_conf_user('user_cookie', serialize($data), $id);

		setcookie(BO_COOKIE_NAME, $id.'_'.$data['uid'], time()+3600*24*$cookie_days,'/');
	}
	else
	{
		//just as info for underlying http server that a user is connecting (i.e. for caching)
		setcookie(BO_COOKIE_NAME, $id, 0,'/');
	}

	$lastlogin = bo_get_conf_user('lastlogin_next', $id);
	bo_set_conf_user('lastlogin', $lastlogin, $id);
	bo_set_conf_user('lastlogin_next', time(), $id);
}

function bo_user_get_id()
{
	return $_SESSION['bo_user'];
}

function bo_user_get_level($user_id = 0)
{
	if (!$user_id)
		return $_SESSION['bo_user_level'];

	if ($user_id == 1)
		return pow(2, BO_PERM_COUNT) - 1;

	$res = BoDb::query("SELECT level FROM ".BO_DB_PREF_USER."user WHERE id='".intval($user_id)."'");
	$row = $res->fetch_assoc();

	return $row['level'];
}

function bo_user_get_name($user_id = 0)
{
	static $names;

	if (!$user_id)
		$user_id = $_SESSION['bo_user'];

	if ($user_id == 1)
		return BO_USER;

	if (!$user_id)
		return '';
		
	if (!isset($names[$user_id]))
	{
		$res = BoDb::query("SELECT login FROM ".BO_DB_PREF_USER."user WHERE id='".intval($user_id)."'");
		$row = $res->fetch_assoc();
		$names[$user_id] = $row['login'];
		
		if ($_SESSION['bo_external_login'] === true && trim($_SESSION['bo_external_name']))
		{
			$names[$user_id] .= ' ('.$_SESSION['bo_external_name'].')';
		}
	}

	return $names[$user_id];
}

function bo_user_get_mail($user_id = 0)
{
	static $mails;

	if (!$user_id)
		$user_id = $_SESSION['bo_user'];

	if (!isset($mails[$user_id]))
	{
		$res = BoDb::query("SELECT mail FROM ".BO_DB_PREF_USER."user WHERE id='".intval($user_id)."'");
		$row = $res->fetch_assoc();
		$mails[$user_id] = $row['mail'];
	}

	return $mails[$user_id];
}


function bo_set_conf_user($name, $data, $id=0)
{
	$id = $id > 0 ? $id : bo_user_get_id();

	if ($id > 0)
		return BoData::set('user_'.$name.'_'.$id, $data);
	else
		return false;
}


function bo_get_conf_user($name, $id=0)
{
	$id = $id > 0 ? $id : bo_user_get_id();

	if ($id > 0)
		return BoData::get('user_'.$name.'_'.$id);
	else
		return false;
}



function bo_user_init($force = false)
{
	global $_BO;
	
	//don't create a session if request comes through non-cookie-domain
	if (strpos(BO_FILE_NOCOOKIE, 'http://'.$_SERVER['HTTP_HOST'].'/') !== false)
	{
		$_BO['radius'] = BO_RADIUS;
		return;
	}
	
	$init = true;
	
	//don't init if GET parameter option is set and no parameter present
	if (BO_SESSION_GET_PARAM !== false && !bo_sess_parms_set() && !$force)
	{
		$init = false;
	}
	
	if ($init)
	{
		session_set_cookie_params(BO_SESSION_COOKIE_LIFETIME, BO_SESSION_COOKIE_PATH);
		
		if (BO_SESSION_NAME)
			session_id(BO_SESSION_NAME);
		
		//Session handling
		@session_start();

		//Set user_id
		if (!isset($_SESSION['bo_user']))
			$_SESSION['bo_user'] = 0;
	}
	else
	{
		bo_user_cookie_login();
	}
	
	$_BO['radius'] = (bo_user_get_level() & BO_PERM_NOLIMIT) ? 0 : BO_RADIUS;

}

function bo_sess_parms_set()
{
	return BO_SESSION_GET_PARAM !== false 
		&& (isset($_GET[BO_SESSION_GET_PARAM]) || strpos($_SERVER['HTTP_REFERER'], BO_SESSION_GET_PARAM) !== false);
}

function bo_add_sess_parms($force = false)
{
	if (bo_sess_parms_set() || (BO_SESSION_GET_PARAM !== false && $force))
	{
		return '&'.BO_SESSION_GET_PARAM;
	}

	return '';
}

function bo_user_cookie_login()
{
	if (isset($_COOKIE[BO_COOKIE_NAME]) && !bo_user_get_id())
	{
		//Check for stored login in cookie
		if (intval(BO_LOGIN_COOKIE_TIME) && preg_match('/^([0-9]+)_([0-9a-z]+)$/i', trim($_COOKIE[BO_COOKIE_NAME]), $r) )
		{
			$cookie_user_id = $r[1];
			$cookie_uid = $r[2];

			$data = unserialize(bo_get_conf_user('user_cookie', $cookie_user_id));

			if ($cookie_uid == $data['uid'] && trim($data['uid']))
			{
				bo_user_do_login_byid($cookie_user_id, $data['pass']);
			}

		}
		else
		{
			//delete cookie
			setcookie(BO_COOKIE_NAME, null, -1, '/');
		}
	}
}


?>