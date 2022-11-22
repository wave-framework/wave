<?php

namespace Wave\Validator\Datatypes;

use Wave\Validator\CleanerInterface;

class FloatDatatype extends AbstractDatatype implements CleanerInterface
{

    public function __invoke()
    {

        if (!is_scalar($this->input)) return false;

        return false !== filter_var($this->input, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND);
    }

    public function getCleanedData()
    {
        return floatval(filter_var($this->input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
    }

    /**
     * @return string a type to use in the violation message
     */
    public function getType()
    {
        return 'decimal number';
    }
}


?>