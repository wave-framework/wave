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
        return $this->validator->getViolation($this->arguments) === null;
    }

    /**
     * @return string
     */
    protected function getViolationKey(){
        return static::ERROR_DEPENDS_ON;
    }

    protected function getViolationMessage($context = 'This value'){
        return sprintf('%s is not valid', $context);
    }

}