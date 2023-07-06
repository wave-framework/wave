<?php

namespace Wave;

class DuplicateKeyException extends \Wave\DB\Exception
{
    public function __construct($message, $code = null)
    {
        parent::__construct($message, $code);
    }
}