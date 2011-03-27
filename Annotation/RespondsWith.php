<?php

class Wave_Annotation_RespondsWith extends Wave_Annotation {
	
	public $inherit = true;
	public $methods = array();
	
	public function isFor() {
		return Annotation::FOR_METHOD;
	}

	public function validate($class) {
		$this->minimumParameterCount(1);
		$this->validOnSubclassesOf($class, Wave_Annotation::CLASS_CONTROLLER);
	}
		
		
	public function build(){
		if(isset($this->parameters['inherit'])){
			$this->inherit = $this->parameters['inherit'] == 'true';
			unset($this->parameters['inherit']);
		}
		
		$this->methods = $this->parameters;	
	}
	
	public function addMethods($methods){
		foreach($methods as $method){
			if(array_search($method, $this->methods) === false)
				$this->methods[] = $method;
		}
	}
}


?>