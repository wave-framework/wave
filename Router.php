<?php



class Wave_Router {

	private static $root;
	
	public $request_method;
	public $request_uri;
	
	public $response_method;
	
	public function __construct($host){	
		self::$root = cfg('routes/'.$host);
	}
	
	public static function init($host = null){
		if($host === null){
			$host = $_SERVER['HTTP_HOST'];
		}
		return new self($host);
	}
	
	public function route($url = null, $method = null){
		
		if($url === null){
			if(isset($_SERVER['PATH_INFO']))
				$this->request_uri = substr($_SERVER['PATH_INFO'], strpos($_SERVER['PATH_INFO'], '.php/'));
			else
				$this->request_uri = $_SERVER['REQUEST_URI'];
		}
		else 
			$this->request_uri = $url;
		
		// trim off any query string parameters etc
		$qs = strpos($this->request_uri, '?');
		if($qs !== false){
			$this->request_uri = substr($this->request_uri, 0, $qs);
		}
		
		// remove the trailing slash and replace with the default response method if required
		if(substr($this->request_uri, -1, 1) == '/'){
			$trimmed = substr($this->request_uri, 0, -1);
			$this->request_uri =  $trimmed . '.' . Wave_Config::get('wave')->controller->default_response;
		}
		
		// deduce the response method
		$path = pathinfo($this->request_uri);
		if(isset($path['extension']) && in_array($path['extension'], Wave_Response::$ALL)){
			$this->response_method = $path['extension'];
			// remove the response method from the url, we dont need it here
			$this->request_uri = substr($this->request_uri, 0, -(strlen($this->response_method)+1));
		}
		else $this->response_method = Wave_Config::get('wave')->controller->default_response;
		// put the response method onto the controller class in case we need it for exceptions
		Wave_Controller::_setResponseMethod($this->response_method);
		
		if($method === null)			
			$this->request_method = $_SERVER['REQUEST_METHOD'];
		else
			$this->request_method = $method;
			
			
		$this->findRoute($this->request_method.$this->request_uri);
	}

	public function findRoute($url){
		$parts = explode('/', $url);
		$parts_count = count($parts);
		$pos = 0;
		$node = self::$root;
		
		$var_stack = array();
		
		while($pos < $parts_count){
			$child = $node->getChild($parts[$pos]);
			if($child === null)
				break;
			if($child->isVarNode()){
				$var_stack[$child->getVarName()] = $parts[$pos];
			}
			$node = $child;
			$pos++;
		}
				
		if($pos == $parts_count && $node !== null && $node->isLeaf()){	
			$destination = $node->getDestination();
			$auth_obj = Wave_Auth::getIdentity();			
						
			if(!in_array($this->response_method, $destination['respondswith'])){
				throw new Wave_Exception(
					'The requested action '.$destination['action'].
					' can not respond with '.$this->response_method.
					'. (Accepts: '.implode(', ', $destination['respondswith']).')');
			}
			else if($destination['requireslevel'] !== null && Wave_Auth::$_is_loaded){
				if(!($auth_obj instanceof Wave_IAuthable) || !$auth_obj->hasAccess($destination['requireslevel'], $var_stack)){
					$auth_class = Wave_Auth::getHandlerClass();
					
					if(!$auth_class::noAuthAction(array(
						'destination' => $destination,
						'auth_obj' => $auth_obj
					)))
						throw new Wave_Exception(
							'The current user does not have the required level to access this page', 403);
				}
			}
												
			Wave_Controller::invoke($destination['action'], $var_stack, $this);
			
		}
		else {
			throw new Wave_Exception('The requested URL does not exist', 404);
		}
	}

}

?>