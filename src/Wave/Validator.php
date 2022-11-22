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

class Validator implements ArrayAccess
{

    const CONSTRAINT_CLASS_MASK = "\\Wave\\Validator\\Constraints\\%sConstraint";

    private static $use_exceptions = false;
    private static $null_cleaned = true;

    private static $_schema_cache = array();

    private $parent_instance = null;
    private $schema;
    private $input;
    private $aliases = array();
    private $options = array(
        'strict' => true,
    );
    private $cleaned = array();
    private $violations = array();

    /**
     * @var array
     * @deprecated Errors can be found in the Validator\Result object passed back from Validator::validate()
     */
    public static $last_errors = array();

    public static function useExceptions($use_exceptions = false)
    {
        self::$use_exceptions = $use_exceptions;
    }

    public static function nullCleaned($null_cleaned = true)
    {
        self::$null_cleaned = $null_cleaned;
    }


    /**
     * @param string $schema A schema file to load from the configured schema path
     * @param array $input
     *
     * @return array|bool|Result
     * @throws InvalidConstraintException
     * @throws InvalidInputException
     * @throws ValidationException
     *
     */
    public static function validate($schema, array $input)
    {

        $instance = new self($input, self::getSchema($schema));
        if ($instance->execute()) {
            return new Result($instance->getCleanedData(), $instance->getViolations());
        } else {
            throw new InvalidInputException($instance->getViolations());
        }

    }

    public static function getSchema($schema)
    {
        if (!array_key_exists($schema, self::$_schema_cache)) {
            $schema_name = strtr($schema, '_', DIRECTORY_SEPARATOR);
            $schema_file = sprintf(Config::get('wave')->schemas->file_format, $schema_name);
            $schema_path = Config::get('wave')->path->schemas . $schema_file;

            if (is_file($schema_path) && is_readable($schema_path)) {
                $schema_data = include $schema_path;

                if (!array_key_exists('fields', $schema_data))
                    throw new InvalidArgumentException("$schema must have a 'fields' definition");

                self::$_schema_cache[$schema] = &$schema_data;
            } else {
                throw new ValidationException("Could not load schema [$schema] from file ($schema_path)");
            }
        }

        return self::$_schema_cache[$schema];

    }

