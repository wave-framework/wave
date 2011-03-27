<?php

class Wave_Annotation_Route extends Wave_Annotation {
	
	public function isFor() {
		return Annotation::FOR_METHOD;
	}

	public function validate($class) {
		$this->minimumParameterCount(2);
		$this->maximumParameterCount(5);
		$this->validOnSubclassesOf($class, Wave_Annotation::CLASS_CONTROLLER);
		if(isset($this->parameters[0])){
			$methods = explode('|', $this->parameters[0]);
			foreach($methods as $method){
				if(!in_array($method, Wave_Method::$ALL))
					$this->errors[] = "Parameter 0 contains \"" . $method . "\". Valid values: " . implode(', ', $values) . '.';
			}
		}
	}
	
	public function build(){
		$this->methods = explode('|', $this->parameters[0]);
		$this->url = $this->parameters[1];
	}
		
}


?>