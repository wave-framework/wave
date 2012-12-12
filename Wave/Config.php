<?php

namespace Wave;

class Config {

	private static $base_directory = null;

	private static $_writable = false;
	private static $_readable = true;
		
	private $_data = false;
    
	public static function init($base_path){
		if(!is_readable($base_path)){
			throw new \Wave\Exception('Base config directory '.$base_path.' is not readable');
		}

		self::$base_directory = $base_path;
	}

    public function __construct($config){
    	$config_data = include ($config);
    	$this->_data = $this->loadFromArray($config_data);
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
			$file_path = self::$base_directory . "/$file.php";
		
			if(!is_readable($file_path))
				throw new \Wave\Exception("Could not load configuration file $file. Looked in $file_path");
				
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