<?php



class Wave_Validator_Datatype_Float extends Wave_Validator_Datatype {

	public function validate(){
		$parentCheck = parent::validate();
		if($parentCheck !== true) return $parentCheck;

		if (is_array($this->input) || (!is_string($this->input) && !is_int($this->input) && !is_float($this->input))) 
            return Wave_Validator::ERROR_INVALID;
        
        
        if (is_float($this->input) || strval(floatval($this->input)) == $this->input) 
            return Wave_Validator::INPUT_VALID;
        else
        	return Wave_Validator::ERROR_INVALID;

	}
	
	public function sanitize(){
		return floatval($this->input);
	}

}


?>