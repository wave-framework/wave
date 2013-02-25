<?php

namespace Wave\DB\Driver;

use Wave\DB,
    Wave\Config\Row;

interface DriverInterface {

    public static function constructDSN(Row $config);
    public static function getTables(DB $database);
    public static function getDriverName();
    public static function getEscapeCharacter();

}

