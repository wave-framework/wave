<?php

/*
 * This class is based substantially off the class of the same name in the
 * Symfony Http-Foundation package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with the Symfony Http-Foundation component
 */

namespace Wave\Http;

use InvalidArgumentException;
use Wave\Config;
use Wave\Http\Exception\BadRequestException;
use Wave\Router\Action;

class Request {

    const METHOD_HEAD = 'HEAD';
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_PATCH = 'PATCH';
    const METHOD_OPTIONS = 'OPTIONS';

    const METHOD_CLI = 'CLI';

    const TYPE_FORM_ENCODED = 'application/x-www-form-urlencoded';
    const TYPE_JSON = 'application/json';
    const TYPE_MULTIPART = 'multipart/form-data';

    private $url;
    private $baseUrl;
    private $basePath;
    private $content;


    private $method;

    private $format;

    /**
     * @var \Wave\Router\Action $action
     */
    private $action;

    /**
     * Arguments passed in via a query string
     * @var ParameterBag $query
     */
    public $query = array();

    /**
     * Parameters passed in via the request body
     * @var ParameterBag $parameters
     */
    public $parameters = array();

    /**
     * Used by the router to set parameters passed in via route variables
     * @var ParameterBag $path_parameters
     */
    public $attributes = array();

    /**
     * The components that make up the request URL
     * @var array $components
     */
    private $components = array();

    /**
     * The headers for this request
     * @var \Wave\Http\HeaderBag $headers
     */
    public $headers;

    /**
     * @var ServerBag $server
     */
    public $server;

    /**
     * Cookies passed in with the request
     * @var ParameterBag $cookies
     */
    public $cookies = array();


