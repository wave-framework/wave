<?php

/**
 *    Main class for interacting with Databases
 *
 *    Supports methods for getting databases, inserting, updating and generating the models.
 *
 * @author Michael michael@calcin.ai
 **/

namespace Wave;

use Wave\Config;
use Wave\DB\Exception as DBException;
use Wave\DB\Model;
use Wave\DB\Query;

class DB {

    /** @var DB[] $instances */
    private static $instances = array();

    /** @var string $default_namespace */
    private static $default_namespace;

    /** @var \Wave\DB\Connection[] $connections */
    private $connections;

    /** @var string $namespace */
    private $namespace;

    /** @var \Wave\Config\Row $config */
    private $config;

    /** @var \Wave\DB\Table[] $tables */
    private $tables;

    // Default alias for the primary database connection
    const PRIMARY_CONNECTION = 'primary';

    /**
     * Set up a new DB instance
     *
     * DB configuration can include multiple connections, for use
     * cases such as read-only replica hosts to be targeted by some queries.
     *
     * In this case, swap out the standard configuration for a key value
     * with the default connection aliased as 'primary', as shown below.
     *
     * [ 'primary' => ['host' => 'a', 'driver...], 'secondary' => ['host' => 'b'...]
     *
     * Note that currently connections must use the same driver, as the driver
     * from the primary connection is used as the escape character across all connections.
     *
     * @param $namespace
     * @param $config
     * @throws DBException
     */
    private function __construct($namespace, $config) {

        $installed_drivers = DB\Connection::getAvailableDrivers();
        $this->namespace = $namespace;
        $connections = [];

        // Default configuration syntax - just one connection
        if (isset($config->driver)) {
            $connections[self::PRIMARY_CONNECTION] = $config;
        }

        // Alternative configuration syntax - multiple connections
        if (isset($config->primary)) {
            $connections = $config;
        }

        // No valid configuration - so we can't proceed.
        if (count($connections) == 0) {
            throw new DBException("Invalid database configuration - at minimum, a 'primary' configuration must be specified");
        }

        foreach ($connections as $alias => $conf)
        {
            /** @var DB\Driver\DriverInterface $driver_class */
            $driver_class = self::getDriverClass($conf->driver);

            // Check PDO driver is installed on system
            if(!in_array($driver_class::getDriverName(), $installed_drivers))
                throw new DBException(sprintf('PDO::%s driver not installed for %s.', $driver_class::getDriverName(), $driver_class));

            // Use the primary connection for default configuration
            if ($alias == self::PRIMARY_CONNECTION) {
                $this->config = $conf;
            }

            $connection = new DB\Connection($conf, $namespace);
            $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->connections[$alias] = $connection;
        }
    }

    /**
     * @param $namespace
     * @param $database
     *
     * @return DB
     * @throws DB\Exception
     */
    private static function init($namespace, $database) {

        $instance = new self($namespace, $database);
        Hook::triggerAction('db.after_init', array(&$instance));
        return $instance;

    }

    public static function getConfigForNamespace($namespace) {

        $databases = Config::get('db')->databases;

        if(!isset($databases[$namespace])) {
            throw new DBException("There is no database configuration for {$namespace}");
        }

        if(isset($databases[$namespace][Core::$_MODE]))
            $mode = Core::$_MODE;
        else
            $mode = Core::MODE_PRODUCTION;

        if(!isset($databases[$namespace][$mode])) {
            throw new DBException('There must be at least a PRODUCTION database defined');
        }

        return $databases[$namespace][$mode];
    }

    /**
     * Returns an instance of a database
     * If no arguments are supplied, the default namespace and mode are selected.
     *
     * @param string|null $namespace The DB namespace to load from (or the default if not supplied)
     *
     * @return DB
     * @throws Exception
     */
    public static function get($namespace = null) {

        if($namespace === null) {

            if(!isset(self::$default_namespace))
                self::$default_namespace = self::getDefaultNamespace();

            $namespace = self::$default_namespace;
        }

        if(!isset(self::$instances[$namespace])) {
            $config = self::getConfigForNamespace($namespace);
            self::$instances[$namespace] = self::init($namespace, $config);
        }

        return self::$instances[$namespace];

    }

    /**
     * @return DB[]
     */
    public static function getAll() {

        $databases = Config::get('db')->databases;
        foreach($databases as $namespace => $modes)
            self::get($namespace);

        return self::$instances;
    }

