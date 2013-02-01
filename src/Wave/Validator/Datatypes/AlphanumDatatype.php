<?php


namespace Wave\Validator\Datatypes;

class AlphanumDatatype extends AbstractDatatype {

	public function __invoke(){
        if(!is_scalar($this->input)) return false;
		return preg_match('/^[A-Za-z0-9]*$/', $this->input) > 0;
	}


    /**
     * @return string a type to use in the violation message
     */
    public function getType() {
        return 'alphanumeric string';
    }
}


?>