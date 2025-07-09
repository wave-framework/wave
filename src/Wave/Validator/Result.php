<?php


namespace Wave\Validator;


use ArrayObject;
use Wave\Validator;

class Result extends ArrayObject {

    private $violations;
    private $validator;

    public function __construct(array $cleaned, array $errors = array(), ?Validator $validator = null) {
        parent::__construct($cleaned);
        $this->violations = $errors;
        $this->validator = $validator;
    }

    public function isValid() {
        return empty($this->violations);
    }

    public function getViolations() {
        return $this->violations;
    }

    public function getCleanedData() {
        return $this->getArrayCopy();
    }

}