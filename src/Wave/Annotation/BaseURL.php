<?php

namespace Wave\Annotation;


use Wave\Annotation;
use Wave\Router\Action;

class BaseURL extends ArrayArguments {
	
	const DEFAULT_KEYWORD = 'default';

	public function validate($class) {
		$this->minimumParameterCount(1);
		$this->maximumParameterCount(1);
		$this->validOnSubclassesOf($class,	Annotation::CLASS_CONTROLLER);
	}

	public function apply(Action &$action) {
		$action->setProfile($this->parameters[0]);
	}

}
