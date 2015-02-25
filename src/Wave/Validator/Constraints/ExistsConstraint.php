<?php


namespace Wave\Validator\Constraints;

use Wave\DB;
use Wave\DB\Model;
use Wave\Validator;
use Wave\Validator\CleanerInterface;
use Wave\Validator\Exception;

class ExistsConstraint extends AbstractConstraint implements CleanerInterface {

    const ERROR_NOT_EXISTS = 'not_exists';

    protected $type = 'exists';
    protected $message = null;
    private $match_fields = array();
    private $instance = null;

    public function __construct($property, $arguments, Validator &$validator) {
        if(!is_array($arguments) || !isset($arguments['model']) || !isset($arguments['property']))
            throw new \InvalidArgumentException("[{$this->type}] constraint requires a model and property to be declared");

        parent::__construct($property, $arguments, $validator);

        $this->match_fields = $arguments['property'];
        if(!is_array($arguments['property'])) {
            $this->match_fields = array($arguments['property'] => $property);
        }

        if(empty($this->match_fields))
            throw new \InvalidArgumentException("[$this->type] constraint requires at least one property to match");

        $this->message = isset($arguments['message']) ? $arguments['message'] : null;
    }

    /**
     * @return bool
     */
    public function evaluate() {
        $statement = DB::get()->from($this->arguments['model']);

        foreach($this->match_fields as $column => $input_key) {
            $statement->where($column . ' = ?', $this->validator[$input_key]);
        }

        $this->instance = $statement->fetchRow();

        return $this->instance instanceof Model;
    }

    public function getCleanedData() {
        return $this->instance;
    }

    /**
     * @return string
     */
    protected function getViolationKey() {
        return static::ERROR_NOT_EXISTS;
    }

    protected function getViolationMessage($context = 'This value') {
        $message = isset($this->message) ? $this->message : '%s does not exist';
        return sprintf($message, $context);
    }

}