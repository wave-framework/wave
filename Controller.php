<?php


class Wave_Controller { 
	
	
	protected static $_response_method;
	
	protected $_data;
	protected $_action;
	
	protected $_is_post = false;
	protected $_is_get = false;
	//protected $_response_method;
	
	public static final function invoke($action, $data, $router = null){
		
		$invoke = explode('.', $action);
		
		if(!isset($invoke[1]))
			$invoke[1] = cfg('wave')->controller->default_method;
		
		if(class_exists($invoke[0], true)){
						
			$controller = new $invoke[0]();		
			
			if($router instanceof Wave_Router){
				// build the default data set from the HTTP request based on the request method
				if($router->request_method == Wave_Method::GET){
					$data = array_merge($_GET, $data);
					$controller->_is_get = true;
				}
				else if($router->request_method == Wave_Method::POST){
					$data = array_merge($_POST, $data);
					$controller->_is_post = true;
				}
				else if($router->request_method == Wave_Method::CLI){
					$data = array_merge($_SERVER['argv'], $data);
				}
				
				$controller->_request_uri = $router->request_uri;
				
				self::$_response_method = $router->response_method;			
			}
					
			$controller->_data = $data;
			$controller->_action = $action;
			unset($data, $router);
			
			if(self::$_response_method == null)
				self::$_response_method = Wave_Config::get('wave')->controller->default_response;
			
			if(method_exists($controller, $invoke[1])){
				$controller->init();
				$controller->{$invoke[1]}();
				return true;
			}
			else 
				throw new Wave_Exception('Could not invoke action '.$action.'. Method '.$invoke[0].'::'.$invoke[1].'() does not exist');

		}
		else 
			throw new Wave_Exception('Could not invoke action '.$action.'. Controller '.$invoke[0].' does not exist');
		
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
        $schema_file = sprintf(Wave_Config::get('wave')->schemas->file_format, $schema_name);
        $schema_path = Wave_Config::get('wave')->path->schemas . $schema_file;

        $v = new Wave_Validator($data, $schema_path);
        $r = $v->validate();
        $this->_sanitized = $v->getSanitizedData();
        if ($r == Wave_Validator::RESULT_VALID) {
            return true;
        } else {
            $this->_input_errors = $v->getErrors();
            return false;
    	}
    }
	
	public static function _setResponseMethod($method){
		self::$_response_method = $method;
	}
	
	
	final public function __construct(){
			
		$this->_post =& $_POST;
		$this->_get =& $_GET;
		
		$this->_identity = Wave_Auth::getIdentity();
	
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
		$response = array(
			'assets' => Wave_Config::get('deploy')->assets,
			'_request_uri' => isset($this->_request_uri) ? $this->_request_uri : $_SERVER['REQUEST_URI'],
			'_identity' => $this->_identity,
			'input' => isset($this->_sanitized) ? $this->_sanitized : $this->_data,
			'errors' => isset($this->_input_errors) ? $this->_input_errors : array()
		);
				
		$properties = $this->_getResponseProperties();
		return array_merge($properties, $response);
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

	
	final protected function respond(){
		return $this->_invoke('respond');
	}
	
	final protected function request(){
		return $this->_invoke('request');
	}
	
	final private function _invoke($type){
		$response_method = $type.strtoupper(self::$_response_method);
		if(method_exists($this, $response_method))
			return $this->{$response_method}();
		else
			throw new Wave_Exception(
				'The action "'.$this->_action.'" tried to respond with "'.
				self::$_response_method.'" but the method does not exist'
			);
	}

	protected function respondHTML(){
		if(!isset($this->_template))
			throw new Wave_Exception('Template not set for '.self::$_response_method.' in action '.$this->_action);
		
		header('Content-type: text/html; charset=utf-8');
		echo Wave_View::getInstance()->render($this->_template, $this->_buildDataSet());
		exit(0);
	}
	
	protected function requestHTML(){
		if(isset($this->_request_template))
			$this->_template = $this->_request_template;
		return $this->respondHTML();
	}
	
	protected function respondDialog(){
		$this->_template .= '-dialog';
		
		$html = Wave_View::getInstance()->render($this->_template, $this->_buildDataSet());
		return $this->respondJSON($html);
	}
	
	protected function requestDialog(){
		if(isset($this->_request_template))
			$this->_template = $this->_request_template;
		return $this->respondDialog();
	}
	
	protected function respondJSON($payload = null){
		if(!isset($this->_status)) $this->_status = Wave_Response::STATUS_OK;
		if(!isset($this->_message)) $this->_message = Wave_Response::getMessageForCode($this->_status);
		header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json');
        echo JSON::encode($this->_buildPayload($this->_status, $this->_message, $payload));
        exit(0);
	}
	
	protected function requestJSON(){
		if(!isset($this->_status)) $this->_status = Wave_Response::STATUS_INPUT_REQUIRED;
		if(!isset($this->_message)) $this->_message = Wave_Response::getMessageForCode($this->_status);
		return $this->respondJSON();
	}
	
	protected function respondXML(){
		if(!isset($this->_status)) $this->_status = Wave_Response::STATUS_OK;
		if(!isset($this->_message)) $this->_message = Wave_Response::getMessageForCode($this->_status);
		header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header("content-type: text/xml; charset=utf-8");
		echo XML::encode($this->_buildPayload($this->_status, $this->_message));
		exit(0);
	}
	
	protected function requestXML(){
		if(!isset($this->_status)) $this->_status = Wave_Response::STATUS_INPUT_REQUIRED;
		if(!isset($this->_message)) $this->_message = Wave_Response::getMessageForCode($this->_status);
		return $this->respondXML();
	}

}


?>