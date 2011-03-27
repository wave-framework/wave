<?php


class Wave_Exception extends Exception {
		
	public function __construct($message, $code = 0){
		parent::__construct($message, $code);	
	}
	
	public static function handle(Exception $e){
		
		Wave_Controller::invoke("ExceptionController", array('exception' => $e));
	}
	
	
	public static function trigger($type){
		
		switch($type){
			case Wave_Response::STATUS_NOT_FOUND : 
				throw new Wave_Exception('The requested resource was not found', Wave_Response::STATUS_NOT_FOUND);
			case Wave_Response::STATUS_OK :
			case Wave_Response::STATUS_CREATED :
			case Wave_Response::STATUS_ACCEPTED :
			case Wave_Response::STATUS_MOVED_PERMANENTLY :
			case Wave_Response::STATUS_NOT_MODIFIED :
			case Wave_Response::STATUS_MOVED_TEMPORARILY :
			case Wave_Response::STATUS_BAD_REQUEST :
			case Wave_Response::STATUS_UNAUTHORISED :  
			case Wave_Response::STATUS_FORBIDDEN : 
			case Wave_Response::STATUS_INPUT_REQUIRED : 
			case Wave_Response::STATUS_EXCEPTION :
			case Wave_Response::STATUS_SERVER_ERROR :  
			case Wave_Response::STATUS_NOT_IMPLEMENTED :
			default :
				throw new Wave_Exception('An error has occured', $type);
		}
		
	}
	
}



?>