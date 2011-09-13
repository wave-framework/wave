<?php

class Wave_Router_Action {
	
	private $baseurl; 	// the URL this route lives under (subdomain ususally)
	private $baseroute = '/'; // a URL to prepend to relative routes in this route
	
	private $routes = array();
	
	private $target_action;
	private $requires_level = array();
	private $response_methods = array();
	
	
	public function __construct(){
		$this->baseurl = self::getBaseURLFromConf('default');
		$this->response_methods = (array) Wave_Config::get('wave')->router->base->methods;
	}
	
	public function setBaseRoute($baseroute){
		$this->baseroute = $baseroute;
	}
		
	public function addRoute($methods, $route){
		// prepend the baseroute if the route is relative
		if(!isset($route[0]) || $route[0] !== '/')
			$route = $this->baseroute . $route;
		// trim the last / off the end if necessary
		if(substr($route, -1, 1) == '/')
			$route = substr($route, 0, -1);

		if(array_search(Wave_Method::ANY, $methods) !== false)
			$methods = Wave_Method::$ALL;
				
		foreach($methods as $method){
			$this->routes[] = $method . $route;
		}
	}
	public function getRoutes() { return $this->routes; }
	
	public function hasRoutes(){ return isset($this->routes[0]); }
	
	public function setAction($action){ $this->target_action = $action; }
	public function getAction() { return $this->target_action;}
	
	public function setRequiresLevel(array $levels, $inherit){
		$this->mergeArrays('requires_level', $levels, $inherit);
		return $this;
	}
	public function checkRequiredLevel($var_stack){
		if(!empty($this->requires_level)){
			$auth_obj = Wave_Auth::getIdentity();
			
			if($auth_obj instanceof Wave_IAuthable)
				return $auth_obj->hasAccess($this->requires_level, $var_stack);
			else
				return false;
		}
		else
			return true;
		
	}
	
	public function setRespondsWith(array $levels, $inherit){
		$this->mergeArrays('response_methods', $levels, $inherit);
		return $this;
	}
	public function getRespondsWith() { return $this->response_methods; }
	public function canRespondWith($method){
		return array_search($method, $this->response_methods) !== false;
	}
	
	public function setBaseURL($baseurl){
		$this->baseurl = self::getBaseURLFromConf($baseurl);
	}
	public function getBaseURL(){ return $this->baseurl; }
	
	private function mergeArrays($property, array $items, $merge){
		if($merge) $this->$property = $items + $this->$property;
		else $this->$property = $items;
	}
	
	private static function getBaseURLFromConf($profile){
		$profiles = Wave_Config::get('deploy')->profiles;
		if(!isset($profiles->$profile)){
			throw new Wave_Exception('BaseURL profile "'.$profile.'" is not defined in deploy configuration');
		}
		else 
			return $profiles->$profile->baseurl;
	}
	
}


?>