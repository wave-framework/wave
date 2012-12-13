<?php



class Wave_Validator_Datatype_Default extends Wave_Validator_Datatype {

	public function validate(){
		$result = parent::validate();
		return $result === true ? Wave_Validator::INPUT_VALID : $result;
	}



}


?>