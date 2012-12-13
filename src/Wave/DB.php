<?php

namespace Wave;

class DB {

	private static $num_databases	= 0;
	private static $instances		= array();
	private static $default;
	
	private $connection;
	private $config;
	
	const GLOBAL_NAMESPACE  = 'Models';
	const NS_SEPARATOR		= '\\';

	public function __construct($config){
	
		$this->connection = new DB\Connection($config);
		$this->config = $config;

		if(in_array(Core::$_MODE, array(Core::MODE_DEVELOPMENT, Core::MODE_TEST)))
			$this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}
	
	
	public function from($table, $fields = null){
		return DB\Query::create($this)->from($table, $fields);
	}
	
	public static function save($object){
		
		if($object->_isLoaded()){
			if($object->_isDirty()){
				return self::update($object);
			}
		} else {
			return self::insert($object);		
		}
		
	}
	
	
	
	public static function insert($object){
	
		$table = $object::_getTableName();
		$data = array();
		$object_data = $object->_getDataArray();
		foreach(array_keys($object->_getFields()) as $field){
			if(isset($object_data[$field]))
				$data[$field] = $object_data[$field];
		}

		$schema = $object::_getSchemaName();
		$conn = self::get($schema)->getConnection();
		
		$params = array();
		$values = array_map(array($conn->getDriverClass(), 'convertValueForSQL'), array_values($data));
							
		$fields = implode('`,`', array_keys($data));
		$placeholders = implode(',', array_fill(0, count($values), '?'));
		
		$sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)', $table, $fields, $placeholders);
		
		$conn->prepare($sql)->execute($values);
		
		$liid = intval($conn->lastInsertId());
		if($liid !== 0){
			$keys = $object::_getKeys(DB\Column::INDEX_PRIMARY);
			if(count($keys) === 1){
				$object->{$keys[0]} = $liid;
				$object->_setLoaded();			
			}
		}
			
