<?php

class Wave_Annotation_RequiresLevel extends Wave_Annotation {
	
	const DEFAULT_KEYWORD = 'default';
	
	public function isFor() {
		return Annotation::FOR_METHOD;
	}

	public function validate($class) {
		$this->minimumParameterCount(1);
		$this->validOnSubclassesOf($class,	Wave_Annotation::CLASS_CONTROLLER);
	}

	public function build(){
		$this->inherit = true;
		if(isset($this->parameters['inherit'])){
			$this->inherit = $this->parameters['inherit'] == 'true';
			unset($this->parameters['inherit']);
		}
		$this->methods = $this->parameters;	
	}

}


?>