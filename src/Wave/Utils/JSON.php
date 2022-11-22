<?php

namespace Wave\Utils;

use ArrayObject;
use DateTime;
use DateTimeInterface;
use Wave\DB\Model;

class JSON
{

    public static function encode($data)
    {
        return json_encode(self::arrayify($data));
    }

    public static function arrayify($data)
    {
        if ($data instanceof DateTimeInterface) {
            /*
             * DateTime::ISO8601 isn't actually iso-8601
             * http://php.net/manual/en/class.datetime.php#datetime.constants.iso8601
             */
            return $data->format(DateTime::ATOM);
        } else if (is_array($data) || is_object($data)) {
            $jsonarr = array();

            if ($data instanceof Model) {
                $data = $data->_toArray();
            }

            if ($data instanceof ArrayObject) {
                $data = $data->getArrayCopy();
            }

            foreach ($data as $key => $value) {
                $jsonarr[$key] = self::arrayify($value);
            }

            // empty objects will be converted to arrays when json_encoded
            // so preserve the object type if the array is empty
            if (is_object($data) && empty($jsonarr)) {
                return (object)$jsonarr;
            } else {
                return $jsonarr;
            }
        } else {
            return $data;
        }
    }
}

?>
