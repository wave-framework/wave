<?php

/**
 *	Main class for interacting with Databases
 *	
 *	Supports methods for getting databases, inserting, updating and generating the models.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave;

use Wave\Core,
    Wave\Config,
    Wave\DB\Model,
    Wave\DB\Exception as DBException;
use Wave\DB\Driver\MySQL;

class DB {

    /** @var int $num_databases */
	private static $num_databases	= 0;

	/** @var DB[] $instances */
    private static $instances		= array();

    /** @var string $default_namespace */
	private static $default_namespace;

    /** @var string $escape_character */
	private $escape_character;

    /** @var \Wave\DB\Connection $connection */
	private $connection;

    /** @var string $namespace */
	private $namespace;

    /** @var \Wave\Config\Row $config */
	private $config;

    /** @var \Wave\DB\Table[] $tables */
	private $tables;

    /**
     * @param $namespace
     * @param $config
     */
    private function __construct($namespace, $config){
	
		$this->connection = new DB\Connection($config);
		$this->namespace = $namespace;
		$this->config = $config;

        /** @var DB\Driver\DriverInterface $driver */
		$driver = $this->connection->getDriverClass();
		$this->escape_character = $driver::getEscapeCharacter();

		if(in_array(Core::$_MODE, array(Core::MODE_DEVELOPMENT, Core::MODE_TEST)))
			$this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}

    /**
     * @param $namespace
     * @param $database
     *
     * @return DB
     * @throws DB\Exception
     */
    private static function init($namespace, $database){
	
		$installed_drivers = DB\Connection::getAvailableDrivers();

        /** @var DB\Driver\DriverInterface $driver_class */
		$driver_class = self::getDriverClass($database->driver);
		
		//Check PDO driver is installed on system
		if(!in_array($driver_class::getDriverName(), $installed_drivers))
			throw new DBException(sprintf('PDO::%s driver not installed for %s.', $driver_class::getDriverName(), $driver_class));
		
		self::$num_databases++;

		$instance = new self($namespace, $database);
        Hook::triggerAction('db.after_init', array(&$instance));
        return $instance;

	}

    /**
     * Returns an instance of a database
     * If no arguments are supplied, the default namespace and mode are selected.
     *
     * @param string|null $namespace  The DB namespace to load from (or the default if not supplied)
     *
     * @return DB
     * @throws Exception
     */
    public static function get($namespace = null){
				
		if($namespace === null){
		
			if(!isset(self::$default_namespace))
				self::$default_namespace = self::getDefaultNamespace();
				
			$namespace = self::$default_namespace;
		}

		if(!isset(self::$instances[$namespace])){
		
			$databases = Config::get('db')->databases;

			if(!isset($databases[$namespace])){
				throw new DBException("There is no database configuration for {$namespace}");
			}
			
			if(isset($databases[$namespace][\Wave\Core::$_MODE])) 
				$mode = Core::$_MODE;
			else
				$mode = Core::MODE_PRODUCTION;
		
			if(!isset($databases[$namespace][$mode])){
				throw new DBException('There must be at least a PRODUCTION database defined');
			}
			
			self::$instances[$namespace] = self::init($namespace, $databases[$namespace][$mode], $namespace);
		}
		
		return self::$instances[$namespace];
	
	}

    /**
     * @return DB[]
     */
    public static function getAll(){

		$databases = Config::get('db')->databases;
		foreach($databases as $namespace => $modes)
			self::get($namespace);
		
		return self::$instances;
	}

    /**
     * Reverse lookup by DB name
     *
     * Note: This function is quite slow, it should only be used during model generation for relations.
     *
     * @param $name
     *
     * @return null|DB
     */
    public static function getByDatabaseName($name){
		
		$databases = Config::get('db')->databases;
		foreach($databases as $namespace => $modes)
			foreach($modes as $mode)
				if($mode['database'] === $name)
					return self::get($namespace);
		
		return null;
	}

    public static function getByConfig($config_options) {

        $databases = Config::get('db')->databases;
        foreach($databases as $namespace => $modes){
            foreach($modes as $mode){
                foreach($config_options as $option => $value){
                    if($mode[$option] !== $value)
                        continue 2;
                }
                return self::get($namespace);
            }
        }

        return null;

    }

    /**
     * Returns the tables list, refreshing it if the $cache parameter is false
     *
     * @param bool $cache
     *
     * @return DB\Table[]
     */
    public function getTables($cache = true){
	
		if(!isset($this->tables) || !$cache){
			$driver_class = $this->getConnection()->getDriverClass();
			$this->tables = $driver_class::getTables($this);
		}
		
		return $this->tables;
	}

    /**
     * @param $table
     * @param $column
     *
     * @return DB\Column
     */
    public function getColumn($table, $column){
		
		$tables = $this->getTables();
		
		if(!isset($tables[$table]))
			return null;
					
		$table = $tables[$table];
		$columns = $table->getColumns();
		
		return $columns[$column];
	}

    /**
     * @return string
     */
    public static function getDefaultNamespace(){
		
		foreach(Config::get('db')->databases as $ns => $database)
			return $ns;
		
	}

    /**
     * @param $driver
     *
     * @return string
     */
    public static function getDriverClass($driver){
		return '\\Wave\\DB\\Driver\\'.$driver;
	}

    /**
     * Escape using the escaping character of the current connection
     *
     * @param $string
     *
     * @return string
     */
    public function escape($string){
		return $this->escape_character . $string . $this->escape_character;
	}

    /**
     * Convert a value to it's valid SQL equivalent using the current driver class
     *
     * @param $value
     *
     * @return mixed
     */
    public function valueToSQL($value){
		$driver_class = $this->connection->getDriverClass();
		return $driver_class::valueToSQL($value);

	}

    /**
     * Convert a value to it's PHP type using the current driver class
     *
     * @param       $value
     * @param array $field_data
     *
     * @return mixed
     */
    public function valueFromSQL($value, array $field_data){
		$driver_class = $this->connection->getDriverClass();
		return $driver_class::valueFromSQL($value, $field_data);

	}

    /**
     * @return DB\Connection
     */
    public function getConnection(){
		return $this->connection;
	}

    /**
     * @param bool $full_namespace
     *
     * @return string
     */
    public function getNamespace($full_namespace = true){
		
		$ns_prefix = $full_namespace ? Config::get('wave')->model->base_namespace.'\\' : '';
		return $ns_prefix.$this->namespace;
	}

    /**
     * @return mixed
     */
    public function getName(){
		return $this->config->database;
	}

    /**
     * @return mixed
     */
    public function getSchema(){
        if(!$this->config->offsetExists('schema'))
            return $this->config->database;

        return $this->config->schema;
    }

    /**
     * Alias to \Wave\DB\Query::from
     *
     * @param      $from
     * @param null $alias
     * @param null $fields
     *
     * @return DB\Query
     */
    public function from($from, &$alias = null, $fields = null){
		$query = new \Wave\DB\Query($this);
		return $query->from($from, $alias, $fields);
	}

    /**
     * Save an object to the database (either INSERT or UPDATE)
     *
     * @param Model $object
     *
     * @return bool
     */
    public static function save(Model &$object){
		
		if($object->_isLoaded()){
			if($object->_isDirty()){
				return self::update($object);
			}
		} else {
			return self::insert($object);
		} 
		
	}

    /**
     * Execute a basic statement on the current connection and return the PDO statement object
     *
     * @param       $sql
     * @param array $params
     *
     * @return \PDOStatement
     */
    public function statement($sql, array $params = array()){
        $statement = $this->connection->prepare($sql);
        $statement->execute( $params );

        return $statement;
    }

    /**
     * @deprecated Because MC doesn't like this name
		
     *
     * @param $sql
     * @param array $params
     * @return \PDOStatement
     */
    public function basicStatement($sql, array $params = array()){
        return $this->statement($sql, $params);
    }

    /**
     * Execute an insert query for an object.
     *
     * @param Model $object
     *
     * @return bool
     */
    public static function insert(Model &$object){

		$database = self::get($object::_getDatabaseNamespace());
		$connection = $database->getConnection();
		
		$fields = $params = $placeholders = array();
        $object_data = $object->_getData();
		foreach($object->_getFields(true) as $object_field => $field_properties){
            $object_value = $object_data[$object_field];

            $fields[] = $database->escape($object_field);
            if($object_value === $field_properties['default'] && $field_properties['serial'] === true){
                $placeholders[] = 'DEFAULT';
            }
            else {
                $params[] = $database->valueToSQL($object_value);
                $placeholders[] = '?';
            }
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


    /**
     * Execute an update query for an object
     *
     * @param Model $object
     *
     * @return bool
     */
    public static function update(Model &$object){
				
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
				
		$sql = sprintf('UPDATE %s.%s SET %s WHERE %s;', $database->escape($object::_getDatabaseName()),
																$database->escape($object::_getTableName()), 
																implode(',', $updates), 
																implode(' AND ', $criteria));
		$connection->prepare($sql)->execute($params);
			
		return true;
	}

    /**
     * Execute a delete query for an object
     *
     * @param Model $object
     *
     * @return bool
     */
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