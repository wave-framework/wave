<?php

namespace Wave;

use ErrorException;
use Wave\Http\Request;
use Wave\Http\Response;
use Wave\Router\Action;

class Exception extends \Exception {

    private static $levels = array(
        E_WARNING => Log::WARNING,
        E_NOTICE => Log::WARNING,
        E_USER_ERROR => Log::ERROR,
        E_USER_WARNING => Log::WARNING,
        E_USER_NOTICE => Log::WARNING,
        E_STRICT => Log::WARNING,
        E_RECOVERABLE_ERROR => Log::ERROR,
        E_DEPRECATED => Log::WARNING,
        E_USER_DEPRECATED => Log::WARNING,
        E_ERROR => Log::ERROR,
        E_CORE_ERROR => Log::CRITICAL,
        E_COMPILE_ERROR => Log::CRITICAL,
        E_PARSE => Log::EMERGENCY,
    );

    private static $_reserved_memory = '';
    private static $_controller = null;
    public static $_response_method = null;

    /** @var \Wave\Http\Request $request */
    private static $request;
    /** @var \Wave\Http\Response $response */
    private static $response;
    private static $_error_reporting_types;
    private static $working_dir;

    public static function register($controller) {
        if(!class_exists($controller) || !is_subclass_of($controller, '\\Wave\\Controller')) {
            throw new \Exception("Controller $controller must be an instance of \\Wave\\Controller");
        }

        self::$_controller = "$controller.execute";
        set_exception_handler(array('\\Wave\\Exception', 'handle'));

        Hook::registerHandler(
            'router.before_routing', function (Router $router) {
                if(Exception::$_response_method === null)
                    Exception::$_response_method = $router->getRequest()->getFormat();

                Exception::setRequest($router->getRequest());
            }
        );
    }

    public static function registerError($error_types = -1, $reserved_memory = 10) {
        self::$working_dir = getcwd();
        set_error_handler(array('\\Wave\\Exception', 'handleError'));
        register_shutdown_function(array('\\Wave\\Exception', 'handleFatalError'));
        self::$_error_reporting_types = $error_types;
        self::$_reserved_memory = str_repeat('x', 1024 * $reserved_memory);
    }

    public static function handleError($code, $message, $file = null, $line = 0) {
        if(!(self::$_error_reporting_types & $code & error_reporting())) {
            return true;
        }

        throw new ErrorException($message, $code, $code, $file, $line);
    }

    public static function handleFatalError() {
        if(null === $lastError = error_get_last()) {
            return;
        }

        chdir(self::$working_dir);

        self::$_reserved_memory = null;

        $errors = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_STRICT;

        if($lastError['type'] & $errors) {
            self::handleError(@$lastError['type'], @$lastError['message'], @$lastError['file'], @$lastError['line']);
        }
    }

    public static function handle($e, $send_response = true) {
        try {
            Hook::triggerAction('exception.handle', array(&$e));

            $log_message = sprintf('%-4s %s', "({$e->getCode()})", $e->getMessage());
            // get the channel manually so the introspection works properly.

            $level = Log::ERROR;
            if($e instanceof ErrorException && isset(self::$levels[$e->getSeverity()])) {
                $level = self::$levels[$e->getSeverity()];
            }

            Log::getChannel('exception')->addRecord(
                $level, $log_message, array(
                    'exception' => $e
                )
            );

            $request = static::$request;
            if($request === null)
                $request = Request::createFromGlobals();

            $action = Action::getDefaultAction(self::$_controller);
            $action->setRespondsWith(array('*'), false);
            $response = Controller::invoke($action, $request, array('exception' => $e));
            $response->prepare($request);

            if($send_response) {
                $response->send();
            }

            return $response;
        } catch(\Exception $_e) {
            $response = new Response();
            $response->setStatusCode(500);

            if(Core::$_MODE === Core::MODE_PRODUCTION) {
                $response->setContent("Internal server error");
            } else {
                $response->setContent(
                    $e->__toString() .
                    "\n\n\nAdditionally, the following exception occurred while trying to handle the error:\n\n" .
                    $_e->__toString()
                );
            }

            return $response;
        }
    }

    public function __construct($message, $code = null) {
        if($code == null && is_numeric($message)) {
            $code = intval($message);
            $message = $this->getInternalMessage($code);
        }
        parent::__construct($message, $code);
    }

    protected function getInternalMessage($type) {

        switch($type) {
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

    public static function getResponseMethod() {
        if(self::$_response_method == null) {
            if(PHP_SAPI === 'cli') return Response::FORMAT_CLI;
            else return Config::get('wave')->response->default_format;
        } else
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