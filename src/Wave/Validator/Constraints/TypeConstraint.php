<?php


namespace Wave\Validator\Constraints;

use \Wave\Validator,
    \Wave\Validator\CleanerInterface;

class TypeConstraint extends AbstractConstraint {

    const ERROR_INVALID = 'invalid';

    const DATATYPE_CLASS_MASK = '\\Wave\\Validator\\Datatypes\\%sDatatype';

    /**
     * @var $handler \Wave\Validator\Datatypes\AbstractDatatype
     */
    private $handler;

    public function __construct($property, $arguments, Validator &$validator){
        parent::__construct($property, $arguments, $validator);

        $handler_class = sprintf(self::DATATYPE_CLASS_MASK, ucfirst($arguments));
        if(!class_exists($handler_class))
           throw new \Wave\Validator\Exception("Datatype handler class for '$arguments' is not valid");

        $this->handler = new $handler_class($this->data);
    }

    /**
     * @return bool
     */
    public function evaluate(){
        return $this->handler->validate();
    }

    /**
     * @return string
     */
    protected function getViolationKey(){
        return static::ERROR_INVALID;
    }

    protected function getViolationMessage($context = 'This value'){
        return sprintf('%s is not a valid type', $context);
    }

    public function getViolationPayload(){
        return array_merge(
            parent::getViolationPayload(),
            array(
                'type' => $this->arguments
            )
        );
    }

    public function getCleanedData() {
        if($this->handler instanceof CleanerInterface)
            return $this->handler->getCleanedData();
        else
            return $this->data;
    }
}