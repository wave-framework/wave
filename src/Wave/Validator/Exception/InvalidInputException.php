<?php

namespace Wave\Validator\Exception;

class InvalidInputException extends ValidationException {

    protected $violations;

    public function __construct(array $violations){
        $this->violations = $violations;

        parent::__construct('Input validation failed', 449);
    }

    public function getViolations(){
        return $this->violations;
    }

}