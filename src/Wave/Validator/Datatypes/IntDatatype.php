<?php

namespace Wave\Validator\Datatypes;

use Wave\Validator\CleanerInterface;

class IntDatatype extends AbstractDatatype implements CleanerInterface {

    public function __invoke() {
        return (is_int($this->input) || (is_string($this->input) && strval(intval($this->input)) === $this->input));
    }

    public function getCleanedData() {
        return intval($this->input);
    }

    /**
     * @return string a type to use in the violation message
     */
    public function getType() {
        return 'integer';
    }
}


?>