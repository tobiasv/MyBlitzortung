<?php


class BoDbMain
{
	static $dbh = NULL;

	private static $host = BO_DB_HOST;
	private static $user = BO_DB_USER;
	private static $pass = BO_DB_PASS;
	private static $port = BO_DB_PORT;
	private static $db   = BO_DB_NAME;
	private static $timeout = null;
	
	// use this class for calling static methods only:
	private function __construct() {}

	private static function reset_config()
	{
		self::$host = BO_DB_HOST;
		self::$user = BO_DB_USER;
		self::$pass = BO_DB_PASS;
		self::$port = BO_DB_PORT;
		self::$db   = BO_DB_NAME;
		self::$timeout = null;
	}
	
	// establishes a connection to the database using
	// the globally defined constants for user, pass etc.
	// returns an existing connection if there is one.
	public static function connect($prepare_all = true, $die_on_error = true, $database = BO_DB_NAME)
	{
		if(!is_null(self::$dbh) && self::$dbh->ping())
		{
			return self::$dbh;
		}

		self::$db = $database;
		
		if (!self::$port)
			self::$port = null;
		
		self::$dbh = mysqli_init();
		
		if (self::$timeout)
			self::$dbh->options(MYSQLI_OPT_CONNECT_TIMEOUT, self::$timeout);

		self::$dbh->real_connect(self::$host, self::$user, self::$pass, "", self::$port);
		
		if (mysqli_connect_error())
		{	
			self::$dbh = null;
			trigger_error('Database: Connect ERROR for "'.self::$user.'@'.self::$host.':'.self::$port.'" ('.mysqli_connect_errno().'). '.mysqli_connect_error(), $die_on_error ? E_USER_ERROR : E_USER_WARNING);
			return false;
		}
			
		if ($prepare_all)
		{
			self::select_db($die_on_error);
			self::set_charset(false, $die_on_error);

			//hope this works for everyone :-/
			self::do_query("SET time_zone = '+00:00'");
		}
			
		return self::$dbh;
	}

	public static function close()
	{
		if (!is_null(self::$dbh))
		{
			self::$dbh->close();
			self::$dbh = null;
		}
		
		self::reset_config();
	}
	
	public static function select_db($die_on_error = true)
	{
		$ok = self::$dbh->select_db(self::$db);
		
		if (!$ok && $die_on_error)
			trigger_error("Database '".self::$db."' not found on '".self::$host."'! (".mysqli_connect_errno().")", E_USER_ERROR);
		
		return $ok;
	}

	//use a different server
	public static function use_server($host = BO_DB_HOST, $user = BO_DB_USER, $pass = BO_DB_PASS, $port = BO_DB_PORT)
	{
		self::close();
		
		self::$host = $host;
		self::$user = $user;
		self::$pass = $pass;
		self::$port = $port;
	}
	
	public static function set_timeout($sec)
	{
		self::$timeout = $sec;
	}
	
	public static function set_charset($charset = false, $die_on_error = true)
	{	
		if (!$charset)
			$charset = 'utf8';
		
		$ok = self::$dbh->set_charset($charset);
		
		if (!$ok && $die_on_error)
			trigger_error('Database: Charset ERROR ('.mysqli_connect_errno().")", E_USER_ERROR);
		
		return $ok;
	}
	
	public static function error()
	{
		return self::$dbh->error;
	}

	public static function affected_rows()
	{
		return self::$dbh->affected_rows;
	}

	public static function insert_id()
	{
		return self::$dbh->insert_id;
	}

	public static function do_query($sql)
	{
		self::connect();
		return self::$dbh->query($sql);
	}

	public static function esc($val)
	{
		self::connect();
		return self::$dbh->real_escape_string($val);
	}
}

?>