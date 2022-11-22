<?php

namespace Wave\Validator\Datatypes;

use Wave\Validator\CleanerInterface;

class BooleanDatatype extends AbstractDatatype implements CleanerInterface
{

    private $bool_true = array(true, 'true', '1', 1);
    private $bool_false = array(false, 'false', '0', 0);

    private $converted;

    public function __invoke()
    {
        if (in_array($this->input, $this->bool_true, true))
            $this->converted = true;
        else if (in_array($this->input, $this->bool_false, true))
            $this->converted = false;
        else
            return false;

        return true;
    }

    public function getCleanedData()
    {
        return $this->converted;
    }

    /**
     * @return string a type to use in the violation message
     */
    public function getType()
    {
        return 'boolean';
    }
}


?>