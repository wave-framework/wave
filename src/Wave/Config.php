<?php

namespace Wave;

use Wave\Config\Row;

class Config {

	private static $base_directory = null;
		
	private $_data = false;
    
	public static function init($base_path){
		if(!is_readable($base_path)){
			throw new \Wave\Exception('Base config directory '.$base_path.' is not readable');
		}

		self::$base_directory = $base_path;
	}

    public function __construct($config){
    	$config_data = include ($config);
    	$this->_data = new Row($config_data);
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

?>