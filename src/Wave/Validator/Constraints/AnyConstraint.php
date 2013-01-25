<?php


namespace Wave\Validator\Constraints;

use \Wave\Validator,
    \Wave\Validator\Exception,
    \Wave\Validator\CleanerInterface;

/**
 * Reads an array of sub-constraints and returns true if any one of them returns true.
 */
class AnyConstraint extends AbstractConstraint {

    private $cleaned = null;
    private $violation_payloads = array();

    /**
     * @return bool
     */
    public function evaluate(){

        if(!is_array($this->arguments))
            throw new Exception("[any] constraint requires an array argument");
        if(!isset($this->arguments[0]))
            $this->arguments = array($this->arguments);

        $input = array($this->property => $this->data);
        foreach($this->arguments as $constraint_group){
            $schema = array($this->property => $constraint_group);
            $instance = new Validator($input, $schema);

            if($instance->execute(true)){
                $cleaned = $instance->getCleanedData();
                $this->cleaned = $cleaned[$this->property];
                return true;
            }
        }
        return false;
    }

    public function getViolationPayload(){
        return array();
    }

    public function getCleanedData() {
        return $this->cleaned;
    }
}