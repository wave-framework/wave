<?php

namespace Wave;

class View {
	
	private $twig;
	
	private static $_filters = array();
	private static $_globals = array();
	
	private static $instance = null;
	
	
	private function __construct(){
	
		$loader = new \Twig_Loader_Filesystem(Config::get('wave')->path->views);
		
		$conf = array('cache' => Config::get('wave')->view->cache);
		if(Core::$_MODE == Core::MODE_DEVELOPMENT){
			$conf['auto_reload'] = true;
			$conf['debug'] = true;
		}
		$this->twig = new View\TwigEnvironment($loader, $conf);
		$this->twig->addExtension(new View\TwigExtension());
		foreach(self::$_filters as $name => $action)
			$this->twig->addFilter($name, $action);
		$this->twig->registerUndefinedFilterCallback(function ($name) {
		    if (function_exists($name)) {
		        return new \Twig_Filter_Function($name);
		    }
		
		    return false;
		});
		$this->twig->addFilter('last', new \Twig_Filter_Function('Utils::array_peek'));
		$this->twig->addFilter('short', new \Twig_Filter_Function('Utils::shorten', array('pre_escape' => 'html', 'is_safe' => array('html'))));

		// global variables
		$this->twig->addGlobal('_assets', Config::get('deploy')->assets);
		//$this->twig->addGlobal('_protocol', Router::$protocol);
		$this->twig->addGlobal('_host', Config::get('deploy')->baseurl);
		$this->twig->addGlobal('_mode', Core::$_MODE);
		
		if(Config::get('deploy')->mode == Core::MODE_DEVELOPMENT || isset($_REQUEST['_wave_show_debugger']))
			$this->twig->addGlobal('_debugger', Debug::getInstance());
		
		foreach(self::$_globals as $key => $value)
			$this->twig->addGlobal($key, $value);
		
	}
	
	public static function getInstance(){
		
		if(self::$instance === null)
			self::$instance = new self();
			
		return self::$instance;
	}
	
	
	public function render($template, $data = array()){
		
		// locate the template file
		$template .= Config::get('wave')->view->extension;
		
		$loaded_template = $this->twig->loadTemplate($template);
		
		return $loaded_template->render($data);
		
	}
	
	public static function registerFilter($filter, $action){
		if(self::$instance == null) self::$_filters[$filter] = $action;
		else self::$instance->twig->addFilter($filter, $action);
	}
	
	public static function registerGlobal($name, $value){
		if(self::$instance == null) self::$_globals[$name] = $value;
		else self::$instance->twig->addGlobal($name, $value);
		
	}
	
	public static function generate(){
		
		$cache_dir = Config::get('wave')->view->cache;
		if(!file_exists($cache_dir))
			@mkdir($cache_dir, 0770, true);

		if(!file_exists($cache_dir))
			throw new Exception('Could not generate routes, the cache directory does not exist or is not writable');

		// delete caches		
		$dir_iterator = new \RecursiveDirectoryIterator($cache_dir);
		$iterator = new \RecursiveIteratorIterator($dir_iterator);
		foreach($iterator as $cache_file){
			@unlink ($cache_file);
		}
		$self = self::getInstance();
		
		$dir_iterator = new \RecursiveDirectoryIterator($cache_dir);
		$iterator = new \RecursiveIteratorIterator($dir_iterator);
		$l = strlen($cache_dir);
		foreach($iterator as $template){
			$i = pathinfo($template);
			if($i['extension'] != 'phtml') continue; 
			
			$self->twig->loadTemplate(substr($template, $l));
		}
		
		
		
		
	}

}



?>