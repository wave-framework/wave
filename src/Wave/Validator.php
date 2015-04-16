<?php

namespace Wave;

use ArrayAccess;
use InvalidArgumentException;
use Wave\Config;
use Wave\Validator\CleanerInterface;
use Wave\Validator\Exception\InvalidConstraintException;
use Wave\Validator\Exception\InvalidInputException;
use Wave\Validator\Exception\ValidationException;
use Wave\Validator\Result;

class Validator implements ArrayAccess {

    const CONSTRAINT_CLASS_MASK = "\\Wave\\Validator\\Constraints\\%sConstraint";

    private static $use_exceptions = false;

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
     * @param Validator $parent_instance
     */
    public function __construct(array $input, array $schema, $parent_instance = null) {
        $this->_data = $input;
        $this->_schema = $schema['fields'];

        if(array_key_exists('aliases', $schema)) {
            $this->aliases = $schema['aliases'];
        }

        $this->parent_instance = $parent_instance;
    }

    public function execute() {

        foreach($this->_schema as $field_name => $definition) {

            $field_alias = $field_name;
            if(!isset($this->_data[$field_name]) && isset($this->aliases[$field_name])) {
                if(!is_array($this->aliases[$field_name]))
                    $this->aliases[$field_name] = array($this->aliases[$field_name]);

                foreach($this->aliases[$field_name] as $alias) {
                    if(isset($this->_data[$alias])) {
                        $field_alias = $alias;
                        break;
                    }
                }
            }

            $this->setCleanedData($field_name, null);

            // manual check, if the field isn't in the data array throw an error
            // if it is a required field, otherwise just skip and continue validating the rest
            if(!isset($this->_data[$field_alias])
                || (is_string($this->_data[$field_alias]) && strlen($this->_data[$field_alias]) <= 0)
                || (is_array($this->_data[$field_alias]) && empty($this->_data[$field_alias]))
            ) {

                $is_required = !(isset($definition['required']) && is_bool($definition['required']) && !$definition['required']);
                $message = 'This field is required';
                if(isset($definition['required'])) {
                    if(is_array($definition['required']) && isset($definition['required']['value'], $definition['required']['message'])) {
                        $message = $definition['required']['message'];
                        $definition['required'] = $definition['required']['value'];
                    }
                    if(is_callable($definition['required']))
                        $is_required = call_user_func($definition['required'], $this);
                    elseif(is_string($definition['required']))
                        $is_required = isset($this->_data[$definition['required']]);

                }

                if($is_required) {
                    $this->addViolation(
                        $field_alias, array(
                            'field_name' => $field_alias,
                            'reason' => 'missing',
                            'message' => $message
                        )
                    );
                }
                // don't continue if there isn't a default value set
                if(!isset($definition['default']))
                    continue;
            }

            if(isset($this->_data[$field_alias]))
                $this->setCleanedData($field_name, $this->_data[$field_alias]);
            else if(isset($definition['default'])) {
                if(is_callable($definition['default']))
                    $this->setCleanedData($field_name, call_user_func($definition['default'], $this));
                else
                    $this->setCleanedData($field_name, $definition['default']);
            }

            foreach($definition as $constraint => $arguments) {
                if($constraint === 'required') continue;
                $handler = self::translateConstraintKeyToClass($constraint);
                if(!class_exists($handler))
                    throw new InvalidConstraintException("Handler for '$constraint' does not exist");

                /** @var $instance \Wave\Validator\Constraints\AbstractConstraint */
                $instance = new $handler($field_name, $arguments, $this);
                if(!$instance->evaluate()) {
                    $violations = $instance->getViolationPayload();
                    if(!empty($violations))
                        $this->addViolation($field_alias, $violations);

                    $this->setCleanedData($field_name, null);
                    break;
                }

                if($instance instanceof CleanerInterface) {
                    $this->setCleanedData($field_name, $instance->getCleanedData());
                }
            }
        }

        return empty($this->_violations);
    }

