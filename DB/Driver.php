<?php

abstract class Wave_DB_Driver {}

interface Wave_DB_IDriver {

	public static function constructDSN($config);
	public static function getDriverName();
	public static function getTables($database);
	public static function getColumns($database, $table);
	public static function getColumnKeys($database, $table, $column);
	
	public static function translateSQLDataType($type);

}

?>