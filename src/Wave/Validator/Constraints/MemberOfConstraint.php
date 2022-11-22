<?php


namespace Wave\Validator\Constraints;

class MemberOfConstraint extends AbstractConstraint
{

    const ERROR_NOT_MEMBER = 'not_member';

    /**
     * @return bool
     */
    public function evaluate()
    {
        return in_array($this->data, $this->arguments, true);
    }

    /**
     * @return string
     */
    protected function getViolationKey()
    {
        return static::ERROR_NOT_MEMBER;
    }

    protected function getViolationMessage($context = 'This value')
    {
        return sprintf('%s is not a valid choice', $context);
    }

    public function getViolationPayload()
    {
        return array_merge(
            parent::getViolationPayload(),
            array(
                'member_of' => $this->arguments
            )
        );
    }

}