    /**
     * Removes existing instances (and therefore connections, forcing a new connection when get() is next called)
     *
     * @return DB
     * @throws Exception
     */
    public static function reset() {
        self::$instances = array();

        return self::get();
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
    public static function getByDatabaseName($name) {

        $databases = Config::get('db')->databases;
        foreach($databases as $namespace => $modes)
            foreach($modes as $mode)
                if((isset($mode['database']) && $mode['database'] === $name) ||
                    (isset($mode[self::PRIMARY_CONNECTION]) && $mode[self::PRIMARY_CONNECTION]['database'] === $name))
                    return self::get($namespace);

        return null;
    }

    public static function getByConfig($config_options) {

        $databases = Config::get('db')->databases;
        foreach($databases as $namespace => $modes) {
            foreach($modes as $mode) {
                foreach($config_options as $option => $value) {
                    if((!isset($mode[$option]) || $mode[$option] !== $value) &&
                        (!isset($mode[self::PRIMARY_CONNECTION]) || $mode[self::PRIMARY_CONNECTION][$option] !== $value))
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
    public function getTables($cache = true) {

        if(!isset($this->tables) || !$cache) {
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
    public function getColumn($table, $column) {

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
    public static function getDefaultNamespace() {

        foreach(Config::get('db')->databases as $ns => $database)
            return $ns;

    }

    /**
     * @param $driver
     *
     * @return string
     */
    public static function getDriverClass($driver) {
        return '\\Wave\\DB\\Driver\\' . $driver;
    }

    /**
     * Escape using the escaping character of the primary connection
     *
     * Like many functions in this class, this should be connection specific
     * and will be fixed when we re-design this to be in the context
     * of a connection.
     *
     * @param $string
     *
     * @return string
     * @throws DBException
     */
    public function escape($string) {
        $driver = $this->getConnection()->getDriverClass();

        return $driver::getEscapeCharacter() . $string . $driver::getEscapeCharacter();
    }

    /**
     * Convert a value to it's valid SQL equivalent using the current driver class
     *
     * @param $value
     *
     * @return mixed
     */
    public function valueToSQL($value) {
        $driver_class = $this->getConnection()->getDriverClass();
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
    public function valueFromSQL($value, array $field_data) {
        $driver_class =$this->getConnection()->getDriverClass();
        return $driver_class::valueFromSQL($value, $field_data);

    }

    /**
     * Retrieve a DB connection
     *
     * If using multiple connections, provide the name of the connection required,
     * otherwise the primary connection is retrieved.
     *
     * @param string $alias
     *
     * @return DB\Connection
     */
    public function getConnection($alias = self::PRIMARY_CONNECTION) {
        if (!isset($this->connections[$alias])) {
            throw new DBException(sprintf('A connection with the alias [%s] has not been defined', $alias));
        }

        return $this->connections[$alias];
    }

    /**
     * @param bool $full_namespace
     *
     * @return string
     */
    public function getNamespace($full_namespace = true) {

        $ns_prefix = $full_namespace ? Config::get('wave')->model->base_namespace . '\\' : '';
        return $ns_prefix . $this->namespace;
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->config->database;
    }

    /**
     * @return mixed
     */
    public function getSchema() {
        if($this->config->offsetExists('schema'))
            return $this->config->schema;

        return $this->config->database;
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
    public function from($from, &$alias = null, $fields = null) {
        $query = new Query($this->getConnection());
        return $query->from($from, $alias, $fields);
    }

    /**
     * Save an object to the database (either INSERT or UPDATE)
     *
     * @param Model $object
     *
     * @return bool
     */
    public static function save(Model &$object) {

        if($object->_isLoaded()) {
            if($object->_isDirty()) {
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
    public function statement($sql, array $params = array()) {
        $statement = $this->getConnection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * @deprecated Because MC doesn't like this name
     *
     * @param $sql
     * @param array $params
     * @return \PDOStatement
     */
    public function basicStatement($sql, array $params = array()) {
        return $this->statement($sql, $params);
    }

    /**
     * Execute an insert query for an object.
     *
     * @param Model $object
     *
     * @return bool
     */
    public static function insert(Model &$object) {

        $database = self::get($object::_getDatabaseNamespace());
        $connection = $database->getConnection();

        $fields = $params = $placeholders = array();
        $object_data = $object->_getData();
        foreach($object->_getFields(true) as $object_field => $field_properties) {
            $object_value = $object_data[$object_field];

            $fields[] = $database->escape($object_field);
            if($object_value === $field_properties['default'] && $field_properties['serial'] === true) {
                $placeholders[] = 'DEFAULT';
            } else {
                $params[] = $database->valueToSQL($object_value);
                $placeholders[] = '?';
            }
        }

        $sql = sprintf(
            'INSERT INTO %s.%s (%s) VALUES (%s);', $database->escape($object::_getSchemaName()),
            $database->escape($object::_getTableName()),
            implode(',', $fields),
            implode(',', $placeholders)
        );

        $connection->prepare($sql)->execute($params);

        $primary_key = $object::_getPrimaryKey();
        if($primary_key !== null && count($primary_key) === 1) {
            $column = current($primary_key);
            $field = $object::_getField($column);
            if($field['serial'] === true){
                $liid = intval($connection->lastInsertId($field['sequence']));
                $object->$column = $liid;
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
    public static function update(Model &$object) {

        $database = self::get($object::_getDatabaseNamespace());
        $connection = $database->getConnection();

        $updates = $criteria = $params = array();
        //dirty data
        foreach($object->_getDirty() as $object_field => $object_value) {
            $updates[] = sprintf('%s = ?', $database->escape($object_field));
            $params[] = $database->valueToSQL($object_value);
        }

        //row identifier
        foreach($object->_getIdentifyingData() as $identifying_field => $identifying_value) {
            $value = $database->valueToSQL($identifying_value);
            $criteria[] = DB\Query::parseWhereCondition(sprintf('%s = ?', $database->escape($identifying_field)), $value);
            $params = array_merge($params, $value);
        }

        $sql = sprintf(
            'UPDATE %s.%s SET %s WHERE %s;', $database->escape($object::_getSchemaName()),
            $database->escape($object::_getTableName()),
            implode(',', $updates),
            implode(' AND ', $criteria)
        );
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
    public static function delete(Model &$object) {

        $database = self::get($object::_getDatabaseNamespace());
        $connection = $database->getConnection();


        //row identifier
        $criteria = $params = array();
        foreach($object->_getIdentifyingData() as $identifying_field => $identifying_value) {
            $value = $database->valueToSQL($identifying_value);
            $criteria[] = DB\Query::parseWhereCondition(sprintf('%s = ?', $database->escape($identifying_field)), $value);
            $params = array_merge($params, $value);
        }

        $sql = sprintf(
            'DELETE FROM %s.%s WHERE %s;', $database->escape($object::_getSchemaName()),
            $database->escape($object::_getTableName()),
            implode(' AND ', $criteria)
        );
        $connection->prepare($sql)->execute($params);

        $object->_setLoaded(false);

        return true;
    }


}