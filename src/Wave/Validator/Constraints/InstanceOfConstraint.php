<?php

namespace Wave\Validator\Constraints;

class InstanceOfConstraint extends AbstractConstraint
{


    /**
     * Evaluate the current constraint against the schema arguments and input data.
     *
     * @return mixed
     */
    public function evaluate()
    {
        return $this->data instanceof $this->arguments;
    }

    protected function getViolationMessage($context = 'This value')
    {
        return sprintf("%s is not a valid %s object", $context, $this->arguments);
    }

}
