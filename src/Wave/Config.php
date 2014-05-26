<?php

namespace Wave;

use Wave\Config\Row;

class Config {

	private static $base_directory = null;

    private static $resolver = array('\\Wave\\Config', 'defaultResolver');

    private $_data = false;
    
	public static function init($base_path){
		if(!is_readable($base_path)){
			throw new \Wave\Exception('Base config directory '.$base_path.' is not readable');
		}

		self::$base_directory = $base_path;
	}

    public function __construct($namespace){

    	$config_data = call_user_func(self::$resolver, $namespace);

    	$this->_data = new Row($config_data);
    }

    public function __get($offset){
    	return $this->_data->{$offset};
    }
    
    public static function get($namespace){
		static $configs;
		if (!isset($configs[$namespace])){
			$configs[$namespace] = new self($namespace);
		}
		return $configs[$namespace];
	}

    public static function setResolver(callable $resolver){
        self::$resolver = $resolver;
    }

    public static function defaultResolver($namespace){
        $file_path = self::$base_directory . "/$namespace.php";

        if(!is_readable($file_path))
            throw new \Wave\Exception("Could not load configuration file $namespace. Looked in $file_path");

        return include $file_path;
    }

}