<?php

class Wave_Annotation_RequiresLevel extends Wave_Annotation {
	
	const DEFAULT_KEYWORD = 'default';
	
	public function isFor() {
		return Annotation::FOR_METHOD;
	}

	protected function validate($class) {
		$this->validOnSubclassesOf($class,	Wave_Annotation::CLASS_CONTROLLER);
	}

	public function build(){
		if(isset($this->parameters['inherit'])){
			$this->inherit = $this->parameters['inherit'] == 'true';
			unset($this->parameters['inherit']);
		}
		
		$this->methods = $this->parameters;	
	}

}


?>