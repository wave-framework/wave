<?php


namespace Wave\Validator\Constraints;

use Wave\Validator,
    Wave\Validator\Exception,
    Wave\Validator\CleanerInterface;

/**
 * Reads an array of sub-constraints and returns true if any one of them returns true.
 */
class MapConstraint extends AbstractConstraint implements CleanerInterface {

    private $cleaned = array();
    private $violations = array();

    public function __construct($property, $arguments, &$validator){
        parent::__construct($property, $arguments, $validator);

        if(!is_array($this->data))
            throw new \InvalidArgumentException("[map] constraint requires an array of input data");
    }

    /**
     * @return bool
     */
    public function evaluate(){

        $schema = array($this->property => $this->arguments);
        foreach($this->data as $i => $data){
            $input = array($this->property => $data);
            $instance = new Validator($input, $schema, $this->validator);

            if($instance->execute(true)){
                $cleaned = $instance->getCleanedData();
                $this->cleaned[$i] = $cleaned[$this->property];
            }
            else {
                $violations = $instance->getViolations();
                $this->violations = $violations[$this->property];
                return false;
            }
        }

        return true;

    }

    public function getViolationPayload(){
        return $this->violations;
    }

    public function getCleanedData() {
        return $this->cleaned;
    }

}