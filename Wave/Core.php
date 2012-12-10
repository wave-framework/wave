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
		
		require_once(WAVE_CORE_PATH . 'Autoload.php');
		Autoload::register();
				
		\Wave\Exception::register();
				
		include_once(WAVE_CORE_PATH . 'Enums.php');
		
		self::checkDependencies();
		Cache::init();
	}
	
	private static function checkDependencies(){
		
		$missing = array();
		
		$required_extensions = array();
		
		foreach($required_extensions as $ext){
			if(!extension_loaded($ext))
				$missing[] = $ext;
		}
		
		if(isset($missing[0]))	
			throw new Exception('Wave Framework requires the following extensions: '.implode(', ', $missing));
		else return true;
	}	
}
?>