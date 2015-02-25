<?php

namespace Wave\Utils;

use DateTime;
use Wave\DB\Model;

class JSON {

    public static function encode($data) {
        return json_encode(self::arrayify($data));
    }

    public static function arrayify($data) {
        if($data instanceof DateTime) {
            return $data->format('r');
        } else if(is_array($data) || is_object($data)) {
            $jsonarr = array();
            if($data instanceof Model)
                $data = $data->_toArray();
            foreach($data as $key => $value) {
                $jsonarr[$key] = self::arrayify($value);
            }
            return $jsonarr;
        } else {
            return $data;
        }
    }
}

?>