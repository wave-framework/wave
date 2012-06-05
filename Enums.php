<?php



abstract class Wave_Method {

	const ANY  = '*';
	const POST = 'POST';
	const GET  = 'GET';
	const PUT  = 'PUT';
	const CLI  = 'CLI';
	const DELETE = 'DELETE';
	const UPDATE = 'UPDATE';
	const CREATE = 'CREATE';

	static $ALL = array(
		self::ANY, self::POST, self::GET,
		self::PUT, self::CLI, self::DELETE, self::UPDATE,
		self::CREATE
	);

}

abstract class Wave_Response {
	
	const STATUS_OK 			= 200;
	const STATUS_CREATED 		= 201;
	const STATUS_ACCEPTED		= 202;
	const STATUS_MOVED_PERMANENTLY = 301;
	const STATUS_NOT_MODIFIED 	= 304;
	const STATUS_MOVED_TEMPORARILY = 307;
	const STATUS_BAD_REQUEST 	= 400;
	const STATUS_UNAUTHORISED 	= 401;
	const STATUS_FORBIDDEN		= 403;
	const STATUS_NOT_FOUND		= 404;
	const STATUS_INPUT_REQUIRED = 420;
	const STATUS_EXCEPTION 		= 421;
	const STATUS_SERVER_ERROR	= 500;
	const STATUS_NOT_IMPLEMENTED= 501;
	
	const HTML		= 'html';
	const JSON		= 'json';
	const XML 		= 'xml';
	const DIALOG 	= 'dialog';
	const CLI		= 'cli';
	const INTERNAL  = 'internal';
	
	static $ALL = array(
		self::HTML, self::JSON,
		self::XML, self::DIALOG, 
		self::CLI, self::INTERNAL
	);
	
	public static function register($method){
		self::$ALL[] = $method;
	}
	
	public static function getMessageForCode($code){
		switch ($code) {
			case self::STATUS_OK:
				return 'Request OK';
			case self::STATUS_CREATED:
				return 'Resource created';
			case self::STATUS_ACCEPTED:
				return 'Request accepted';
			case self::STATUS_MOVED_PERMANENTLY:
				return 'Resource moved permanently';
			case self::STATUS_NOT_MODIFIED:
				return 'Resource not modified';
			case self::STATUS_MOVED_TEMPORARILY:
				return 'Resource moved temporarily';
			case self::STATUS_BAD_REQUEST:
				return 'Bad request';
			case self::STATUS_UNAUTHORISED:
				return 'Unauthorised';
			case self::STATUS_FORBIDDEN:
				return 'Forbidden';
			case self::STATUS_NOT_FOUND:
				return 'Resource not found';
			case self::STATUS_INPUT_REQUIRED:
				return 'Input is required for this action';
			case self::STATUS_EXCEPTION:
				return 'An error occured while processing this request';
			case self::STATUS_SERVER_ERROR:
				return 'Internal server srror';
			case self::STATUS_NOT_IMPLEMENTED:
				return 'Request method not supported';
			default:
				return '';
		}
	}
}


?>