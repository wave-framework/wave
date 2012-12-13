<?php



class Wave_Validator_Datatype_Password extends Wave_Validator_Datatype {
	
	public function validate(){
		return parent::validate() == true ? Wave_Validator::INPUT_VALID : Wave_Validator::ERROR_INVALID;
	}
	
	public function sanitize(){
		return $this->input;
	}

}


?>