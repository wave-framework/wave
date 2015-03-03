<?php

namespace Wave\Validator\Constraints;

use DateTime;
use Wave\Validator;

abstract class AbstractLengthConstraint extends AbstractConstraint {

    const COMPARATOR_ARRAY = 'array';
    const COMPARATOR_INT = 'int';
    const COMPARATOR_STRING = 'string';
    const COMPARATOR_DATETIME = 'datetime';

    protected $comparator = null;
    protected $limit = null;
    protected $count = null;
    protected $message = null;

    public function __construct($property, $arguments, Validator $validator) {
        parent::__construct($property, $arguments, $validator);

        if(is_array($arguments)) {
            $this->limit = isset($arguments['limit'])
                ? $arguments['limit']
                : null;

            $comparators = array(
                self::COMPARATOR_ARRAY,
                self::COMPARATOR_INT,
                self::COMPARATOR_STRING,
                self::COMPARATOR_DATETIME
            );
            $this->comparator = isset($arguments['comparator']) && in_array($arguments['comparator'], $comparators)
                ? $arguments['comparator']
                : null;

            $this->message = isset($arguments['message']) ? $arguments['message'] : null;
        } else {
            $this->limit = $arguments;
        }

        if($this->comparator === null) {
            if(is_array($this->data))
                $this->count = self::COMPARATOR_ARRAY;
            elseif(is_numeric($this->data))
                $this->comparator = self::COMPARATOR_INT;
            elseif(is_string($this->data))
                $this->comparator = self::COMPARATOR_STRING;
            elseif($this->data instanceof DateTime)
                $this->comparator = self::COMPARATOR_DATETIME;
        }

        switch($this->comparator) {
            case self::COMPARATOR_ARRAY:
                $this->count = count($this->data);
                break;
            case self::COMPARATOR_STRING:
                $this->count = strlen($this->data);
                break;
            case self::COMPARATOR_DATETIME:
                $this->count = $this->data;
                break;
            default:
                $this->count = (double) $this->data;
        }
    }

}