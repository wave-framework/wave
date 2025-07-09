<?php


namespace Wave\Validator\Constraints;

use Wave\DB;
use Wave\Validator;
use Wave\Validator\CleanerInterface;
use Wave\Validator\Exception;

class CallableConstraint extends AbstractConstraint implements CleanerInterface {

    const ERROR_CALLABLE = 'callable';

    private $key = self::ERROR_CALLABLE;
    private $message = '%s is not valid';
    private $cleaned;

    public function __construct($property, $arguments, Validator &$validator) {
        if(!is_callable($arguments))
            throw new \InvalidArgumentException('The argument passed to [callable] must be callable');

        parent::__construct($property, $arguments, $validator);
        $this->cleaned = $this->data;
    }

    /**
     * @return bool
     */
    public function evaluate() {
        return call_user_func_array(
            $this->arguments, array(
                &$this->data,
                &$this->validator,
                &$this->key,
                &$this->message,
            )
        );
    }

    /**
     * @return string
     */
    protected function getViolationKey() {
        return $this->key;
    }

    protected function getViolationMessage($context = 'This value') {
        return sprintf($this->message, $context);
    }

    public function getCleanedData() {
        return $this->data;
    }
}