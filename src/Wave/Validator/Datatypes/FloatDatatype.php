<?php

namespace Wave\Validator\Datatypes;

use Wave\Validator\CleanerInterface;

class FloatDatatype extends AbstractDatatype implements CleanerInterface  {

	public function __invoke(){

        if(!is_scalar($this->input)) return false;

        return is_float($this->input) || strval(floatval($this->input)) == $this->input;

	}
	
	public function getCleanedData(){
		return floatval($this->input);
	}

}


?>