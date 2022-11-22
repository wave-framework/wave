<?php

namespace Wave\Validator\Exception;

class InvalidInputException extends ValidationException
{

    protected $violations;

    public function __construct(array $violations)
    {
        $this->violations = $violations;

        parent::__construct('Input validation failed', 400);
    }

    public function getViolations()
    {
        return $this->violations;
    }

}