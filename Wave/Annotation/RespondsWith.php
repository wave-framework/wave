<?php

namespace Wave\Annotation;

use \Wave;

class RespondsWith extends Wave\Annotation {
	
	public $inherit = true;
	public $methods = array();
	
	public function isFor() {
		return Wave\Annotation::FOR_METHOD;
	}

	public function validate($class) {
		$this->minimumParameterCount(1);
		$this->validOnSubclassesOf($class, Wave\Annotation::CLASS_CONTROLLER);
		if(isset($this->parameters[0])){
			foreach($this->parameters as $i => $method){
				if(is_numeric($i) && !in_array($method, Wave\Response::$ALL))
					$this->errors[] = "Parameter $i contains \"" . $method . "\". Valid values: " . implode(', ', Wave\Response::$ALL) . '.';
			}
		}
	}
		
		
	public function build(){
		$this->inherit = false;
		if(isset($this->parameters['inherit'])){
			$this->inherit = $this->parameters['inherit'] == 'true';
			unset($this->parameters['inherit']);
		}
		
		$this->methods = $this->parameters;	
	}

	public function apply(Wave\Router\Action &$action){
		$action->setRespondsWith($this->methods, $this->inherit);
	}
}


?>