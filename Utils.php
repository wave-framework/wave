<?php


abstract class Wave_Utils {
	
	const DATE_FORMAT_MYSQL = 'Y-m-d H:i:s';
	
	public static function array_peek($arr){
		if(!is_array($arr)) return null;
		$element = array_pop($arr);
		array_push($arr, $element);
		return $element;
	}

	public static function redirect($uri, $profile = 'default', $permanent = false){
		if($permanent)
			header("Status: 302 Moved Permanently");
		
		if($profile !== null){
			$conf = Wave_Config::get('deploy')->profiles->$profile;
			$domain = $conf->baseurl;
			$protocol = 'http';
			if(isset($conf->ssl) && $conf->ssl == true)
				$protocol .= 's';
			$uri = $protocol . '://' . $domain . $uri;
		}

		header('Location: '.$uri);
	}
	
	public static function extractFromObjectArray($key, $objs){
		
		$extract = array();
		foreach($objs as $obj){
			if(isset($obj->$key))
				$extract[] = $obj->$key;
		}
		
		return $extract;
	}

	public static function shorten($string, $length = 20, $by_word = true, $elipsis = true){
		
		if(strlen($string) <= $length) return $string;
		
		$str = substr($string, 0, $length);
		
		if($by_word){
			$pos = strrpos($str, ' ');
			if($pos !== false)
				$str = substr($str, 0, $pos);
		}
		
		if($elipsis)
			$str .= '&hellip;';
		
		return $str;
		
	}

}


?>