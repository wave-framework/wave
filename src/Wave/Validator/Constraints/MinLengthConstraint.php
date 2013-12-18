<?php


namespace Wave\Validator\Constraints;

class MinLengthConstraint extends AbstractLengthConstraint {

    const ERROR_TOO_SHORT = 'too_short';

    /**
     * @return bool
     */
    public function evaluate(){
        return $this->count >= $this->limit;
    }

    /**
     * @return string
     */
    protected function getViolationKey(){
        return static::ERROR_TOO_SHORT;
    }

    protected function getViolationMessage($context = 'This value'){
        if($this->message !== null)
            return $this->message;
        else if($this->comparator === static::COMPARATOR_ARRAY)
            return sprintf('%s must have at least %s members', $context, $this->limit);
        elseif($this->comparator === static::COMPARATOR_INT)
            return sprintf('%s must be greater than %s', $context, $this->limit);
        elseif($this->comparator === static::COMPARATOR_DATETIME)
            return sprintf('%s must be after %s', $context, $this->limit->format('c'));
        else
            return sprintf('%s must be at least %s characters', $context, $this->limit);
    }

    public function getViolationPayload(){
        return array_merge(
            parent::getViolationPayload(),
            array(
                'min_length' => $this->limit
            )
        );
    }


}