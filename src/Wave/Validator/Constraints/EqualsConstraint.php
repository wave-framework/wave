<?php

namespace Wave\Validator\Constraints;

class EqualsConstraint extends AbstractConstraint {

    /**
     * Evaluate the current constraint against the schema arguments and input data.
     *
     * @return mixed
     */
    public function evaluate() {
        return $this->data === $this->arguments;
    }

    protected function getViolationMessage($context = 'This value'){
        return sprintf("%s does not equal %s", $context, $this->arguments);
    }

}
