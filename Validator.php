<?php

class Wave_Validator {

	private $_search_paths = array();
	private $_schema;
	public $_data;
	public $_sanitized = array();
	
	private $_invalid_keys = array();
	
	private $_status;
	
	private $datatypeClassPrefix = 'Wave_Validator_Datatype_';
	private $datatypeClassDefault = 'Default';
		
	const RESULT_MISSING_KEYS = -10;
	const RESULT_INVALID = -11;
	const RESULT_DUPLICATES   = -12;
	const RESULT_VALID = 10;
	
	const INPUT_VALID = 99;
	const ERROR_MISSING = 'missing';
	const ERROR_DUPLICATE = 'duplicate';
	const ERROR_INVALID = 'invalid';
	const ERROR_TOO_SHORT = 'too_short';
	const ERROR_TOO_LONG = 'too_long';
	const ERROR_NOT_IN_ARRAY = 'not_member';
	const ERROR_NOT_EQUAL = 'not_equal';
	const ERROR_NOT_UNIQUE = 'not_unique';
	const ERROR_NOT_EXISTS = 'not_exists';
	
	const ERROR_NO_FILE = -20;
	
	const TYPE_INHERIT = 1;
	const TYPE_STRING  = 2;
	const TYPE_INTEGER = 3;
	const TYPE_ARRAY   = 4;
	
	private static $_schema_cache = array();

	public function __construct($data, $schema_path){
		//check schema exisits
		 if(!isset(self::$_schema_cache[$schema_path])){
			if(file_exists($schema_path)){
				self::$_schema_cache[$schema_path] = include $schema_path; // cache the included file to reduce memory usage
			}
			else {
				$this->_status = self::ERROR_NO_FILE;
				throw new Wave_Exception("Schema file '".$schema_path."' does not exist");
			}
		}
		$this->_data = $data;
		$this->_schema =& self::$_schema_cache[$schema_path];
	}
	
	public function validate(){
		
		foreach($this->_schema['fields'] as $field_name => $properties){
			
			if(!isset($properties['type']) || (!is_string($properties['type']) && !is_callable($properties['type'])))
                    throw new Wave_Exception('The type must be set for property '.$field_name);
			
			// if its a custom validator, do it first
			if(is_callable($properties['type']) && $properties['type'] instanceof Closure){
				$properties['type']($this);
			}
			else {

	            if(!isset($properties['required']) || !is_bool($properties['required']))
	                    throw new Wave_Exception('Required must be set for non-custom types on property '.$field_name);
	
				// Assume all properties required, or check if required is true
				if(!isset($properties['required']) || $properties['required'] === true){
					// key must exist in data array
					if(!$this->checkExistance($field_name)){
						$this->addError($field_name, $properties, self::ERROR_MISSING);
						continue;
					}
				}
				// if its not in the data array, or it is empty and it is not required, dont bother validating it.
				else if($properties['required'] === false && (!isset($this->_data[$field_name]) || empty($this->_data[$field_name]))){
					$this->_sanitized[$field_name] = null;
					continue;
				}
					
				// try load the validating class
	            if(class_exists($this->datatypeClassPrefix . ucfirst($properties['type']), true))
	                $validatorClass = $this->datatypeClassPrefix . ucfirst($properties['type']);
	            else 
	                $validatorClass = $this->datatypeClassPrefix . $this->datatypeClassDefault;
	
	            $validator = new $validatorClass($properties, $this->_data[$field_name]);
	            $result = $validator->validate();
	            $this->_sanitized[$field_name] = $validator->sanitize();
	            if($result !== Wave_Validator::INPUT_VALID)
	                $this->addError($field_name, $properties, $result);
			}
		}

		return (empty($this->_invalid_keys)) ? self::RESULT_VALID : self::RESULT_INVALID;
		
	}
	
	public function getSanitizedData(){
		return $this->_sanitized;
	}
	
	public function checkExistance($key){
		return isset($this->_data[$key]) && $this->_data[$key] !== "";
	}
	
	public function addError($field, $properties, $reason){
		$this->_invalid_keys[$field] = array('reason' => $reason, 'properties' => $properties);
	}

	public function getErrors(){
		return $this->_invalid_keys;
	}
	
	public function getSchema(){
		return $this->_schema;
	}
	
	public function addSearchPath($path){
		if (file_exists($path)){
			if (substr($path, -1, 1) != DS)
				$path .= DS;
			$this->_search_paths[] = $path;
		} else {
			throw new Wave_Exception("Directory does \"$path\" not exist");
		}
		
	}
	
	public function getField($fieldname){
		return $this->_schema['fields'][$fieldname];
	}

}

?>