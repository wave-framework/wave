<?php

namespace Wave\Validator\Constraints;

class RegexConstraint extends AbstractConstraint {

    /**
     * Evaluate the current constraint against the schema arguments and input data.
     *
     * @return mixed
     */
    public function evaluate() {
        return preg_match($this->arguments, $this->data) > 0;
    }

}
