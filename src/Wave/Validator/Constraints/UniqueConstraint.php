<?php


namespace Wave\Validator\Constraints;

use Wave\Validator\CleanerInterface;

class UniqueConstraint extends ExistsConstraint implements CleanerInterface {

    const ERROR_NOT_UNIQUE = 'not_unique';

    protected $type = 'unique';

    /**
     * @return bool
     */
    public function evaluate() {
        return !parent::evaluate();
    }

    public function getCleanedData() {
        return $this->data;
    }

    /**
     * @return string
     */
    protected function getViolationKey() {
        return static::ERROR_NOT_UNIQUE;
    }

    protected function getViolationMessage($context = 'This value') {
        $message = isset($this->message) ? $this->message : '%s is not unique';
        return sprintf($message, $context);
    }

}