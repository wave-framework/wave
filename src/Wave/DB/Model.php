<?php

/**
 *    Base model, common functionality
 *
 * @author Michael michael@calcin.ai
 **/

namespace Wave\DB;

use Wave;
use Wave\DB;

class Model implements \JsonSerializable {

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
     *
     * @var array
     */
    protected $_joined_aliases = array();

    /**
     * The parent object for chained loading.  Useful for getting the m2m relation object.
     *
     * @var Model
     */
    protected $_parent_object = null;


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
     * Generic function for updating model data. Only updates
     * properties present in the $data array. Can use setters or amend data
     * directly depending on $user_setters flag.
     *
     * @param Array $data
     * @param bool $use_setters
     */
    public function updateFromArray($data, $use_setters = true){

        foreach(self::_getFields() as $field) {
            if(array_key_exists($field, $data)){
                // Handle the case where we get a model instance back from the validator instead of [object_name]_id
                $value = ($data[$field] instanceof self) ? $data[$field]->getid() : $data[$field];

                if($use_setters) {
                    $this->$field = $value;
                }
                else {
                    $this->_data[$field] = $value;
                }
            }
        }

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
     * Generic paging function that returns Model instances.
     *
     * @param int|null $results_per_page
     * @param int $page_number
     * @param $number_of_results
     *
     * @return Model[]
     * @throws Wave\Exception
     */
    public static function loadAllByPage($results_per_page = null, $page_number = 0, &$number_of_results = 0) {

        $stmt = DB::get(self::_getDatabaseNamespace())
            ->from(get_called_class());

        if(isset($results_per_page)) {
            $stmt = $stmt->paginate($results_per_page * $page_number, $results_per_page * ($page_number + 1));
        }

        $results = $stmt->fetchAll();

        if(isset($results_per_page)) {
            $number_of_results = $stmt->fetchRowCount();
        }

        return $results;
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
    public static function _getSchemaName() {

        if(!isset(static::$_schema_name)) {
            static::$_schema_name = DB::get(static::_getDatabaseNamespace())->getSchema();
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
     * If searching by a key that gets autocorrected, it'll be updated.
     *
     * @param $relation_name
     *
     * @return mixed
     * @throws \Wave\Exception
     */
    public static function _getRelation($relation_name) {

        if(isset(static::$_relations[$relation_name])){
            return static::$_relations[$relation_name];
        }

        throw new Wave\Exception(sprintf('Invalid relation: [%s]', $relation_name));
    }

    /**
     * Finds the relations on a given class by class name, returns the first relation that matches.
     *
     * @param $class_name
     * @return null|string
     */
    public static function _findRelationName($class_name){
        foreach(static::$_relations as $rel_name => $relation){
            if($relation['related_class'] === $class_name){
                return $rel_name;
            }
        }
        return null;
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
     * Returns what should be serialized when object is encountered by json_encode
     *
     * @return array
     */
    public function jsonSerialize() {
        return $this->_data;
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

        //then all (if no keys are set for some reason) @todo - throw appropriate error
        return self::_getFields();
    }


    /**
     * Generates a string hash of the object based on 'identifying data' returned from the above methods.
     */
    public function _getFingerprint(){
        return sha1(implode('::', $this->_getIdentifyingData()));
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
            trigger_error('Notice: Undefined property ' . get_called_class() . '::' . $property . ' in ' . $stack['file'] . ' on line ' . $stack['line'] . " (via by Wave\\DB_Model::__get())\n");
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

    public function __unset($property){
        $this->__set($property, null);
    }


    public function __clone(){
        if(null === $pk = $this->_getPrimaryKey()){
            return;
        }

        foreach($pk as $field_name) {
            unset($this->$field_name);
        }
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

    /**
     * @return Model
     */
    public function _getParentObject() {
        return $this->_parent_object;
    }

    /**
     * @param Model $parent_object
     */
    public function _setParentObject(Model $parent_object) {
        $this->_parent_object = $parent_object;
    }


    /**
     * *-to-one
     *
     * @param $relation_name
     * @param Model $object
     *
     */
    protected function _setRelationObject($relation_name, Model $object, $create_relation) {

        $this->_data[$relation_name] = $object;
        $object->_setParentObject($this);

        if($object !== null && $create_relation) {
            if(!$object->_isLoaded())
                $object->save();

            $relation_data = $this->_getRelation($relation_name);

            foreach($relation_data['local_columns'] as $position => $local_column){
                $setter = self::_getSetter($local_column);
                $this->$setter($object->{$relation_data['related_columns'][$position]});
            }
        }

    }

    /**
     * *-to-many
     *
     * @param $relation_name
     * @param Model $object
     * @param $create_relation
     * @throws Wave\Exception
     */
    protected function _addRelationObject($relation_name, Model $object, $create_relation = true, $join_data = array()) {

        $this->_data[$relation_name][] = $object;

        $relation_data = $this->_getRelation($relation_name);

        if($object !== null && $create_relation) {

            switch($relation_data['relation_type']) {
                case Relation::MANY_TO_MANY:
                    if(!$object->_isLoaded())
                        $object->save();

                    $related_class_name = $relation_data['related_class'];

                    $rc = new $related_class_name();
                    foreach($relation_data['target_relation']['local_columns'] as $position => $local_column)
                        $rc->{$local_column} = $object->{$relation_data['target_relation']['related_columns'][$position]};
                    foreach($join_data as $key => $value)
                        $rc->{$key} = $value;

                    $object->_setParentObject($rc);
                    //Deliberate non-break here so the -many logic flows on with $object reassigned.
                    $object = $rc;
                case Relation::ONE_TO_MANY:
                    foreach($relation_data['local_columns'] as $position => $local_column)
                        $object->{$relation_data['related_columns'][$position]} = $this->_data[$local_column];

                    $object->_setParentObject($this);
                    break;

            }
            $object->save();
        }
    }


    /**
     * Unfortunately this function is a plural when it sometimes returns a singular.
     * I couldn't justify duplicating the function.
     *
     * @param $relation_name
     * @return null
     * @throws Wave\Exception
     */
    protected function _getRelationObjects($relation_name, $query_transform_callback = null) {

        $relation_data = $this->_getRelation($relation_name);

        if(!isset($this->_data[$relation_name])) {

            $related_class = $relation_data['related_class'];
            $db = Wave\DB::get($related_class::_getDatabaseNamespace());
            $query = $db->from($related_class, $from_alias);

            //Add in all of the related columns
            foreach($relation_data['local_columns'] as $position => $local_column) {
                //At this point, may as well return as there aren't null-relations
                if($this->_data[$local_column] === null)
                    return null;

                $query->where(sprintf('%s.%s = ?', $from_alias, $db->escape($relation_data['related_columns'][$position])), $this->_data[$local_column]);
            }

            if($query_transform_callback !== null && is_callable($query_transform_callback)){
                call_user_func($query_transform_callback, $query);
            }

            switch($relation_data['relation_type']){
                case Relation::MANY_TO_MANY:
                    $target_relation = $related_class::_findRelationName($relation_data['target_relation']['related_class']);
                    $query->with($target_relation); //I can put this here!
                    //Relation on the 'with' will always be -to-one
                    $this->_data[$relation_name] = array();
                    while($row = $query->fetchRow()){
                        $target = $row->$target_relation;
                        //If you have a m2m in the DB without the related row?
                        if($target !== null)
                            $this->_data[$relation_name][] = $target;
                    }
                    break;

                case Relation::ONE_TO_MANY:
                    $this->_data[$relation_name] = $query->fetchAll();
                    break;

                case Relation::MANY_TO_ONE:
                case Relation::ONE_TO_ONE:
                    $this->_data[$relation_name] = $query->fetchRow();
            }

        }

        return $this->_data[$relation_name];
    }


    /**
     * @param $relation_name
     * @param Model $object
     * @param bool $remove_relation
     * @return bool
     * @throws Exception
     * @throws Wave\Exception
     */
    public function _removeRelationObject($relation_name, Model $object, $remove_relation = true){


        if(isset($this->_data[$relation_name])) {
            foreach($this->_data[$relation_name] as $key => $relation){
                if($relation->_getFingerprint() === $object->_getFingerprint()){
                    //Unset
                    unset($this->_data[$relation_name][$key]);
                    //Renumber (so it doesn't accidentally map later down the track).
                    $this->_data[$relation_name] = array_values($this->_data[$relation_name]);
                }
            }
        }

        if($remove_relation) {
            $relation_data = $this->_getRelation($relation_name);

            switch($relation_data['relation_type']){
                case Relation::MANY_TO_MANY:
                    $related_class = $relation_data['related_class'];
                    $db = Wave\DB::get($related_class::_getDatabaseNamespace());
                    $query = $db->from($related_class, $from_alias);

                    //Add in all of the related columns
                    foreach($relation_data['local_columns'] as $position => $local_column) {
                        //At this point, may as well return as there aren't null-relations
                        if($this->_data[$local_column] === null)
                            return false;

                        $query->where(sprintf('%s.%s = ?', $from_alias, $db->escape($relation_data['related_columns'][$position])), $this->_data[$local_column]);
                    }

                    //Add in all of the related columns
                    foreach($relation_data['target_relation']['related_columns'] as $position => $related_column) {
                        //At this point, may as well return as there aren't null-relations
                        if($object->{$related_column} === null)
                            return false;

                        $query->where(sprintf('%s.%s = ?', $from_alias, $db->escape($relation_data['target_relation']['local_columns'][$position])), $object->{$related_column});
                    }

                    if($rc = $query->fetchRow())
                        return $rc->delete();

                    break;

                case Relation::ONE_TO_MANY:
                    foreach($relation_data['related_columns'] as $position => $related_column)
                        $object->{$related_column} = null;

                    return $object->save();

                    break;
            }

        }
    }


}
