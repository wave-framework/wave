<?php


namespace Wave\Validator\Datatypes;

class StringDatatype extends AbstractDatatype {

	public function __invoke(){
        return is_string($this->input);
	}

}


?>