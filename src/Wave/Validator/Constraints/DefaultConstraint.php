<?php


namespace Wave\Validator\Constraints;

use Wave\Validator\CleanerInterface;

/**
 * Sets the default cleaned data for this key
 */
class DefaultConstraint extends AbstractConstraint implements CleanerInterface
{

    /**
     * Always returns true,
     */
    public function evaluate()
    {
        return true;
    }

    public function getCleanedData()
    {
        return $this->validator->hasInputData($this->property) === null ? $this->arguments : $this->data;
    }

}