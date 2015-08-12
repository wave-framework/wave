<?php


namespace Wave\Validator\Constraints;

use Wave\Validator\CleanerInterface;

/**
 * Sets the default cleaned data for this key
 */
class AllowNullConstraint extends AbstractConstraint implements CleanerInterface {

    /**
     * Always returns true,
     */
    public function evaluate() {

        if($this->data === null) {
            // we return false here but with no violations
            // to stop execution of subsequent constraints
            return false;
        }
        return true;

    }

    public function getViolationPayload() {
        return array();
    }


    public function getCleanedData() {
        return $this->data;
    }

}