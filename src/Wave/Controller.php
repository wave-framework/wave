<?php

namespace Wave;

use \Wave\Utils\JSON,
    \Wave\Utils\XML;

class Controller { 
	
	
	protected $_response_method;
	
	protected $_data;
	protected $_action;
	
	protected $_is_post = false;
	protected $_is_get = false;
	protected $_check_csrf = false;
	//protected $_response_method;
	
	public static final function invoke($action, $data, $router = null){
		
		$invoke = explode('.', $action);
		
		if(!isset($invoke[1]))
			$invoke[1] = Config::get('wave')->controller->default_method;
		
		if(class_exists($invoke[0], true)){
					
			$controller = new $invoke[0]();		
			
			if($router instanceof Router){
				// build the default data set from the HTTP request based on the request method
				if($router->request_method == Method::GET){
					$data = array_merge($_GET, $data);
					$controller->_is_get = true;
				}
				else if($router->request_method == Method::POST){
					$data = array_merge($_POST, $data);
					$controller->_is_post = true;
					$controller->_check_csrf = Config::get('deploy')->auth->csrf->enabled;
				}
				else if($router->request_method == Method::CLI){
					$data = array_merge($_SERVER['argv'], $data);
				}
				
				$controller->_request_uri = $router->request_uri;
				
				$controller->_response_method = $router->response_method;			

			}
				
			$controller->_data = $data;
			$controller->_action = $action;
			unset($data, $router);
			
			if($controller->_response_method == null)
				$controller->_response_method = Config::get('wave')->controller->default_response;
			
			if(method_exists($controller, $invoke[1])){
				$controller->init();
				return $controller->{$invoke[1]}();
			}
			else 
				throw new \Wave\Exception('Could not invoke action '.$action.'. Method '.$invoke[0].'::'.$invoke[1].'() does not exist');

		}
		else 
			throw new \Wave\Exception('Could not invoke action '.$action.'. Controller '.$invoke[0].' does not exist');
		
	}
	
    /**
     * Use the Jade Validator to check form input. If errors exist, the offending
     * values are inserted into $this->_input_errors.
     * 
     * @param		$schema		-		The validation schema for the Jade Validator
     * @param		$data		-		[optional] Supply a data array to use for validation
     * @return		Boolean true for no errors, or false.
     */
    protected function inputValid($schema, $data = null) {

        if ($data === null)
            $data = $this->_data;
		
        $schema_name = strtr($schema, '_', DS);
        $schema_file = sprintf(Config::get('wave')->schemas->file_format, $schema_name);
        $schema_path = Config::get('wave')->path->schemas . $schema_file;
		
		if(!$this->confirmCSRFToken($data)) return false;

        if($output = Validator::validate($schema, $data)){
            $this->_sanitized = $output;
            return true;
        }

        $this->_input_errors = Validator::$last_errors;
		return false;
    }
    
    public function confirmCSRFToken($data = null){
    	if($data == null)
    		$data = $this->_data;
    		
    	if($this->_check_csrf && isset($this->_identity) && $this->_identity instanceof IAuthable){
			$field_name = Config::get('deploy')->auth->csrf->form_name;
			if(!isset($this->_data[$field_name]) || !$this->_identity->confirmCSRFKey($this->_data[$field_name])){
				$this->_input_errors = array($field_name => array('reason' => Validator::ERROR_INVALID));
				return false;
			}
		}
		return true;
    }
	
	public function _setResponseMethod($method){
		$this->_response_method = $method;
	}

	public function _getResponseMethod(){
		return $this->_response_method;
	}
	
	
	final public function __construct(){
			
		$this->_post =& $_POST;
		$this->_get =& $_GET;
		
		$this->_identity = \Wave\Auth::getIdentity();
	
	}
	
	public function init() {}
	
	protected function _buildPayload($status, $message = '', $payload = null){
		if($payload === null)
			$payload = $this->_getResponseProperties();
		
		return array(
			'status' => $status,
			'message' => $message,
			'payload' => $payload
		);
	}
	
	protected function _buildDataSet(){
		$this->_setTemplatingGlobals();
		$properties = $this->_getResponseProperties();
		return array_merge($properties);
	}
	
	protected function _getResponseProperties(){
		$arr = array();
		foreach ($this as $key => $val) {
            if ($key[0] === '_')
                continue;
            $arr[$key] = $val;
        }
        return $arr;
	}
	
	protected function _setTemplatingGlobals(){
		View::registerGlobal('input', isset($this->_sanitized) ? $this->_sanitized : $this->_data);
		View::registerGlobal('errors', isset($this->_input_errors) ? $this->_input_errors : array());
		View::registerGlobal('_identity', $this->_identity);
		View::registerGlobal('_request_uri', isset($this->_request_uri) ? $this->_request_uri : $_SERVER['REQUEST_URI']);
	}
	
	final protected function respond(){
		return $this->_invoke('respond');
	}
	
	final protected function request(){
		return $this->_invoke('request');
	}
	
	final private function _invoke($type){
		$response_method = $type.strtoupper($this->_response_method);
		if(method_exists($this, $response_method) && $response_method !== $type)
			return $this->{$response_method}();
		else
			throw new Exception(
				'The action "'.$this->_action.'" tried to respond with "'.
				$this->_response_method.'" but the method does not exist'
			);
	}

	protected function respondHTML(){
		if(!isset($this->_template))
			throw new Exception('Template not set for '.$this->_response_method.' in action '.$this->_action);
		
		header('Content-type: text/html; charset=utf-8');
		echo View::getInstance()->render($this->_template, $this->_buildDataSet());
		exit(0);
	}
	
	protected function requestHTML(){
		if(isset($this->_request_template))
			$this->_template = $this->_request_template;
		return $this->respondHTML();
	}
	
	protected function respondDialog(){
		$this->_template .= '-dialog';
		
		$html = View::getInstance()->render($this->_template, $this->_buildDataSet());
		return $this->respondJSON($html);
	}
	
	protected function requestDialog(){
		if(isset($this->_request_template))
			$this->_template = $this->_request_template;
		return $this->respondDialog();
	}
	
	protected function respondJSON($payload = null){
		if(!isset($this->_status)) $this->_status = Response::STATUS_OK;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
		header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json');
        echo JSON::encode($this->_buildPayload($this->_status, $this->_message, $payload));
        exit(0);
	}
	
	protected function requestJSON(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_INPUT_REQUIRED;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
		return $this->respondJSON();
	}
	
	protected function respondXML(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_OK;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
		header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header("content-type: text/xml; charset=utf-8");
		echo XML::encode($this->_buildPayload($this->_status, $this->_message));
		exit(0);
	}
	
	protected function requestXML(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_INPUT_REQUIRED;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
		return $this->respondXML();
	}
	
	protected function respondInternal(){
    	if(isset($this->_input_errors)){
	    	$this->validation = $this->_input_errors;
    	}
    	return $this->_getResponseProperties();
	}
	protected function requestInternal(){
    	return $this->respondInternal();
	}

}


?>