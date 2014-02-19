<?php

namespace Wave;

use ErrorException;
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
		self::$_controller = "$controller.execute";
		set_exception_handler(array('\\Wave\\Exception', 'handle'));

        Hook::registerHandler('router.before_routing', function(Router $router){
            if(Exception::$_response_method === null)
                Exception::$_response_method = $router->getRequest()->getFormat();

            Exception::setRequest($router->getRequest());
        });
	}

    public static function registerError(){
        set_error_handler(array('\\Wave\\Exception', 'handleError'));
    }

    public static function handleError($code, $message, $file = null, $line = 0){
        if (error_reporting() == 0) {
            return true;
        }
        throw new ErrorException($message, $code, 0, $file, $line);
    }
	
	public static function handle(\Exception $e, $send_response = true){
        try {
            Hook::triggerAction('exception.handle', array(&$e));

            $log_message = sprintf('%-4s %s', "({$e->getCode()})", $e->getMessage());
            // get the channel manually so the introspection works properly.
            Log::getChannel('exception')->addRecord(Log::ERROR, $log_message, array(
                'exception' => $e
            ));

            $request = static::$request;
            if($request === null)
                $request = Request::createFromGlobals();

            $action = Action::getDefaultAction(self::$_controller);
            $action->setRespondsWith(array('*'), false);
    		$response = Controller::invoke($action, $request, array('exception' => $e));
            $response->prepare($request);

            if($send_response)
                $response->send();

            return $response;
        }
        catch(\Exception $_e){
            echo $e->__toString();
            echo "\n\n\nAdditionally, the following exception occurred while trying to handle the error:\n\n";
            echo $_e->__toString();
        }
	}

    public function __construct($message, $code = null){
        if($code == null && is_numeric($message)){
            $code = intval($message);
            $message = $this->getInternalMessage($code);
        }
        parent::__construct($message, $code);
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
			case Response::STATUS_SERVER_ERROR :
			case Response::STATUS_NOT_IMPLEMENTED :
			default :
				return 'Unknown error';
		}
	}
	
	public static function getResponseMethod(){
		if(self::$_response_method == null){
			if(PHP_SAPI === 'cli') return Response::FORMAT_CLI;
			else return Config::get('wave')->response->default_format;
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