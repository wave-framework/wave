<?php

namespace Wave\Router;



use Wave\Auth;
use Wave\Config;
use Wave\Exception;
use Wave\Http\Request;
use Wave\IAuthable;
use Wave\Method;

class Action {

    private $profile; // the URL profile this route lives under
	private $baseurl; 	// the URL this route lives under (subdomain ususally)
	private $baseroute = '/'; // a URL to prepend to relative routes in this route
	
	private $routes = array();
	
	private $target_action;
	private $requires_level = array();
	private $response_methods = array();

    private $validation_schema = null;

    public static function getDefaultAction($action_method){
        $action = new Action();
        $action->setAction($action_method);

        return $action;
    }

	public function __construct(){
        $this->setProfile('default');
		$this->response_methods = (array) Config::get('wave')->router->base->methods;
	}
	
	public function setBaseRoute($baseroute){
		$this->baseroute = $baseroute;
		if(substr($this->baseroute, -1, 1) !== '/')
			$this->baseroute .= '/';
	}
		
	public function addRoute($methods, $route){
		// prepend the baseroute if the route is relative
		if(!isset($route[0]) || $route[0] !== '/')
			$route = $this->baseroute . $route;
		// trim the last / off the end if necessary
		if(substr($route, -1, 1) == '/')
			$route = substr($route, 0, -1);

		if(array_search(Method::ANY, $methods) !== false)
			$methods = Method::$ALL;
				
		foreach($methods as $method){
			$this->routes[] = $method . $route;
		}
	}
	public function getRoutes() { return $this->routes; }
	
	public function hasRoutes(){ return isset($this->routes[0]); }
	
	public function setAction($action){ $this->target_action = $action; }
	public function getAction() { return $this->target_action; }
	
	public function setRequiresLevel(array $levels, $inherit){
		$this->mergeArrays('requires_level', $levels, $inherit);
		return $this;
	}
	public function checkRequiredLevel(Request $request){
		if(!empty($this->requires_level)){
			$authorization = $request->getAuthorization();
            if($authorization instanceof Request\AuthorizationAware)
                return $authorization->hasAuthorization($this->requires_level, $request);
            elseif(is_callable($authorization))
                return $authorization($this->requires_level, $request);
            else
                throw new \InvalidArgumentException('Can not determine required level without authorization function');
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
        foreach($this->response_methods as $allowed){
            if($allowed === '*' || $allowed === $method){
                return true;
            }
        }
		return false;
	}

    public function setProfile($profile){
        $profiles = Config::get('deploy')->profiles;
        if(!isset($profiles->$profile)){
            throw new Exception('BaseURL profile "'.$profile.'" is not defined in deploy configuration');
        }
        else {
            $this->profile = $profile;
            $this->setBaseURL($profiles->$profile->baseurl);
        }
    }
    public function getProfile() { return $this->profile; }

	public function setBaseURL($baseurl){
		$this->baseurl = $baseurl;
	}
	public function getBaseURL(){ return $this->baseurl; }
	
	private function mergeArrays($property, array $items, $merge){
		if($merge) $this->$property = $items + $this->$property;
		else $this->$property = $items;
    }

    public function setValidationSchema($schema) {
        $this->validation_schema = $schema;
    }

    public function getValidationSchema() {
        return $this->validation_schema;
    }

    public function needsValidation(){
        return $this->validation_schema !== null;
    }


}


?>