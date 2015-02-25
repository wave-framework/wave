<?php

/**
 *    For representing a relation in the database.  Used for Model generation.
 *
 * @author Michael michael@calcin.ai
 **/

namespace Wave\DB;

use Wave;


class Relation {

    const RELATION_UNKNOWN = 00;

    const ONE_TO_ONE = 11;
    const ONE_TO_MANY = 12;
    const MANY_TO_ONE = 21;
    const MANY_TO_MANY = 22;

    static $instances = array();

    private $local_columns = array();
    private $referenced_columns = array();
    private $target_relation = null;

    private $is_reverse_relation;
    private $type;

    private $instance_name;

    private function __construct(Column $local_column, Column $referenced_column, $is_reverse_relation, $instance_name = '') {

        $this->local_columns[] = $is_reverse_relation ? $referenced_column : $local_column;
        $this->referenced_columns[] = $is_reverse_relation ? $local_column : $referenced_column;
        $this->is_reverse_relation = $is_reverse_relation;
        $this->instance_name = $instance_name;
        $this->type = $this->determineRelationType();

    }

    public static function create(Column $local_column, Column $referenced_column, $constraint_name, $is_reverse_relation) {

        //This is to support multiple column foreign keys, any constraints with the same name will be treated as an additional column.
        //An assumption is made in the driver that the constraint names will be unique across the table
        $instance_name = sprintf(
            '%s.%s.%s__%s', $local_column->getTable()->getDatabase()->getName(), $local_column->getTable()
                ->getName(), $constraint_name,
            $is_reverse_relation ? 'reverse' : 'forward'
        );

        if(isset(self::$instances[$instance_name])) {
            self::$instances[$instance_name]->addColumns($local_column, $referenced_column, $is_reverse_relation);
        } else {
            self::$instances[$instance_name] = new self($local_column, $referenced_column, $is_reverse_relation, $instance_name);
        }

        return self::$instances[$instance_name];

    }

    private function determineRelationType() {

        //all relation definitions based on the first column in the key
        if($this->local_columns[0]->isPrimaryKey() && $this->referenced_columns[0]->isPrimaryKey()) {
            //will either be a one-one relation or a many-many join table

            if($this->getLocalTable()->getPrimaryKey() !== null && count(
                    $this->getLocalTable()->getPrimaryKey()->getColumns()
                ) > 1
            ) {
                //if local table has more than one PK it's modt likely a join table.
                $type = self::MANY_TO_ONE;
            } elseif($this->getReferencedTable()->getPrimaryKey() !== null && 1 < $num_ref_column = count(
                    $this->getReferencedTable()->getPrimaryKey()->getColumns()
                )
            ) {
                //if referencing a table with dual PK, it's most likely a m2m join table that has more to load.
                if($num_ref_column === 2) {
                    $type = self::MANY_TO_MANY;
                    //go back and find the other relation
                    //need to iterate to find the one that's not this.
                    foreach($this->getReferencedTable()->getRelations() as $relation)
                        if($relation->getReferencedColumns() != $this->local_columns)
                            $this->target_relation = $relation;

                    //if target relation isn't found, there must be a special case, so just roll back to one-to-many
                    //or if target relation has a dual primary key, can't base it being a join table on it anymore.
                    if($this->target_relation === null || count(
                            $this->target_relation->getReferencedTable()->getPrimaryKey()->getColumns()
                        ) > 1
                    )
                        $type = self::ONE_TO_MANY;

                } else {
                    //if not 2 PKS it is not reliable enough to assume what's going on.
                    $type = self::RELATION_UNKNOWN;
                }
            } else {
                //Otherwise o2o
                $type = self::ONE_TO_ONE;
            }

        } elseif($this->is_reverse_relation) {
            $type = self::ONE_TO_MANY;
        } else {
            $type = self::MANY_TO_ONE;
        }


        return $type;
    }

    public function addColumns($local_column, $referenced_column, $is_reverse_relation) {
        $column = $is_reverse_relation ? $referenced_column : $local_column;
        if(!in_array($column, $this->local_columns))
            $this->local_columns[] = $column;

        $column = $is_reverse_relation ? $local_column : $referenced_column;
        if(!in_array($column, $this->referenced_columns))
            $this->referenced_columns[] = $column;
    }

    public function getLocalColumns() {
        return $this->local_columns;
    }

    public function getLocalTable() {
        //it will always be the same if there are multiple columns
        return $this->local_columns[0]->getTable();
    }

    public function getReferencedColumns() {
        return $this->referenced_columns;
    }

    public function getReferencedTable() {
        //it will always be the same if there are multiple columns
        return $this->referenced_columns[0]->getTable();
    }

    public function getTargetRelation() {
        return $this->target_relation;
    }

    public function getType() {
        return $this->type;
    }

    public function getIdentifyingName() {
        return $this->instance_name;
    }

    /**
     * Returns the name of the relation.  It needs to be based on the column name as if there
     * is more than one relation to the same table, the relation won't have a unique name.
     * If the column ends with '_id', it will be removed.
     **/
    public function getName() {

        //$local_column
        switch($this->type) {
            case self::ONE_TO_ONE:
                $name = $this->getReferencedTable()->getName();
                break;
            case self::MANY_TO_ONE:
                //in this case we need to name the relation based on the column, trimming off _id (if it exists)
                $name = $this->local_columns[0]->getName();
                if(substr($name, -3) === '_id')
                    $name = substr($name, 0, -3);
                break;
            case self::ONE_TO_MANY:
                //slightly more complex to remove collisions between m2m names
                $name = Wave\Inflector::pluralize($this->getReferencedTable()->getName());
                $ref_name = $this->referenced_columns[0]->getName();
                if(substr($ref_name, -3) === '_id')
                    $ref_name = substr($ref_name, 0, -3);
                if($ref_name !== $this->getLocalTable()->getName())
                    $name .= '_' . $ref_name;
                break;
            case self::MANY_TO_MANY:
                $columns = $this->target_relation->getLocalColumns();
                $name = $columns[0]->getMetadata('relation_name');
                if($name === null) {
                    $name = $this->target_relation->getReferencedTable()->getName();
                }

                $name = Wave\Inflector::pluralize($name);
                break;
        }

        return Wave\Inflector::camelize($name);

    }


}