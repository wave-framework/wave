<?php

class Wave_Cache {
	
	private static $_ready = false;
	private static $_cachepath = null;
	
	public static function init(){
		
		self::$_cachepath = cfg('wave')->path->cache;
		self::$_ready = true;
		
	}
	
	public static function load($key){
		$filename = self::$_cachepath . $key;
		if(file_exists($filename))
			return unserialize(file_get_contents(self::$_cachepath . $key));
		else 
			return null;
	}
	
	public static function store($key, $data){
		if(!file_exists(self::$_cachepath))
			@mkdir(self::$_cachepath, 0770, true);
		file_put_contents(self::$_cachepath . $key, serialize($data));
	}
	
	public static function delete($key){
		@unlink(self::$_cachepath . $key);
	}
	
	public static function cachetime($key){
		$filename = self::$_cachepath . $key;
		if(file_exists($filename))
			return filemtime(self::$_cachepath . $key);
		else 
			return 0;
	}

}