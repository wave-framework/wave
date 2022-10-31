<?php

namespace Wave;

use Wave\Annotation\BaseRoute;
use Wave\Annotation\BaseURL;
use Wave\Annotation\InvalidAnnotationException;
use Wave\Annotation\RequiresLevel;
use Wave\Annotation\RespondsWith;
use Wave\Annotation\Route;
use Wave\Annotation\Schedule;
use Wave\Annotation\Validate;
use Wave\DB\Model;

class Annotation {

    const FOR_CLASS = 'class';

    const CLASS_CONTROLLER = Controller::class;
    const CLASS_MODEL = Model::class;

    protected $key;
    protected $value;
    protected $from_class;

    protected static $handlers = array(
        'baseroute' => BaseRoute::class,
        'baseurl' => BaseURL::class,
        'requireslevel' => RequiresLevel::class,
        'respondswith' => RespondsWith::class,
        'route' => Route::class,
        'validate' => Validate::class,
        'schedule' => Schedule::class,
    );

    public static function factory($key, $value, $from_class = null){

        $class = __CLASS__;
        if(isset(self::$handlers[$key])){
            $class = self::$handlers[$key];
        }

        return new $class($key, $value, $from_class);
    }

    public static function parse($block, $originating_class){

        if(empty($block)) return array();

        $block = self::sanitizeDocBlock($block);

        $annotations = array();
        $pattern = '/^[~@](?<annotation>\w+)(?:[\t\f ](?<arguments>.+?(?=\s[~@\s])))?/ms';
        preg_match_all($pattern, $block, $found);

        foreach ($found['annotation'] as $position => $annotation) {
            $arguments = $found['arguments'][$position];
            $type = strtolower($annotation);
            $annotations[] = self::factory($type, $arguments, $originating_class);
        }
        return $annotations;

    }

    /**
     * Remove comment notation (/ and *) from a raw docblock
     *
     * @param $docblock
     * @return mixed
     */
    protected static function sanitizeDocBlock($docblock){
        return preg_replace('/^([\t\f ]*\*[\t\f ]+)/m', '', $docblock);
    }

    public function __construct($key, $value, $from_class = null) {
        $this->key = $key;
        $this->value = $value;
        $this->from_class = $from_class;
    }

    public function apply(Router\Action &$action){}

    /**
     * @return mixed
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }

    protected function validOnSubclassesOf($annotatedClass, $baseClass) {
        if( $annotatedClass != $baseClass && !is_subclass_of($annotatedClass, $baseClass) )
            throw new InvalidAnnotationException(get_class($this) . " is only valid on objects of type {$baseClass}.");

    }


}