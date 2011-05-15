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
	public static function connect()
	{
		if(!is_null(self::$dbh) && self::$dbh->ping())
		{
			return self::$dbh;
		}

		self::$dbh = new mysqli(BO_DB_HOST, BO_DB_USER, BO_DB_PASS, BO_DB_NAME);

		if (mysqli_connect_error())
				die('Database: Connect ERROR (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		self::$dbh->set_charset('latin1') or die('Database: Charset ERROR');

		return self::$dbh;
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
