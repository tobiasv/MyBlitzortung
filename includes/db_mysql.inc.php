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


class BoDbQuery
{
	public $query;
	public $num_rows;
	
	function __construct() 
	{}

	public function DoQuery($sql, $dbh)
	{
		$this->query = mysql_query($sql, $dbh);
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
	public static function connect()
	{
		if(!is_null(self::$dbh))
		{
			return self::$dbh;
		}

		self::$dbh = mysql_connect(BO_DB_HOST, BO_DB_USER, BO_DB_PASS) or die("Database: Connect ERROR ");
		mysql_select_db(BO_DB_NAME, self::$dbh) or die ("Database not found");

		mysql_set_charset('latin1', self::$dbh) or die('Database: Charset ERROR');

		return self::$dbh;
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