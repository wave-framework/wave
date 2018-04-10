<?php

/**
 *    MySQL Driver
 *
 * @author Patrick patrick@hindmar.sh
 **/

namespace Wave\DB\Driver;

use Wave\Config\Row;
use Wave\DB;
use Wave\Log;

class PostgreSQL extends AbstractDriver implements DriverInterface {

    //Selecting from the information schema tables is slow as they are built on select so need to cache the whole set and manipulate in php.
    static $_column_cache;
    static $_relation_cache;

    public static function constructDSN(Row $config) {

        return "pgsql:host={$config->host};port={$config->port};dbname={$config->database}";
    }

    public static function getDriverName() {
        return 'pgsql';
    }

    public static function getEscapeCharacter() {
        return '"';
    }

    public static function getTables(DB $database) {

        $table_sql = 'SELECT "table_name", "table_type" FROM "information_schema"."tables" WHERE "table_schema" = ?';

        $table_stmt = $database->getConnection()->prepare($table_sql);
        $table_stmt->execute(array($database->getSchema()));

        $tables = array();
        while($table_row = $table_stmt->fetch()) {

            $table = new DB\Table($database, $table_row['table_name']);

            $tables[$table_row['table_name']] = $table;

        }
        return $tables;

    }


    public static function getColumns(DB\Table $table) {

        //using namespace for the table identifier as there might be same name DBs on different servers
        $namespace = $table->getDatabase()->getNamespace();

        if(!isset(self::$_column_cache[$namespace])) {

            self::$_column_cache[$namespace] = array();

            $column_sql = 'SELECT "table_name", "column_name", "column_default", "is_nullable", "data_type" ' .
                'FROM "information_schema"."columns" WHERE "table_schema" = ? ORDER BY ordinal_position';

            $column_stmt = $table->getDatabase()->getConnection()->prepare($column_sql);
            $column_stmt->execute(array($table->getDatabase()->getSchema()));

            while($column_row = $column_stmt->fetch())
                self::$_column_cache[$namespace][$column_row['table_name']][] = $column_row;

        }

        $columns = array();
        //may not be any columns
        if(isset(self::$_column_cache[$namespace][$table->getName()])) {
            foreach(self::$_column_cache[$namespace][$table->getName()] as $cached_row) {

                list($default_value, $is_serial, $sequence) = self::translateSQLDefault($cached_row['column_default']);

                $column = new DB\Column(
                    $table,
                    $cached_row['column_name'],
                    self::translateSQLNullable($cached_row['is_nullable']),
                    self::translateSQLDataType($cached_row['data_type']),
                    $default_value,
                    $is_serial
                );

                if($sequence !== null)
                    $column->setSequenceName($sequence);

                $columns[$cached_row['column_name']] = $column;
            }
        }

        return $columns;

    }

    public static function getRelations(DB\Table $table) {

        //using namespace for the table identifier as there might be same name DBs on different servers
        $namespace = $table->getDatabase()->getNamespace();

        $relation_cache = self::_getRelationCache($table);

        $relations = array();
        //may not be any constraints
        if($relation_cache !== null) {
            foreach($relation_cache as $cached_row) {

                //--- check both ends of the relation can be built.
                $local_db = DB::getByConfig(
                    array(
                        'database' => $cached_row['table_catalog'],
                        'schema' => $cached_row['table_schema']
                    )
                );
                if($local_db === null) {
                    Log::write('pgsql_driver', sprintf('Database [%s] is not referenced in the configuration - skipping building relations.', $cached_row['table_catalog']), Log::WARNING);
                    continue;
                }
                $local_column = $local_db->getColumn($cached_row['table_name'], $cached_row['column_name']);

                //skip if there's no referenced schema.  This is because primary keys will be in the relation cache (no ref schema)
                if($cached_row['referenced_table_schema'] === null)
                    continue;

                $referenced_db = DB::getByConfig(
                    array(
                        'database' => $cached_row['referenced_table_catalog'],
                        'schema' => $cached_row['referenced_table_schema']
                    )
                );
                if($referenced_db === null) {
                    Log::write('pgsql_driver', sprintf('Database [%s] is not referenced in the configuration - skipping building relations.', $cached_row['referenced_table_schema']), Log::WARNING);
                    continue;
                }
                $referenced_column = $referenced_db->getColumn($cached_row['referenced_table_name'], $cached_row['referenced_column_name']);
                //-----

                $relation = DB\Relation::create($local_column, $referenced_column, $cached_row['constraint_name'], isset($cached_row['reverse']));

                if($relation !== null)
                    $relations[$relation->getIdentifyingName()] = $relation;
                else
                    Log::write('mysql_driver', sprintf('[%s.%s.%s] has duplicate relations.', $cached_row['table_schema'], $cached_row['table_name'], $cached_row['column_name']), Log::WARNING);

            }
        }

        return $relations;

    }

