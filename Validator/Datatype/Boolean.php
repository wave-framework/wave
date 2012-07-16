<?php



class Wave_Validator_Datatype_Boolean extends Wave_Validator_Datatype {

	private $bool_true = array('true', '1', 1);
	private $bool_false = array('false', '0', 0);
	
	public function validate(){
		$parentCheck = parent::validate();
		if($parentCheck !== true) return $parentCheck;
		
		
		if(in_array($this->input, $this->bool_true, true))
			$this->converted = true;
		else if(in_array($this->input, $this->bool_false, true))
			$this->converted = false;
		else
			$this->converted = $this->input;
		
		if(!isset($this->params['must_be_true']) || $this->params['must_be_true'])
			return $this->converted === true ? Wave_Validator::INPUT_VALID : Wave_Validator::ERROR_INVALID;
		else
			return is_bool($this->converted) ? Wave_Validator::INPUT_VALID : Wave_Validator::ERROR_INVALID;
	}
	
	public function sanitize(){
		return $this->converted;
	}

}


?>