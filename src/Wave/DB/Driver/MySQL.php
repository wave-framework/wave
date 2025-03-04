<?php

/**
 *    MySQL Driver
 *
 * @author Michael michael@calcin.ai
 **/

namespace Wave\DB\Driver;

use Wave;
use Wave\Config\Row;
use Wave\DB;

class MySQL extends AbstractDriver implements DriverInterface
{

    //Selecting from the information schema tables is slow as they are built on select so need to cache the whole set and manipulate in php.
    static $_column_cache;
    static $_relation_cache;

    public static function constructDSN(Row $config)
    {

        $dsn = "mysql:host={$config->host};dbname={$config->database};port={$config->port}";

        $optional_properties = ['charset'];
        foreach ($optional_properties as $prop) {
            if (isset($config->{$prop}))
                $dsn .= ";{$prop}={$config->$prop}";

        }

        return $dsn;
    }

    public static function getDriverName()
    {
        return 'mysql';
    }

    public static function getEscapeCharacter()
    {
        return '`';
    }

    public static function getTables(DB $database)
    {

        $table_sql = 'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_COMMENT ' .
            'FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = ?;';


        $table_stmt = $database->getConnection()->prepare($table_sql);
        $table_stmt->execute(array($database->getName()));

        $tables = array();
        while ($table_row = $table_stmt->fetch()) {

            $table = new DB\Table(
                $database,
                $table_row['TABLE_NAME'],
                $table_row['ENGINE'],
                $table_row['TABLE_COLLATION'],
                $table_row['TABLE_COMMENT']
            );

            $tables[$table_row['TABLE_NAME']] = $table;

        }
        return $tables;

    }


    public static function getColumns(DB\Table $table)
    {

        //using namespace for the table identifier as there might be same name DBs on different servers
        $namespace = $table->getDatabase()->getNamespace();

        if (!isset(self::$_column_cache[$namespace])) {

            self::$_column_cache[$namespace] = array();

            $column_sql = 'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, COLUMN_TYPE, EXTRA, COLUMN_COMMENT ' .
                'FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = ? ORDER BY `TABLE_NAME`, ORDINAL_POSITION;';

            $column_stmt = $table->getDatabase()->getConnection()->prepare($column_sql);
            $column_stmt->execute(array($table->getDatabase()->getName()));

            while ($column_row = $column_stmt->fetch())
                self::$_column_cache[$namespace][$column_row['TABLE_NAME']][] = $column_row;

        }

        $columns = array();
        //may not be any columns
        if (isset(self::$_column_cache[$namespace][$table->getName()])) {
            foreach (self::$_column_cache[$namespace][$table->getName()] as $cached_row) {

                $column = new DB\Column(
                    $table,
                    $cached_row['COLUMN_NAME'],
                    self::translateSQLNullable($cached_row['IS_NULLABLE']),
                    self::translateSQLDataType($cached_row['DATA_TYPE']),
                    self::translateSQLDefault($cached_row),
                    $cached_row['EXTRA'] === 'auto_increment',
                    $cached_row['COLUMN_TYPE'],
                    $cached_row['EXTRA'],
                    $cached_row['COLUMN_COMMENT']
                );

                $columns[$cached_row['COLUMN_NAME']] = $column;
            }
        }

        return $columns;

    }

