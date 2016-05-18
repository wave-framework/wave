<?php

namespace Wave\DB\Driver;

use DateTime;
use Wave\DB\Column;

abstract class AbstractDriver {

    public static function valueToSQL($value) {

        switch(true) {
            case is_bool($value):
                return $value ? 1 : 0;
            case $value instanceof DateTime:
                if($value->getTimezone()->getName() !== date_default_timezone_get()){
                    $value->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                }
                return $value->format('Y-m-d H:i:s');

            default:
                return $value;
        }
    }

    public static function valueFromSQL($value, array $field_data) {

        if($value === null)
            return null;
        else if(!is_scalar($value))
            return $value;

        switch($field_data['data_type']) {

            case Column::TYPE_BOOL:
                return (bool) $value;

            case Column::TYPE_INT:
                return (int) $value;

            case Column::TYPE_FLOAT:
                return (float) $value;

            case Column::TYPE_STRING:
                return (string) $value;

            case Column::TYPE_DATE:
            case Column::TYPE_TIMESTAMP:
                if($value == 'CURRENT_TIMESTAMP'){
                    $value = 'now';
                }
                return new DateTime($value);

            default:
                return $value;
        }
    }

}


?>