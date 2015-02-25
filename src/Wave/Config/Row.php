<?php

namespace Wave\Config;

use ArrayObject;

class Row extends ArrayObject {

    public function __construct(array $config) {

        foreach($config as $key => $value) {
            if(is_array($value)) {
                $value = new static($value);
            }
            $this->offsetSet($key, $value);
        }

    }

    public function __get($property) {
        if(isset($this[$property])) {
            return $this[$property];
        }
        throw new UnknownConfigOptionException("Unknown config option [$property]");
    }

    public function __isset($property) {
        return isset($this[$property]);
    }
}
