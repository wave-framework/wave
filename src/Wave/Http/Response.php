<?php


namespace Wave\Http;

use Wave\Config;
use Wave\Hook;

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
        'plain' => '\\Wave\\Http\\Response\\TextResponse',
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
    public $headers;

    protected $version;


    public function __construct($content = '', $status = self::STATUS_OK, array $headers = array()){

        $this->setHeaders(new HeaderBag($headers));
        $this->setContent($content);
        $this->setStatusCode($status);
        $this->setProtocolVersion('1.0');

    }


    public function prepare(Request $request){

        if ('HTTP/1.0' != $request->server->get('SERVER_PROTOCOL')) {
            $this->setProtocolVersion('1.1');
        }

        return $this;
    }

    public function send(){
        Hook::triggerAction('response.before_send', array(&$this));
        $this->sendHeaders();
        $this->sendContent();
        Hook::triggerAction('response.after_send', array(&$this));
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

    public static function getMessageForCode($_status) {
        if(array_key_exists($_status, static::$statusTexts)){
            return static::$statusTexts[$_status];
        }
        return '';
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

    public function setProtocolVersion($version) {
        $this->version = $version;
    }

    public function getProtocolVersion(){
        return $this->version;
    }

}