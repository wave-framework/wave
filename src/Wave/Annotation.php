<?php

namespace Wave;

use Wave\Annotation\InvalidAnnotationException;

class Annotation {
	
	const FOR_CLASS = 'class';
	
	const CLASS_CONTROLLER = '\\Wave\\Controller';
	const CLASS_MODEL = '\\Wave\\Model';

    protected $key;
    protected $value;
    protected $from_class;

    protected static $handlers = array(
        'baseroute' => '\\Wave\\Annotation\\BaseRoute',
        'baseurl' => '\\Wave\\Annotation\\BaseURL',
        'requireslevel' => '\\Wave\\Annotation\\RequiresLevel',
        'respondswith' => '\\Wave\\Annotation\\RespondsWith',
        'route' => '\\Wave\\Annotation\\Route',
        'validate' => '\\Wave\\Annotation\\Validate',
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
        $pattern = '/(?<=(?:\@|\~))([a-zA-Z\_\-\\\][a-zA-Z0-9\_\-\.\\\]*)(((?!\s(?:\@|\~)).)*)/s';
        preg_match_all($pattern, $block, $found);
        foreach ($found[2] as $key => $value) {
            $type = strtolower($found[1][$key]);
            $value = trim($value);
            $annotations[] = self::factory($type, $value, $originating_class);
        }

        return $annotations;

		//preg_match_all('%(?:\s|\*)*~(\S+)[^\n\r\S]*(?:(.*?)(?:\*/)|(.*))%', $block, $result, PREG_PATTERN_ORDER);


		//$annotations = $result[1];
		//if(isset($result[2][0]) && $result[2][0] != '') {
            // this is the value when the close comment block '*/' is at the end of the annotation
		//	$values = $result[2];
		//} else {
            // this is the value when the comment block ends on a new line
		//	$values = $result[3];
		//}
        /*
		$returns = array();
		if(empty($result[1])) return array();
		foreach($annotations as $key => $annotation) {

            $annotation = new

			$annotationClass = 'Wave\\Annotation\\' . $annotation;
			if(class_exists($annotationClass, true)) {
				$annotation = new $annotationClass;
				$annotation->init($arguments)
					->validate($originating_class);

				if(isset($annotation->errors)){
					throw new \Wave\Exception('Annotation format error, '.implode(', ', $annotation->errors), 0);
				}
				else{
					$annotation->build();
				}
			} else {
				throw new \Wave\Exception('Unknown annotation: "' . $annotation . '"',0);
			}

			$returns[] = $annotation;
		}

		return $returns;
		*/
	}

    /**
     * Remove comment notation (/ and *) from a raw docblock
     *
     * @param $docblock
     * @return mixed
     */
    protected static function sanitizeDocBlock($docblock){
        return preg_replace('/^(\s*\*+\s{0,1}\/?)|(\/\*{1,2})/m', '', $docblock);
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