    public function __construct($url, $method = self::METHOD_GET,
                                array $query = array(),
                                array $parameters = array(),
                                array $attributes = array(),
                                array $server = array(),
                                array $cookies = array()) {

        $this->setUrl($url);
        $this->setMethod($method);

        $this->query = new ParameterBag($query);
        $this->parameters = new ParameterBag($parameters);
        $this->attributes = new ParameterBag($attributes);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());
        $this->cookies = new ParameterBag($cookies);

    }

    /**
     * Creates a new Request based on the PHP globals.
     *
     * @return Request
     */
    public static function createFromGlobals() {

        $url = 'http://localhost';
        if(isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
            $url = sprintf('%s://%s', $protocol, $_SERVER['HTTP_HOST']);
        }
        if(isset($_SERVER['SERVER_PORT']) && !in_array($_SERVER['SERVER_PORT'], array(80, 443))) {
            $url .= ':' . $_SERVER['SERVER_PORT'];
        }

        if(isset($_SERVER['PATH_INFO'])) {
            $url .= substr($_SERVER['PATH_INFO'], strpos($_SERVER['PATH_INFO'], '.php/'));
            if(isset($_SERVER['QUERY_STRING']))
                $url .= '?' . $_SERVER['QUERY_STRING'];
        } else if(isset($_SERVER['REQUEST_URI'])) {
            $url .= $_SERVER['REQUEST_URI'];
        }

        $parameters = array();
        $method = static::METHOD_CLI;
        if(isset($_SERVER['REQUEST_METHOD'])) {
            $method = strtoupper($_SERVER['REQUEST_METHOD']);
            if('POST' === $method && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
            switch($method) {
                case static::METHOD_POST:
                case static::METHOD_PATCH:
                case static::METHOD_PUT:
                case static::METHOD_DELETE:
                    if(isset($_SERVER['CONTENT_TYPE']))
                        $parameters = static::parseRequestBody($_SERVER['CONTENT_TYPE']);
                    else
                        $parameters = $_POST;
                    break;
                case static::METHOD_OPTIONS:
                case static::METHOD_HEAD:
                case static::METHOD_GET:
                case static::METHOD_CLI:
                    break;
                default:
                    throw new InvalidArgumentException("Invalid HTTP method $method");
            }
        }

        return new static($url, $method, $_GET, $parameters, array(), $_SERVER, $_COOKIE);
    }

    /**
     * Parses the request body into a readable parameter set. Used by the createFromGlobals method
     *
     * @param string $content_type
     *
     * @throws Exception\BadRequestException
     * @return array
     */
    protected static function parseRequestBody($content_type = self::TYPE_FORM_ENCODED) {
        list($content_type) = explode(';', $content_type);
        switch($content_type) {
            case static::TYPE_JSON:
                $data = json_decode(file_get_contents('php://input'), true);

                if(json_last_error() !== JSON_ERROR_NONE)
                    throw new BadRequestException("Error encountered while decoding JSON payload");

                if(!is_array($data)) return array();
                else return $data;

            case static::TYPE_FORM_ENCODED:
                parse_str(file_get_contents('php://input'), $data);
                if(!is_array($data)) return array();
                else return $data;
            case static::TYPE_MULTIPART:
            default:
                return $_POST;
        }

    }

    /**
     * Determines the request format (html, json, xml etc) for a request based on the extension in the
     * given url
     *
     * @param string $url
     * @param string|null $default
     *
     * @return string
     */
    protected static function parseFormat($url, $default = null) {
        if(null === $default) {
            $default = PHP_SAPI === 'cli' ? 'cli' : Config::get('wave')->response->default_format;
        }

        $path = [];
        if (!empty($url)) {
            $path = pathinfo($url);
        }

        return isset($path['extension']) ? $path['extension'] : $default;
    }

    /**
     * Returns an array dataset for this request, which is a merge of either the query or parameter sets and the
     * attribute set depending on the request method.
     *
     * @return array
     */
    public function getData() {
        switch($this->getMethod()) {
            case self::METHOD_POST:
            case self::METHOD_PUT:
            case self::METHOD_PATCH:
                return array_replace($this->parameters->all(), $this->attributes->all());
            case self::METHOD_HEAD:
            case self::METHOD_GET:
            case self::METHOD_DELETE:
            default:
                return array_replace($this->query->all(), $this->attributes->all());
        }
    }

    public function getAuthorization() {
        return $this->attributes->get('_authorization');
    }

    public function setAuthorization($authorization) {
        $this->attributes->set('_authorization', $authorization);
    }

    /**
     * Returns whether the given parameter is found in the attributes, query or parameters
     *
     * @param $parameter
     *
     * @return bool
     */
    public function has($parameter) {

        foreach(array('attributes', 'query', 'parameters') as $property) {
            if($this->$property->has($parameter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * This will search attribute, query and parameter sets for a specified argument
     *
     * @param $parameter
     * @param null $default the default value if the parameter is not set
     *
     * @return mixed
     */
    public function get($parameter, $default = null) {

        foreach(array('attributes', 'query', 'parameters') as $property) {
            if($this->$property->has($parameter)) {
                return $this->$property->get($parameter);
            }
        }

        return $default;
    }

    /**
     * Sets the full URL for this request. This will *not* update the server parameter bag.
     *
     * @param $url
     */
    public function setUrl($url) {
        $this->components = parse_url($url);
        $this->url = $url;

        $this->format = static::parseFormat($this->getPath(false));
    }

    /**
     * Get the full URL of this request
     *
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }

    /**
     * Get the HTTP method for this request
     *
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * Sets the HTTP method for this request. If an invalid method is passed an exception is thrown
     *
     * @param $method
     *
     * @throws \InvalidArgumentException
     */
    public function setMethod($method) {
        $method = strtoupper($method);
        if(!in_array(
            $method, array(
                static::METHOD_HEAD,
                static::METHOD_GET,
                static::METHOD_POST,
                static::METHOD_PUT,
                static::METHOD_DELETE,
                static::METHOD_CLI,
                static::METHOD_PATCH,
                static::METHOD_OPTIONS
            )
        )
        )
            throw new InvalidArgumentException("Request method [$method] is not valid");

        $this->method = $method;
    }


    /**
     * Returns a component of the request url. See the parse_url() docs for available parameters
     *
     * @param $component
     *
     * @return string|null
     */
    public function getComponent($component) {
        if(isset($this->components[$component]))
            return $this->components[$component];
        else
            return null;
    }

    /**
     * Get the request scheme (http/https)s
     *
     * @return null|string
     */
    public function getScheme() {
        return $this->getComponent('scheme');
    }

    /**
     * Get the host
     *
     * @return null|string
     */
    public function getHost() {
        return $this->getComponent('host');
    }

    /**
     * Get the port
     *
     * @return null|string
     */
    public function getPort() {
        return $this->getComponent('port');
    }

    /**
     * Get the path of the request. This is the part after the host without the querystring
     *
     * @return null|string
     */
    public function getPath($exclude_base_path = true) {
        $path = $this->getComponent('path');
        if($exclude_base_path) {
            $base = $this->getBasePath();
            if(!empty($base) && strpos($path, $base) === 0)
                $path = substr($path, strlen($base));
        }

        return $path;
    }


    /**
     * Get the raw querystring of the request
     *
     * @return null|string
     */
    public function getQueryString() {
        return $this->getComponent('query');
    }

    /**
     * Gets the path and query string component (basically the URL without the host)
     *
     * @return string
     */
    public function getPathAndQueryString() {
        return $this->getPath() . '?' . $this->getQueryString();
    }

    /**
     * Prepares the base path.
     *
     * @return string base path
     */
    protected function prepareBasePath() {
        $filename = basename($this->server->get('SCRIPT_FILENAME', ''));
        $baseUrl = $this->getBaseUrl();
        if(empty($baseUrl)) {
            return '';
        }

        if(basename($baseUrl) === $filename) {
            $basePath = dirname($baseUrl);
        } else {
            $basePath = $baseUrl;
        }

        if('\\' === DIRECTORY_SEPARATOR) {
            $basePath = str_replace('\\', '/', $basePath);
        }

        return rtrim($basePath, '/');
    }

    /**
     * Returns the root path from which this request is executed.
     *
     * Suppose that an index.php file instantiates this request object:
     *
     *  * http://localhost/index.php         returns an empty string
     *  * http://localhost/index.php/page    returns an empty string
     *  * http://localhost/web/index.php     returns '/web'
     *  * http://localhost/we%20b/index.php  returns '/we%20b'
     *
     * @return string The raw path (i.e. not urldecoded)
     *
     * @api
     */
    public function getBasePath() {
        if(null === $this->basePath) {
            $this->basePath = $this->prepareBasePath();
        }

        return $this->basePath;
    }

    /**
     * Returns the root URL from which this request is executed.
     *
     * The base URL never ends with a /.
     *
     * This is similar to getBasePath(), except that it also includes the
     * script filename (e.g. index.php) if one exists.
     *
     * @return string The raw URL (i.e. not urldecoded)
     *
     * @api
     */
    public function getBaseUrl() {
        if(null === $this->baseUrl) {
            $this->baseUrl = $this->prepareBaseUrl();
        }

        return $this->baseUrl;
    }

    /**
     * Prepares the base URL.
     *
     * @return string
     */
    protected function prepareBaseUrl() {
        $filename = basename($this->server->get('SCRIPT_FILENAME', ''));

        if(basename($this->server->get('SCRIPT_NAME', '')) === $filename) {
            $baseUrl = $this->server->get('SCRIPT_NAME', '');
        } elseif(basename($this->server->get('PHP_SELF', '')) === $filename) {
            $baseUrl = $this->server->get('PHP_SELF', '');
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = $this->server->get('PHP_SELF', '');
            $file = $this->server->get('SCRIPT_FILENAME', '');
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/' . $seg . $baseUrl;
                ++$index;
            } while($last > $index && (false !== $pos = strpos($path, $baseUrl)) && 0 != $pos);
        }

        // Does the baseUrl have anything in common with the request_uri?
        $requestUri = $this->server->get('REQUEST_URI', '');

        if($baseUrl && false !== $prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl)) {
            // full $baseUrl matches
            return $prefix;
        }

        if($baseUrl && false !== $prefix = $this->getUrlencodedPrefix($requestUri, dirname($baseUrl))) {
            // directory portion of $baseUrl matches
            return rtrim($prefix, '/');
        }

        $truncatedRequestUri = $requestUri;
        if(false !== $pos = strpos($requestUri, '?')) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl);
        if(empty($basename) || !strpos(rawurldecode($truncatedRequestUri), $basename)) {
            // no match whatsoever; set it blank
            return '';
        }

        // If using mod_rewrite or ISAPI_Rewrite strip the script filename
        // out of baseUrl. $pos !== 0 makes sure it is not matching a value
        // from PATH_INFO or QUERY_STRING
        if(strlen($requestUri) >= strlen($baseUrl) && (false !== $pos = strpos($requestUri, $baseUrl)) && $pos !== 0) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * @return \Wave\Http\HeaderBag
     */
    public function getHeaders() {
        return $this->headers;
    }

    /**
     * @param \Wave\Http\HeaderBag $headers
     */
    public function setHeaders(HeaderBag $headers) {
        $this->headers = $headers;
    }

    /**
     * Returns the format for this request (e.g. html, json etc). This is denoted by the extension on the REQUEST_URI.
     * If no extension is present the default format is inherited.
     *
     * @return string
     */
    public function getFormat() {
        return $this->format;
    }

    /**
     * Sets the format for this request
     *
     * @param $format
     */
    public function setFormat($format) {
        $this->format = $format;
    }

    /**
     * @return Action
     */
    public function getAction() {
        return $this->action;
    }

    /**
     * @param Action $action
     */
    public function setAction(Action $action) {
        $this->action = $action;
    }

    /**
     * Returns the request as a string.
     *
     * @return string The request
     */
    public function __toString() {
        return
            sprintf('%s %s %s', $this->getMethod(), $this->getPathAndQueryString(), $this->server->get('SERVER_PROTOCOL')) . "\r\n" .
            $this->headers . "\r\n" .
            $this->getContent();
    }

    /**
     * Returns the request body content.
     *
     * @return string The request body content.
     */
    public function getContent() {
        if(null === $this->content) {
            $this->content = file_get_contents('php://input');
        }

        return $this->content;
    }

    /*
     * Returns the prefix as encoded in the string when the string starts with
     * the given prefix, false otherwise.
     *
     * @param string $string The urlencoded string
     * @param string $prefix The prefix not encoded
     *
     * @return string|false The prefix as it is encoded in $string, or false
     */
    private function getUrlencodedPrefix($string, $prefix) {
        if(0 !== strpos(rawurldecode($string), $prefix)) {
            return false;
        }

        $len = strlen($prefix);

        if(preg_match("#^(%[[:xdigit:]]{2}|.){{$len}}#", $string, $match)) {
            return $match[0];
        }

        return false;
    }

}