<?php



class Wave_Validator_Datatype_Array extends Wave_Validator_Datatype {


	public function validate(){
		$parentCheck = parent::validate();
		if($parentCheck !== true) return $parentCheck;

		if (!is_array($this->input)) 
            return Wave_Validator::ERROR_INVALID;
        else
            return Wave_Validator::INPUT_VALID;

	}
	
	public function sanitize(){
		$out = $this->clean($this->input);
		return is_array($out) ? $out : array($out);
	}
	
	private function clean($input){
		if(!is_array($input) && !is_object($input)) 
			return htmlentities($input);
			
		$out = array();
		foreach($input as $key => $value){
			if(is_array($value) || is_object($value))
				$out[$key] = $this->clean($value);
			else 
				$out[$key] = htmlentities($value);
		}
			
		return $out;
	}

}


?>