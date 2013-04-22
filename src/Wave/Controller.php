<?php

namespace Wave;

use Wave\Utils\JSON,
    Wave\Utils\XML,
    Wave\Http\Request;

class Controller { 

    /** @var \Wave\Http\Request */
    protected $_request;
	
	protected $_response_method;
	
	protected $_data;
    protected $_cleaned = array();
	protected $_action;
	protected $_input_errors;

	protected $_is_post = false;
	protected $_is_get = false;


	public static final function invoke($action, array $arguments = array()){
		
		list($controller_class, $action_method) = explode('.', $action, 2) + array(null, null);
		if(!isset($action_method))
            $action_method = Config::get('wave')->controller->default_method;

        $data = array();
        if(isset($arguments['data']) && is_array($arguments['data']))
            $data = $arguments['data'];

		if(class_exists($controller_class, true)){

            /** @var \Wave\Controller $controller */
			$controller = new $controller_class();

            if(isset($arguments['request'])){
                $controller->_request = $arguments['request'];

                switch($controller->_request->getMethod()){
                    case Request::METHOD_GET:
                        $controller->_is_get = true;
                    case Request::METHOD_HEAD:
                    case Request::METHOD_DELETE:
                        $data = array_merge($controller->_request->getQuery(), $data);
                        break;
                    case Request::METHOD_POST:
                        $controller->_is_post = true;
                    case Request::METHOD_PUT:
                        $data = array_merge($controller->_request->getParameters(), $data);
                        break;
                }
            }

            $controller->_data = $data;
            $controller->_action = $action;
            if(isset($arguments['router']) && $arguments['router'] instanceof Router)
                $controller->_response_method = $arguments['router']->response_method;

			if($controller->_response_method == null)
				$controller->_response_method = Config::get('wave')->controller->default_response;



			if(method_exists($controller, $action_method)){
                $controller->init();
                $validated = true;
                if(isset($arguments['validation_schema'])){
                    $validated = $controller->inputValid($arguments['validation_schema']);
                }
                if($validated)
				    return $controller->{$action_method}();
                else {
                    return $controller->request();
                }

			}
			else 
				throw new \Wave\Exception('Could not invoke action '.$action.'. Method '.$controller_class.'::'.$action_method.'() does not exist');

		}
		else 
			throw new \Wave\Exception('Could not invoke action '.$action.'. Controller '.$controller_class.' does not exist');
		
	}
	
    /**
     * Use the Wave Validator to check form input. If errors exist, the offending
     * values are inserted into $this->_input_errors.
     * 
     * @param		$schema		-		The validation schema for the Jade Validator
     * @param		$data		-		[optional] Supply a data array to use for validation
     * @return		Boolean true for no errors, or false.
     */
    protected function inputValid($schema, $data = null) {

        if ($data === null)
            $data = $this->_data;

        if($output = Validator::validate($schema, $data)){
            $this->_cleaned = $output;
            return true;
        }

        $this->_input_errors = Validator::$last_errors;
		return false;
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

        header('X-Wave-Response: html');
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
		return $this->respondJSON(array('html' => $html));
	}
	
	protected function requestDialog(){
		if(isset($this->_request_template))
			$this->_template = $this->_request_template;
		return $this->respondDialog();
	}
	
	protected function respondJSON($payload = null){
		if(!isset($this->_status)) $this->_status = Response::STATUS_OK;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
        header('X-Wave-Response: json');
		header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json');
        echo JSON::encode($this->_buildPayload($this->_status, $this->_message, $payload));
        exit(0);
	}
	
	protected function requestJSON(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_INPUT_REQUIRED;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
        $payload = array('errors' => isset($this->_input_errors) ? $this->_input_errors : array());
		return $this->respondJSON($payload);
	}
	
	protected function respondXML(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_OK;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
        header('X-Wave-Response: xml');
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