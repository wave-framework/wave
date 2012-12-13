<?php



class Wave_Validator_Datatype_Intlphone extends Wave_Validator_Datatype {

	
	public function validate(){
		
		$len = $this->checkLength(25, self::LENGTH_LT, self::COMPARATOR_STRING) && $this->checkLength(2, self::LENGTH_GT, self::COMPARATOR_STRING);

		preg_match('/^\+?([0-9]+( )?)+$/', $this->input, $result);
		
		return count($result) > 0 ? Wave_Validator::INPUT_VALID : Wave_Validator::ERROR_INVALID;
	}
	
	public function sanitize(){
		return $this->input;
	}

}


?>