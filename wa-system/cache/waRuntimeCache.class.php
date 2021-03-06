<?php

class waRuntimeCache implements waiCache
{
	
	protected $key;
	protected static $cache = array();
	
	public function __construct($key, $ttl = 0)
	{
		$this->key = $key;
	}
	
	public function get()
	{
		return isset(self::$cache[$this->key]) ? self::$cache[$this->key] : null; 
	}
	
	public function set($value)
	{
		self::$cache[$this->key] = $value;
	}
	
	public function delete()
	{
		if (isset(self::$cache[$this->key])) {
			unset(self::$cache[$this->key]);
		}
		return true;
	}
	
	public function isCached()
	{
		return isset(self::$cache[$this->key]);
	}
}