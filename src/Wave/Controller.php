<?php

namespace Wave;

use Wave\Http\Exception\NotFoundException;
use Wave\Http\Exception\UnauthorizedException;
use Wave\Http\Request;
use Wave\Http\Response;
use Wave\Http\Response\HtmlResponse;
use Wave\Http\Response\JsonResponse;
use Wave\Http\Response\XmlResponse;
use Wave\Router\Action;
use Wave\Validator\Exception\InvalidInputException;

class Controller
{

    const RETURN_FORMAT_REQUEST = 'request';
    const RETURN_FORMAT_RESPOND = 'respond';

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
    /** @var \Wave\Validator\Result */
    protected $_cleaned;
    protected $_action;
    protected $_input_errors;

    protected $_is_post = false;
    protected $_is_get = false;

    protected $_status;
    protected $_message;

    private $_invoke_method = self::INVOKE_NORMAL;


    /**
     * @param Action $action
     * @param Request $request
     * @param array $data
     * @param int $invoke_type
     *
     * @return Http\Response
     * @throws Http\Exception\UnauthorizedException
     * @throws Http\Exception\NotFoundException
     * @throws Exception
     * @throws Http\Exception\ForbiddenException
     * @throws Http\Exception\ForbiddenException
     */
    public static final function invoke(Action $action, Request $request, $data = array(), $invoke_type = self::INVOKE_NORMAL)
    {

        list($controller_class, $action_method) = explode('.', $action->getAction(), 2) + array(null, null);
        if (!isset($action_method))
            $action_method = Config::get('wave')->controller->default_method;

        if (class_exists($controller_class, true) && method_exists($controller_class, $action_method)) {

            /** @var \Wave\Controller $controller */
            $controller = new $controller_class();

            $controller->_action = $action;
            $controller->_request = $request;
            $controller->_response_method = $request->getFormat();
            $controller->_invoke_method = $invoke_type;

            switch ($controller->_request->getMethod()) {
                case Request::METHOD_GET:
                    $controller->_is_get = true;
                    break;
                case Request::METHOD_POST:
                    $controller->_is_post = true;
                    break;
            }
            $data = array_replace($controller->_request->getData(), $data);

            $controller->_data = $data;
            Hook::triggerAction('controller.before_init', array(&$controller));
            $controller->init();

            if ($invoke_type !== self::INVOKE_SUB_REQUEST && !$action->canRespondWith($request->getFormat())) {
                throw new NotFoundException(
                    'The requested action ' . $action->getAction() .
                    ' can not respond with ' . $request->getFormat() .
                    '. (Accepts: ' . implode(', ', $action->getRespondsWith()) . ')', $request
                );
            } else if (!$action->checkRequiredLevel($request)) {
                throw new UnauthorizedException('You are not authorized to view this resource');
            } else if ($action->needsValidation() && !$controller->inputValid($action->getValidationSchema($data))) {
                return $controller->request();
            }

            Hook::triggerAction('controller.before_dispatch', array(&$controller));

            $parameters = array();
            foreach ($action->getMethodParameters() as $parameter) {
                list($parameter_name, $parameter_type) = $parameter;
                if (isset($controller->_cleaned[$parameter_name])) {
                    //Try first in validator output
                    $parameters[] = $controller->_cleaned[$parameter_name];
                } elseif (isset($controller->_data[$parameter_name])) {
                    //Then if just using the passed data - there may be a legitimate use for this?
                    $parameters[] = $controller->_data[$parameter_name];
                } elseif ($parameter_type === 'Wave\\Validator\\Result') {
                    //If the validator is requested, give it
                    $parameters[] = $controller->_cleaned;
                } elseif ($parameter_type === get_class($request)) {
                    //If the request is requested, give it
                    $parameters[] = $request;
                } else {
                    //Otherwise place hold. Could maybe get the default value during generation and pass that instead
                    $parameters[] = null;
                }
            }

            try {
                $response = call_user_func_array(array($controller, $action_method), $parameters);
            } catch (InvalidInputException $e) {
                $controller->_input_errors = $e->getViolations();
                $response = $controller->request();
            }

            Hook::triggerAction('controller.after_dispatch', array(&$controller, &$response));
            return $response;

        } else
            throw new Exception('Could not invoke action ' . $action->getAction() . '. Method ' . $controller_class . '::' . $action_method . '() does not exist', Response::STATUS_SERVER_ERROR);

    }

    public function __construct()
    {
        $this->_post =& $_POST;
        $this->_get =& $_GET;

        $this->_identity = \Wave\Auth::getIdentity();
    }

    public function _setResponseMethod($method)
    {
        $this->_response_method = $method;
    }

    public function _getResponseMethod()
    {
        return $this->_response_method;
    }

    public function init()
    {
    }

    /**
     * Use the Wave Validator to check form input. If errors exist, the offending
     * values are inserted into $this->_input_errors.
     *
     * @param        $schema -        The validation schema for the Jade Validator
     * @param        $data -        [optional] Supply a data array to use for validation
     * @return        Boolean true for no errors, or false.
     */
    protected function inputValid($schema, $data = null)
    {
        if ($data === null)
            $data = $this->_data;

        try {
            $output = Validator::validate($schema, $data);
            $this->_cleaned = $output;
            return true;
        } catch (InvalidInputException $e) {
            $this->_input_errors = $e->getViolations();
        }

        return false;
    }

