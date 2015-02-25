<?php

namespace Wave\Validator\Constraints;

use DateTime;
use Wave\Validator;
use Wave\Validator\CleanerInterface;

class DateConstraint extends AbstractConstraint implements CleanerInterface {

    private $format = null;
    private $datetime;

    public function __construct($property, $arguments, Validator $validator) {
        parent::__construct($property, $arguments, $validator);

        if($arguments !== '*')
            $this->format = $arguments;

    }

    /**
     * Evaluate the current constraint against the schema arguments and input data.
     *
     * @return mixed
     */
    public function evaluate() {

        $this->datetime = null;

        try {
            if($this->format !== null)
                $this->datetime = DateTime::createFromFormat($this->format, $this->data);
            else
                $this->datetime = new DateTime($this->data);

            if(!($this->datetime instanceof DateTime)) return false;
            return true;

        } catch(\Exception $e) {
            return false;
        }

    }

    protected function getViolationMessage($context = 'This date') {
        if($this->format === null)
            return sprintf('%s is not in a recognised date format', $context);
        else
            return sprintf('%s is not in the required format (%s)', $context, $this->format);
    }

    public function getCleanedData() {
        return $this->datetime;
    }
}
