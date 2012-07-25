<?php


class BoData
{
	static $cache = array();
	static $bulk_save = false;
	static $query = false;
	static $search_string = "";
	static $do_cache = false;
	static $do_write_on_exit = false;

	public static function get($name, &$changed=0)
	{
		if (isset(self::$cache['data'][$name]))
			return self::$cache['data'][$name];
		
		$sql = "SELECT data, UNIX_TIMESTAMP(changed) changed FROM ".BO_DB_PREF."conf WHERE name='".BoDb::esc($name)."'";
		$row = BoDb::query($sql)->fetch_assoc();
		$changed = $row['changed'];
		self::uncompress($row['data']);
		
		if (self::$do_cache)
			self::$cache['data'][$name] = $row['data'];
		
		return $row['data'];
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
		
		self::uncompress($row['data']);
		
		if (self::$do_cache)
			self::$cache['data'][$row['name']] = $row['data'];
		
		return array('name' => $row['name'], 
			'data' => $row['data'], 
			'changed' => $row['changed']);
	}

	public static function set($name, $data, $is_binary = false, $ignore_cache = false)
	{
		//data hasn't changed
		if (!$ignore_cache && isset(self::$cache['data'][$name]) && self::$cache['data'][$name] == $data)
			return true;

		$data_enc = $is_binary ? $data : utf8_encode($data);
		self::compress($data_enc);
		$data_esc = BoDb::esc($data_enc);
		$name_esc = BoDb::esc($name);
		
		$low_prio = BO_DB_UPDATE_LOW_PRIORITY ? "LOW_PRIORITY" : "";
		$sql = "REPLACE $low_prio INTO ".BO_DB_PREF."conf SET data='$data_esc', name='$name_esc'";
		$ok = BoDb::query($sql);
		
		if ($ok && self::$do_cache)
		{
			self::$cache['data'][$name] = $data;
			unset(self::$cache['delay'][$name]);
		}
			
		return $ok;
	}

	public static function update_add($name, $add)
	{
		if ($add == 0)
			return true;
		
		//unset cache, as we cannot be sure about the value after update
		unset(self::$cache['data'][$name]);
	
		$name_esc = BoDb::esc($name);
		$low_prio = BO_DB_UPDATE_LOW_PRIORITY ? "LOW_PRIORITY" : "";
		$sql = "UPDATE $low_prio ".BO_DB_PREF."conf SET data=data+$add WHERE name='$name_esc'";
		$ok = BoDb::query($sql, false);
		
		if ($ok === false) //!
		{
			//update failed, try insert
			$ok = self::set($name, $add);
		}
		
		return $ok;
	}

	public static function update_if($name, $value, $if)
	{
		//unset cache, as we cannot be sure about the value after update
		unset(self::$cache['data'][$name]);
	
		$name_esc = BoDb::esc($name);
		$low_prio = BO_DB_UPDATE_LOW_PRIORITY ? "LOW_PRIORITY" : "";
		$sql = "UPDATE $low_prio ".BO_DB_PREF."conf SET data='$value' WHERE name='$name_esc' AND data $if";
		$ok = BoDb::query($sql, false);
		
		if ($ok === false) //!
		{
			//update failed, try insert
			$ok = self::set($name, $value);
		}
		
		return $ok;
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
	
	
	
	
	
	/************* Delayed Writes **************/
	// if multipe ::set with same name have been called, only the last one is used
	
	public static function set_delayed($name, $data, $is_binary = false)
	{
		//data hasn't changed
		if (isset(self::$cache['data'][$name]) && self::$cache['data'][$name] == $data)
			return true;
	
		if (!self::$do_write_on_exit)
		{
			register_shutdown_function('BoData::write_shutdown');
			self::$do_write_on_exit = true;
		}
		
		self::$cache['data'][$name] = $data;
		self::$cache['delay'][$name] = true;
		self::$cache['is_binary'][$name] = $is_binary;
	}
	
	public static function write_shutdown()
	{
		if (is_array(self::$cache['delay']))
		{
			foreach(self::$cache['delay'] as $name => $data)
			{
				if (isset(self::$cache['data'][$name]))
				{
					self::set($name, self::$cache['data'][$name], self::$cache['is_binary'][$name], true);
				}
			}
		}
	
	}
	
	
	
	/************* Compression **************/
	
	public static function compress(&$data)
	{
		//never compress numbers or short text!
		if (BO_DB_COMPRESSION === true && strlen($data) > 100)
		{
			$zdata = gzcompress($data);
			
			if ($zdata)
				$data = $zdata;
		}
	}

	public static function uncompress(&$data)
	{
		//check if it is compressed data
		if (ord($data[0]) == 120 && ord($data[1]) == 156)
		{
			$udata = gzuncompress($data);
			
			if ($udata)
				$data = $udata;
		}
	}

}




?>