    protected function _buildPayload($status, $message = '', $payload = null)
    {
        if ($payload === null)
            $payload = $this->_getResponseProperties();

        return array(
            'status' => $status,
            'message' => $message,
            'payload' => $payload
        );
    }

    protected function _buildDataSet()
    {
        $this->_setTemplatingGlobals();
        $properties = $this->_getResponseProperties();
        return array_merge($properties);
    }

    protected function _getResponseProperties()
    {
        $payload = array();
        foreach ($this as $key => $val) {
            if ($key[0] === '_') {
                continue;
            }
            $payload[$key] = $val;
        }
        return $payload;
    }

    protected function _setTemplatingGlobals()
    {
        View::registerGlobal('input', isset($this->_sanitized) ? $this->_sanitized : $this->_data);
        View::registerGlobal('errors', isset($this->_input_errors) ? $this->_input_errors : array());
        View::registerGlobal('_identity', $this->_identity);
    }

    protected function respond($payload = null)
    {
        if ($this->_invoke_method === self::INVOKE_SUB_REQUEST) {
            if ($payload !== null) {
                return $payload;
            } else {
                return $this->_getResponseProperties();
            }
        } else {
            $callable = $this->getResponseMethodHandler(self::RETURN_FORMAT_RESPOND);
            return call_user_func($callable, $payload);
        }
    }

    protected function request($payload = null)
    {
        if ($this->_invoke_method === self::INVOKE_SUB_REQUEST) {
            if ($payload === null) {
                $payload = isset($this->_input_errors) ? $this->_input_errors : array();
            }
            return array('errors' => $payload);
        } else {
            $callable = $this->getResponseMethodHandler(self::RETURN_FORMAT_REQUEST);
            return call_user_func($callable, $payload);
        }
    }

    protected function requestHTML()
    {
        if (isset($this->_request_template)) {
            $this->_template = $this->_request_template;
        }
        return $this->respondHTML();
    }

    protected function respondHTML()
    {
        if (!isset($this->_template)) {
            throw new Exception(
                "Template not set for {$this->_response_method} in action {$this->_action->getAction()}");
        }

        Hook::triggerAction('controller.before_build_html', array(&$this));
        $content = View::getInstance()->render($this->_template, $this->_buildDataSet());
        Hook::triggerAction('controller.after_build_html', array(&$this));

        return new HtmlResponse($content);
    }

    protected function requestDialog()
    {
        if (isset($this->_request_template))
            $this->_template = $this->_request_template;

        return $this->respondDialog();
    }

    protected function respondDialog()
    {
        if (!isset($this->_template)) {
            throw new Exception(
                "Template not set for {$this->_response_method} in action {$this->_action->getAction()}");
        }

        $this->_template .= '-dialog';

        Hook::triggerAction('controller.before_build_dialog', array(&$this));
        $html = View::getInstance()->render($this->_template, $this->_buildDataSet());
        Hook::triggerAction('controller.after_build_dialog', array(&$this));

        return $this->respondJSON(array('html' => $html));
    }

    protected function requestJSON($payload = null)
    {
        if (!isset($this->_status)) {
            $this->_status = Response::STATUS_BAD_REQUEST;
        }
        if (!isset($this->_message)) {
            $this->_message = 'Invalid request or parameters';
        }

        if ($payload === null) {
            $payload = array('errors' => isset($this->_input_errors) ? $this->_input_errors : array());
        }
        return $this->respondJSON($payload);
    }

    protected function respondJSON($payload = null)
    {
        if (!isset($this->_status)) {
            $this->_status = Response::STATUS_OK;
        }
        if (!isset($this->_message)) {
            $this->_message = Response::getMessageForCode($this->_status);
        }

        Hook::triggerAction('controller.before_build_json', array(&$this));
        $payload = $this->_buildPayload($this->_status, $this->_message, $payload);
        Hook::triggerAction('controller.before_build_json', array(&$this));

        return new JsonResponse($payload, $this->_status);
    }

    protected function respondXML($payload = null)
    {
        if (!isset($this->_status)) {
            $this->_status = Response::STATUS_OK;
        }
        if (!isset($this->_message)) {
            $this->_message = Response::getMessageForCode($this->_status);
        }

        return new XmlResponse($this->_buildPayload($this->_status, $this->_message, $payload));
    }

    protected function requestXML($payload = null)
    {
        if (!isset($this->_status)) {
            $this->_status = Response::STATUS_BAD_REQUEST;
        }
        if (!isset($this->_message)) {
            $this->_message = Response::getMessageForCode($this->_status);
        }

        return $this->respondXML($payload);
    }

    private function getResponseMethodHandler($type)
    {
        $response_method = $type . strtoupper($this->_response_method);
        if (method_exists($this, $response_method) && $response_method !== $type)
            return [$this, $response_method];
        else
            throw new Exception(
                'The action "' . $this->_action->getAction() . '" tried to respond with "' .
                $this->_response_method . '" but the method does not exist'
            );
    }

}


?>
