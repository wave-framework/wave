<?php

namespace Wave\Http;

use InvalidArgumentException;
use Wave\Config;

class Request {

    const METHOD_HEAD = 'HEAD';
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_PATCH = 'PATCH';

    const METHOD_CLI = 'CLI';

    const TYPE_FORM_ENCODED = 'application/x-www-form-urlencoded';
    const TYPE_JSON = 'application/json';
    const TYPE_MULTIPART = 'multipart/form-data';

    private $url;

    private $method;

    private $format;

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


    public function __construct($url, $method = self::METHOD_GET,
                                array $query = array(),
                                array $parameters = array(),
                                array $attributes = array(),
                                array $server = array()){

        $this->setUrl($url);
        $this->setMethod($method);

        $this->query = new ParameterBag($query);
        $this->parameters = new ParameterBag($parameters);
        $this->attributes = new ParameterBag($attributes);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());

    }

    /**
     * Creates a new Request based on the PHP globals.
     *
     * @return Request
     */
    public static function createFromGlobals(){

        $url = 'http://localhost';
        if(isset($_SERVER['HTTP_HOST'])){
            $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
            $url = sprintf('%s://%s', $protocol, $_SERVER['HTTP_HOST']);
        }
        if(isset($_SERVER['SERVER_PORT']) && !in_array($_SERVER['SERVER_PORT'], array(80, 443))){
            $url .= ':' . $_SERVER['SERVER_PORT'];
        }

        if(isset($_SERVER['PATH_INFO'])){
            $url .= substr($_SERVER['PATH_INFO'], strpos($_SERVER['PATH_INFO'], '.php/'));
            if(isset($_SERVER['QUERY_STRING']))
                $url .= '?' . $_SERVER['QUERY_STRING'];
        }
        else if(isset($_SERVER['REQUEST_URI'])) {
            $url .= $_SERVER['REQUEST_URI'];
        }

        $parameters = array();
        $method = static::METHOD_CLI;
        if(isset($_SERVER['REQUEST_METHOD'])){
            $method = strtoupper($_SERVER['REQUEST_METHOD']);
            if('POST' === $method && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])){
                $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
            switch($method){
                case static::METHOD_POST:
                case static::METHOD_PATCH:
                case static::METHOD_PUT:
                    if(isset($_SERVER['CONTENT_TYPE']))
                        $parameters = static::parseRequestBody($_SERVER['CONTENT_TYPE']);
                    else
                        $parameters = $_POST;
                    break;
                case static::METHOD_HEAD:
                case static::METHOD_GET:
                case static::METHOD_CLI:
                    break;
                default:
                    throw new InvalidArgumentException("Invalid HTTP method $method");
            }
        }

        return new static($url, $method, $_GET, $parameters, array(), $_SERVER);
    }

    /**
     * Parses the request body into a readable parameter set. Used by the createFromGlobals method
     *
     * @param string $content_type
     *
     * @return array
     */
    protected static function parseRequestBody($content_type = self::TYPE_FORM_ENCODED){
        list($content_type) = explode(';', $content_type);
        switch($content_type){
            case static::TYPE_JSON:
                $data = json_decode(file_get_contents('php://input'), true);
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
     * @param string       $url
     * @param string|null  $default
     *
     * @return string
     */
    protected static function parseFormat($url, $default = null){
        if(null === $default){
            $default = PHP_SAPI === 'cli' ? 'cli' : Config::get('wave')->response->default_format;
        }
        $path = pathinfo($url);

        return isset($path['extension']) ? $path['extension'] : $default;
    }

    /**
     * Returns an array dataset for this request, which is a merge of either the query or parameter sets and the
     * attribute set depending on the request method.
     *
     * @return array
     */
    public function getData(){
        switch($this->getMethod()){
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

    /**
     * This will search attribute, query and parameter sets for a specified argument
     *
     * @param $parameter
     *
     * @return mixed
     */
    public function get($parameter){

        foreach(array('attributes', 'query', 'parameters') as $property){
            if($this->$property->has($parameter)){
                return $this->$property->get($parameter);
            }
        }

        return null;
    }

    /**
     * Sets the full URL for this request. This will *not* update the server parameter bag.
     *
     * @param $url
     */
    public function setUrl($url){
        $this->components = parse_url($url);
        $this->url = $url;

        $this->format = static::parseFormat($this->getPath());
    }

    /**
     * Get the full URL of this request
     *
     * @return string
     */
    public function getUrl(){
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
        if(!in_array($method, array(
            static::METHOD_HEAD, static::METHOD_GET, static::METHOD_POST,
            static::METHOD_PUT, static::METHOD_DELETE, static::METHOD_CLI,
            static::METHOD_PATCH
        )))
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
    public function getComponent($component){
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
    public function getScheme(){
        return $this->getComponent('scheme');
    }

    /**
     * Get the host
     *
     * @return null|string
     */
    public function getHost(){
        return $this->getComponent('host');
    }

    /**
     * Get the port
     *
     * @return null|string
     */
    public function getPort(){
        return $this->getComponent('port');
    }

    /**
     * Get the path of the request. This is the part after the host without the querystring
     *
     * @return null|string
     */
    public function getPath(){
        return $this->getComponent('path');
    }


    /**
     * Get the raw querystring of the request
     *
     * @return null|string
     */
    public function getQueryString(){
        return $this->getComponent('query');
    }

    /**
     * Gets the path and query string component (basically the URL without the host)
     *
     * @return string
     */
    public function getPathAndQueryString(){
        return $this->getPath() . $this->getQueryString();
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

}