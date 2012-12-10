<?php

namespace Wave;

class Config {

	private static $_writable = false;
	private static $_readable = true;
		
	private $_data = false;
    
    public function __construct($config){    		
    		
    	$config_data = include ($config);
    	$this->_data =  $this->loadFromArray($config_data);
    
    }

    public static function loadFromArray($array) {
		$return = new Config\Row();
		foreach ($array as $k => $v) {
			if (is_array($v)) {
				$return->$k = self::loadFromArray($v);
			} else {
				$return->$k = $v;
			}
		}
	
		return $return;
	}
	    
    
    public function __get($offset){
    	
    	return $this->_data->{$offset};
    
    }
    
    public static function get($file){
		
		static $configs;
		if (!isset($configs[$file])){
			//no special config - try to load file.
			$file_path = APP_ROOT . "config/$file.php";
		
			if(!file_exists($file_path))
				return null;
				
			$configs[$file] = new self($file_path);
		}
		return $configs[$file];
	}
	
}

namespace Wave\Config;

class Row implements \ArrayAccess {

    public function offsetSet($offset, $value) {
        $this->{$offset} = $value;
    }
    public function offsetExists($offset) {
        return isset($this->{$offset});
    }
    public function offsetUnset($offset) {
        unset($this->{$offset});
    }
    public function offsetGet($offset) {
        return isset($this->{$offset}) ? $this->{$offset} : null;
    }

}


?>