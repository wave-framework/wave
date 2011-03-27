<?php


abstract class Wave_Utils {

	public static function array_peek($arr){
		if(!is_array($arr)) return null;
		$element = array_pop($arr);
		array_push($arr, $element);
		return $element;
	}

	public static function redirect($uri, $permanent = false){
		if($permanent)
			header("Status: 302 Moved Permanently");
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


}


?>