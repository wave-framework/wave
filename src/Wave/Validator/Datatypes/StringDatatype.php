<?php


namespace Wave\Validator\Datatypes;

class StringDatatype extends AbstractDatatype {

	public function __invoke(){
        return is_string($this->input);
	}

    /**
     * @return string a type to use in the violation message
     */
    public function getType() {
        return 'string';
    }
}


?>