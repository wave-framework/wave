<?php

class Wave_DB {

	private static $num_databases	= 0;
	private static $instances		= array();
	private static $default;
	
	private $connection;
	private $config;
	
	
	const NS_SEPARATOR		= '_';

	public function __construct($config){
	
		$this->connection = new Wave_DB_Connection($config);
		$this->config = $config;
		
		if(Wave_Core::$_MODE == Wave_Core::MODE_DEVELOPMENT)
			$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	
	
	public function from($table, $fields = null){
		return Wave_DB_Query::create($this)->from($table, $fields);
	}
	
	public static function save($object){
		
		if($object->_isLoaded()){
			if($object->_isDirty()){
				return Wave_DB::update($object);
			}
		} else {
			return Wave_DB::insert($object);		
		}
		
	}
	
	
	//CRUDE IMPLEMENTATION TO BE REPLACED   vvvvvvvv (euphemism?)
	public static function insert($object){
	
		$schema = $object::_getSchemaName();
		$db = self::get($schema)->getConnection();
	
		$table = $object::_getTableName();
		$data = $object->_getDataArray();
				
		$values = '';
		foreach($data as $key => $value){
			if($value === null)
				$values .= 'NULL,';
			elseif($value instanceof DateTime)
				$values .= '"'.$value->format('Y-m-d H:i:s').'",';
			else
				$values .= '"'.addslashes($value).'",';
		}
			
		$fields = '`'.implode('`,`', array_keys($data)).'`';
		$values = trim($values,',');
					
		$sql = "INSERT INTO `$table` ($fields) VALUES ($values)";
		
		// WHAT THE HELL, SOMEONE PUT A GROSS ON HERE
		
		$db->exec($sql);
		
		$liid = $db->lastInsertId();
		if($liid !== 0){
			$keys = $object::_getKeys(Wave_DB_Column::INDEX_PRIMARY);
			if(count($keys) === 1){
				$object->{$keys[0]} = $liid;
				$object->_setLoaded();			
			}
		}
			
		return true;
	}
	
	
	public static function update($object){
	
		$schema = $object::_getSchemaName();
		$db = self::get($schema)->getConnection();
	
		$table = $object::_getTableName();
		$data = $object->_getDirtyArray();
		$keys = $object::_getKeys(Wave_DB_Column::INDEX_PRIMARY);
		
		$sql = "UPDATE $table SET ";
		
		foreach($data as $key => $value){
			if(is_object($object->$key) || is_array($object->$key))
				continue;
				
			$sql .= "`$key` = '".addslashes($object->$key)."',";
		}
		//remove comma
		$sql = substr($sql, 0, strlen($sql)-1);
		
		$sql .= "WHERE ";
		
		foreach($keys as $key){
			$sql .= "`$key` = '{$object->$key}',";
		}
		//remove comma
		$sql = substr($sql, 0, strlen($sql)-1);
		
		return $db->exec($sql);

	
	}
	//FOR BOTH OF THESE ^^^^^^^^^^
	
	
	/**
	* Function to return the results of a basic query
	*/
	public function basicQuery($sql, $params = array()){
		
		if(!is_array($params))
			$params = array($params);
		
		$statement = $this->connection->prepare($sql);
		$statement->execute($params);
		
		//$statement->debugDumpParams();
			
		return $statement->fetchAll();
	}
	
	public function getConnection(){
		return $this->connection;
	}
	
	public function getName(){
		return $this->config->database;
	}
	
	public function getNamespace(){
		return $this->config->namespace;
	}
	
	public function isDefault(){
		return isset($database->default) && $database->default == true;
	}
	
	

	public static function init($config){
	
		$databases = $config->databases;
		
		$installed_drivers = Wave_DB_Connection::getAvailableDrivers();

		foreach($databases as $database){
		
			
			$driver_class = self::getDriverClass($database->driver);
			
			//Check PDO driver is installed on system
			if(!in_array($driver_class::getDriverName(), $installed_drivers))
				throw new Wave_DB_Exception(sprintf('PDO::%s driver not installed for %s.', $driver_class::getDriverName(), $driver_class));
			
			self::$instances[$database->namespace] = new self($database);
			
			/*
			* Define default database if it is either first or flagged as default.
			* First db is always flagged as default to avoid the case where there is no default, 
			* (it'll be overwritten by any db with the default flag).
			*/
			
			if(self::$num_databases == 0 || isset($database->default) && $database->default == true)
				self::$default = $database->namespace;
			
			self::$num_databases++;
			
		}
		
	}
	
	public static function get($database = null){
		
		//if no db spec, return default
		if($database === null)
			 $database = self::$default;
	
		return self::$instances[$database];
	}
	
	public static function getNumDatabases(){
		return self::$num_databases;
	}
	
	public static function getAllDatabases(){
		return self::$instances;
	}
	
	public static function tableNameToClass($table_name){
	
		$class_name = '';
		$parts = explode('_', $table_name);
		
		foreach($parts as $part)
			$class_name .= ucfirst($part);
			
		return $class_name;
	
	}
	
	public static function columnToRelationName($key, $target_table = ''){
	
		$column_name = $key['table_name'] == $key['referenced_table_name'] ? $key['column_name'] : $key['table_name'];
		$column_name = $target_table === '' ? $column_name : $target_table;
		
		
		$relation_name = '';
		$parts = explode('_', $column_name);
		
		foreach($parts as $part)
			$relation_name .= $part == 'id' ? '' : ucfirst($part);
			
		return $relation_name;
	
	}
	
	
	
	public static function getDriverClass($driver){
		return 'Wave_DB_Driver_'.$driver;
	}
	
	public static function getClassNameForTable($table, $database = null, $raw_table = false){
	
		if($raw_table)
			$table = self::tableNameToClass($table);
		
		if(strpos($table, self::NS_SEPARATOR) !== false)
			return $table;
		
		if(is_null($database))
			$database = self::get();
		
		$class_name = $database->getNameSpace().self::NS_SEPARATOR.$table;
		
		if(!class_exists($class_name))
			throw new Wave_DB_Exception("Class does not exist: $table");
		
		return $class_name;
	
	}
	
	public static function getFieldsForTable($table){
		
		$class_name = self::getClassNameForTable($table);
		
		$fields = $class_name::_getFields();
		
		return $fields;
		
	}
	
}