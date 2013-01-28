<?php

/**
 *	Base model, common functionality
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave;

class Model {

	protected $_data	= array();
	protected $_dirty	= array();
	protected $_loaded	= false;
	protected $_joined_objects = array();

	public function __construct($data = null){

		foreach(self::_getFields() as $field){
			$this->_data[$field] = isset($data[$field]) ? $data[$field] : self::getFieldDefault($field);
		}

	}
	
	public static function createFromArray($data){
		
		foreach(self::_getIdentifyingColumns() as $required_column){
			if(!isset($data[$required_column]) || empty($data[$required_column]))
				return null;
		}
				
		return new static($data);
	
	}
	
	public static function getFieldDefault($field_name){
		$field = self::_getField($field_name);
		return \Wave\DB::get(self::_getDatabaseNamespace())->valueFromSQL($field['default'], $field);
	}
	
	public static function loadByID(){
		
		$stmt = Wave\DB::get(self::_getDatabaseNamespace())->from(get_called_class());
		
		foreach(self::_getIdentifyingColumns() as $index => $column)
			$stmt->where("$column = ?", func_get_arg($index));
			
		return $stmt->fetchRow();	
	}
	
	public function save($save_relations = true){
		return Wave\DB::save($this, $save_relations);
	}
	
	public function delete(){
		return Wave\DB::delete($this);
	}
	
	public function addJoinedObject(&$object, $alias){
        if(!isset($this->_joined_objects[$alias]))
            $this->_joined_objects[$alias] = array();

        $this->_joined_objects[$alias][] = $object;
	}
		
	//assumption for tables with an ID, would be overloaded if the table's ID column was just 'id'
	public function getid(){
		return $this->_data[self::_getTableName().'_id'];
	}
	
	public function _getData(){
		return $this->_data;
	}
	
	public function _getDirty(){
		return array_intersect_key($this->_data, $this->_dirty);
	}

	public static function _getTableName(){
		return static::$_table_name;
	}
	
	public static function _getDatabaseName(){
		return static::$_schema_name;
	}
	
	public static function _getDatabaseNamespace(){
		return static::$_database;
	}
	
	public static function _getFields($field_data = false){
		return $field_data ? static::$_fields : array_keys(static::$_fields);
	}
	
	public static function _getField($field_name){
		return static::$_fields[$field_name];
	}
	
	public static function _getRelations(){
		return static::$_relations;
	}
	
	public static function _getRelation($relation_name){
		
		if(!self::isRelation($relation_name))
			throw new Wave\Exception(sprintf('Invalid relation: [%s]', $relation_name));
			
		return static::$_relations[$relation_name];
	}
	
	public static function isRelation($relation_name){
		
		return isset(static::$_relations[$relation_name]);
	}

    public function _getJoinedObjects(){
        return $this->_joined_objects;
    }
	
	//returns whether this was loaded from db
	public function _setLoaded($loaded = true){
		//at this point it won't be dirty.
		$this->_dirty = array();
		return $this->_loaded = $loaded;
	}
	
	//returns whether this was loaded from db
	public function _isLoaded(){
		return $this->_loaded;
	}

	//Returns dirty status for if fields have been modified
	public function _isDirty(){
		return count($this->_dirty) !== 0;
	}
	
	public static function _getPrimaryKey(){
		foreach(static::$_constraints as $constraint)
			if($constraint['type'] === Constraint::TYPE_PRIMARY)
				return $constraint['fields'];

		return null;
	}

	public function _getIdentifyingData(){
		$columns = self::_getIdentifyingColumns();
		return array_intersect_key($this->_data, array_flip($columns));
	}
	
	public static function _getIdentifyingColumns(){
		//first PK
		foreach(static::$_constraints as $constraint){
			if($constraint['type'] === Constraint::TYPE_PRIMARY)
				return $constraint['fields'];
			elseif($constraint['type'] === Constraint::TYPE_UNIQUE)
				$unique = $constraint['fields'];
		}
		//then unique if no return for primary
		if(isset($unique))
			return $unique;
		
		//then all (if no keys are set for some reason) @todo - throw apropriate error
		return self::_getFields();
	}
	
	//After a lot of consideration, benefits > small performance hit.
	public function __set($property, $data){
		
		$setter = self::_getSetter($property);
		if(method_exists($this, $setter)){
			if($this->$property === $data)
				return $data;
			
			$this->_dirty[$property] = true;
			return $this->$setter($data);
		} 
			
		return $this->$property = $data;
	
	}
	
	public function __get($property){
		$getter = self::_getGetter($property);
		if(!method_exists($this, $getter)){
			$stack = debug_backtrace(false);
			$stack = array_shift($stack);
			trigger_error('Notice: Undefined property '. get_called_class() . '::' . $property . ' in ' . $stack['file'] . ' on line ' . $stack['line'] . " (via by Wave\DB_Model::__get())\n");
		}else {
			return $this->$getter();
		}
				
	}
	
	public function __isset($property){
		return method_exists($this, self::_getGetter($property));
	}	
	
	private static function _getSetter($property){
		return 'set' . $property;
	}
	
	private static function _getGetter($property){
		return 'get' . $property;
	}
	
	
}