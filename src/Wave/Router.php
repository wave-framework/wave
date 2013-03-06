<?php

namespace Wave;

use Wave\Http\Request;

class Router {

	private static $root;
	
	public $request_method;
	public $request_uri;
	public $profile;
	
	public $response_method;

    /** @var \Wave\Http\Request */
    public $request;

    public function __construct($host){
		self::$root = $this->loadRoutesCache($host);
	}
	
	public static function init($host = null){
		Hook::triggerAction('router.before_init', array(&$host));
		if($host === null){
			if(isset($_SERVER['HTTP_HOST']))
				$host = $_SERVER['HTTP_HOST'];
			else 
				$host = Config::get('deploy')->profiles->default->baseurl;
		}
		$instance = new self($host);
		Hook::triggerAction('router.after_init', array(&$instance));
		return $instance;
	}
	
	public function route(Request $request = null){

        if(null === $request)
            $request = Request::createFromGlobals();

        $this->request = $request;

		$this->request_uri = $request->getPath();
        $this->request_method = $request->getMethod();

		// deduce the response method
		$path = pathinfo($this->request_uri);
		if(isset($path['extension']) && in_array($path['extension'], Response::$ALL)){
			$this->response_method = $path['extension'];
            $this->request_uri = substr($this->request_uri, 0, -(strlen($this->response_method)+1));
        }
		else
            $this->response_method = Config::get('wave')->controller->default_response;
		
		if(Exception::$_response_method === null)
			Exception::$_response_method = $this->response_method;

		Hook::triggerAction('router.before_routing', array(&$this));
		return $this->findRoute($this->request_method.$this->request_uri);
	}

	public function findRoute($url){

		$var_stack = array();
		$node = self::$root->findChild($url, $var_stack);

        /** @var \Wave\Router\Action $action */
		if($node instanceof Router\Node && $action = $node->getAction()){
			
			if(!$action->canRespondWith($this->response_method)){
				throw new Exception(
					'The requested action '.$action->getAction().
					' can not respond with '.$this->response_method.
					'. (Accepts: '.implode(', ', $action->getRespondsWith()).')');
			}
			elseif(!$action->checkRequiredLevel($var_stack)){
					
				$auth_obj = Auth::getIdentity();
				$auth_class = Auth::getHandlerClass();

				if(!in_array('Wave\IAuthable', class_implements($auth_class)))
					throw new Exception('A valid Wave\IAuthable class is required to use RequiresLevel annotations', 500);
				
				if(!$auth_class::noAuthAction(array(
					'destination' => $action,
					'auth_obj' => $auth_obj,
					'args' => $var_stack
				)))
					throw new Exception(
						'The current user does not have the required level to access this page', 403);
			}
			Hook::triggerAction('router.before_invoke', array(&$action, &$var_stack, &$this));
			return Controller::invoke($action->getAction(), array(
                'request' => $this->request,
                'router' => $this,
                'data' => $var_stack,
                'validation_schema' => $action->getValidationSchema()
            ));
		}
		else
			throw new Exception('The requested URL '.$url.' does not exist', 404);
	}
	
	public function loadRoutesCache($host){
		$profiles = Config::get('deploy')->profiles;
        if(isset($profiles->$host)){
            $this->profile = $host;
            $host = $profiles->$host->baseurl;
        }
        else {
            foreach($profiles as $name => $profile){
                if($profile->baseurl == $host){
                    $this->profile = $name;
                    break;
                }
            }
        }

		$routes = Cache::load(self::getCacheName($host));
		if($routes == null){
			$defaultdomain = $profiles->default->baseurl;
			$routes = Cache::load(self::getCacheName($defaultdomain));
		}
		
		if($routes == null)
			throw new Exception('Could not load routes for domain: '.$host.' nor default domain: '.$defaultdomain);
		else
			return $routes;
	}
	
	public static function getCacheName($host){
		return 'routes/'.md5($host);
	}

}

?>