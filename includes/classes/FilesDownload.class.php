<?php


class FilesDownload
{
	private $LastModified;
	private $LastByte;
	private $LastTime;
	
	public $NewModified;
	private $NewByte;
	private $NewTime;
	
	private $Type = '';
	private $Files = array();
	private $Lines = array();
	
	private $BaseURL = '';
	
	public $TimeStart = 0;
	public $TimeEnd = 0;
	public $FoundFiles = 0;
	
	public $NumFiles  = 0;
	public $NumErrors = 0;
	public $NumBytes  = 0;
	public $NumBytesFile  = 0;
	public $NumLines  = 0;

	public $LastLineLen = '';
	public $LastMessage = '';
	
	private $Cache = false;
	private $MaxAge = 0;
	
	public function __construct($type, $min_step, $url, $last_time = 0, $time_format = '/%Y/%m/%d/%H/%i.log', $default_range = 120)
	{
		$this->Type = $type;
		$this->BaseURL = $url;
		
		$tmp = unserialize(BoData::get($this->Type.'_dldata'));
		$this->LastModified = $tmp['last_file_modified'];
		$this->LastByte     = $tmp['last_file_bytes'];
		$this->LastTime     = $tmp['last_file_time'];
	
		if ($this->LastModified > time() || $this->LastModified <= 0)
			$this->LastModified = 0;

		if ($this->LastTime > time() || $this->LastTime <= 0)
			$this->LastTime = time() - $default_range*60;

		//time of data is newer than filetime? --> use time of data
		if (floor($last_time/60/$min_step) > floor($this->LastTime/60/$min_step))
		{
			$this->LastTime = $last_time;
		}
		
		//which files to download
		$this->TimeStart = $this->LastTime;
		$this->TimeEnd = time(); //Todo

		$this->TimeStart = floor($this->TimeStart/60/$min_step)*60*$min_step;
		$this->TimeEnd   = floor($this->TimeEnd/60/$min_step)*60*$min_step;
		
		for ($time=$this->TimeStart; $time<=$this->TimeEnd; $time+=60*$min_step)
		{
			if ( ((int)PHP_MAJOR_VERSION >= 5 && (int)PHP_MINOR_VERSION >= 3) || (int)PHP_MAJOR_VERSION >= 6)
			{
				$this->Files[$time] = preg_replace_callback('/%([A-Z])/i', function($matches) use($time) { 
					return gmdate($matches[1], $time);
				}, $time_format);
			}
			else
			{
				$this->Files[$time] = preg_replace('/%([A-Z])/ie', 'gmdate("\1", $time)', $time_format);
			}
		}
		
		$this->FoundFiles = count($this->Files);
	}
	
	public function __destruct()
	{
		$this->Close();
	}

	public function CacheEnable($max_age = 60)
	{
		$this->Cache = true;
		$this->MaxAge = $max_age;
	}
	
	public function Close()
	{
		if (!$this->Type)
			return;
		
		if ($this->Lines === false)
			$tmp['last_file_modified'] = $this->NewModified;
		else
			$tmp['last_file_modified'] = 0; //no all data has been read --> redownload next time
			
		$tmp['last_file_bytes']    = $this->NewByte + $this->NumBytesFile - $this->LastLineLen;
		$tmp['last_file_time']     = $this->NewTime;
		BoData::set($this->Type.'_dldata', serialize($tmp));
		
		$this->Type = '';
	}
	
	public function GetNextFile($as_array = true)
	{
		list($time, $url) = each($this->Files);
		
		if (!$time)
			return false;
		
		if ($url == false)
		{
			$this->Close();
			return false;
		}
		
		$this->NumFiles++;
		$this->LastLineLen = 0;
		
		$range = 0;
		$modified = 0;
	
		$this->LastMessage = "Downloading file $url";

		
		//send a last modified header for last downloaded file
		if ($time > 0 && $this->LastTime == $time)
		{
			$modified = $this->LastModified;
			$range = $this->LastByte;
			
			if ($modified)
				$this->LastMessage .= ", last modified ".gmdate("Y-m-d H:i:s", $modified);
			
			$this->LastMessage .= ", last position ".$range." bytes";
		}
		
		
		$url = $this->BaseURL.$url;
		
		//get the file
		$file = bo_get_file($url, $code, $this->Type, $range, $modified, $as_array);

		$this->NewModified = 0;
		$this->NewByte = 0;
		$this->NumBytesFile = 0;
		
		if ($file !== false && is_array($range) && !empty($range) && $this->LastByte)
		{
			//accepted range
			$this->NewModified = $modified;
			$this->NewByte = $this->LastByte;
			$this->NewTime = $time;
			$this->LastMessage .= ": OK";
		}
		else if ($file === false && $code == 304)
		{
			//Not Modified
			$file = null;
			$this->NewModified = $this->LastModified;
			$this->NewByte = $this->LastByte;
			$this->NewTime = $time;
			$this->LastMessage .= ": OK, not modified! ";
		}
		else if ($file === false)
		{
			//Some error
			//Try a second time without range
			if ($code != 404)
			{
				$range = 0;
				$file = bo_get_file($url, $code, $this->Type, $range, $modified, $as_array);
				$this->LastMessage .= ": Error ($code), 2nd try ";
			}
			
			if ($file === false && $code == 304)
			{
				$this->NewModified = $this->LastModified;
				$this->LastMessage .= ": OK, not modified! ";
				$file = null;
			}
			else if ($file === false)
			{
				$this->LastMessage .= ": Error ($code).";
				$this->NumErrors++;

				//set entry to last working file
				$this->NewModified = $this->LastModified;
				$this->NewByte = $this->LastByte;
				
				return null;
			}
			
			$this->NewTime = $time;
		}
		else
		{
			//didn't accept range 
			//or download without range
			
			$this->LastMessage .= ": OK";
			
			if ($this->LastByte && $this->LastTime == $time)
				$this->LastMessage .= ", but got whole file";

			$this->NewModified = $modified;
			$this->NewTime = $time;
		}
		
		return $file;

	}
	
	public function GetNextLine()
	{
		if (!is_array($this->Lines))
			$line = null;
		else
			$line = array_shift($this->Lines);
		
		
		if ($line === null)
		{
			while ( ($this->Lines = $this->GetNextFile(true)) === null)
			{};
			
			//no more files
			if ($this->Lines === false)
				return false;
			
			$line = array_shift($this->Lines);
			$this->NumBytesFile = 0;
		}

		$this->LastLineLen = strlen($line);
		
		$this->NumLines++;
		$this->NumBytes += $this->LastLineLen;
		$this->NumBytesFile += $this->LastLineLen;
		
		return $line;
	}

}


?>