    public static function getConstraints(DB\Table $table) {

        $constraints = array();

        if(null === $relation_cache = self::_getRelationCache($table))
            return $constraints;

        foreach($relation_cache as $relation) {

            $column = $table->getDatabase()->getColumn($relation['table_name'], $relation['column_name']);

            if(!isset($constraints[$relation['constraint_name']])) {
                $constraints[$relation['constraint_name']] = new DB\Constraint($column, self::translateSQLConstraintType($relation['constraint_type']), $relation['constraint_name']);
            } else {
                $idx = $constraints[$relation['constraint_name']];
                $idx->addColumn($column);
            }
        }
        return $constraints;
    }

    private static function _getRelationCache(DB\Table $table) {

        $namespace = $table->getDatabase()->getNamespace();

        if(!isset(self::$_relation_cache[$namespace])) {

            self::$_relation_cache[$namespace] = array();

            //join across these memory views is slow but it's much tidier than any other way.
            $relations_sql = 'SELECT
                DISTINCT (tc.constraint_name, kcu.column_name),
                tc.table_catalog,
                tc.table_schema,
                tc.table_name,
                kcu.column_name,
                CASE WHEN constraint_type = \'FOREIGN KEY\' THEN ccu.table_catalog END AS referenced_table_catalog,
                CASE WHEN constraint_type = \'FOREIGN KEY\' THEN ccu.table_schema END AS referenced_table_schema,
                CASE WHEN constraint_type = \'FOREIGN KEY\' THEN ccu.table_name END AS referenced_table_name,
                CASE WHEN constraint_type = \'FOREIGN KEY\' THEN ccu.column_name END AS referenced_column_name,
                tc.constraint_name,
                constraint_type
            FROM
                information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
                JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
            WHERE tc.table_schema = :schema OR kcu.table_schema = :schema';

            $relations_stmt = $table->getDatabase()->getConnection()->prepare($relations_sql);
            $relations_stmt->execute(array('schema' => $table->getDatabase()->getSchema()));

            while($relations_row = $relations_stmt->fetch()) {
                self::$_relation_cache[$namespace][$relations_row['table_name']][] = $relations_row;
                //Relations added for both directions, flag the one that's reversed.
                $relations_row['reverse'] = true;
                self::$_relation_cache[$namespace][$relations_row['referenced_table_name']][] = $relations_row;
            }
        }

        return isset(self::$_relation_cache[$namespace][$table->getName()]) ? self::$_relation_cache[$namespace][$table->getName()] : null;

    }

    public static function translateSQLDataType($type) {

        switch($type) {

            case 'text':
            case 'char':
            case 'character varying':
            case 'varchar':
                return DB\Column::TYPE_STRING;

            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int2':
            case 'int':
            case 'int4':
            case 'serial4':
            case 'integer':
            case 'int8':
            case 'bigint':
            case 'bigserial':
            case 'serial8':
            case 'int24':
                return DB\Column::TYPE_INT;

            case 'real':
            case 'float':
            case 'float4':
            case 'decimal':
            case 'numeric':
            case 'double':
            case 'float8':
                return DB\Column::TYPE_FLOAT;

            case 'bool':
            case 'boolean':
                return DB\Column::TYPE_BOOL;

            case 'datetime':
            case 'timestamp':
            case 'timestamptz':
            case 'timestamp with time zone':
            case 'timestamp without time zone':
                return DB\Column::TYPE_TIMESTAMP;

            case 'date' :
                return DB\Column::TYPE_DATE;

            case 'json' :
                return DB\Column::TYPE_JSON;

            default:
                return DB\Column::TYPE_UNKNOWN;
        }
    }


    public static function translateSQLConstraintType($type) {

        switch($type) {
            case 'PRIMARY KEY':
                return DB\Constraint::TYPE_PRIMARY;
            case 'UNIQUE':
                return DB\Constraint::TYPE_UNIQUE;
            case 'FOREIGN KEY':
                return DB\Constraint::TYPE_FOREIGN;
            default:
                return DB\Constraint::TYPE_UNKNOWN;
        }
    }


    public static function translateSQLNullable($nullable) {

        switch($nullable) {
            case 'NO':
                return false;
            case 'YES':
                return true;
        }
    }

    public static function translateSQLDefault($column_default) {

        list($value, $original_type) = explode('::', $column_default, 2) + array(null, null);

        $is_serial = false;
        $sequence = null;
        $type = self::translateSQLDataType($original_type);
        if(strtolower($value) === 'null') {
            $value = null;
        } else if(preg_match('/nextval\(\'(?<sequence_name>.+?)\'/', $column_default, $matches)) {
            $value = null;
            $is_serial = true;
            $sequence = $matches['sequence_name'];
        } else if(DB\Column::TYPE_FLOAT == $type) {
            $value = (float) $value;
        } else if(DB\Column::TYPE_INT === $type) {
            $value = (int) $value;
        } else if('now()' === $value) {
            $value = 'CURRENT_TIMESTAMP';
        } else {
            // just trim any quotes and make it a string (catches enums/custom types)
            $value = trim($value, '\'');
        }

        return array($value, $is_serial, $sequence);
    }


}

?>