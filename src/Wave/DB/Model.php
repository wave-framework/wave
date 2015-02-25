<?php

/**
 *    Base model, common functionality
 *
 * @author Michael michael@calcin.ai
 **/

namespace Wave\DB;

use Wave;
use Wave\DB;

class Model {

    /** @var string */
    protected static $_database;
    /** @var string */
    protected static $_schema_name;
    /** @var string */
    protected static $_table_name;

    /**
     * A list of fields this model has and their associated metadata
     * @var array
     */
    protected static $_fields = array();

    /**
     * A list of relations this model has to other models
     * @var array
     */
    protected static $_relations = array();

    /**
     * A list of constraints this model has on it's columns
     * @var array
     */
    protected static $_constraints = array();

    /**
     * The internal cache of data for this model. Accessed via getter/setter
     * methods and __get/__set magic methods
     *
     * @var array $_data
     */
    protected $_data = array();

    /**
     * A list of properties that have been written to since
     * this object was loaded from the data source
     * @var array $_dirty
     */
    protected $_dirty = array();

    /**
     * Flags if this model has been loaded from the data source or not
     * @var bool
     */
    protected $_loaded = false;

    /**
     * An associative array of arrays of joined objects when this model
     * was loaded from a data source using joins. The keys are the aliases
     * used when loading the object from the query builder
     *
     * @var array
     */
    protected $_joined_objects = array();

    /**
     * A map of the joined aliases to their namespaced class names
     * @var array
     */
    protected $_joined_aliases = array();


    public function __construct($data = null, $is_loaded = false) {

        $connection = DB::get(self::_getDatabaseNamespace());

        foreach(self::_getFields() as $field) {
            $this->_data[$field] = $data !== null && array_key_exists($field, $data)
                ? $connection->valueFromSQL($data[$field], self::_getField($field))
                : self::getFieldDefault($field);
        }

        if($is_loaded)
            $this->_setLoaded();

    }

    /**
     * Makes a new instance of a model from an array of data, returns either
     * the new object on success or null on failure
     *
     * @param array $data
     *
     * @return null|Model
     */
    public static function createFromArray(array $data) {

        foreach(self::_getIdentifyingColumns() as $required_column) {
            if(!isset($data[$required_column]) || $data[$required_column] === '') {
                return null;
            }
        }

        return new static($data);

    }

    /**
     * Gets the default casted value for a field
     *
     * @param $field_name
     *
     * @return mixed
     */
    public static function getFieldDefault($field_name) {
        $field = self::_getField($field_name);
        return DB::get(self::_getDatabaseNamespace())->valueFromSQL($field['default'], $field);
    }

    /**
     * Generic loadById method that returns a single Model instance loaded from the data source
     * by it's primary keys
     *
     * @return Model
     */
    public static function loadByID() {

        $stmt = DB::get(self::_getDatabaseNamespace())->from(get_called_class());

        foreach(self::_getIdentifyingColumns() as $index => $column)
            $stmt->where("$column = ?", func_get_arg($index));

        return $stmt->fetchRow();
    }

    /**
     * Save the current model instance to the database
     *
     * @param bool $save_relations
     *
     * @return bool
     */
    public function save($save_relations = true) {
        return DB::save($this, $save_relations);
    }

    /**
     * Delete the current model instance from the database
     *
     * @return bool
     */
    public function delete() {
        return DB::delete($this);
    }

    public function _equals(Model $object) {
        return (get_class($this) === get_class($object)) &&
        array_diff_assoc($this->_getIdentifyingData(), $object->_getIdentifyingData());
    }

    /**
     * Add a joined object to the internal join cache.
     *
     * @param Model $object
     * @param $alias
     * @param $resolved_class
     */
    public function addJoinedObject(Model &$object, $alias, $resolved_class) {
        $this->_joined_aliases[$resolved_class][] = $alias;
        if(!isset($this->_joined_objects[$alias]))
            $this->_joined_objects[$alias] = array();

        $this->_joined_objects[$alias][] = $object;
    }

    /**
     * Convenience method that returns a primary key for the current object
     * based in the convention of keys being called table_name_id.
     *
     * Can/Will be overloaded in the case it clashes with an actual field called
     * id in the data source
     *
     * @return mixed
     */
    public function getid() {
        return $this->_data[self::_getTableName() . '_id'];
    }

    /**
     * @return array
     */
    public function _getData() {
        return $this->_data;
    }

    /**
     * @return array
     */
    public function _getDirty() {
        return array_intersect_key($this->_data, $this->_dirty);
    }

    /**
     * @return string
     */
    public static function _getTableName() {
        return static::$_table_name;
    }

    /**
     * @return string
     */
    public static function _getDatabaseName() {
        if(!isset(static::$_schema_name)) {
            $config = DB::getConfigForNamespace(static::_getDatabaseNamespace());
            static::$_schema_name = $config['database'];
        }
        return static::$_schema_name;
    }

    /**
     * @return string
     */
    public static function _getDatabaseNamespace() {
        return static::$_database;
    }

