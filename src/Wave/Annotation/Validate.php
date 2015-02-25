<?php

namespace Wave\Annotation;

use Wave;
use Wave\Router\Action;

class Validate extends Wave\Annotation {

    private $schema;

    public function isFor() {
        return Wave\Annotation::FOR_METHOD;
    }

    public function validate($class) {
        $this->exactParameterCount(1);
        $this->validOnSubclassesOf($class, Wave\Annotation::CLASS_CONTROLLER);
    }

    public function build() {
        $this->schema = $this->parameters[0];
    }

    public function apply(Action &$action) {
        $action->setValidationSchema($this->schema);
        return true;
    }

}


?>