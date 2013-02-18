<?php

/**
 *	Main class for interacting with Databases
 *	
 *	Supports methods for getting databases, inserting, updating and generating the models.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave;

use Wave\DB\Model;

class DB {

	private static $num_databases	= 0;
	private static $instances		= array();
	private static $default_namespace;
	
	private $escape_character;
	
	private $connection;
	private $namespace;
	private $config;
	private $tables;
	
	private function __construct($namespace, $config){
	
		$this->connection = new DB\Connection($config);
		$this->namespace = $namespace;
		$this->config = $config;
		
		$driver = $this->connection->getDriverClass();
		$this->escape_character = $driver::getEscapeCharacter();

		if(in_array(Core::$_MODE, array(Core::MODE_DEVELOPMENT, Core::MODE_TEST)))
			$this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}
	
	
	private static function init($namespace, $database){
	
		$installed_drivers = DB\Connection::getAvailableDrivers();

		$driver_class = self::getDriverClass($database->driver);
		
		//Check PDO driver is installed on system
		if(!in_array($driver_class::getDriverName(), $installed_drivers))
			throw new DB\Exception(sprintf('PDO::%s driver not installed for %s.', $driver_class::getDriverName(), $driver_class));
		
		self::$num_databases++;
		return new self($namespace, $database);

	}
	
	/**
	*	Returns an instance of a database
	*	If no arguments are suppplied, the default namespace and mode are selected.
	*
	**/
	public static function get($namespace = null){
				
		if($namespace === null){
		
			if(!isset(self::$default_namespace))
				self::$default_namespace = self::getDefaultNamespace();
				
			$namespace = self::$default_namespace;
		}

		if(!isset(self::$instances[$namespace])){
		
			$databases = \Wave\Config::get('db')->databases;

			if(!isset($databases[$namespace])){
				throw new \Wave\Exception("There is no database configuration for {$namespace}");
			}
			
			if(isset($databases[$namespace][\Wave\Core::$_MODE])) 
				$mode = \Wave\Core::$_MODE;
			else
				$mode = \Wave\Core::MODE_PRODUCTION;
		
			if(!isset($databases[$namespace][$mode])){
				throw new \Wave\Exception('There must be at least a PRODUCTION database defined');
			}
			
			self::$instances[$namespace] = self::init($namespace, $databases[$namespace][$mode], $namespace);
		}
		
		return self::$instances[$namespace];
	
	}
	
	public static function getAll(){

		$databases = \Wave\Config::get('db')->databases;
		foreach($databases as $namespace => $modes)
			self::get($namespace);
		
		return self::$instances;
	}
	
	//reverse lookup by DB name, should only be used during model generation for relations (slow).
	public static function getByDatabaseName($name){
		
		$databases = \Wave\Config::get('db')->databases;
		foreach($databases as $namespace => $modes)
			foreach($modes as $mode)
				if($mode['database'] === $name)
					return self::get($namespace);
		
		return null;
	}
	
	public function getTables($cache = true){
	
		if(!isset($this->tables) || !$cache){
			$driver_class = $this->getConnection()->getDriverClass();
			$this->tables = $driver_class::getTables($this);
		}
		
		return $this->tables;
	}
	
	public function getColumn($table, $column){
		
		$tables = $this->getTables();
		$table = $tables[$table];
		$columns = $table->getColumns();
		
		return $columns[$column];
	}
	
	public static function getDefaultNamespace(){
		
		foreach(\Wave\Config::get('db')->databases as $ns => $database)
			return $ns;
		
	}
	
	public static function getDriverClass($driver){
		return '\\Wave\\DB\\Driver\\'.$driver;
	}
	
	public function escape($string){
		return $this->escape_character . $string . $this->escape_character;
	}	
	
	public function valueToSQL($value){
		$driver_class = $this->connection->getDriverClass();
		return $driver_class::valueToSQL($value);

	}
	
	public function valueFromSQL($value, array $field_data){
		$driver_class = $this->connection->getDriverClass();
		return $driver_class::valueFromSQL($value, $field_data);

	}
	
	public function getConnection(){
		return $this->connection;
	}
	
	public function getNamespace($full_namespace = true){
		
		$ns_prefix = $full_namespace ? Config::get('wave')->model->base_namespace.'\\' : '';
		return $ns_prefix.$this->namespace;
	}
	
	public function getName(){
		return $this->config->database;
	}
	
	//alias function for Wave\DB\Qurey::from
	public function from($from, &$alias = null, $fields = null){
		$query = new \Wave\DB\Query($this);
		return $query->from($from, $alias, $fields);
	}
	
	public static function save(&$object){
		
		if($object->_isLoaded()){
			if($object->_isDirty()){
				return self::update($object);
			}
		} else {
			return self::insert($object);
		} 
		
	}

    public function basicStatement($sql, array $params = array()){
        $statement = $this->connection->prepare($sql);
        $statement->execute( $params );

        return $statement;
    }
	

	public static function insert(&$object){

		$database = self::get($object::_getDatabaseNamespace());
		$connection = $database->getConnection();
		
		$fields = $params = $placeholders = array();
        $object_data = $object->_getData();
		foreach($object->_getFields(false) as $object_field){
            $object_value = $object_data[$object_field];
			$fields[] = $database->escape($object_field);
			$params[] = $database->valueToSQL($object_value);
			$placeholders[] = '?';
		}
				
		$sql = sprintf('INSERT INTO %s.%s (%s) VALUES (%s);', $database->escape($object::_getDatabaseName()), 
															  $database->escape($object::_getTableName()), 
															  implode(',', $fields), 
															  implode(',', $placeholders));
		
		$connection->prepare($sql)->execute($params);
		
		$liid = intval($connection->lastInsertId());
		if($liid !== 0){
			$primary_key = $object::_getPrimaryKey();
			if($primary_key !== null && count($primary_key) === 1){
				$object->{current($primary_key)} = $liid;
			}
		}
			
		return $object->_setLoaded();
	}
	
	public static function update(&$object){
				
		$database = self::get($object::_getDatabaseNamespace());
		$connection = $database->getConnection();
		
		$updates = $criteria = $params = array();
		//dirty data
		foreach($object->_getDirty() as $object_field => $object_value){
			$updates[] = sprintf('%s = ?', $database->escape($object_field));
			$params[] = $database->valueToSQL($object_value);
		}
		
		//row identifier
		foreach($object->_getIdentifyingData() as $identifying_field => $identifying_value){
			$value = $database->valueToSQL($identifying_value);
			$criteria[] = DB\Query::parseWhereCondition(sprintf('%s = ?', $database->escape($identifying_field)), $value);
			$params = array_merge($params, $value);
		}
				
		$sql = sprintf('UPDATE %s.%s SET %s WHERE %s LIMIT 1;', $database->escape($object::_getDatabaseName()), 
																$database->escape($object::_getTableName()), 
																implode(',', $updates), 
																implode(' AND ', $criteria));
		$connection->prepare($sql)->execute($params);
			
		return true;
	}

	public static function delete(Model &$object){
		
		$database = self::get($object::_getDatabaseNamespace());
		$connection = $database->getConnection();

		
		//row identifier
		$criteria = $params = array();
		foreach($object->_getIdentifyingData() as $identifying_field => $identifying_value){
			$value = $database->valueToSQL($identifying_value);
			$criteria[] = DB\Query::parseWhereCondition(sprintf('%s = ?', $database->escape($identifying_field)), $value);
			$params = array_merge($params, $value);
		}
		
		$sql = sprintf('DELETE FROM %s.%s WHERE %s LIMIT 1;', $database->escape($object::_getDatabaseName()), 
															  $database->escape($object::_getTableName()), 
															  implode(' AND ', $criteria));		
		$connection->prepare($sql)->execute($params);
		
		$object->_setLoaded(false);
				
		return true;
	}
	


}