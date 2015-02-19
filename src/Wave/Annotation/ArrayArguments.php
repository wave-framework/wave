<?php



namespace Wave\Annotation;

use Wave\Annotation;

abstract class ArrayArguments extends Annotation {

    protected $parameters;
    protected $errors = array();

    public function __construct($key, $value, $from_class = null) {
        parent::__construct($key, $value, $from_class);

        $this->parameters = $this->parseParameters();

        $this->validate($from_class);
        if(!empty($this->errors)){
            throw new InvalidAnnotationException('Annotation format error, '.implode(', ', $this->errors), 0);
        }

        $this->build();
    }

    protected function validate($class){}
    protected function build(){}

    protected function parseParameters(){

        $arguments = array();
        foreach(explode(',', $this->value) as $argument){
            // attempt to explode the argument into a key:value
            list($k, $value) = explode(':', $argument) + array(null, null);
            // no value means it was just a plain argument, so just clean it and insert as normal
            $k = trim($k, ' \'"');
            if($value === null){
                $arguments[] = $k;
            }
            else {
                $arguments[strtolower($k)] = trim($value, ' \'"');
            }
        }

        return $arguments;

    }

    protected function acceptedKeys($keys) {
        foreach($this->parameters as $key => $value) {
            if (is_string($key) && !in_array($key, $keys)) {
                $this->errors[] = "Invalid parameter: \"$key\".";
            }
        }
    }

    protected function requiredKeys($keys) {
        foreach($keys as $key) {
            if(!array_key_exists($key, $this->parameters)) {
                $this->errors[] = get_class($this) . " requires a '$key' parameter.";
            }
        }
    }

    protected function acceptedKeylessValues($values) {
        foreach($this->parameters as $key => $value) {
            if(!is_string($key) && !in_array($value, $values)) {
                $this->errors[] = "Unknown parameter: \"$value\".";
            }
        }
    }

    protected function acceptedIndexedValues($index, $values, $optional = true) {
        if($optional && !isset($this->parameters[$index])) return;

        if(!in_array($this->parameters[$index],$values)) {
            $this->errors[] = "Parameter $index is set to \"" . $this->parameters[$index] . "\". Valid values: " . implode(', ', $values) . '.';
        }
    }

    protected function acceptsNoKeylessValues() {
        $this->acceptedKeylessValues(array());
    }

    protected function acceptsNoKeyedValues() {
        $this->acceptedKeys(array());
    }


    protected function minimumParameterCount($count) {
        if( ! (count($this->parameters) >= $count) ) {
            $this->errors[] = get_class($this) . " takes at least $count parameters.";
        }
    }

    protected function maximumParameterCount($count) {
        if( ! (count($this->parameters) <= $count) ) {
            $this->errors[] = get_class($this) . " takes at most $count parameters.";
        }
    }

    protected function exactParameterCount($count) {
        if ( count($this->parameters) != $count ) {
            $this->errors[] = get_class($this) . " requires exactly $count parameters.";
        }
    }


}