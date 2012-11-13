<?php



class Wave_Storage_Cookie extends Wave_Storage {


	public static function store($key, $data, $expires = 0, $path = '/', $domain = null, $secure = false, $httponly = true){
		if($domain === null) $domain = $_SERVER['SERVER_NAME'];
		return setcookie($key, $data, $expires, $path, $domain, $secure, $httponly);
	}
	
	public static function fetch($key){
		if (isset($_COOKIE[$key]))
			return $_COOKIE[$key];
		else
			trigger_error('Cookie '.$key.' is not set', E_USER_NOTICE);
	}
	
	public static function key_set($key){
		return isset($_COOKIE[$key]);
	}

}

?>