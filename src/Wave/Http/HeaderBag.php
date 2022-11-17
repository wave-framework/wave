<?php

/*
 * This class is based substantially off the class of the same name in the
 * Symfony Http-Foundation package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with the Symfony Http-Foundation component
 */

namespace Wave\Http;

/**
 * HeaderBag is a container for HTTP headers. Inspired by the HeaderBag from Symfony
 *
 */
class HeaderBag implements \IteratorAggregate, \Countable {
    protected $headers = array();

    /**
     * Constructor.
     *
     * @param array $headers An array of HTTP headers
     *
     * @api
     */
    public function __construct(array $headers = array()) {

        $this->add($headers);
    }

    /**
     * Returns the headers.
     * @return array An array of headers
     */
    public function all() {
        return $this->headers;
    }

    /**
     * Returns the parameter keys.
     * @return array An array of parameter keys
     */
    public function keys() {
        return array_keys($this->headers);
    }

    /**
     * Replaces the current HTTP headers by a new set.
     * @param array $headers An array of HTTP headers
     */
    public function replace(array $headers = array()) {
        $this->headers = array();
        $this->add($headers);
    }

    /**
     * Adds new headers the current HTTP headers set.
     *
     * @param array $headers An array of HTTP headers
     *
     * @api
     */
    public function add(array $headers) {
        foreach($headers as $key => $values) {
            $this->set($key, $values);
        }
    }

    /**
     * Returns a header value by name.
     *
     * @param string $key The header name
     * @param mixed $default The default value
     * @param Boolean $first Whether to return the first value or all header values
     *
     * @return string|array The first header value if $first is true, an array of values otherwise
     */
    public function get($key, $default = null, $first = true) {

        $key = strtr(strtolower($key), '_', '-');

        if(!array_key_exists($key, $this->headers)) {
            if(null === $default) {
                return $first ? null : array();
            }

            return $first ? $default : array($default);
        }

        if($first) {
            return count($this->headers[$key]) ? $this->headers[$key][0] : $default;
        }

        return $this->headers[$key];
    }

    /**
     * Sets a header by name.
     *
     * @param string $key The key
     * @param string|array $values The value or an array of values
     * @param Boolean $replace Whether to replace the actual value or not (true by default)
     *
     * @api
     */
    public function set($key, $values, $replace = true) {

        $key = strtr(strtolower($key), '_', '-');

        $values = array_values((array) $values);

        if(true === $replace || !isset($this->headers[$key])) {
            $this->headers[$key] = $values;
        } else {
            $this->headers[$key] = array_merge($this->headers[$key], $values);
        }
    }

    /**
     * Returns true if the HTTP header is defined.
     *
     * @param string $key The HTTP header
     *
     * @return Boolean true if the parameter exists, false otherwise
     *
     * @api
     */
    public function has($key) {
        return array_key_exists(strtr(strtolower($key), '_', '-'), $this->headers);
    }

    /**
     * Returns true if the given HTTP header contains the given value.
     *
     * @param string $key The HTTP header name
     * @param string $value The HTTP value
     *
     * @return Boolean true if the value is contained in the header, false otherwise
     *
     * @api
     */
    public function contains($key, $value) {
        return in_array($value, $this->get($key, null, false));
    }

    /**
     * Removes a header.
     *
     * @param string $key The HTTP header name
     *
     * @api
     */
    public function remove($key) {
        $key = strtr(strtolower($key), '_', '-');

        unset($this->headers[$key]);
    }

    /**
     * Returns an iterator for headers.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator(): \ArrayIterator {
        return new \ArrayIterator($this->headers);
    }

    /**
     * Returns the number of headers.
     *
     * @return int The number of headers
     */
    public function count(): int {
        return count($this->headers);
    }

    /**
     * Returns the headers as a string.
     *
     * @return string The headers
     */
    public function __toString() {
        if(!$this->headers) {
            return '';
        }

        $max = max(array_map('strlen', array_keys($this->headers))) + 1;
        $content = '';
        ksort($this->headers);
        foreach($this->headers as $name => $values) {
            $name = implode('-', array_map('ucfirst', explode('-', $name)));
            foreach($values as $value) {
                $content .= sprintf("%-{$max}s %s\r\n", $name . ':', $value);
            }
        }

        return $content;
    }

}