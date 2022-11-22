<?php

namespace Wave\Validator\Datatypes;

class ArrayDatatype extends AbstractDatatype
{


    public function __invoke()
    {
        return is_array($this->input);

    }


    /**
     * @return string a type to use in the violation message
     */
    public function getType()
    {
        return 'array';
    }
}


?>