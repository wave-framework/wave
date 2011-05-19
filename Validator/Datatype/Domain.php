<?php



class Wave_Validator_Datatype_Domain extends Wave_Validator_Datatype {

	
	public function validate(){
		$parentCheck = parent::validate();
		if($parentCheck !== true) return $parentCheck;
		$result = preg_match('/^([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i', $this->input) > 0;
		return $result ? Wave_Validator::INPUT_VALID : Wave_Validator::ERROR_INVALID;
	}
	
	public function sanitize(){
		return htmlentities($this->input);
	}

}


?>