<?php

/*
    MyBlitzortung - a tool for participants of blitzortung.org
	to display lightning data on their web sites.

    Copyright (C) 2011  Tobias Volgnandt

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if (!defined('BO_VER'))
	exit('No BO_VER');

class BoDbQuery
{
	public $query;
	public $num_rows;
	
	function __construct() 
	{}

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

class BoDb
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

	public function query($sql)
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

?>