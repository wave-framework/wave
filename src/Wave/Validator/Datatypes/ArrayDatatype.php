<?php

namespace Wave\Validator\Datatypes;

class ArrayDatatype extends AbstractDatatype {


	public function validate(){
		return is_array($this->input);

	}


}


?>