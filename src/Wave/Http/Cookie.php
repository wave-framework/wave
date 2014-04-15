<?php

/*
 * This class is based substantially off the class of the same name in the
 * Symfony Http-Foundation package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with the Symfony Http-Foundation component
 */

namespace Wave\Http;

class Cookie {

    protected $name;
    protected $value;
    protected $domain;
    protected $expire;
    protected $path;
    protected $secure;
    protected $httpOnly;

    public function __construct($name, $value = null, $expire = 0, $path = '/', $domain = null, $secure = false, $httpOnly = true) {
        $this->name = $name;
        $this->value = $value;
        $this->domain = $domain;
        $this->expire = $expire;
        $this->path = empty($path) ? '/' : $path;
        $this->secure = !!$secure;
        $this->httpOnly = !!$httpOnly;
    }

    /**
     * Gets the name of the cookie.
     *
     * @return string
     *
     * @api
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Gets the value of the cookie.
     *
     * @return string
     *
     * @api
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * Gets the domain that the cookie is available to.
     *
     * @return string
     *
     * @api
     */
    public function getDomain() {
        return $this->domain;
    }

    /**
     * Gets the time the cookie expires.
     *
     * @return integer
     *
     * @api
     */
    public function getExpires() {
        return $this->expire;
    }

    /**
     * Gets the path on the server in which the cookie will be available on.
     *
     * @return string
     *
     * @api
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * Checks whether the cookie should only be transmitted over a secure HTTPS connection from the client.
     *
     * @return Boolean
     *
     * @api
     */
    public function isSecure() {
        return $this->secure;
    }

    /**
     * Checks whether the cookie will be made accessible only through the HTTP protocol.
     *
     * @return Boolean
     *
     * @api
     */
    public function isHttpOnly() {
        return $this->httpOnly;
    }

    /**
     * Whether this cookie is about to be cleared
     *
     * @return Boolean
     *
     * @api
     */
    public function isCleared() {
        return $this->expire < time();
    }
}