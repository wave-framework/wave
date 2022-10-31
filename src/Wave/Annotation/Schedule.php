<?php

namespace Wave\Annotation;

use Cron\CronExpression;
use Wave\Annotation;
use Wave\Http\Response;
use Wave\Router\Action;

class Schedule extends ArrayArguments
{

    protected $expression;
    protected $timezone;

    /**
     * @param $class
     * @return void
     * @throws InvalidAnnotationException
     */
    public function validate($class)
    {
        $this->minimumParameterCount(1);
        $this->maximumParameterCount(2);
        $this->validOnSubclassesOf($class, Annotation::CLASS_CONTROLLER);

        if (!CronExpression::isValidExpression($this->parameters[0])) {
            throw new InvalidAnnotationException(sprintf('Cron expression [%s] in %s not valid', $this->parameters[0], $class));
        }
    }

    public function build()
    {
        $this->expression = $this->parameters[0];
        if (isset($this->parameters[1])) {
            $this->timezone = $this->parameters[1];
        }
    }

    public function apply(Action &$action)
    {
        $action->setSchedule($this->expression);
        $action->setScheduleTimezone($this->timezone);
        $action->setRespondsWith([Response::FORMAT_CLI], false);
    }

    /**
     * Overload parameter parsing to allow * / (with a space)
     * for a cron expression
     *
     * @return array
     */
    public function parseParameters(): array
    {
        $parameters = parent::parseParameters();
        $parameters[0] = str_replace('* /', '*/', $parameters[0]);
        return $parameters;
    }

}
