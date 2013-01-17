<?php

namespace Wave\Validator\Datatypes;

use Wave\Validator\CleanerInterface;

class IntDatatype extends AbstractDatatype implements CleanerInterface  {

	public function validate(){
        return (is_int($this->input) || strval(intval($this->input)) === $this->input);
	}
	
	public function getCleanedData(){
		return intval($this->input);
	}

}


?>