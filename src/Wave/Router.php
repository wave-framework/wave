<?php

namespace Wave;

use Wave\Http\Exception\NotFoundException;
use Wave\Http\Request;
use Wave\Http\Response;

class Router {

	private static $root;
	
	public $request_method;
	public $request_uri;
	public $profile;
	
	public $response_method;

    /** @var \Wave\Http\Request $request */
    protected $request;

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

    /**
     * @param Request  $request
     *
     * @throws \LogicException
     * @throws Http\Exception\NotFoundException
     * @return Response
     */
    public function route(Request $request = null){

        if(null === $request)
            $request = Request::createFromGlobals();

        $this->request = $request;

        $this->request_uri = $request->getPath();
        if(strrpos($this->request_uri, $request->getFormat()) !== false){
            $this->request_uri = substr($this->request_uri, 0, -(strlen($request->getFormat())+1));
        }
        $this->request_method = $request->getMethod();

        Hook::triggerAction('router.before_routing', array(&$this));

        $url = $this->request_method.$this->request_uri;
        $node = self::$root->findChild($url, $this->request);

        /** @var \Wave\Router\Action $action */
        if($node instanceof Router\Node && $action = $node->getAction()){
            Hook::triggerAction('router.before_invoke', array(&$action, &$this));
            $this->response = Controller::invoke($action, $this->request);
            if(!($this->response instanceof Response)){
                throw new \LogicException("Action {$action->getAction()} should return a \\Wave\\Http\\Response object", 500);
            }
            else {
                return $this->response->prepare($this->request);
            }
        }
        else
            throw new NotFoundException('The requested URL '.$url.' does not exist', $this->request);
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

    /**
     * @return \Wave\Http\Request
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * @param \Wave\Http\Request $request
     */
    public function setRequest($request) {
        $this->request = $request;
    }
}

?>