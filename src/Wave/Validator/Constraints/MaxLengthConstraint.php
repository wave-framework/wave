<?php


namespace Wave\Validator\Constraints;

class MaxLengthConstraint extends AbstractLengthConstraint {

    const ERROR_TOO_LONG = 'too_long';

    /**
     * @return bool
     */
    public function evaluate(){
        return $this->count <= $this->limit;
    }

    /**
     * @return string
     */
    protected function getViolationKey(){
        return static::ERROR_TOO_LONG;
    }

    protected function getViolationMessage($context = 'This value'){
        if($this->message !== null)
            return $this->message;
        else if($this->comparator === static::COMPARATOR_ARRAY)
            return sprintf('%s must have no more than %s members', $context, $this->limit);
        elseif($this->comparator === static::COMPARATOR_INT)
            return sprintf('%s must be less than %s', $context, $this->limit);
        elseif($this->comparator === static::COMPARATOR_DATETIME)
            return sprintf('%s must be before %s', $context, $this->limit->format('c'));
        else
            return sprintf('%s must have no more than %s characters', $context, $this->limit);
    }

    public function getViolationPayload(){
        return array_merge(
            parent::getViolationPayload(),
            array(
                'max_length' => $this->limit
            )
        );
    }


}