<?php

namespace Wave\Annotation;

use Wave;

class BaseRoute extends Wave\Annotation {

    public function isFor() {
        return Wave\Annotation::FOR_METHOD;
    }

    protected function validate($class) {
        $this->minimumParameterCount(1);
        $this->maximumParameterCount(1);
        $this->validOnSubclassesOf($class, Wave\Annotation::CLASS_CONTROLLER);
    }

    public function apply(Wave\Router\Action &$action) {
        return $action->addBaseRoute($this->parameters[0]);
    }

}


?>