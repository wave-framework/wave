<?php

namespace Wave\DB\Driver;
use Wave\DB;

class MySQL extends DB\Driver implements DB\IDriver {

	public static function constructDSN($config){
	
		return "mysql:host={$config->host};dbname={$config->database};port={$config->port}";
	}
	
	public static function getDriverName(){
		return 'mysql';
	}
	
	public static function getTables($database){
		
		$tables = $database->basicQuery('SELECT `TABLE_SCHEMA` AS table_schema, `TABLE_NAME` AS table_name, `ENGINE` AS table_engine, '.
										'`TABLE_COLLATION` AS table_collation, `TABLE_COMMENT` AS table_comment '.
										'FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = ?;', $database->getName());		
		
		return $tables;	
	
	}
	
	public static function getColumns($database, $table = null){
		
		$columns = $database->basicQuery('SELECT `TABLE_SCHEMA` AS table_schema, `TABLE_NAME` AS table_name, `COLUMN_NAME` AS column_name, '.
										 '`COLUMN_DEFAULT` AS column_default,`IS_NULLABLE` AS nullable, `DATA_TYPE` AS data_type, '.
										 '`COLUMN_TYPE` AS column_type, `COLUMN_KEY` AS column_key, `EXTRA` AS column_extra, '.
										 '`COLUMN_COMMENT` AS column_comment '.
										 'FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = ? '.
										 'ORDER BY `TABLE_NAME`, `ORDINAL_POSITION` ASC;', $database->getName());
		
		return $columns;	
	
	}
	
	public static function getColumnKeys($database, $table = null, $column = null){
		
		$keys = $database->basicQuery('SELECT `TABLE_SCHEMA` AS table_schema, `TABLE_NAME` AS table_name, `COLUMN_NAME` AS column_name, '.
									  '`REFERENCED_TABLE_SCHEMA` AS referenced_table_schema, `REFERENCED_TABLE_NAME` AS referenced_table_name, '.
									  '`REFERENCED_COLUMN_NAME` AS referenced_column_name, `CONSTRAINT_NAME` AS constraint_name '.
									  'FROM `information_schema`.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA` = ?;', $database->getName());		

		
		return $keys;	
	
	}
	
	
	public static function translateSQLDataType($type){

		switch($type){
			case 'varchar':
				return Wave_DB_Column::TYPE_STRING;
				
			case 'int':
				return Wave_DB_Column::TYPE_INT;

			case 'float':
				return Wave_DB_Column::TYPE_FLOAT;
				
			case 'tinyint':
				return Wave_DB_Column::TYPE_BOOL;
			
			case 'datetime':
			case 'timestamp':
				return Wave_DB_Column::TYPE_TIMESTAMP;
			
			case 'date' :
				return Wave_DB_Column::TYPE_DATE;
			
			default:
				return Wave_DB_Column::TYPE_UNKNOWN;
		}
	}


	public static function translateSQLIndexType($type){
	
		switch($type){
			case 'PRI':
				return Wave_DB_Column::INDEX_PRIMARY;
			
			case 'MUL':			
			default:
				return Wave_DB_Column::INDEX_UNKNOWN;
		}
	}
	
	
	public static function translateSQLNullable($nullable){
	
		switch($nullable){
			case 'NO':
				return 'false';
			case 'YES':
				return 'true';
		}
	}
	
	public static function convertValueForSQL($value){
	
		switch(true){
			case is_null($value):
				return null;

			case $value instanceof DateTime:
				return $value->format('Y-m-d H:i:s');

			default:
				return $value;
		}
	}
	
	

}

?>