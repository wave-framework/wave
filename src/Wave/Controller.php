<?php

namespace Wave;

use Wave\Http\Exception\ForbiddenException;
use Wave\Http\Exception\NotFoundException;
use Wave\Http\Exception\UnauthorizedException;
use Wave\Http\Response\HtmlResponse;
use Wave\Http\Response\JsonResponse;
use Wave\Http\Response\XmlResponse;
use Wave\Router\Action;
use Wave\Utils\JSON;
use Wave\Utils\XML;
use Wave\Http\Request;
use Wave\Http\Response;

class Controller {

    /**
     * If invoking this controller from within another it is possible to get the computed data
     * as an array rather than formatting it into one of the response objects
     */
    const INVOKE_NORMAL = 1;
    const INVOKE_SUB_REQUEST = 2;

    /** @var \Wave\Http\Response */
    public $_response;

    /** @var \Wave\Http\Request */
    protected $_request;
	
	protected $_response_method;
	
	protected $_data;
    protected $_cleaned = array();
	protected $_action;
	protected $_input_errors;

	protected $_is_post = false;
	protected $_is_get = false;

    protected $_status;
    protected $_message;

    private $_invoke_method = self::INVOKE_NORMAL;


    /**
     * @param Action $action
     * @param Request       $request
     * @param array         $data
     * @param int           $invoke_type
     *
     * @throws Http\Exception\UnauthorizedException
     * @throws Http\Exception\NotFoundException
     * @throws Exception
     * @throws Http\Exception\ForbiddenException
     * @return Http\Response
     * @throws Http\Exception\ForbiddenException
     */
    public static final function invoke(Action $action, Request $request, $data = array(), $invoke_type = self::INVOKE_NORMAL){

		list($controller_class, $action_method) = explode('.', $action->getAction(), 2) + array(null, null);
        if(!isset($action_method))
            $action_method = Config::get('wave')->controller->default_method;

		if(class_exists($controller_class, true) && method_exists($controller_class, $action_method)){

            /** @var \Wave\Controller $controller */
			$controller = new $controller_class();

            $controller->_action = $action;
            $controller->_request = $request;
            $controller->_response_method = $request->getFormat();
            $controller->_invoke_method = $invoke_type;

            switch($controller->_request->getMethod()){
                case Request::METHOD_GET:
                    $controller->_is_get = true;
                    break;
                case Request::METHOD_POST:
                    $controller->_is_post = true;
                    break;
            }
            $data = array_replace($controller->_request->getData(), $data);

            $controller->_data = $data;
            $controller->init();

            if($invoke_type !== self::INVOKE_SUB_REQUEST && !$action->canRespondWith($request->getFormat())){
                throw new NotFoundException(
                    'The requested action ' . $action->getAction().
                    ' can not respond with ' . $request->getFormat() .
                    '. (Accepts: '.implode(', ', $action->getRespondsWith()).')', $request);
            }
            else if(!$action->checkRequiredLevel($request)){

                $auth_obj = Auth::getIdentity();
                $auth_class = Auth::getHandlerClass();

                if(!in_array('Wave\IAuthable', class_implements($auth_class)))
                    throw new Exception('A valid Wave\IAuthable class is required to use RequiresLevel annotations', Response::STATUS_SERVER_ERROR);
                else if(!$auth_obj instanceof IAuthable)
                    throw new UnauthorizedException('You are not authorized to view this resource');
                else
                    throw new ForbiddenException('The current user does not have the required level to access this page');
            }
            else if($action->needsValidation() && !$controller->inputValid($action->getValidationSchema())){
                return $controller->request();
            }


            return $controller->{$action_method}();

		}
		else
            throw new Exception('Could not invoke action '.$action->getAction().'. Method '.$controller_class.'::'.$action_method.'() does not exist', Response::STATUS_SERVER_ERROR);
		
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

        if(($output = Validator::validate($schema, $data, true)) && $output->isValid()){
            $this->_cleaned = $output;
            return true;
        }

        $this->_input_errors = $output->getViolations();
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
        // if this controller is running under a sub request then just return the computed response array
        if($this->_invoke_method === self::INVOKE_SUB_REQUEST){
            if($type === 'request')
                return array('errors' => isset($this->_input_errors) ? $this->_input_errors : array());
            else
                return $this->_getResponseProperties();
        }
        else {
            $response_method = $type.strtoupper($this->_response_method);
            if(method_exists($this, $response_method) && $response_method !== $type)
                return $this->{$response_method}();
            else
                throw new Exception(
                    'The action "'.$this->_action->getAction().'" tried to respond with "'.
                    $this->_response_method.'" but the method does not exist'
                );
        }
	}

	protected function respondHTML(){
		if(!isset($this->_template))
			throw new Exception('Template not set for '.$this->_response_method.' in action '.$this->_action->getAction());


        $content = View::getInstance()->render($this->_template, $this->_buildDataSet());
        return new HtmlResponse($content);
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

        $payload = $this->_buildPayload($this->_status, $this->_message, $payload);
        return new JsonResponse($payload, $this->_status);
	}
	
	protected function requestJSON(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_BAD_REQUEST;
		if(!isset($this->_message)) $this->_message = 'Invalid request or parameters';
        $payload = array('errors' => isset($this->_input_errors) ? $this->_input_errors : array());
		return $this->respondJSON($payload);
	}
	
	protected function respondXML(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_OK;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);

        return new XmlResponse($this->_buildPayload($this->_status, $this->_message));
	}
	
	protected function requestXML(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_BAD_REQUEST;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
		return $this->respondXML();
	}

}


?>