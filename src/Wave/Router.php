<?php

namespace Wave;

use Wave\Http\Exception\NotFoundException;
use Wave\Http\Request;
use Wave\Http\Response;
use Wave\Router\Node;
use Wave\Router\RoutingException;

class Router {

    private static $profiles;

    private $root;
    private $table;

    public $request_method;
    public $request_uri;
    public $profile;

    public $response_method;

    /** @var \Wave\Http\Request $request */
    protected $request;

    /** @var \Wave\Http\Response $response */
    protected $response;

    public static function init($host = null) {
        Hook::triggerAction('router.before_init', array(&$host));
        if($host === null) {
            if(isset($_SERVER['HTTP_HOST']))
                $host = $_SERVER['HTTP_HOST'];
            else
                $host = Config::get('deploy')->profiles->default->baseurl;
        }
        $instance = new self($host);
        Hook::triggerAction('router.after_init', array(&$instance));
        return $instance;
    }

    public static function getActionByName($profile, $action_name) {
        $table = static::getRoutesTableCache($profile);
        $action_name = ltrim($action_name, '\\');

        return isset($table[$action_name]) ? $table[$action_name] : null;
    }

    public static function getProfiles() {
        if(!isset(self::$profiles))
            self::$profiles = Config::get('deploy')->profiles;

        return self::$profiles;
    }

    public static function getCacheName($host, $type) {
        return "routes/$host.$type";
    }

    public static function getRoutesTreeCache($profile) {
        $root = Cache::load(self::getCacheName($profile, 'tree'));
        if($root == null) {
            $root = Cache::load(self::getCacheName('default', 'tree'));
        }

        if(!($root instanceof Node))
            throw new RoutingException("Could not load route tree for profile: {$profile} nor default profile");

        return $root;
    }

    public static function getRoutesTableCache($profile) {
        $table = Cache::load(self::getCacheName($profile, 'table'));
        if($table == null) {
            $table = Cache::load(self::getCacheName('default', 'table'));
        }

        if(!is_array($table))
            throw new RoutingException("Could not load routes table for profile: {$profile} nor default profile");

        return $table;
    }

    public function __construct($profile) {
        if(isset(static::getProfiles()->$profile)) {
            $this->profile = $profile;
        } else {
            // try looking for the profile using the baseurl instead
            foreach(static::getProfiles() as $name => $config) {
                if($config->baseurl == $profile) {
                    $this->profile = $name;
                    break;
                }
            }
        }

        if(!isset($this->profile)) {
            throw new RoutingException("Unknown routing profile {$profile}");
        }
    }

    /**
     * @param Request $request
     *
     * @throws \LogicException
     * @throws Http\Exception\NotFoundException
     * @return Response
     */
    public function route(Request $request = null) {

        if(null === $request)
            $request = Request::createFromGlobals();

        $this->request = $request;

        $this->request_uri = $request->getPath();
        if(strrpos($this->request_uri, $request->getFormat()) !== false) {
            $this->request_uri = substr($this->request_uri, 0, -(strlen($request->getFormat()) + 1));
        }
        $this->request_method = $request->getMethod();

        Hook::triggerAction('router.before_routing', array(&$this));

        $url = $this->request_method . $this->request_uri;
        $node = $this->getRootNode()->findChild($url, $this->request);

        /** @var \Wave\Router\Action $action */
        if($node instanceof Router\Node && $action = $node->getAction()) {
            Hook::triggerAction('router.before_invoke', array(&$action, &$this));
            $this->request->setAction($action);
            $this->response = Controller::invoke($action, $this->request);
            Hook::triggerAction('router.before_response', array(&$action, &$this));
            if(!($this->response instanceof Response)) {
                throw new \LogicException("Action {$action->getAction()} should return a \\Wave\\Http\\Response object", 500);
            } else {
                return $this->response->prepare($this->request);
            }
        } else
            throw new NotFoundException('The requested URL ' . $url . ' does not exist', $this->request);
    }

    public function getRootNode() {
        if(!($this->root instanceof Node)) {
            $this->root = static::getRoutesTreeCache($this->profile);
        }
        return $this->root;
    }

    /**
     * @return \Wave\Http\Request
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * @param \Wave\Http\Request $request
     */
    public function setRequest($request) {
        $this->request = $request;
    }
}

?>