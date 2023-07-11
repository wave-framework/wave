<?php

namespace Wave\DB\Driver;

use Wave\Config\Row;
use Wave\DB;

interface DriverInterface {

    public static function constructDSN(Row $config);

    public static function getTables(DB $database);

    public static function getDriverName();

    public static function getEscapeCharacter();

    public static function convertException(\PDOException $exception);
}

