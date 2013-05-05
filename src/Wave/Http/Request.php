<?php

namespace Wave\Http;

use Guzzle\Http\Message\Header;
use InvalidArgumentException;

class Request {

    const METHOD_HEAD = 'HEAD';
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';

    const METHOD_CLI = 'CLI';

    const TYPE_FORM_ENCODED = 'application/x-www-form-urlencoded';
    const TYPE_JSON = 'application/json';
    const TYPE_MULTIPART = 'multipart/form-data';

    private $url;

    private $method;

    /**
     * Arguments passed in via a query string
     * @var array $query
     */
    private $query = array();

    /**
     * Parameters passed in via the request body
     * @var array $parameters
     */
    private $parameters = array();

    /**
     * The componets that make up the request URL
     * @var array $components
     */
    private $components = array();

    /**
     * The headers for this request
     * @var \Wave\Http\HeaderBag $headers
     */
    private $headers;


    public function __construct($url, $method = self::METHOD_GET,
                                array $query = array(), array $parameters = array(), array $headers = array()){

        $this->url = $url;
        $this->setMethod($method);
        $this->setQuery($query);
        $this->setParameters($parameters);
        $this->components = parse_url($url);
        $this->headers = new HeaderBag($headers);
    }

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

        $query = array();
        $parameters = array();
        $method = static::METHOD_CLI;
        if(isset($_SERVER['REQUEST_METHOD'])){
            $method = strtoupper($_SERVER['REQUEST_METHOD']);
            switch($method){
                case static::METHOD_POST:
                case static::METHOD_PUT:
                    if(isset($_SERVER['CONTENT_TYPE']))
                        $parameters = static::parseRequestBody($_SERVER['CONTENT_TYPE']);
                    else
                        $parameters = $_POST;

                case static::METHOD_HEAD:
                case static::METHOD_GET:
                case static::METHOD_DELETE:
                default:
                    $query = $_GET;
            }
        }

        $headers = static::parseHeaders($_SERVER);

        return new static($url, $method, $query, $parameters, $headers);
    }

    protected static function parseRequestBody($content_type = self::TYPE_FORM_ENCODED){
        list($content_type) = explode(';', $content_type);
        switch($content_type){
            case static::TYPE_JSON:
                $data = json_decode(file_get_contents('php://input'), true);
                if(!is_array($data)) return array();
                else return $data;
            case static::TYPE_FORM_ENCODED:
            case static::TYPE_MULTIPART:
            default:
                return $_POST;
        }

    }

    private static function parseHeaders($sent_headers = null) {

        if($sent_headers === null)
            $sent_headers = $_SERVER;

        $headers = array();
        foreach ($sent_headers as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[substr($key, 5)] = $value;
            }

            else if(in_array($key, array('CONTENT_LENGTH', 'CONTENT_MD5', 'CONTENT_TYPE'))) {
                $headers[$key] = $value;
            }
        }

        return $headers;

    }

    public function get($parameter){

        if(array_key_exists($parameter, $this->query)){
            return $this->query[$parameter];
        }
        else if(array_key_exists($parameter, $this->parameters)){
            return $this->query[$parameter];
        }
        else
            return null;

    }

    public function setUrl($url){
        $this->components = parse_url($url);
        $this->url = $url;
    }

    public function getUrl(){
        return $this->url;
    }

    public function getMethod() {
        return $this->method;
    }

    public function setMethod($method) {
        if(!in_array($method, array(
            static::METHOD_HEAD, static::METHOD_GET, static::METHOD_POST,
            static::METHOD_PUT, static::METHOD_DELETE, static::METHOD_CLI
        )))
            throw new InvalidArgumentException("Request method [$method] is not valid");

        $this->method = $method;
    }

    public function getQuery() {
        return $this->query;
    }

    public function setQuery($query) {
        $this->query = $query;
    }

    /**
     * Adds a query string parameter. Supports array notation for the $key parameter where
     * an existing query array might be array('foo' => array('bar')) with $key = 'foo[]' and
     * $value = 'qux' the new query array would be array('foo' => array('bar', 'qux'))
     *
     * @param string $key
     * @param string $value
     */
    public function addQueryParameter($key, $value){
        parse_str("{$key}={$value}", $parameter);
        $this->query = array_merge_recursive($this->query, $parameter);
    }

    public function getParameters() {
        return $this->parameters;
    }

    public function setParameters($parameters) {
        $this->parameters = $parameters;
    }

    public function getComponent($component){
        if(isset($this->components[$component]))
            return $this->components[$component];
        else
            return null;
    }

    public function getScheme(){
        return $this->getComponent('scheme');
    }

    public function getHost(){
        return $this->getComponent('host');
    }

    public function getPort(){
        return $this->getComponent('port');
    }

    public function getPath(){
        return $this->getComponent('path');
    }

    public function getQueryString(){
        return $this->getComponent('query');
    }

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

}