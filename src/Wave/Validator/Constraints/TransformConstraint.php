<?php


namespace Wave\Validator\Constraints;

use Wave\DB,
    Wave\DB\Model,
    Wave\Validator,
    Wave\Validator\Exception,
    Wave\Validator\CleanerInterface;

class TransformConstraint extends AbstractConstraint implements CleanerInterface {

    protected $key;
    protected $value;


    public function __construct($property, $arguments, Validator &$validator){
        if(!is_callable($arguments))
            throw new \InvalidArgumentException('The argument passed to [transform] must be callable');

        parent::__construct($property, $arguments, $validator);
    }

    /**
     * @return bool
     */
    public function evaluate(){
        $this->data = call_user_func_array($this->arguments, array(
            &$this->data,
            &$this->validator,
            &$this->key,
            &$this->message,
        ));

        return true;
    }


    public function getCleanedData() {
       return $this->data;
    }
}