		return $object->_isLoaded();
	}
	
	public static function update($object){
		
		$keys = $object::_getKeys(DB\Column::INDEX_PRIMARY);
		$table = $object::_getTableName();
		$schema = $object::_getSchemaName();
		
		if(count($keys) === 0)
			throw new \Wave\Exception("No primary key defined for $schema.");
		
		$dirty = $object->_getDirtyArray();
		$data = $object->_getDataArray();
		$fields = $object->_getFields();
		
		$conn = self::get($schema)->getConnection();
		
		$updates = array();
		$params = array();
		$driver_class = $conn->getDriverClass();
		foreach($dirty as $key => $value){
			if(!isset($fields[$key])) continue;
			
			$updates[] = "`$key` = ?";
			$params[] = $driver_class::convertValueForSQL($data[$key]);
		}
		
		$where = array();
		foreach($keys as $key){
			$where[] = "`$key` = ?";
			$params[] = $object->$key;
		}
		
		if(!isset($updates[0])) return false;
		
		$sql = sprintf('UPDATE `%s` SET %s WHERE %s LIMIT 1;', $table, implode(',', $updates), implode(' AND ', $where));
		$conn->prepare($sql)->execute($params);
			
		return true;
	}

	public static function delete(&$object){
	
		$keys = $object::_getKeys(DB\Column::INDEX_PRIMARY);
		$table = $object::_getTableName();
		$schema = $object::_getSchemaName();
		
		if(count($keys) === 0)
			throw new \Wave\Exception("No primary key defined for $schema.");
		
		$conn = self::get($schema)->getConnection();
		
		$params = array();
		$where = array();
		foreach($keys as $key){
			$where[] = "`$key` = ?";
			$params[] = $object->$key;
		}
		
		$sql = sprintf('DELETE FROM `%s` WHERE %s LIMIT 1;', $table, implode(' AND ', $where));
		
		$conn->prepare($sql)->execute($params);
		
		$object->_setLoaded(false);			
		
		return true;
	}
	
	
	
	
	public function rawQuery($sql){
		$db = $this->getConnection();
		$db->exec($sql);
	}
	
	
	
	/**
	* Function to return the results of a basic query
	*/
	public function basicQuery($sql, $params = array()){
		$statement = $this->basicStatement($sql, $params);
		return $statement->fetchAll();
	}
	
	public function basicStatement($sql, $params = array()){
		if(!is_array($params))
			$params = array($params);
		
		$statement = $this->connection->prepare($sql);
		$start = microtime(true);
		$statement->execute( $params );
		$time = microtime(true) - $start;           
		
		\Wave\Debug::getInstance()->addQuery($time, $statement);
		
		return $statement;
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

	public static function init($database){
	
		$installed_drivers = DB\Connection::getAvailableDrivers();

		$driver_class = self::getDriverClass($database->driver);
		
		//Check PDO driver is installed on system
		if(!in_array($driver_class::getDriverName(), $installed_drivers))
			throw new DB\Exception(sprintf('PDO::%s driver not installed for %s.', $driver_class::getDriverName(), $driver_class));
		
		self::$instances[$database->namespace] = new self($database);
		
		/*
		* Define default database if it is either first or flagged as default.
		* First db is always flagged as default to avoid the case where there is no default, 
		* (it'll be overwritten by any db with the default flag).
		*/
		
		if(self::$num_databases == 0 || isset($database->default) && $database->default == true)
			self::$default = $database->namespace;
		
		self::$num_databases++;
		

		return self::$instances[$database->namespace];

	}
	
	public static function get($namespace = null, $mode = null){

		$databases = \Wave\Config::get('db')->databases;

		if($namespace === null){
			if(isset(self::$default))
				$namespace = self::$default;
			else {
				$namespace = self::getDefaultNamespace();
			}
		}

		if(!isset($databases[$namespace])){
			throw new \Wave\Exception("There is no database configuration for {$namespace}");
		}

		if($mode === null) 
			$mode = isset($databases[$namespace][\Wave\Core::$_MODE]) 
					? \Wave\Core::$_MODE 
					: \Wave\Core::MODE_PRODUCTION;

		if(!isset($databases[$namespace][$mode])){
			throw new \Wave\Exception('There must be at least a PRODUCTION database defined');
		}
		else {
			$databases[$namespace][$mode]->namespace = $namespace;
			$databases[$namespace][$mode]->mode = $mode;

			return isset(self::$instances[$namespace]) 
				? self::$instances[$namespace] 
				: self::init($databases[$namespace][$mode]);	
		}
	}

	public static function set($namespace, DB\Connection $connection){
		if(isset(self::$instances[$namespace])){
			self::$instances[$namespace]->connection = $connection;
			return self::$instances[$namespace];
		}
		return null;
	}

	public static function getDefaultNamespace(){
		try {
			foreach(Config::get('db')->databases as $ns => $database){
				return $ns;
			}
		}
		catch(Exception $e) {
			return null;
		}
		
	}
	
	public static function getNumDatabases(){
		return self::$num_databases;
	}
	
	public static function getAllDatabases(){

		$databases = \Wave\Config::get('db')->databases;
		foreach($databases as $namespace => $modes)
			self::get($namespace);
		
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
		return '\\Wave\\DB\\Driver\\'.$driver;
	}
	
	public static function getClassNameForTable($table, $database = null, $raw_table = false){
		
		if($raw_table)
			$table = self::tableNameToClass($table);

		if(strpos($table, self::GLOBAL_NAMESPACE . self::NS_SEPARATOR) !== false){
			return $table;
		}
		
		if(is_null($database))
			$database = self::get();
		
		$class_name = '\\' . self::GLOBAL_NAMESPACE . '\\' . $database->getNameSpace() . '\\';
		$class_name .= substr($table, max(-1, strrpos($table, '\\')) + 1);
		
		if(!class_exists($class_name))
			throw new \Wave\DB\Exception("Could not derive class name for table: $table ($class_name)");
		
		return $class_name;
	
	}
	
	public static function getFieldsForTable($table){
		
		$class_name = self::getClassNameForTable($table);
		
		$fields = $class_name::_getFields();
		
		return $fields;
		
	}
	
}