    private static function translateConstraintKeyToClass($key)
    {
        return sprintf(self::CONSTRAINT_CLASS_MASK, str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    }

    /**
     * @param array $input The data to validate against
     * @param array $schema The schema to validate against
     * @param Validator $parent_instance
     */
    public function __construct(array $input, array $schema, $parent_instance = null)
    {
        $this->input = $input;
        $this->schema = $schema['fields'];

        if (array_key_exists('aliases', $schema)) {
            $this->aliases = $schema['aliases'];
        }

        if (array_key_exists('options', $schema)) {
            $this->options = array_merge($this->options, $schema['options']);
        }

        $this->parent_instance = $parent_instance;
    }

    public function execute()
    {

        foreach ($this->schema as $field_name => $definition) {

            // check if the field was supplied using one of its aliases
            $field_alias = $field_name;
            if (!isset($this->input[$field_name]) && isset($this->aliases[$field_name])) {
                if (!is_array($this->aliases[$field_name]))
                    $this->aliases[$field_name] = array($this->aliases[$field_name]);

                foreach ($this->aliases[$field_name] as $alias) {
                    if (isset($this->input[$alias])) {
                        $field_alias = $alias;
                        break;
                    }
                }
            }

            if (self::$null_cleaned) {
                $this->setCleanedData($field_name, null);
            }

            $is_required = !(isset($definition['required']) && is_bool($definition['required']) && !$definition['required']);
            $message = 'This field is required';
            if (isset($definition['required'])) {
                if (is_array($definition['required']) && isset($definition['required']['value'], $definition['required']['message'])) {
                    $message = $definition['required']['message'];
                    $definition['required'] = $definition['required']['value'];
                }

                if (is_callable($definition['required'])) {
                    $is_required = call_user_func($definition['required'], $this);
                } else if (is_string($definition['required'])) {
                    $is_required = isset($this->input[$definition['required']]);
                }
            }

            // A default value of null implies allow_null => true so prepend that constraint to the definition
            if (array_key_exists('default', $definition) && $definition['default'] === null) {
                // use array_replace to get the allow_null early in the definition order
                $definition = array_replace(array('allow_null' => true), $definition);
            }

            // if the field is_required and not supplied then fail with a violation,
            // otherwise attempt to use a default if there is one specified, skipping the remaining
            // constraints if that is the case
            $input_present = array_key_exists($field_alias, $this->input);
            $is_strict = isset($definition['strict']) ? $definition['strict'] : $this->options['strict'];
            if (!$is_strict && $input_present) {
                $empty_string = is_string($this->input[$field_alias]) && strlen($this->input[$field_alias]) <= 0;
                $empty_array = is_array($this->input[$field_alias]) && empty($this->input[$field_alias]);
                if ($empty_string || $empty_array) {
                    unset($this->input[$field_alias]);
                    $input_present = false;
                }
            }

            if (!$input_present) {
                if ($is_required) {
                    $this->addViolation($field_alias, array(
                        'field_name' => $field_alias,
                        'reason' => 'missing',
                        'message' => $message
                    ));
                    continue;
                } else {
                    if (array_key_exists('default', $definition)) {
                        if (is_callable($definition['default'])) {
                            $this->setCleanedData($field_name, call_user_func($definition['default'], $this));
                        } else {
                            $this->setCleanedData($field_name, $definition['default']);
                        }
                    } else {
                        continue;
                    }
                }
            } else {
                $this->setCleanedData($field_name, $this->input[$field_alias]);
            }

            unset($definition['required'], $definition['strict'], $definition['default']);
            foreach ($definition as $constraint => $arguments) {
                //if($constraint === 'required') continue;
                $handler = self::translateConstraintKeyToClass($constraint);
                if (!class_exists($handler))
                    throw new InvalidConstraintException("Handler for '$constraint' does not exist");

                /** @var $instance \Wave\Validator\Constraints\AbstractConstraint */
                $instance = new $handler($field_name, $arguments, $this);
                if (!$instance->evaluate()) {
                    $violations = $instance->getViolationPayload();
                    if (!empty($violations)) {
                        $this->addViolation($field_alias, $violations);
                        $this->unsetCleanedData($field_name, true);
                    }
                    break;
                }

                if ($instance instanceof CleanerInterface) {
                    $this->setCleanedData($field_name, $instance->getCleanedData());
                }
            }
        }

        return empty($this->violations);
    }

    /**
     * @param string $field the name of the field with the violation
     * @param array $payload information about the violation
     */
    public function addViolation($field, array $payload)
    {
        $this->violations[$field] = $payload;
    }

    /**
     * @param string $field the name of the field with the violation
     * @return
     */
    public function getViolation($field_name)
    {
        $field_alias = $field_name;
        if (!isset($this->violations[$field_name]) && isset($this->aliases[$field_name])) {
            if (!is_array($this->aliases[$field_name]))
                $this->aliases[$field_name] = array($this->aliases[$field_name]);

            foreach ($this->aliases[$field_name] as $alias) {
                if (isset($this->violations[$alias])) {
                    return $this->violations[$alias];
                }
            }
        }

        return isset($this->violations[$field_alias]) ? $this->violations[$field_alias] : null;
    }

    public function getViolations()
    {
        return $this->violations;
    }

    public function getSchemaKey($key)
    {
        if (array_key_exists($key, $this->schema))
            return $this->schema[$key];

        return null;
    }

    public function setCleanedData($field, $value)
    {
        $this->cleaned[$field] = $value;
    }

    public function unsetCleanedData($field, $remove = false)
    {
        if ($remove) unset($this->cleaned[$field]);
        else $this->cleaned[$field] = null;
    }

    public function getCleanedData()
    {
        return $this->cleaned;
    }

    public function getInputData($key = null)
    {
        if ($key === null)
            return $this->input;
        elseif (isset($this->input[$key]))
            return $this->input[$key];
        else return null;
    }

    public function hasInputData($key)
    {
        return array_key_exists($key, $this->input);
    }

    public function getParentInstance()
    {
        return $this->parent_instance;
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->cleaned);
    }

    public function offsetGet($offset): mixed
    {
        return $this->cleaned[$offset];
    }

    public function offsetGetOrNull($offset)
    {
        return isset($this->cleaned[$offset]) ? $this->cleaned[$offset] : null;
    }

    public function offsetSet($offset, $value): void
    {
        throw new \BadMethodCallException("Setting validator input data is not supported");
    }

    public function offsetUnset($offset): void
    {
        throw new \BadMethodCallException("Unsetting validator input data is not supported");
    }

    private function valueIsNullish($input)
    {
        return is_null($input)                              // actually null
            || (is_string($input) && strlen($input) <= 0)   // an empty string (from a query string)
            || (is_array($input) && empty($input));         // an empty array (from a query string)
    }

}