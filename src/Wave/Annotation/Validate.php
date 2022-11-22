<?php

namespace Wave\Annotation;

use Wave\Annotation;
use Wave\Router\Action;

class Validate extends ArrayArguments
{

    private $schema;

    public function validate($class)
    {
        $this->exactParameterCount(1);
        $this->validOnSubclassesOf($class, Annotation::CLASS_CONTROLLER);
    }

    public function build()
    {
        $this->schema = $this->parameters[0];
    }

    public function apply(Action &$action)
    {
        $action->setValidationSchema($this->schema);
    }

}

