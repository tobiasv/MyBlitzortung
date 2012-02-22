<?php


class BoDbMain
{
	static $dbh = NULL;

	// use this class for calling static methods only:
	private function __construct() {}

	// establishes a connection to the database using
	// the globally defined constants for user, pass etc.
	// returns an existing connection if there is one.
	public static function connect($prepare_all = true)
	{
		if(!is_null(self::$dbh) && self::$dbh->ping())
		{
			return self::$dbh;
		}

		$port = (int)BO_DB_PORT;

		if (!$port)
			$port = null;
		
		self::$dbh = new mysqli(BO_DB_HOST, BO_DB_USER, BO_DB_PASS, "", $port);

		if (mysqli_connect_error())
			die('Database: Connect ERROR (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		if ($prepare_all)
		{
			self::select_db();
			self::set_charset();

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
	}
	
	public static function select_db($die_on_error = true)
	{
		$ok = self::$dbh->select_db(BO_DB_NAME);
		
		if (!$ok && $die_on_error)
			die ("Database not found! (".mysqli_connect_errno().")");
		
		return $ok;
	}

	public static function set_charset($charset = false, $die_on_error = true)
	{	
		if (!$charset)
			$charset = 'latin1';
		
		$ok = self::$dbh->set_charset($charset);
		
		if (!$ok && $die_on_error)
			die('Database: Charset ERROR ('.mysqli_connect_errno().")");
		
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

	public function do_query($sql)
	{
		self::connect();
		return self::$dbh->query($sql);
	}

	public function esc($val)
	{
		self::connect();
		return self::$dbh->real_escape_string($val);
	}
}
