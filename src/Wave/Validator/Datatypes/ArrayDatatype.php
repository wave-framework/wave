<?php

namespace Wave\Validator\Datatypes;

class ArrayDatatype extends AbstractDatatype {


	public function __invoke(){
		return is_array($this->input);

	}


}


?>