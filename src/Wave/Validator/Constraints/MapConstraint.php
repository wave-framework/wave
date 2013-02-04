<?php


namespace Wave\Validator\Constraints;

use \Wave\Validator,
    \Wave\Validator\Exception;

/**
 * Reads an array of sub-constraints and returns true if any one of them returns true.
 */
class MapConstraint extends AbstractConstraint {

    public function __construct($property, $arguments, &$validator){
        parent::__construct($property, $arguments, $validator);

        if(!is_array($this->data))
            throw new Exception("[map] constraint requires an array of input data");
    }

    /**
     * @return bool
     */
    public function evaluate(){

        $schema = array($this->property => $this->arguments);
        foreach($this->data as $data){
            $input = array($this->property => $data);
            $instance = new Validator($input, $schema, $this->validator);

            if(!$instance->execute(true)){
                return false;
            }
        }

        return true;

    }

    public function getViolationPayload(){
        return array();
    }

}