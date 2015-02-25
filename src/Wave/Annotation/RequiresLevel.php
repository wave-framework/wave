<?php

namespace Wave\Annotation;

use Wave;

class RequiresLevel extends Wave\Annotation {

    const DEFAULT_KEYWORD = 'default';

    public function isFor() {
        return Wave\Annotation::FOR_METHOD;
    }

    public function validate($class) {
        $this->minimumParameterCount(1);
        $this->validOnSubclassesOf($class, Wave\Annotation::CLASS_CONTROLLER);
    }

    public function build() {
        $this->inherit = true;
        if(isset($this->parameters['inherit'])) {
            $this->inherit = $this->parameters['inherit'] == 'true';
            unset($this->parameters['inherit']);
        }
        $this->methods = $this->parameters;
    }

    public function apply(Wave\Router\Action &$action) {
        return $action->setRequiresLevel($this->methods, $this->inherit);
    }

}


?>