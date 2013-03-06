<?php

namespace Wave;

class Exception extends \Exception {
	
	private static $_controller = null;
	public static $_response_method = null;
	
	public static function register($controller){
		if(!class_exists($controller) || !is_subclass_of($controller, '\\Wave\\Controller')){
			throw new \Exception("Controller $controller must be an instance of \\Wave\\Controller");
		}
		self::$_controller = $controller;
		set_exception_handler(array('\Wave\Exception', 'handle'));
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
		Controller::invoke(self::$_controller, array('data' => array('exception' => $e)));
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
	
	public static function getResponseMethod(){
		if(self::$_response_method == null){
			if(PHP_SAPI === 'cli') return Response::CLI;
			else return \Wave\Config::get('wave')->controller->default_response;
		} 
		else
			return self::$_response_method;
	}
}

?>