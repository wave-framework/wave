<?php

class Wave_View {
	
	private $twig;
	
	private static $_filters = array();
	
	private static $instance = null;
	
	
	private function __construct(){
	
		$loader = new Twig_Loader_Filesystem(Wave_Config::get('wave')->path->views);
		
		$conf = array('cache' => Wave_Config::get('wave')->view->cache);
		if(Wave_Core::$_MODE == Wave_Core::MODE_DEVELOPMENT){
			$conf['auto_reload'] = true;
			$conf['debug'] = true;
		}
		$this->twig = new Wave_View_TwigEnvironment($loader, $conf);
		$this->twig->addExtension(new Wave_View_TwigExtension());
		foreach(self::$_filters as $name => $action)
			$this->twig->addFilter($name, $action);
		$this->twig->registerUndefinedFilterCallback(function ($name) {
		    if (function_exists($name)) {
		        return new Twig_Filter_Function($name);
		    }
		
		    return false;
		});
		$this->twig->addFilter('last', new Twig_Filter_Function('Wave_Utils::array_peek'));
		$this->twig->addFilter('short', new Twig_Filter_Function('Wave_Utils::shorten'));
		
		// global variables
		$this->twig->addGlobal('_assets', Wave_Config::get('deploy')->assets);
		//$this->twig->addGlobal('_protocol', Wave_Router::$protocol);
		$this->twig->addGlobal('_host', Wave_Config::get('deploy')->baseurl);
		$this->twig->addGlobal('_mode', Wave_Core::$_MODE);
	}
	
	public static function getInstance(){
		
		if(self::$instance === null)
			self::$instance = new self();
			
		return self::$instance;
	}
	
	
	public function render($template, $data = array()){
		
		// locate the template file
		$template .= Wave_Config::get('wave')->view->extension;
		
		$loaded_template = $this->twig->loadTemplate($template);
		
		return $loaded_template->render($data);
		
	}
	
	public static function registerFilter($filter, $action){
		self::$_filters[$filter] = $action;
	}
	
	public static function generate(){
		
		// delete caches		
		$dir_iterator = new RecursiveDirectoryIterator(Wave_Config::get('wave')->view->cache);
		$iterator = new RecursiveIteratorIterator($dir_iterator);
		foreach($iterator as $cache_file){
			@unlink ($cache_file);
		}
		$self = self::getInstance();
		
		$dir_iterator = new RecursiveDirectoryIterator(Wave_Config::get('wave')->path->views);
		$iterator = new RecursiveIteratorIterator($dir_iterator);
		$l = strlen(Wave_Config::get('wave')->path->views);
		foreach($iterator as $template){
			$i = pathinfo($template);
			if($i['extension'] != 'phtml') continue; 
			
			$self->twig->loadTemplate(substr($template, $l));
		}
		
		
		
		
	}

}



?>