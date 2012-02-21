<?php

/*
 * Database Class for MyBlitzortung
 */


/*
 * Select connection method
 * Todo: User-configurable
 */

if (class_exists('mysqli'))
	require_once 'Db/Mysqli.class.php';
else
	require_once 'Db/Mysql.class.php';



/* Main Database class */
class BoDb extends BoDbMain
{

	/*
	 * Connect, send query, return result or id or rows according to
	 * query. Automatic error handling.
	 */
	public static function query($query = '', $die_on_errors = true)
	{
		if (!$query)
			return self::$dbh;

		$qtype = strtolower(substr(trim($query), 0, 6));
		$result = self::do_query($query);

		if ($result === false)
		{
			if ($die_on_errors !== false)
				echo("<p>Database Query Error:</p><pre>" . htmlspecialchars(self::error()) .
					"</pre> <p>for query</p> <pre>" . htmlspecialchars($query) . "</pre>");

			if ($die_on_errors === true)
				die();
		}

		switch ($qtype)
		{
			case 'insert':
				return self::insert_id();

			case 'replace':
			case 'delete':
			case 'update':
				$rows = self::affected_rows();
				return $rows == -1 ? false : $rows;

			default:
				return $result;
		}

	}

}




?>