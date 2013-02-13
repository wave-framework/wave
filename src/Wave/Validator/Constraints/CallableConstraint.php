<?php


namespace Wave\Validator\Constraints;

use \Wave\DB,
    \Wave\DB\Model,
    \Wave\Validator,
    \Wave\Validator\Exception;

class CallableConstraint extends AbstractConstraint {

    const ERROR_CALLABLE = 'callable';

    private $key = self::ERROR_CALLABLE;
    private $message = '%s is not valid';

    public function __construct($property, $arguments, Validator &$validator){
        if(!is_callable($arguments))
            throw new Exception('The argument passed to [callable] must be callable');

        parent::__construct($property, $arguments, $validator);
        $this->cleaned = $this->data;
    }

    /**
     * @return bool
     */
    public function evaluate(){
        return call_user_func_array($this->arguments, array(
            &$this->data,
            &$this->validator,
            &$this->key,
            &$this->message,
        ));
    }

    /**
     * @return string
     */
    protected function getViolationKey(){
        return $this->key;
    }

    protected function getViolationMessage($context = 'This value'){
        return sprintf($this->message, $context);
    }

}