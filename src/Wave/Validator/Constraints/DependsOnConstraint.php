<?php


namespace Wave\Validator\Constraints;

use \Wave\DB,
    \Wave\DB\Model,
    \Wave\Validator;

class DependsOnConstraint extends AbstractConstraint {

    const ERROR_DEPENDS_ON = 'depends_on';

    /**
     * @return bool
     */
    public function evaluate(){
        if(!is_array($this->arguments)) $this->arguments = array($this->arguments);
        foreach($this->arguments as $field){
            if(!$this->validator->offsetExists($field) || $this->validator->getViolation($field) !== null){
                return false;
            }
        }
        return true;
    }


    public function getViolationPayload(){
        return array();
    }

}