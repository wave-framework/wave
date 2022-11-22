<?php

namespace Wave\Annotation;


use Wave\Annotation;
use Wave\Router\Action;

class RequiresLevel extends ArrayArguments
{

    const DEFAULT_KEYWORD = 'default';

    public function validate($class)
    {
        $this->minimumParameterCount(1);
        $this->validOnSubclassesOf($class, Annotation::CLASS_CONTROLLER);
    }

    public function build()
    {
        $this->inherit = true;
        if (isset($this->parameters['inherit'])) {
            $this->inherit = $this->parameters['inherit'] == 'true';
            unset($this->parameters['inherit']);
        }
        $this->methods = $this->parameters;
    }

    public function apply(Action &$action)
    {
        return $action->setRequiresLevel($this->methods, $this->inherit);
    }

}
