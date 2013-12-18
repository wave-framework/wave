<?php


namespace Wave\Validator\Constraints;

use \Wave\Validator,
    \Wave\Validator\Exception,
    \Wave\Validator\CleanerInterface;

/**
 * Reads an array of sub-constraints and returns true if any one of them returns true.
 */
class AnyConstraint extends AbstractConstraint implements CleanerInterface {

    private $cleaned = null;
    private $violations = array();

    private $message = null;

    /**
     * @throws \InvalidArgumentException
     * @return bool
     */
    public function evaluate(){

        if(!is_array($this->arguments))
            throw new \InvalidArgumentException("[any] constraint requires an array argument");
        if(!isset($this->arguments[0]))
            $this->arguments = array($this->arguments);

        $input = array($this->property => $this->data);
        foreach($this->arguments as $key => $constraint_group){

            if($key === 'message'){
                $this->message = $constraint_group;
                continue;
            }

            $schema = array('fields' => array($this->property => $constraint_group));
            $instance = new Validator($input, $schema);

            if($instance->execute()){
                $cleaned = $instance->getCleanedData();
                $this->cleaned = $cleaned[$this->property];
                return true;
            }
            else {
                $violations = $instance->getViolations();
                $messages = array_intersect_key($violations[$this->property], array_flip(array('reason', 'message')));
                if(!empty($messages))
                    $this->violations[] = $messages;
            }
        }
        return empty($this->violations);
    }

    public function getViolationPayload(){
        $payload = array(
            'reason' => 'invalid',
        );
        if($this->message !== null){
            $payload['message'] = $this->message;
        }
        else {
            $payload['message'] = 'This value does not match any of the following conditions';
            $payload['conditions'] = $this->violations;
        }
        return $payload;
    }

    public function getCleanedData() {
        return $this->cleaned;
    }
}