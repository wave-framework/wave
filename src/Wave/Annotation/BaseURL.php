<?php

namespace Wave\Annotation;

use Wave;

class BaseURL extends Wave\Annotation {

    const DEFAULT_KEYWORD = 'default';

    public function isFor() {
        return Wave\Annotation::FOR_METHOD;
    }

    public function validate($class) {
        $this->minimumParameterCount(1);
        $this->maximumParameterCount(1);
        $this->validOnSubclassesOf($class, Wave\Annotation::CLASS_CONTROLLER);
    }

    public function apply(Wave\Router\Action &$action) {
        $action->setProfile($this->parameters[0]);
    }

}


?>