<?php

namespace Wave;

abstract class Annotation {
	
	const FOR_CLASS = 'class';
	
	const CLASS_CONTROLLER = '\\Wave\\Controller';
	const CLASS_MODEL = '\\Wave\\Model';
	
	public static function parse($block, $originating_class){
		preg_match_all('%(?:\s|\*)*~(\S+)[^\n\r\S]*(?:(.*?)(?:\*/)|(.*))%', $block, $result, PREG_PATTERN_ORDER);
		
		$annotations = $result[1];
		if(isset($result[2][0]) && $result[2][0] != '') {
			$values = $result[2];
		} else { 
			$values = $result[3];
		}
		$returns = array();
		if(empty($result[1])) return array();
		foreach($annotations as $key => $annotation) {
			// Strip Whitespace
			$value = preg_replace('/\s*(\(|:|,|\))[^\n\r\S]*/', '${1}', '(' . $values[$key] . ')');
			// Extract Strings
			preg_match_all('/\'(.*?)(?<!\\\\)\'|"(.*?)(?<!\\\\)"/', $value, $result, PREG_PATTERN_ORDER);
			$quoted_strings = $result[2];
			$value = preg_replace('/\'.*?(?<!\\\\)\'|".*?(?<!\\\\)"/', '%s', $value);
			// Insert Single Quotes
			$value = preg_replace('/((?!\(|,|:))(?!\))(.*?)((?=\)|,|:))/', '${1}\'${2}\'${3}', $value);
			// Array Keyword
			$value = str_replace('(','array(',$value);
			// Arrows
			$value = str_replace(':', '=>', $value);
			
			$value = vsprintf($value . ';', $quoted_strings);
			
			@eval('$array = ' . $value);
			if(!isset($array)) { 
				throw new \Wave\Exception('There is an unparseable annotation value: "~' . $annotation . ': ' . $values[$key] . '"',0);
			}
			
			$annotationClass = 'Wave\\Annotation\\' . $annotation;
					
			if(class_exists($annotationClass, true)) {
				$annotation = new $annotationClass;
				$annotation->init($array)
					->validate($originating_class);
	
				if(isset($annotation->errors)){
					throw new \Wave\Exception('Annotation format error, '.implode(', ', $annotation->errors), 0);
				}
				else{
					$annotation->build();
				}
			} else {
				throw new \Wave\Exception('Unknown annotation: "' . $annotation . '"',0);
			}
			
			$returns[] = $annotation;
		}
		
		return $returns;
	}

	public function init($parameters) {
		$this->parameters = array_change_key_case($parameters, CASE_LOWER);
		return $this;
	}
	
	public function build() {} 
	abstract public function apply(Router\Action &$action);
	
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
	
	protected function validOnSubclassesOf($annotatedClass, $baseClass) {
		if( $annotatedClass != $baseClass && !is_subclass_of($annotatedClass, $baseClass) ) {
			$this->errors[] = get_class($this) . " is only valid on objects of type $baseClass.";
		}
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


?>