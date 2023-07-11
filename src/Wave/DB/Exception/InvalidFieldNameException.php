<?php

namespace Wave\DB\Exception;

use Wave\DB\Exception;

class InvalidFieldNameException extends Exception
{
    public function __construct($message, $code = null)
    {
        parent::__construct($message, $code);
    }
}
