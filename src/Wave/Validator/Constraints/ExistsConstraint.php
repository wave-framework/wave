<?php


namespace Wave\Validator\Constraints;

use \Wave\DB,
    \Wave\DB\Model,
    \Wave\Validator,
    \Wave\Validator\CleanerInterface,
    \Wave\Validator\Exception;

class ExistsConstraint extends AbstractConstraint implements CleanerInterface {

    const ERROR_NOT_EXISTS = 'not_exists';

    private $instance = null;

    public function __construct($property, $arguments, Validator &$validator){
        if(!is_array($arguments) || !isset($arguments['model']) || !isset($arguments['property']))
            throw new Exception("[exists] constraint requires a model and property to be declared");

        parent::__construct($property, $arguments, $validator);
    }

    /**
     * @return bool
     */
    public function evaluate(){
        $this->instance = DB::get()->from($this->arguments['model'])
                        ->where($this->arguments['property'] . ' = ?', $this->data)
                        ->fetchRow();

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
        return sprintf('%s does not exist', $context);
    }

    public function getViolationPayload(){
        return array_merge(
            parent::getViolationPayload(),
            array(
                'exists' => $this->arguments
            )
        );
    }

}