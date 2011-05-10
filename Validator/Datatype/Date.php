<?php



class Wave_Validator_Datatype_Date extends Wave_Validator_Datatype {


	public function validate(){
		$parentCheck = parent::validate();
		if($parentCheck !== true) return $parentCheck;
	
		$this->datetime = null;
		
		if(isset($this->params['strict']) && $this->params['strict'] == true){
			if(!isset($this->params['format']))
				throw new Wave_Exception('Date validator requires \'format\' property when in strict mode');
			
			try { 	
				$this->datetime = DateTime::createFromFormat($this->params['format'], $this->input);
			} catch (Exception $e) { 
				return Wave_Validator::ERROR_INVALID; 
			}
		}
		else {
			try {
				$this->datetime = new DateTime($this->input);
			} catch (Exception $e) { 
				return Wave_Validator::ERROR_INVALID; 
			}
		}
		
		return $this->datetime instanceof DateTime ? Wave_Validator::INPUT_VALID : Wave_Validator::ERROR_INVALID;
	
	}
	
	public function sanitize(){
		return $this->datetime;
	}


}


?>