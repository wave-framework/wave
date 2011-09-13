<?php


class Wave_Core {
	
	const MODE_TEST			= 0;
	const MODE_DEVELOPMENT 	= 1;
	const MODE_PRODUCTION 	= 2;
	
	static $_MODE = self::MODE_PRODUCTION; 
	
	public static function bootstrap($mode = null){
			
		if($mode == null)
			$mode = Wave_Config::get('deploy')->mode;
		self::$_MODE = $mode;
		
		require_once(WAVE_CORE_PATH . 'Autoload.php');
		Wave_Autoload::register();
				
		Wave_Exception::register();
				
		include_once(WAVE_CORE_PATH . 'Enums.php');
		
		self::checkDependencies();
		Wave_Cache::init();
	}
	
	private static function checkDependencies(){
		
		$missing = array();
		
		$required_extensions = array();
		
		foreach($required_extensions as $ext){
			if(!extension_loaded($ext))
				$missing[] = $ext;
		}
		
		if(isset($missing[0]))	
			throw new Wave_Exception('Wave Framework requires the following extensions: '.implode(', ', $missing));
		else return true;
	}	
}
?>