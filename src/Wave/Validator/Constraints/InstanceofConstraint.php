<?php

namespace Wave\Validator\Constraints;

class InstanceofConstraint extends AbstractConstraint {


    /**
     * Evaluate the current constraint against the schema arguments and input data.
     *
     * @return mixed
     */
    public function evaluate() {
        return $this->data instanceof $this->arguments;
    }

    protected function getViolationMessage($context = 'This value'){
        return sprintf("%s is not an instance of %s", $context, $this->arguments);
    }

}
