<?php


class Wave_Core {
	
	const MODE_DEVELOPMENT = 1;
	const MODE_PRODUCTION = 2;
	
	static $_MODE = self::MODE_PRODUCTION; 
	
	public static function bootstrap($mode = null){
			
		if($mode == null)
			$mode = Wave_Config::get('deploy')->mode;
		
		self::$_MODE = $mode;
		
		// when unserialising and an undefined class is encountered, call the spl_autoload_call method to 
		// trigger the autoloader
		ini_set('unserialize_callback_func', 'spl_autoload_call');
		
		spl_autoload_register(array('Wave_Core', 'autoload'));
		
		set_exception_handler(array('Wave_Exception', 'handle'));
		
		include_once(WAVE_CORE_PATH . 'Enums.php');
		
		self::checkDependencies();
		Wave_Cache::init();
	}
	
	private static function checkDependencies(){
		
		$missing = array();
		
		$required_extensions = array('mcrypt');
		
		foreach($required_extensions as $ext){
			if(!extension_loaded($ext))
				$missing[] = $ext;
		}
		
		if(isset($missing[0]))	
			throw new Wave_Exception('Wave Framework requires the following extensions: '.implode(', ', $missing));
		else return true;
	}
	
	/**
	* Function to autoload classes
	* 
	* @param $class Class required class
	*
	*/
	static function autoload($class){
		$search_paths = array();
	 	$skip_app = false;
	
	 	if (substr($class, 0, 5) === 'Wave_') {
			$filename = substr($class, 5);
			$search_paths[] = WAVE_CORE_PATH . strtr($filename, '_', DS).'.php';
			$skip_app = true;
		} else if(substr($class, -10) === 'Controller' && $class !== 'Base_Controller'){
			$search_paths[] = Wave_Config::get('wave')->path->controllers . strtr(substr($class, 0, -10), '_', DS) . '.php';
		} else if (0 === strpos($class, 'Twig')) {
           $path = Wave_Config::get('wave')->path->third_party . 'twig' . DS . 'lib' . DS;
           $path .= str_replace('_', '/', $class).'.php';
           $search_paths[] = $path;
        } else {
			$search_paths[] = Wave_Config::get('wave')->path->models . strtr($class, '_', DS) . '.php';
			$search_paths[] = Wave_Config::get('wave')->path->libraries . strtr($class, '_', DS) . '.php';
		}
		
		// application autoloading
		//if(!$skip_app && isset(cfg('main')->app->path->search)){
		//	foreach(cfg('main')->app->path->search as $path){
		//		$search_paths[] = strtr($path, array('%s' => strtr($class, '_', DS)));
		//	}
		//}	
		
		foreach ($search_paths as $search_path){
			if(file_exists($search_path) && include_once($search_path)){
				//debug()->addUsedFile($search_path, __FUNCTION__);
				return;
			}
		}

		//if still not found, try with alias for model
		if(Wave_Config::get('db') !== null){
			$alias_class = Wave_DB::get()->getNamespace().Wave_DB::NS_SEPARATOR.$class;
			$filename = Wave_Config::get('wave')->path->models . strtr($alias_class, '_', DS) . '.php';
			if(file_exists($filename) && include_once($filename)){
				class_alias($alias_class, $class);			
			}
		}
		
	}
	
	
	public static function encrypt($unencrypted, $key){
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
  		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
  		
  		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $unencrypted, MCRYPT_MODE_ECB, $iv));
	}
	
	public static function decrypt($encrypted, $key){
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
  		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
  		
  		return mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, base64_decode($encrypted), MCRYPT_MODE_ECB, $iv);
	}
	
}

if(!function_exists('cfg')){
	function cfg($file){
		return Wave_Config::get($file);
	}
}
?>