    /**
     * @param string $field the name of the field with the violation
     * @param array $payload information about the violation
     */
    public function addViolation($field, array $payload) {
        $this->_violations[$field] = $payload;
    }

    /**
     * @param string $field the name of the field with the violation
     * @return
     */
    public function getViolation($field_name) {
        $field_alias = $field_name;
        if(!isset($this->_violations[$field_name]) && isset($this->aliases[$field_name])) {
            if(!is_array($this->aliases[$field_name]))
                $this->aliases[$field_name] = array($this->aliases[$field_name]);

            foreach($this->aliases[$field_name] as $alias) {
                if(isset($this->_violations[$alias])) {
                    return $this->_violations[$alias];
                }
            }
        }

        return isset($this->_violations[$field_alias]) ? $this->_violations[$field_alias] : null;
    }

    public function getViolations() {
        return $this->_violations;
    }

    public function getSchemaKey($key) {
        if(array_key_exists($key, $this->_schema))
            return $this->_schema[$key];

        return null;
    }

    /**
     * @param string $schema A schema file to load from the configured schema path
     * @param array $input
     *
     * @param bool $use_result
     *
     * @throws \InvalidArgumentException
     * @throws Validator\Exception\InvalidInputException
     * @throws Validator\Exception\ValidationException
     * @return array|bool|\Wave\Validator\Result
     */
    public static function validate($schema, array $input, $use_result = false) {

        $instance = new self($input, self::getSchema($schema));
        $result = $instance->execute();

        if($use_result || $result){
            return new Result($instance->getCleanedData(), $instance->getViolations());
        }
        else if(self::$use_exceptions) {
            throw new InvalidInputException($instance->getViolations());
        }
        else {
            trigger_error('Deprecated use of validator. Validator::validate() will return a Wave\\Validator\\Result object soon', E_USER_DEPRECATED);
            return $result ? $instance->getCleanedData() : false;
        }

    }

    public static function getSchema($schema){
        if(!array_key_exists($schema, self::$_schema_cache)) {
            $schema_name = strtr($schema, '_', DIRECTORY_SEPARATOR);
            $schema_file = sprintf(Config::get('wave')->schemas->file_format, $schema_name);
            $schema_path = Config::get('wave')->path->schemas . $schema_file;

            if(is_file($schema_path) && is_readable($schema_path)) {
                $schema_data = include $schema_path;

                if(!array_key_exists('fields', $schema_data))
                    throw new InvalidArgumentException("$schema must have a 'fields' definition");

                self::$_schema_cache[$schema] = &$schema_data;
            } else {
                throw new ValidationException("Could not load schema [$schema] from file ($schema_path)");
            }
        }

        return self::$_schema_cache[$schema];

    }

    public static function useExceptions($use_exceptions = false) {
        self::$use_exceptions = $use_exceptions;
    }

    public function setCleanedData($field, $value) {
        $this->_cleaned[$field] = $value;
    }

    public function unsetCleanedData($field, $remove = false) {
        if($remove) unset($this->_cleaned[$field]);
        else $this->_cleaned[$field] = null;
    }

    public function getCleanedData() {
        return $this->_cleaned;
    }

    public function getInputData($key = null) {
        if($key === null)
            return $this->_data;
        elseif(isset($this->_data[$key]))
            return $this->_data[$key];
        else return null;
    }

    private static function translateConstraintKeyToClass($key) {
        return sprintf(self::CONSTRAINT_CLASS_MASK, str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    }

    public function getParentInstance() {
        return $this->parent_instance;
    }

    public function offsetExists($offset) {
        return array_key_exists($offset, $this->_cleaned);
    }

    public function offsetGet($offset) {
        return $this->_cleaned[$offset];
    }

    public function offsetSet($offset, $value) {
        throw new \BadMethodCallException("Setting validator input data is not supported");
    }

    public function offsetUnset($offset) {
        throw new \BadMethodCallException("Unsetting validator input data is not supported");
    }

}