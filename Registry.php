<?php



class Wave_Registry {

	private static $store = array();

	public static function store($key, $value){
		
		self::$store[$key] = $value;
		
		return self::$store[$key];
	}
	
	public static function fetch($key){
		
		if(isset(self::$store[$key]))
			return self::$store[$key];
		else
			return null;
		
	}
	
	public static function destroy($key){
		if(isset(self::$store[$key])){
			unset(self::$store[$key]);
			return true;
		}
		return false;
	}
	
	public static function _isset($key){
		return isset(self::$store[$key]);
	}
	
}


?>