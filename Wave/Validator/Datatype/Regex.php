<?php



class Wave_Validator_Datatype_Regex extends Wave_Validator_Datatype {

	
	public function validate(){
		$parentCheck = parent::validate();
		if($parentCheck !== true) return $parentCheck;
		
		$result = preg_match($this->params['match'], $this->input) > 0;
		return $result ? Wave_Validator::INPUT_VALID : Wave_Validator::ERROR_INVALID;
	}
	
	public function sanitize(){
		return $this->input;
	}

}


?>