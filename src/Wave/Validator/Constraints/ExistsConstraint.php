<?php


namespace Wave\Validator\Constraints;

use \Wave\DB,
    \Wave\DB\Model,
    \Wave\Validator,
    \Wave\Validator\CleanerInterface,
    \Wave\Validator\Exception;

class ExistsConstraint extends AbstractConstraint implements CleanerInterface {

    const ERROR_NOT_EXISTS = 'not_exists';

    protected $type = 'exists';
    protected $message = null;
    private $instance = null;

    public function __construct($property, $arguments, Validator &$validator){
        if(!is_array($arguments) || !isset($arguments['model']) || !isset($arguments['property']))
            throw new Exception("[{$this->type}] constraint requires a model and property to be declared");

        parent::__construct($property, $arguments, $validator);

        $this->message = isset($arguments['message']) ? $arguments['message'] : null;
        $this->instance = DB::get()->from($this->arguments['model'])
            ->where($this->arguments['property'] . ' = ?', $this->data)
            ->fetchRow();
    }

    /**
     * @return bool
     */
    public function evaluate(){
        return $this->instance instanceof Model;
    }

    public function getCleanedData(){
        return $this->instance;
    }

    /**
     * @return string
     */
    protected function getViolationKey(){
        return static::ERROR_NOT_EXISTS;
    }

    protected function getViolationMessage($context = 'This value'){
        $message = isset($this->message) ? $this->message : '%s does not exist';
        return sprintf($message, $context);
    }

    public function getViolationPayload(){
        return array_merge(
            parent::getViolationPayload(),
            array(
                $this->type => $this->arguments
            )
        );
    }

}