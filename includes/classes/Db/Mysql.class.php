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
		if(!is_null(self::$dbh))
		{
			return self::$dbh;
		}

		$host  = BO_DB_HOST;
		$host .= intval(BO_DB_PORT) ? ':'.intval(BO_DB_PORT) : '';
		
		self::$dbh = mysql_connect($host, BO_DB_USER, BO_DB_PASS) or die("Database: Connect ERROR ");

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
			mysql_close(self::$dbh);
			self::$dbh = null;
		}
	}
	
	public static function select_db($die_on_error = true)
	{
		$ok = mysql_select_db(BO_DB_NAME, self::$dbh);
		if (!$ok && $die_on_error)
			die ("Database not found (".mysql_error(self::$dbh).")");
		
		return $ok;
	}

	public static function set_charset($charset = false, $die_on_error = true)
	{
		if (!$charset)
			$charset = 'latin1';
			
		$ok = mysql_set_charset($charset, self::$dbh);
		if (!$ok && $die_on_error)
			die("Database: Charset ERROR (".mysql_error(self::$dbh).")");
			
		return $ok;
	}
	
	public static function error()
	{
		return mysql_error(self::$dbh);
	}

	public static function affected_rows()
	{
		return mysql_affected_rows(self::$dbh);
	}
	
	public static function insert_id()
	{
		return mysql_insert_id(self::$dbh);
	}

	public function do_query($sql)
	{
		self::connect();
		$query = new BoDbQuery();
		$query->DoQuery($sql, self::$dbh);
		return $query;
	}

	public function esc($val)
	{
		self::connect();
		return mysql_real_escape_string($val, self::$dbh);
	}
}




class BoDbMainQuery
{
	public $query;
	public $num_rows;

	function __construct() { }

	public function DoQuery($sql, $dbh)
	{
		$this->query = mysql_query($sql, $dbh);

		if (strtolower(substr(trim($sql), 0, 6)) == 'select')
		$this->num_rows = mysql_num_rows($this->query);

		return $this->query;
	}

	public function fetch_assoc()
	{
		return mysql_fetch_assoc($this->query);
	}

	public function fetch_object()
	{
		return mysql_fetch_object($this->query);
	}

}

?>