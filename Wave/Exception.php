<?php

namespace Wave;

class Exception extends \Exception {
		
	public static $_response_method = null;
	
	public static function register(){
		set_exception_handler(array('\Wave\Exception', 'handle'));
		//set_error_handler(array('\Wave\Exception', 'handleError'));
	}
	
	public function __construct($message, $code = null){
		if($code == null && is_numeric($message)){
			$code = intval($message);
			$message = $this->getInternalMessage($code);
		}
		parent::__construct($message, $code);	
	}
	
	public static function handle(\Exception $e){
		Controller::invoke("\\Controller\\Exception", array('exception' => $e));
	}
	
	public static function handleError($level, $message, $file, $line, $context) {
	    if(error_reporting() & $level){
		    throw new \Wave\Exception($message, 0, $file, $line);
	    }
	    else return;
	    
	    //Handle user errors, warnings, and notices here
	    if($level === E_USER_ERROR || $level === E_USER_WARNING || $level === E_USER_NOTICE) {
	        echo $message;
	        return(true); //And prevent the PHP error handler from continuing
	    }
	    return(false); //Otherwise, use PHP's error handler
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
		return (self::$_response_method == null) ? \Wave\Config::get('wave')->controller->default_response : self::$_response_method;
	}
}

?>