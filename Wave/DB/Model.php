<?php

namespace Wave\DB;
use Wave;

abstract class Model {

	const RELATION_ONE_TO_ONE	= 11;
	const RELATION_ONE_TO_MANY	= 12;
	const RELATION_MANY_TO_ONE	= 21;
	const RELATION_MANY_TO_MANY	= 22;
	
	
	protected $_data = array();
	protected $_relation_data = array();
	protected $_dirty = array();
	protected $_loaded = false;
	protected $_pk_null = true;
	
	public function __construct($data = null, $assoc_prefix = ''){
	
		$fields = self::_getFields();
		
		if(!is_null($data)){			
			$this->_loaded = true;
			//construct object form data
			foreach($fields as $field => $value){
				//if load fails, flag
				/*
				if(!isset($data[$assoc_prefix.$field])){
					$this->_loaded = false;
					continue;
				}
				*/
				
				//if(isset($data[$assoc_prefix.$field]) && $data[$assoc_prefix.$field] != '')

				
				$this->_pk_null = $data[$assoc_prefix.$field] === null ? $this->_pk_null : false;
				$this->_data[$field] = self::_castField($data[$assoc_prefix.$field], $value);
			}
		} else {
			foreach($fields as $field => $value){
				$this->_data[$field] = self::_castField($value['default'], $value);
			}
		}
		
		//flag the object as clean
		$this->_dirty = array();	
			
	}
	
	public function save($save_relations = true){
	
		return Wave\DB::save($this, $save_relations);
	}
	
	public function delete(){
	
		return Wave\DB::delete($this);
	}
	
	
	public function _getid(){
		return $this->_data[self::_getTableName().'_id'];
	}
	
	public function _getRelationData(){
		return $this->_relation_data;
	}
	
	public function _addRelationObject($type, $object){
	
		return $this->_relation_data[] = $object;
	
	}
	
	public function _removeRelationObject($type, $object){
		
		$key = array_search($object, $this->_relation_data);
		
		if($key !== false)
			unset($this->_relation_data[$key]);
	}

	
	public function _isLoaded(){
		return $this->_loaded;
	}
	
	public function _setLoaded($value = true){
		return $this->_loaded = $value;
	}

	public function _isDirty(){
		return count($this->_dirty) !== 0;
	}
	
	public function pkNull(){
		return $this->_pk_null;
	}
	
	public function _getDataArray(){
		return $this->_data;
	}	
	
	public function _getDirtyArray(){
		return $this->_dirty;
	}
	
	private static function _castField($data, $field){
	
		if($data === null || $data == 'NULL' || $data == '')
			return null;
		
		switch($field['data_type']){
		
			case Column::TYPE_BOOL:
				return (bool) $data;
		
			case Column::TYPE_INT:
				return (int) $data;
			
			case Column::TYPE_FLOAT:
				return (float) $data;
				
			case Column::TYPE_STRING:
				return (string) $data;
				
			case Column::TYPE_DATE:
			case Column::TYPE_TIMESTAMP:
				if($data == 'CURRENT_TIMESTAMP')
					$data = 'now';
				return new \DateTime($data);
		
			default:
				return $data;
		}
	}
	
	public static function _getTableName(){
		
		$called_class = get_called_class();
		return $called_class::$_table_name;
	}
	
	public static function _getSchemaName(){
		
		$called_class = get_called_class();
		return $called_class::$_schema_name;
	}
	
	public static function _getFields(){
		
		$called_class = get_called_class();
		return $called_class::$_fields;
	}
	
	public static function _getKeys($key_type){
		
		static $cache;
		
		if(!isset($cache[$key_type])){
			$called_class = get_called_class();
			$fields = $called_class::$_fields;
			
			$keys = array();
			foreach($fields as $field_name => $field){
				if($field['key'] === $key_type)
					$keys[] = $field_name;
			}
			$cache[$key_type] = $keys;
		}
				
		return $cache[$key_type];
			
	}
	
	public static function _getRelations(){
		
		$called_class = get_called_class();
		return $called_class::$_relations;
	}
	
	public static function _getRelationByName($name){
		
		$called_class = get_called_class();
		if(isset($called_class::$_relations[$name]))
			return $called_class::$_relations[$name];
		else
			return false;
	}
	
	//After a lot of consideration, benefits > small performance hit.
	public function __set($property, $data){
			
		if(method_exists($this, $this->getGetter($property)) && $this->$property === $data)
			return;
			
		$this->_dirty[$property] = true;
		
		if(method_exists($this, $this->getSetter($property)))
			return $this->{'_set'.$property}($data);
		else
			$this->$property = $data;
	
	}
	
	public function __get($property){
		$method = $this->getGetter($property);
		if(!method_exists($this, $method)){
			$stack = debug_backtrace(false);
			$stack = array_shift($stack);
			trigger_error('Notice: Undefined property '. get_called_class() . '::' . $property . ' in ' . $stack['file'] . ' on line ' . $stack['line'] . " (via by Wave\DB_Model::__get())\n");
		}
		else {
			return $this->$method();
		}
				
	}
	
	public function __isset($property){
		return method_exists($this, '_get'.$property);	
	}	
	
	public function _toArray(){
		$props = array();
		
		$fields = array_keys($this->_data);
		foreach($fields as $field){
			$props[$field] = $this->{'_get'.$field}();
		}
		
		return $props;
	}
	
	private function getSetter($property){
		return '_set' . $property;
	}
	
	private function getGetter($property){
		return '_get' . $property;
	}
	
}

?>