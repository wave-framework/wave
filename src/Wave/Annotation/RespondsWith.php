<?php

namespace Wave\Annotation;

use Wave\Annotation;
use Wave\Http\Response;
use Wave\Router\Action;

class RespondsWith extends Annotation {
	
	public $inherit = true;
	public $methods = array();
	
	public function isFor() {
		return Annotation::FOR_METHOD;
	}

	public function validate($class) {
		$this->minimumParameterCount(1);
		$this->validOnSubclassesOf($class, Annotation::CLASS_CONTROLLER);
        $valid_options = array_keys(Response::getFormats());
		if(isset($this->parameters[0])){
			foreach($this->parameters as $i => $method){
				if(is_numeric($i) && !in_array($method, $valid_options))
					$this->errors[] = "Parameter $i contains \"" . $method . "\". Valid values: " . implode(', ', $valid_options) . '.';
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

	public function apply(Action &$action){
		$action->setRespondsWith($this->methods, $this->inherit);
	}
}


?>