    /**
     * @param bool $field_data
     *
     * @return array
     */
    public static function _getFields($field_data = false) {
        return $field_data ? static::$_fields : array_keys(static::$_fields);
    }

    /**
     * @param $field_name
     *
     * @return mixed
     */
    public static function _getField($field_name) {
        return static::$_fields[$field_name];
    }

    /**
     * @return mixed
     */
    public static function _getRelations() {
        return static::$_relations;
    }

    /**
     * Returns relation metadata
     * @param $relation_name
     *
     * @return mixed
     * @throws \Wave\Exception
     */
    public static function _getRelation($relation_name) {

        if(!self::isRelation($relation_name))
            throw new Wave\Exception(sprintf('Invalid relation: [%s]', $relation_name));

        return static::$_relations[$relation_name];
    }

    /**
     * @param $relation_name
     *
     * @return bool
     */
    public static function isRelation($relation_name) {

        return isset(static::$_relations[$relation_name]);
    }

    /**
     * @return array
     */
    public function _getJoinedObjects() {
        return $this->_joined_objects;
    }

    /**
     * Returns the joined objects for a given class name. Looks for the class in the list
     * of aliases used when registering joined objects against this model
     * @param string $class
     *
     * @return array
     */
    public function _getJoinedObjectsForClass($class) {
        $objects = array();
        if(isset($this->_joined_aliases[$class])) {
            foreach($this->_joined_aliases[$class] as $alias) {
                $objects = array_merge($objects, $this->_joined_objects[$alias]);
            }
        }
        return $objects;
    }

    /**
     * Sets this objects as being loaded from the database manually.
     * Clears the dirty array as well.
     *
     * @param bool $loaded
     *
     * @return bool
     */
    public function _setLoaded($loaded = true) {
        //at this point it won't be dirty.
        $this->_dirty = array();
        return $this->_loaded = $loaded;
    }

    /**
     * @return bool
     */
    public function _isLoaded() {
        return $this->_loaded;
    }

    /**
     * @return bool
     */
    public function _isDirty() {
        return count($this->_dirty) !== 0;
    }

    /**
     * @return null|array
     */
    public static function _getPrimaryKey() {
        foreach(static::$_constraints as $constraint)
            if($constraint['type'] === Constraint::TYPE_PRIMARY)
                return $constraint['fields'];

        return null;
    }

    /**
     * Attempts to provide data that uniquely identifies this modal instance.
     *
     * @return array
     */
    public function _getIdentifyingData() {
        $columns = self::_getIdentifyingColumns();
        return array_intersect_key($this->_data, array_flip($columns));
    }

    /**
     * Attempts to provide the columns that uniquely identify this modal instance.
     * Typically this is just the primary keys for this modal, failing that it tries
     * any unique keys, and last of all it just returns all the keys with the assumption
     * that if every column uniquely identifies this modal then duplicate data is somewhat
     * not of a concern
     *
     * @return array
     */
    public static function _getIdentifyingColumns() {
        //first PK
        foreach(static::$_constraints as $constraint) {
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

    /**
     * Returns all the data for this model as an array. Differs from the _getData
     * method in that this one will call each getter to allow for data to be transformed
     * before being added to the resulting array
     *
     * @return array
     */
    public function _toArray() {
        $data = array();
        foreach(static::_getFields(false) as $field) {
            $getter = self::_getGetter($field);
            $data[$field] = $this->$getter();
        }
        return $data;
    }


    /**
     * Provides a property based interface to the set<property> function
     *
     * @param $property
     * @param $data
     *
     * @return mixed
     */
    public function __set($property, $data) {

        $setter = self::_getSetter($property);
        if(method_exists($this, $setter)) {
            if($this->$property === $data)
                return $data;

            $this->_dirty[$property] = true;
            return $this->$setter($data);
        }

        return $this->$property = $data;

    }

    /**
     * Provides a property based interface to the get<property> function
     *
     * @param $property
     *
     * @return mixed
     */
    public function __get($property) {
        $getter = self::_getGetter($property);
        if(!method_exists($this, $getter)) {
            $stack = debug_backtrace(false);
            $stack = array_shift($stack);
            trigger_error('Notice: Undefined property ' . get_called_class() . '::' . $property . ' in ' . $stack['file'] . ' on line ' . $stack['line'] . " (via by Wave\DB_Model::__get())\n");
        } else {
            return $this->$getter();
        }

    }

    /**
     * Check if there is a getter for the given property
     *
     * @param $property
     *
     * @return bool
     */
    public function __isset($property) {
        return method_exists($this, self::_getGetter($property));
    }

    /**
     * Returns the function representing the setter for the given property
     * @param $property
     *
     * @return string
     */
    private static function _getSetter($property) {
        return 'set' . $property;
    }

    /**
     * Returns the function representing the getter for the given property
     * @param $property
     *
     * @return string
     */
    private static function _getGetter($property) {
        return 'get' . $property;
    }


}