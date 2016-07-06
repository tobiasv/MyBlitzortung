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

			bo_user_log("Login $user (BO_USER)");
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
				bo_user_log("Login $user");
				bo_user_set_session($row['id'], $row['level'], $cookie, $pass);
				return true;
			}
		}

	}

	bo_user_log("Login failed $user");
	
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

	bo_user_log("Login $id (ID)");
	bo_user_do_login($user, $pass, false, true);
}

function bo_user_do_logout()
{
	if ($_COOKIE[BO_COOKIE_NAME] && !$_BO['headers_sent'])
	{
		setcookie(BO_COOKIE_NAME, '', time()+3600*24*9999,'/');
		bo_user_log("Cookie emptied (Logout)");
	}

	bo_set_conf_user('cookie', '');

	$_SESSION['bo_user'] = 0;
	$_SESSION['bo_user_level'] = 0;
	$_SESSION['bo_logged_out'] = true;
	$_SESSION['bo_login_time'] = 0;
	
	unset($_SESSION['bo_external_login']);
	unset($_SESSION['bo_external_name']);
	
}

function bo_user_set_session($id, $level, $cookie, $md5pass='')
{
	bo_user_init(true);
	
	$_SESSION['bo_user'] = $id;
	$_SESSION['bo_user_level'] = $level;
	$_SESSION['bo_logged_out'] = false;
	$_SESSION['bo_login_time'] = time();

	$cookie_days = intval(BO_LOGIN_COOKIE_TIME);
	
	//user checked "stay logged in"
	if ($cookie && !$_BO['headers_sent'] && $cookie_days)
	{
		$data = unserialize(bo_get_conf_user('cookie', $id));

		if (!is_array($data['uid']))
			$data['uid'] = md5(uniqid('', true));
		
		$data['pass'] = $md5pass;
		
		bo_set_conf_user('user_cookie', serialize($data), $id);
		$cookie_data = $id.'_'.$data['uid'];
		setcookie(BO_COOKIE_NAME, $cookie_data, time()+3600*24*$cookie_days, '/');
		bo_user_log("Cookie added $cookie_data");
	}
	else if (!$_COOKIE[BO_COOKIE_NAME])
	{
		//only set if not present (otherwise overwriting cookie-login data)
		setcookie(BO_COOKIE_NAME, $id, 0, '/');
		bo_user_log("Cookie added (session only) $id");
	}

	$lastlogin = bo_get_conf_user('lastlogin_next', $id);
	bo_set_conf_user('lastlogin', $lastlogin, $id);
	bo_set_conf_user('lastlogin_next', time(), $id);
}

function bo_user_get_id()
{
	return $_SESSION['bo_user'] > 0 ? $_SESSION['bo_user'] : 0;
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
	if ( (BO_SESSION_GET_PARAM !== false || BO_SESSION_COOKIE_PARAM !== false) && !bo_sess_parms_set() && !$force)
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

		//check cookie here if no user logged in
		if (!$_SESSION['bo_user'])
		{
			$_SESSION['bo_user'] = -1; //in case cookie-login doesn't work, session and cookie will be destroyed next time (untested)
			
			if (bo_user_cookie_login())
				return;
			
			$_SESSION['bo_user'] = 0;
		}
		
		//remove session-info cookie if no user logged in in this session
		if (!$force && !count($_POST) && BO_SESSION_COOKIE_PARAM !== false && $_SESSION['bo_user'] <= 0)
		{
			//delete cookie
			setcookie(BO_COOKIE_NAME, null, -1, '/');
			session_destroy();
			bo_user_log("Cookie removed");
		}
		else if (!isset($_SESSION['bo_user'])) //Set user_id
		{
			$_SESSION['bo_user'] = 0;
		}
		
		if ($_SESSION['bo_user'] && !$_COOKIE[BO_COOKIE_NAME])
		{
			//user is in session, but cookie not set
			setcookie(BO_COOKIE_NAME, $_SESSION['bo_user'], 0,'/');
			bo_user_log("Cookie added (session only)");
		}
	}
	
	$_BO['radius'] = (bo_user_get_level() & BO_PERM_NOLIMIT) ? 0 : BO_RADIUS;
}

function bo_sess_parms_set()
{
	$get_present = BO_SESSION_GET_PARAM !== false 
		&& (isset($_GET[BO_SESSION_GET_PARAM]) || strpos($_SERVER['HTTP_REFERER'], BO_SESSION_GET_PARAM) !== false);
	
	$cookie_present = BO_SESSION_COOKIE_PARAM !== false && isset($_COOKIE[BO_COOKIE_NAME]);
	
	return $get_present || $cookie_present;
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
	static $checked = false;
	
	if ($checked)
		return false;
		
	$checked = true;

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
				bo_user_log("Cookie login $cookie_uid");
				bo_user_do_login_byid($cookie_user_id, $data['pass']);
				return true;
			}
			
			bo_user_log("Cookie not valid $cookie_uid");
		}
		else
		{
			//delete cookie
			setcookie(BO_COOKIE_NAME, null, -1, '/');
			bo_user_log("Cookie deleted $cookie_uid");
		}
	}
	
	return false;
}

function bo_user_log($message)
{
	$cache_dir = BO_DIR.'/'.BO_CACHE_DIR;
	$file = $cache_dir.'/user.log';
	file_put_contents($file, gmdate("Y-m-d H:i:s")." | ".$message."\n", FILE_APPEND);
}


?>