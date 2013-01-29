<?php

namespace Wave\Validator\Constraints;

use \Wave\Validator,
    \Wave\Validator\Exception;

class PropertyEqualsConstraint extends AbstractConstraint {


    public function __construct($property, $arguments, Validator &$validator){
        if(!is_array($arguments) || !isset($arguments['property']) || !isset($arguments['value']))
            throw new Exception("[property_equals] constraint must have a property and value declared");

        parent::__construct($property, $arguments, $validator);

        if(!is_object($this->data))
            throw new Exception("[property_equals] constraint requires an object parameter");
    }

    /**
     * Evaluate the current constraint against the schema arguments and input data.
     *
     * @return mixed
     */
    public function evaluate() {
        $property = $this->arguments['property'];
        return isset($this->data->$property) && $this->data->$property === $this->arguments['value'];
    }

    protected function getViolationMessage($context = 'This value'){
        return sprintf("%s failed a property comparison", $context, $this->arguments);
    }

}
