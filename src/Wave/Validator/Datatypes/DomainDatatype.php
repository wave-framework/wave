<?php

namespace Wave\Validator\Datatypes;

class DomainDatatype extends AbstractDatatype {

	public function validate(){
		return preg_match('/^([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i', $this->input) > 0;
	}

}


?>