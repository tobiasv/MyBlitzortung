<?php


class BoData
{
	static $cache = array();
	static $bulk_save = false;
	static $query = false;
	static $search_string = "";

	public static function cache_load($name)
	{
	
	
	
	}
	
	public static function get($name, &$changed=0)
	{
		$sql = "SELECT data, UNIX_TIMESTAMP(changed) changed FROM ".BO_DB_PREF."conf WHERE name='".BoDb::esc($name)."'";
		$row = BoDb::query($sql)->fetch_object();
		$changed = $row->changed;
		$row->data = $row->data;
		self::$cache['data'][$name] = $row->data;
		
		return $row->data;
	}
	
	public static function get_all($search, $limit = 0)
	{
		if (!self::$query || self::$search_string != $search)
		{
			self::$search_string = $search;
			$sql = "SELECT name, data, UNIX_TIMESTAMP(changed) changed FROM ".BO_DB_PREF."conf WHERE name LIKE '".BoDb::esc($search)."'";
			
			if ($limit)
				$sql .= " LIMIT $limit";
			
			self::$query = BoDb::query($sql);
		}
	
		$row = self::$query->fetch_assoc();
		
		if (!$row || empty($row))
		{
			$query = false;
			return false;
		}

		self::$cache['data'][$row['name']] = $row['data'];
		
		return array('name' => $row['name'], 
			'data' => $row['data'], 
			'changed' => $row['changed']);
	}

	public static function set($name, $data)
	{
		//data hasn't changed
		if (isset(self::$cache['data'][$name]) && self::$cache['data'][$name] == $data)
			return true;
		
		$data = utf8_encode($data);
		$name_esc = BoDb::esc($name);
		
		//we don't need to ask the database whether row exists, if it's in cache
		// --> Maybe dangerous!??
		if (isset(self::$cache['data'][$name]))
		{
			$update = true;
		}
		else
		{
			$sql = "SELECT data, name FROM ".BO_DB_PREF."conf WHERE name='$name_esc'";
			$row = BoDb::query($sql)->fetch_object();
			$update = $row->name;
		}
		
		self::$cache['data'][$name] = $data;
		
		//insert data
		$data_esc = BoDb::esc($data);
		
		if (!$update)
			$sql = "INSERT INTO ".BO_DB_PREF."conf SET data='$data_esc', name='$name_esc'";
		elseif ($update && $row->data != $data)
			$sql = "UPDATE ".BO_DB_PREF."conf SET data='$data_esc' WHERE name='$name_esc'";
		else
			$sql = NULL; // no update necessary

		return $sql ? BoDb::query($sql) : true;
	}

	public static function update_add($name, $add)
	{
	
		//unset cache, as we cannot be sure about the value after update
		unset(self::$cache['data'][$name]);
	
	
	}

	public static function delete($name)
	{
		$sql = "DELETE FROM ".BO_DB_PREF."conf WHERE name='$name_esc'";
		unset(self::$cache['data'][$name]);
		return BoDb::query($sql);
	}

	
	public static function delete_all($search)
	{
		//delete whole cache, otherwise we have to check what was deleted
		self::$cache['data'] = array();
		
		$sql = "DELETE FROM ".BO_DB_PREF."conf WHERE name LIKE '$search'";
		return BoDb::query($sql);
	}

}




?>