<?php

class Wave_Cache {
	
	private static $_ready = false;
	private static $_cachepath = null;
	
	public static function init(){
		self::$_cachepath = Wave_Config::get('wave')->path->cache;
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
		$path = self::$_cachepath . $key;
		$dir = dirname(self::$_cachepath . $key);
		if(!is_dir($dir))
			@mkdir($dir, 0770, true);
		file_put_contents($path, serialize($data));
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