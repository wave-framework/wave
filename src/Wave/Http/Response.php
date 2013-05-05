<?php


namespace Wave\Http;

use Wave\Config;
use Wave\Http\Exception\InvalidResponseFormatException;

class Response {

    const STATUS_OK 			= 200;
    const STATUS_CREATED 		= 201;
    const STATUS_ACCEPTED		= 202;
    const STATUS_MOVED_PERMANENTLY = 301;
    const STATUS_FOUND          = 302;
    const STATUS_NOT_MODIFIED 	= 304;
    const STATUS_MOVED_TEMPORARILY = 307;
    const STATUS_BAD_REQUEST 	= 400;
    const STATUS_UNAUTHORISED 	= 401;
    const STATUS_FORBIDDEN		= 403;
    const STATUS_NOT_FOUND		= 404;
    const STATUS_NOT_ACCEPTABLE = 406;
    const STATUS_SERVER_ERROR	= 500;
    const STATUS_NOT_IMPLEMENTED= 501;

    const FORMAT_HTML = 'html';
    const FORMAT_JSON = 'json';
    const FORMAT_XML = 'xml';
    const FORMAT_DIALOG = 'dialog';
    const FORMAT_CLI = 'cli';


    private static $formats = array(
        'plain' => '\\Wave\\Http\\Response',
        'html'  => '\\Wave\\Http\\Response\\HtmlResponse',
        'json'  => '\\Wave\\Http\\Response\\JsonResponse',
        'xml'   => '\\Wave\\Http\\Response\\XmlResponse',
        'dialog'=> '\\Wave\\Http\\Response\\JsonResponse',
    );

    public static $statusTexts = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC-reschke-http-status-308-07
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        422 => 'Unprocessable Entity',                                        // RFC4918
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Reserved for WebDAV advanced collections expired proposal',   // RFC2817
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)',                      // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    );

    /**
     * The content to send in this response. Typically this is a string but it could be
     * any scalar value when used in conjunction with other response methods.
     * @var mixed $content
     */
    protected $content;

    /**
     * The response format (html, json, xml etc) to respond with
     * @var string $format
     */
    protected $format;

    /**
     * Holds the HTTP status code for this response
     * @var int $status
     */
    protected $statusCode;

    /**
     * The text representation of the status code
     * @var string $statusText
     */
    protected $statusText;

    /**
     * Holds the headers to be sent with this response
     * @var \Wave\Http\HeaderBag
     */
    protected $headers;

    protected $version = '1.1';


    public function __construct($content = '', $status = self::STATUS_OK, $format = null, array $headers = array()){
        $this->setContent($content);
        $this->setHeaders(new HeaderBag($headers));

        $this->setStatusCode($status);
        $this->setFormat($format);
    }

    /**
     * @param \Wave\Http\Response $class
     *
     * @throws \InvalidArgumentException
     */
    public static function registerFormat($class){
        if(!in_array(__CLASS__, class_parents($class))){
            throw new \InvalidArgumentException("Response format must extend ".__CLASS__, 500);
        }

        $extension = $class::getExtension();
        static::$formats[$extension] = $class;
    }

    public static function getExtension(){
        static $default = null;
        if($default === null)
            $default = Config::get('wave')->response->default_format;

        return $default;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public static function createFromRequest(Request $request) {

        $path = pathinfo($request->getPath());
        $format = self::getExtension();
        if(isset($path['extension']) && array_key_exists($path['extension'], static::$formats))
            $format = $path['extension'];

        $response_class = static::$formats[$format];
        return new $response_class(null, self::STATUS_OK, $format);
    }

    public function prepare(Request $request){
        $this->headers->set('X-Wave-Response', $this->format);

        return $this;
    }

    public function send(){
        $this->sendHeaders();
        $this->sendContent();

        return $this;
    }

    public function sendHeaders() {
        if (headers_sent()) { return $this; }

        // status
        header(sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText));

        // headers
        foreach ($this->headers->all() as $name => $values) {
            foreach ($values as $value) {
                header($name.': '.$value, false);
            }
        }

        return $this;
    }

    public function sendContent(){

        echo $this->content;

        return $this;
    }

    /**
     * @param $format
     *
     * @throws Exception\InvalidResponseFormatException
     */
    private function setFormat($format) {
        if($format === null){
            $format = Config::get('wave')->response->default_method;
        }
        if(!array_key_exists($format, self::$formats)) {
            throw new InvalidResponseFormatException("Format $format is not a recognised response format");
        }
        $this->format = $format;
    }

    public static function getFormats() {
        return self::$formats;
    }

    public static function getMessageForCode($_status) {
        if(array_key_exists($_status, static::$statusTexts)){
            return static::$statusTexts[$_status];
        }
        return '';
    }

    public function getFormat() {
        return $this->format;
    }

    public function setContent($content) {
        $this->content = $content;
    }

    public function setStatusCode($code, $text = null) {
        $this->statusCode = (int)$code;

        if(null === $this->statusText = $text)
            $this->statusText = isset(self::$statusTexts[$code]) ? self::$statusTexts[$code] : '';

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