<?php

namespace Wave;

use Wave\Config,
    Wave\Validator\CleanerInterface,
    \ArrayAccess;

class Validator implements ArrayAccess {

    const CONSTRAINT_CLASS_MASK = "\\Wave\\Validator\\Constraints\\%sConstraint";

    private static $_schema_cache = array();
    private $_schema;

    private $_data;
    private $_cleaned = array();
    private $_violations = array();

    public static $last_errors = array();

    /**
     * @param array $input The data to validate against
     * @param array $schema The schema to validate against
     */
    public function __construct(array $input, array $schema){
        $this->_data = $input;
        $this->_schema = $schema;
    }

    public function execute($merge_violations = false){

        foreach($this->_schema as $field => $definition){
            $this->_cleaned[$field] = null;

            // manual check, if the field isn't in the data array throw an error
            // if it is a required field, otherwise just skip and continue validating the rest
            if(!isset($this->_data[$field]) || empty($this->_data[$field])){
                if(!isset($definition['required']) || $definition['required']){
                    $this->addViolation($field, array(
                        'field_name' => $field,
                        'reason' => 'missing',
                        'message' => 'This field is required'
                    ));
                }
                $this->_cleaned[$field] = null;
                continue;
            }

            $this->_cleaned[$field] = $this->_data[$field];

            foreach($definition as $constraint => $arguments){
                if($constraint === 'required') continue;
                $handler = self::translateConstraintKeyToClass($constraint);
                if(!class_exists($handler))
                    throw new \Wave\Validator\Exception("Handler for '$constraint' does not exist");

                /** @var $instance \Wave\Validator\Constraints\AbstractConstraint */
                $instance = new $handler($field, $arguments, $this);
                if(!$instance->evaluate()){
                    $this->addViolation($field, $instance->getViolationPayload());
                    $this->_cleaned[$field] = null;
                    break;
                }

                if($instance instanceof CleanerInterface){
                    $this->_cleaned[$field] = $instance->getCleanedData();
                }
            }
        }

        if($merge_violations)
            self::$last_errors = array_merge(self::$last_errors, $this->_violations);
        else
            self::$last_errors = $this->_violations;

        return empty($this->_violations);
    }

    /**
     * @param string $field the name of the field with the violation
     * @param array $payload information about the violation
     */
    public function addViolation($field, array $payload){
        $this->_violations[$field] = $payload;
    }

    /**
     * @param string $schema A schema file to load from the configured schema path
     * @param array $input
     *
     * @return array|bool
     * @throws Validator\Exception
     */
    public static function validate(array $input, $schema){
        self::$last_errors = array();

        if(!array_key_exists($schema, self::$_schema_cache)) {
            $schema_name = strtr($schema, '_', DIRECTORY_SEPARATOR);
            $schema_file = sprintf(Config::get('wave')->schemas->file_format, $schema_name);
            $schema_path = Config::get('wave')->path->schemas . $schema_file;

            if(is_file($schema_path) && is_readable($schema_path)){
                $schema_data = include $schema_path;
                self::$_schema_cache[$schema] = &$schema_data['fields'];
            }
            else {
                throw new Validator\Exception("Could not load schema [$schema] from file ($schema_path)");
            }
        }

        $instance = new self($input, self::$_schema_cache[$schema]);

        if($instance->execute())
            return $instance->getCleanedData();
        else
            return false;

    }

    public function getCleanedData(){
        return $this->_cleaned;
    }

    private static function translateConstraintKeyToClass($key){
        return sprintf(self::CONSTRAINT_CLASS_MASK, str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    }


    public function offsetExists($offset) {
        return array_key_exists($offset, $this->_cleaned);
    }

    public function offsetGet($offset) {
        return $this->_cleaned[$offset];
    }

    public function offsetSet($offset, $value) {
        throw new \Wave\Exception("Setting validator input data is not supported");
    }

    public function offsetUnset($offset) {
        throw new \Wave\Exception("Unsetting validator input data is not supported");
    }
}