<?php


namespace Wave\Validator\Constraints;


use Wave\Validator;

class RenameConstraint extends AbstractConstraint {

    public function __construct($property, $arguments, Validator &$validator){
        if(!is_string($arguments))
            throw new \InvalidArgumentException('The argument passed to [rename] must be a string');

        parent::__construct($property, $arguments, $validator);
    }

    /**
     * @return bool
     */
    public function evaluate(){
        $this->validator->setCleanedData($this->arguments, $this->data);
        $this->validator->unsetCleanedData($this->property, true);
        return true;
    }



}