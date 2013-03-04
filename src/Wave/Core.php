<?php

namespace Wave;

class Core {
	
	const MODE_TEST			= 'test';
	const MODE_DEVELOPMENT 	= 'development';
	const MODE_PRODUCTION 	= 'production';
	
	static $_MODE = self::MODE_PRODUCTION;

	public static function bootstrap($mode = null){
			
		if($mode == null)
			$mode = Config::get('deploy')->mode;
		self::$_MODE = $mode;

        self::setErrorReporting($mode !== self::MODE_PRODUCTION);
		Cache::init();
	}

    public static function setErrorReporting($display = false){
        error_reporting($display ? E_ALL | E_STRICT : E_ALL & ~E_DEPRECATED);
        ini_set('display_errors', $display ? '1' : '0');
    }
}
?>