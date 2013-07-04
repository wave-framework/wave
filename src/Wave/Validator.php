<?php

namespace Wave;

use Wave\Config,
    Wave\Validator\CleanerInterface,
    \ArrayAccess;
use Wave\Validator\Result;

class Validator implements ArrayAccess {

    const CONSTRAINT_CLASS_MASK = "\\Wave\\Validator\\Constraints\\%sConstraint";

    private static $_schema_cache = array();
    private $_schema;

    private $parent_instance = null;
    private $_data;
    private $_cleaned = array();
    private $_violations = array();

    /**
     * @var array
     * @deprecated Errors can be found in the Validator\Result object passed back from Validator::validate()
     */
    public static $last_errors = array();

    /**
     * @param array $input The data to validate against
     * @param array $schema The schema to validate against
     */
    public function __construct(array $input, array $schema, $parent_instance = null){
        $this->_data = $input;
        $this->_schema = $schema;

        $this->parent_instance = $parent_instance;
    }

    public function execute(){

        foreach($this->_schema as $field => $definition){
            $this->_cleaned[$field] = null;
            if(isset($this->_data[$field]))
                $this->_cleaned[$field] = $this->_data[$field];
            else if(isset($definition['default'])){
                if(is_callable($definition['default']))
                    $this->_cleaned[$field] = call_user_func($definition['default'], $this);
                else
                    $this->_cleaned[$field] = $definition['default'];
            }

            // manual check, if the field isn't in the data array throw an error
            // if it is a required field, otherwise just skip and continue validating the rest
            if(!isset($this->_data[$field])
                || (is_string($this->_data[$field]) && strlen($this->_data[$field]) <= 0)
                || (is_array($this->_data[$field]) && empty($this->_data[$field]))){

                $is_required = !(isset($definition['required']) && is_bool($definition['required']) && !$definition['required']);
                if(isset($definition['required'])){
                    if(is_callable($definition['required']))
                        $is_required = call_user_func($definition['required'], $this);
                    elseif(is_string($definition['required']))
                        $is_required = isset($this->_data[$definition['required']]);
                }
                if($is_required){
                    $this->addViolation($field, array(
                        'field_name' => $field,
                        'reason' => 'missing',
                        'message' => 'This field is required'
                    ));
                }
                // don't continue if there isn't a default value set
                if(!isset($definition['default']))
                    continue;
            }

            foreach($definition as $constraint => $arguments){
                if($constraint === 'required') continue;
                $handler = self::translateConstraintKeyToClass($constraint);
                if(!class_exists($handler))
                    throw new \Wave\Validator\Exception("Handler for '$constraint' does not exist");

                /** @var $instance \Wave\Validator\Constraints\AbstractConstraint */
                $instance = new $handler($field, $arguments, $this);
                if(!$instance->evaluate()){
                    $violations = $instance->getViolationPayload();
                    if(!empty($violations))
                        $this->addViolation($field, $violations);
                    $this->_cleaned[$field] = null;
                    break;
                }

                if($instance instanceof CleanerInterface){
                    $this->_cleaned[$field] = $instance->getCleanedData();
                }
            }
        }

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
     * @param string $field the name of the field with the violation
     * @return
     */
    public function getViolation($field){
        return isset($this->_violations[$field]) ? $this->_violations[$field] : null;
    }

    public function getViolations(){
        return $this->_violations;
    }

    /**
     * @param string $schema A schema file to load from the configured schema path
     * @param array $input
     * @param bool $use_result
     *
     * @throws Validator\Exception
     * @return array|bool|\Wave\Validator\Result
     */
    public static function validate($schema, array $input, $use_result = false){
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


        $result = $instance->execute();
        if($use_result){
            return new Result($instance->getCleanedData(), $instance->getViolations());
        }
        else {
            trigger_error('Deprecated use of validator. Validator::validate() will return a Wave\\Validator\\Result object soon', E_USER_DEPRECATED);
            return $result ? $instance->getCleanedData() : false;
        }
    }

    public function getCleanedData(){
        return $this->_cleaned;
    }

    private static function translateConstraintKeyToClass($key){
        return sprintf(self::CONSTRAINT_CLASS_MASK, str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    }

    public function getParentInstance(){
        return $this->parent_instance;
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