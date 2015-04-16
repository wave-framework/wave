<?php

namespace Wave\Annotation;


use Wave\Annotation;
use Wave\Router\Action;

class BaseRoute extends ArrayArguments {
	
    protected function validate($class) {
        $this->minimumParameterCount(1);
        $this->maximumParameterCount(1);
        $this->validOnSubclassesOf($class, Annotation::CLASS_CONTROLLER);
    }

    public function apply(Action &$action) {
        $action->addBaseRoute($this->parameters[0]);
    }

}
