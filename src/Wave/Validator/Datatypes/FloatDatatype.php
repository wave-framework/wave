<?php

namespace Wave\Validator\Datatypes;

use Wave\Validator\CleanerInterface;

class FloatDatatype extends AbstractDatatype implements CleanerInterface  {

	public function __invoke(){

        if(!is_scalar($this->input)) return false;

        $this->input = strtr($this->input, ',', '');
        return is_float($this->input) || (is_string($this->input) && strval(floatval($this->input) == $this->input));

	}
	
	public function getCleanedData(){
		return floatval($this->input);
	}

    /**
     * @return string a type to use in the violation message
     */
    public function getType() {
        return 'decimal number';
    }
}


?>