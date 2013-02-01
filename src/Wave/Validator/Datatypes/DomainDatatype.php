<?php

namespace Wave\Validator\Datatypes;

class DomainDatatype extends AbstractDatatype {

	public function __invoke(){
		return preg_match('/^([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i', $this->input) > 0;
	}

    /**
     * @return string a type to use in the violation message
     */
    public function getType() {
        return 'domain';
    }
}


?>