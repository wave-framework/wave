<?php

namespace Wave\Validator\Datatypes;

abstract class AbstractDatatype {

    /**
     * @var $input mixed
     */
    protected $input;

    public function __construct($input){
        $this->input = $input;
    }

    /**
     * @param mixed $input
     * @return bool
     */
    abstract public function __invoke();

}
