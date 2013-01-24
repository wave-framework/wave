<?php

namespace Wave\DB;

abstract class Driver {}

interface IDriver {

	public static function constructDSN($config);
	public static function getDriverName();
	public static function getTables($database);
	
}

?>