    public static function getRelations(DB\Table $table)
    {

        //using namespace for the table identifier as there might be same name DBs on different servers
        $namespace = $table->getDatabase()->getNamespace();

        $relation_cache = self::_getRelationCache($table);

        $relations = array();
        //may not be any constraints
        if ($relation_cache !== null) {
            foreach ($relation_cache as $cached_row) {

                //--- check both ends of the relation can be built.
                $local_db = DB::getByDatabaseName($cached_row['TABLE_SCHEMA']);
                if ($local_db === null) {
                    Wave\Log::write('mysql_driver', sprintf('Database [%s] is not referenced in the configuration - skipping building relations.', $cached_row['TABLE_SCHEMA']), Wave\Log::WARNING);
                    continue;
                }
                $local_column = $local_db->getColumn($cached_row['TABLE_NAME'], $cached_row['COLUMN_NAME']);

                //skip if there's no referenced schema.  This is because primary keys will be in the relation cache (no ref schema)
                if ($cached_row['REFERENCED_TABLE_SCHEMA'] === null)
                    continue;

                $referenced_db = DB::getByDatabaseName($cached_row['REFERENCED_TABLE_SCHEMA']);
                if ($referenced_db === null) {
                    Wave\Log::write('mysql_driver', sprintf('Database [%s] is not referenced in the configuration - skipping building relations.', $cached_row['REFERENCED_TABLE_SCHEMA']), Wave\Log::WARNING);
                    continue;
                }
                $referenced_column = $referenced_db->getColumn($cached_row['REFERENCED_TABLE_NAME'], $cached_row['REFERENCED_COLUMN_NAME']);
                if ($referenced_column === null) {
                    Wave\Log::write('mysql_driver', sprintf('Column [%s] is not referenced in the configuration - skipping building relations.', $cached_row['REFERENCED_COLUMN_NAME']), Wave\Log::WARNING);
                    continue;
                }
                //-----

                if ($cached_row['REFERENCED_TABLE_SCHEMA'] != $cached_row['TABLE_SCHEMA']) {
                    //print_r($cached_row);
                    //exit;
                }


                $relation = DB\Relation::create($local_column, $referenced_column, $cached_row['CONSTRAINT_NAME'], isset($cached_row['REVERSE']));

                if ($relation !== null)
                    $relations[$relation->getIdentifyingName()] = $relation;
                else
                    Wave\Log::write('mysql_driver', sprintf('[%s.%s.%s] has duplicate relations.', $cached_row['TABLE_SCHEMA'], $cached_row['TABLE_NAME'], $cached_row['COLUMN_NAME']), Wave\Log::WARNING);

            }
        }

        return $relations;

    }

    public static function getConstraints(DB\Table $table)
    {

        $constraints = array();

        if (null === $relation_cache = self::_getRelationCache($table))
            return $constraints;

        foreach ($relation_cache as $relation) {

            $column = $table->getDatabase()->getColumn($relation['TABLE_NAME'], $relation['COLUMN_NAME']);

            if ($column === null)
                continue;

            if (!isset($constraints[$relation['CONSTRAINT_NAME']])) {
                $constraints[$relation['CONSTRAINT_NAME']] = new DB\Constraint($column, self::translateSQLConstraintType($relation['CONSTRAINT_TYPE']), $relation['CONSTRAINT_NAME']);
            } else {
                $idx = $constraints[$relation['CONSTRAINT_NAME']];
                $idx->addColumn($column);
            }
        }
        return $constraints;
    }

    private static function _getRelationCache(DB\Table $table)
    {

        $namespace = $table->getDatabase()->getNamespace();

        if (!isset(self::$_relation_cache[$namespace])) {

            self::$_relation_cache[$namespace] = array();

            //join across these memory views is slow but it's much tidier than any other way.
            $relations_sql = 'SELECT kcu.TABLE_SCHEMA, kcu.TABLE_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_SCHEMA, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME, tc.CONSTRAINT_NAME, tc.CONSTRAINT_TYPE ' .
                'FROM information_schema.TABLE_CONSTRAINTS tc ' .
                'INNER JOIN information_schema.KEY_COLUMN_USAGE kcu USING(CONSTRAINT_SCHEMA, CONSTRAINT_NAME, TABLE_NAME) ' .
                'WHERE tc.TABLE_SCHEMA = ? OR kcu.REFERENCED_TABLE_SCHEMA = ?;';

            $relations_stmt = $table->getDatabase()->getConnection()->prepare($relations_sql);
            $relations_stmt->execute(array($table->getDatabase()->getName(), $table->getDatabase()->getName()));

            while ($relations_row = $relations_stmt->fetch()) {
                self::$_relation_cache[$namespace][$relations_row['TABLE_NAME']][] = $relations_row;
                //Relations added for both directions, flag the one that's reversed.
                $relations_row['REVERSE'] = true;
                self::$_relation_cache[$namespace][$relations_row['REFERENCED_TABLE_NAME']][] = $relations_row;
            }
        }

        return isset(self::$_relation_cache[$namespace][$table->getName()]) ? self::$_relation_cache[$namespace][$table->getName()] : null;

    }

