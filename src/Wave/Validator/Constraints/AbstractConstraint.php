<?php

namespace Wave\Validator\Constraints;

use Wave\Validator;

abstract class AbstractConstraint {

    const ERROR_INVALID = 'invalid';

    protected $data;
    protected $arguments;

    protected $validator;

    /**
     * @param string $property the property to evaluate against (from the input data)
     * @param mixed $arguments the arguments for the current constraint (from the schema)
     * @param \Wave\Validator $validator the current instance of the validator
     */
    public function __construct($property, $arguments, Validator &$validator){

        $this->property = $property;
        $this->data = isset($validator[$property]) ? $validator[$property] : null;
        $this->arguments = $arguments;

        $this->validator = $validator;
    }

    /**
     * Evaluate the current constraint against the schema arguments and input data.
     *
     * @return mixed
     */
    abstract public function evaluate();


    protected function getViolationKey(){
        return static::ERROR_INVALID;
    }

    /**
     * Forms a message that can be displayed in a UI.
     *
     * @param string $context Allows setting a relevant name for the field
     *                        (for example: 'Your email address' is not valid).
     * @return string
     */
    protected function getViolationMessage($context = 'This value'){
        return sprintf('%s is not valid', $context);
    }

    public function getViolationPayload(){
        return array(
            'field_name' => $this->property,
            'field_value' => $this->data,
            'reason'     => $this->getViolationKey(),
            'message'    => $this->getViolationMessage()
        );
    }

}