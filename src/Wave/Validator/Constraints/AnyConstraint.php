<?php


namespace Wave\Validator\Constraints;

use Wave\Validator;
use Wave\Validator\CleanerInterface;
use Wave\Validator\Exception;

/**
 * Reads an array of sub-constraints and returns true if any one of them returns true.
 */
class AnyConstraint extends AbstractConstraint implements CleanerInterface
{

    private $inherit_schema = array(
        'required' => false
    );

    private $cleaned = null;
    private $violations = array();

    private $message = null;

    public function __construct($property, $arguments, Validator &$validator)
    {
        parent::__construct($property, $arguments, $validator);

        // inherit the default value from the parent instance of
        $schema = $validator->getSchemaKey($property);
        if (array_key_exists('default', $schema)) {
            $this->inherit_schema['default'] = $schema['default'];
        }
    }


    /**
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function evaluate()
    {

        if (!is_array($this->arguments))
            throw new \InvalidArgumentException("[any] constraint requires an array argument");
        if (!isset($this->arguments[0]))
            $this->arguments = array($this->arguments);

        $input = array($this->property => $this->data);
        foreach ($this->arguments as $key => $constraint_group) {

            if ($key === 'message') {
                $this->message = $constraint_group;
                continue;
            }

            $instance = new Validator($input, array(
                'fields' => array(
                    $this->property => array_replace($this->inherit_schema, $constraint_group)
                )
            ), $this->validator);

            if ($instance->execute()) {
                $cleaned = $instance->getCleanedData();
                if (isset($cleaned[$this->property])) {
                    $this->cleaned = $cleaned[$this->property];
                }
                return true;
            } else {
                $violations = $instance->getViolations();
                $messages = array_intersect_key($violations[$this->property], array_flip(array('reason', 'message')));
                if (!empty($messages))
                    $this->violations[] = $messages;
            }
        }
        return empty($this->violations);
    }

    public function getViolationPayload()
    {
        $payload = array(
            'reason' => 'invalid',
        );
        if ($this->message !== null) {
            $payload['message'] = $this->message;
        } else {
            $payload['message'] = 'This value does not match any of the following conditions';
            $payload['conditions'] = $this->violations;
        }
        return $payload;
    }

    public function getCleanedData()
    {
        return $this->cleaned;
    }

}