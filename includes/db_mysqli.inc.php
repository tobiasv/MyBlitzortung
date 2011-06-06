<?php

/*
    MyBlitzortung - a tool for participants of blitzortung.org
	to display lightning data on their web sites.

    Copyright (C) 2011  Tobias Volgnandt
    Copyright (C) 2011  Ingmar Runge

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
		if(!is_null(self::$dbh) && self::$dbh->ping())
		{
			return self::$dbh;
		}

		self::$dbh = new mysqli(BO_DB_HOST, BO_DB_USER, BO_DB_PASS, "", BO_DB_PORT);

		if (mysqli_connect_error() && $die_on_error)
			die('Database: Connect ERROR (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		if ($prepare_all)
		{
			self::select_db();
			self::set_charset();
		}
			
		return self::$dbh;
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

	public function query($sql)
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
