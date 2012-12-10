<?php

namespace Wave\Annotation;
use \Wave;

class Route extends Wave\Annotation {
	
	public function isFor() {
		return Wave\Annotation::FOR_METHOD;
	}

	public function validate($class) {
		$this->minimumParameterCount(2);
		$this->maximumParameterCount(2);
		$this->validOnSubclassesOf($class, Wave\Annotation::CLASS_CONTROLLER);
		if(isset($this->parameters[0])){
			$methods = explode('|', $this->parameters[0]);
			foreach($methods as $method){
				if(!in_array($method, Wave\Method::$ALL))
					$this->errors[] = "Parameter 0 contains \"" . $method . "\". Valid values: " . implode(', ', Wave\Method::$ALL) . '.';
			}
		}
	}
	
	public function build(){
		$this->methods = explode('|', $this->parameters[0]);
		$this->url = $this->parameters[1];
	}

	public function apply(Wave\Router\Action &$action){
		return $action->addRoute($this->methods, $this->url);
	}
		
}


?>