<?php

namespace Wave\Validator\Constraints;

use Wave\Validator;

class RegexConstraint extends AbstractConstraint {

    private $message;

    public function __construct($property, $arguments, Validator &$validator) {
        parent::__construct($property, $arguments, $validator);

        if(is_array($arguments) && !is_callable($arguments)) {
            if(isset($arguments['message'], $arguments['pattern'])) {
                $this->message = $arguments['message'];
                $this->arguments = $arguments['pattern'];
            } else throw new \InvalidArgumentException("Invalid format for regex constraint, must contain a [message] and [pattern]");
        }

    }

    /**
     * Evaluate the current constraint against the schema arguments and input data.
     *
     * @return mixed
     */
    public function evaluate() {
        return preg_match($this->arguments, $this->data) > 0;
    }

    protected function getViolationMessage($context = 'This value') {
        if(isset($this->message)) {
            return sprintf($this->message, $context);
        } else return parent::getViolationMessage($context);
    }

}
