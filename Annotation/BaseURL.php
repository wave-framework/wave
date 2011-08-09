<?php

class Wave_Annotation_BaseURL extends Wave_Annotation {
	
	const DEFAULT_KEYWORD = 'default';
	
	public function isFor() {
		return Annotation::FOR_METHOD;
	}

	public function validate($class) {
		$this->minimumParameterCount(1);
		$this->maximumParameterCount(1);
		$this->validOnSubclassesOf($class,	Wave_Annotation::CLASS_CONTROLLER);
	}

}


?>