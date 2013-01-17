<?php


namespace Wave\Validator\Datatypes;

class StringDatatype extends AbstractDatatype {

	public function validate(){
        return is_string($this->input);
	}

}


?>