<?php


class Wave_Autoload {
	
	
	public static function register(){
		// when unserialising and an undefined class is encountered, call the spl_autoload_call method to 
		// trigger the autoloader
		ini_set('unserialize_callback_func', 'spl_autoload_call');
		spl_autoload_register(array(new self, 'autoload'));
		
		include_once WAVE_CORE_PATH . 'Enums.php';
	}
	
	/**
	* Function to autoload classes
	* 
	* @param $class Class required class
	*
	*/
	static public function autoload($class){
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
        } else if (0 === strpos($class, 'Event_')) {
           $path = Wave_Config::get('wave')->path->events;
           $path .= substr(str_replace('_', DS, $class), 6).'.php';
           $search_paths[] = $path;
        } else {
			$search_paths[] = Wave_Config::get('wave')->path->models . strtr($class, '_', DS) . '.php';
			$search_paths[] = Wave_Config::get('wave')->path->libraries . strtr($class, '_', DS) . '.php';
		}
			
		foreach ($search_paths as $search_path){
			if(file_exists($search_path) && include_once($search_path)){
				Wave_Debug::getInstance()->addUsedFile($search_path, __FUNCTION__);
				return;
			}
		}

		//if still not found, try with alias for model
		if(Wave_DB::get() !== null){
			$alias_class = Wave_DB::get()->getNamespace().Wave_DB::NS_SEPARATOR.$class;
			$filename = Wave_Config::get('wave')->path->models . strtr($alias_class, '_', DS) . '.php';
			if(file_exists($filename) && include_once($filename)){
				Wave_Debug::getInstance()->addUsedFile($filename, __FUNCTION__);
				class_alias($alias_class, $class);			
			}
		}
		
	}
	
}

?>