<?php


namespace Wave\Validator\Constraints;

use \Wave\Validator,
    \Wave\Validator\Datatypes\AbstractDatatype,
    \Wave\Validator\CleanerInterface;

class TypeConstraint extends AbstractConstraint implements CleanerInterface {

    const ERROR_INVALID = 'invalid';

    const DATATYPE_CLASS_MASK = '\\Wave\\Validator\\Datatypes\\%sDatatype';

    /**
     * @var $handler \Wave\Validator\Datatypes\AbstractDatatype
     */
    private $handler;

    private $message;

    public function __construct($property, $arguments, Validator &$validator){
        parent::__construct($property, $arguments, $validator);

        $this->message = '%s is not a valid %s';
        if(is_array($arguments) && !is_callable($arguments)){
            if(isset($arguments['message'], $arguments['datatype'])){
                $this->message = $arguments['message'];
                $this->arguments = $arguments = $arguments['datatype'];
            }
            else throw new \InvalidArgumentException("Invalid format for type constraint, must contain a [message] and [datatype]");
        }

        if(is_callable($arguments)){
            $this->handler = $arguments;
        }
        else if(is_string($arguments)) {
            $handler_class = sprintf(self::DATATYPE_CLASS_MASK, ucfirst($arguments));
            if(!class_exists($handler_class))
                throw new \InvalidArgumentException("'type' handler '$arguments' is not valid for '$property'");

            $this->handler = new $handler_class($this->data);
        }
        else {
            throw new \InvalidArgumentException("Invalid 'type' specified for $property");
        }
    }

    /**
     * @return bool
     */
    public function evaluate(){
        return call_user_func($this->handler, $this->data, $this->validator);
    }

    /**
     * @return string
     */
    protected function getViolationKey(){
        return static::ERROR_INVALID;
    }

    protected function getViolationMessage($context = 'This value'){
        $type = 'type';
        if($this->handler instanceof AbstractDatatype)
            $type = $this->handler->getType();

        return sprintf($this->message, $context, $type);
    }

    public function getViolationPayload(){
        return array_merge(
            parent::getViolationPayload(),
            array(
                'type' => is_callable($this->arguments) ? 'custom' : $this->arguments
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