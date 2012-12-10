<?php



class Wave_Validator_Datatype_Alphanum extends Wave_Validator_Datatype {

	/**
	 *	For the purposes of this validation we will say the space character is a
	 *	valid alphanumeric input because alphanum is commonly used for names etc
	**/
	public function validate(){
		$parentCheck = parent::validate();
		if($parentCheck !== true) return $parentCheck;
		
		if (!is_string($this->input) && !is_int($this->input) && !is_float($this->input)) {
            return Wave_Validator::ERROR_INVALID;
        }

		$result = preg_match('/^[A-Za-z0-9]*$/', $this->input) > 0;
		return $result ? Wave_Validator::INPUT_VALID : Wave_Validator::ERROR_INVALID;
	}

}


?>