<?php

namespace Wave;

use Wave\Http\Request;
use Wave\Http\Response;
use Wave\Router\Action;

class Exception extends \Exception {
	
	private static $_controller = null;
	public static $_response_method = null;

    /** @var \Wave\Http\Request $request */
    private static $request;
    /** @var \Wave\Http\Response $response */
    private static $response;
	
	public static function register($controller){
		if(!class_exists($controller) || !is_subclass_of($controller, '\\Wave\\Controller')){
			throw new \Exception("Controller $controller must be an instance of \\Wave\\Controller");
		}
		self::$_controller = $controller;
		set_exception_handler(array('\\Wave\\Exception', 'handle'));

        Hook::registerHandler('router.before_routing', function(Router $router){
            if(Exception::$_response_method === null)
                Exception::$_response_method = $router->getResponse()->getFormat();

            Exception::setRequest($router->getRequest());
            Exception::setResponse($router->getResponse());
        });

	}
	
	public function __construct($message, $code = null){
		if($code == null && is_numeric($message)){
			$code = intval($message);
			$message = $this->getInternalMessage($code);
		}
		parent::__construct($message, $code);	
	}
	
	public static function handle(\Exception $e){
		$log_message = sprintf('%-4s %s', "({$e->getCode()})", $e->getMessage());
		// get the channel manually so the introspection works properly.
		Log::getChannel('exception')->addRecord(Log::ERROR, $log_message);

        $request = static::$request;
        if($request === null)
            $request = Request::createFromGlobals();

        $response = static::$response;
        if($response === null)
            $response = Response::createFromRequest($request);

		$response = Controller::invoke(self::getRouterAction(), $request, $response, array('exception' => $e));

        $response->prepare($request)->send();
	}
	
	protected function getInternalMessage($type){
		
		switch($type){
			case Response::STATUS_NOT_FOUND : 
				return 'The requested resource was not found';
			case Response::STATUS_FORBIDDEN : 
				return 'You do not have permission to access the requested resource';
			case Response::STATUS_UNAUTHORISED :  
				return 'Authorisation is required to complete this action';
			case Response::STATUS_OK :
				return 'Request OK';
			case Response::STATUS_BAD_REQUEST :
				return 'Bad request format or the format was not understood';
			case Response::STATUS_CREATED :
			case Response::STATUS_ACCEPTED :
			case Response::STATUS_MOVED_PERMANENTLY :
			case Response::STATUS_NOT_MODIFIED :
			case Response::STATUS_MOVED_TEMPORARILY :
			case Response::STATUS_INPUT_REQUIRED : 
			case Response::STATUS_EXCEPTION :
			case Response::STATUS_SERVER_ERROR :  
			case Response::STATUS_NOT_IMPLEMENTED :
			default :
				return 'Unknown error';
		}
	}

    protected static function getRouterAction(){
        $action = new Action();
        $action->setAction(self::$_controller);
        $action->setRespondsWith(array_keys(Response::getFormats()), false);

        return $action;
    }
	
	public static function getResponseMethod(){
		if(self::$_response_method == null){
			if(PHP_SAPI === 'cli') return Response::FORMAT_CLI;
			else return Config::get('wave')->response->default_method;
		} 
		else
			return self::$_response_method;
	}

    public static function setRequest($request) {
        self::$request = $request;
    }

    public static function setResponse($response) {
        self::$response = $response;
    }
}

?>