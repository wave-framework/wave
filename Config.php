<?php


class Wave_Config {

	private static $_writable = false;
	private static $_readable = true;
		
	private $_data = false;
    
    public function __construct($config){    		
    		
    	$config_data = include ($config);
    	$this->_data =  $this->loadFromArray($config_data);
    
    }

    public static function loadFromArray($array) {
		$return = new Wave_Config_Row();
		foreach ($array as $k => $v) {
			if (is_array($v)) {
				$return->$k = Wave_Config::loadFromArray($v);
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
				
			$configs[$file] = new Wave_Config($file_path);
		}
		return $configs[$file];
	}
	
	
	public static function buildRoutes($controllers){		
		trigger_error('Wave_Router::buildRoutes() is depreciated. Use Wave_Router_Generator::buildRoutes() instead', E_USER_DEPRECATED);
		return Wave_Router_Generator::buildRoutes($controllers);
	}
	
	
}

class Wave_Config_Row implements ArrayAccess {

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