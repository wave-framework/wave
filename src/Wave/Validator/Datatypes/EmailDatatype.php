<?php

namespace Wave\Validator\Datatypes;

class EmailDatatype extends AbstractDatatype {

    private $cleaned;

    public function __invoke() {
        $this->cleaned = trim($this->input);
        return filter_var($this->cleaned, FILTER_VALIDATE_EMAIL);
    }

    public function getCleanedData() {
        return $this->cleaned;
    }

    /**
     * @return string a type to use in the violation message
     */
    public function getType() {
        return 'email';
    }
}


?>