    public static function translateSQLDataType($type)
    {

        switch ($type) {
            case 'varchar':
                return DB\Column::TYPE_STRING;

            case 'bigint':
            case 'int':
                return DB\Column::TYPE_INT;

            case 'float':
            case 'decimal':
                return DB\Column::TYPE_FLOAT;

            case 'tinyint':
                return DB\Column::TYPE_BOOL;

            case 'datetime':
            case 'timestamp':
                return DB\Column::TYPE_TIMESTAMP;

            case 'date' :
                return DB\Column::TYPE_DATE;

            case 'json' :
                return DB\Column::TYPE_JSON;

            default:
                return DB\Column::TYPE_UNKNOWN;
        }
    }


    public static function translateSQLConstraintType($type)
    {

        switch ($type) {
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


    public static function translateSQLNullable($nullable)
    {

        switch ($nullable) {
            case 'NO':
                return false;
            case 'YES':
                return true;
        }
    }

    public static function translateSQLDefault($row)
    {

        $value = $row['COLUMN_DEFAULT'];
        $type = self::translateSQLDataType($row['DATA_TYPE']);

        if (is_null($value) || strtolower($value) === 'null' || self::translateSQLNullable($row['IS_NULLABLE'])) {
            $value = null;
        } else if (DB\Column::TYPE_FLOAT == $type) {
            $value = (float)$value;
        } else if (DB\Column::TYPE_INT === $type) {
            $value = (int)$value;
        } elseif (DB\Column::TYPE_BOOL === $type) {
            $value = !!$value;
        }

        return $value;
    }

    public static function convertException(\PDOException $exception)
    {
        switch ($exception->errorInfo[1]) {
            case '1050':
                return new DB\Exception\TableExistsException($exception->getMessage(), $exception->getCode());
            case '1051':
            case '1146':
                return new DB\Exception\TableNotFoundException($exception->getMessage(), $exception->getCode());
            case '1216':
            case '1217':
            case '1451':
            case '1452':
                return new DB\Exception\ForeignKeyConstraintViolationException($exception->getMessage(), $exception->getCode());
            case '1062':
            case '1557':
            case '1569':
            case '1586':
                return new DB\Exception\DuplicateKeyException($exception->getMessage(), $exception->getCode());

            case '1054':
            case '1166':
            case '1611':
                return new DB\Exception\InvalidFieldNameException($exception->getMessage(), $exception->getCode());
            case '1052':
            case '1060':
            case '1110':
                return new DB\Exception\NonUniqueFieldNameException($exception->getMessage(), $exception->getCode());
            case '1064':
            case '1149':
            case '1287':
            case '1341':
            case '1342':
            case '1343':
            case '1344':
            case '1382':
            case '1479':
            case '1541':
            case '1554':
            case '1626':
                return new DB\Exception\SyntaxErrorException($exception->getMessage(), $exception->getCode());
            case '1044':
            case '1045':
            case '1046':
            case '1049':
            case '1095':
            case '1142':
            case '1143':
            case '1227':
            case '1370':
            case '2002':
            case '2005':
                return new DB\Exception\ConnectionException($exception->getMessage(), $exception->getCode());
            case '1048':
            case '1121':
            case '1138':
            case '1171':
            case '1252':
            case '1263':
            case '1566':
                return new DB\Exception\NotNullConstraintViolationException($exception->getMessage(), $exception->getCode());
        }

        return new DB\Exception($exception->getMessage(), $exception->getCode());
    }

}

?>
