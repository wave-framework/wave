<?php

namespace Wave\Validator\Datatypes;

class EmailDatatype extends AbstractDatatype {

	public function __invoke(){
		return preg_match('/^([_a-z0-9-]+)((\+|\.)[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i', $this->input) > 0;
	}

}


?>