<?php

class Wave_Annotation_BaseRoute extends Wave_Annotation {
	
	public function isFor() {
		return Annotation::FOR_METHOD;
	}

	protected function validate($class) {
		$this->minimumParameterCount(1);
		$this->maximumParameterCount(1);
		$this->validOnSubclassesOf($class,	Wave_Annotation::CLASS_CONTROLLER);
	}

}


?>