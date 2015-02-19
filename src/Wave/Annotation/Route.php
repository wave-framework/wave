<?php

namespace Wave\Annotation;

use Wave\Annotation;
use Wave\Router\Action;

class Route extends ArrayArguments {

    protected $methods;
    protected $url;

    public function validate($class) {
		$this->minimumParameterCount(2);
		$this->maximumParameterCount(2);
		$this->validOnSubclassesOf($class, Annotation::CLASS_CONTROLLER);
	}
	
	public function build(){
		$this->methods = explode('|', $this->parameters[0]);
		$this->url = $this->parameters[1];
	}

	public function apply(Action &$action){
		$action->addRoute($this->methods, $this->url);
	}
		
}
