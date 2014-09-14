<?php

/**
 *	DB Genration class. Creates models.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;

use Wave;
use Twig_Environment;
use Twig_Loader_Filesystem;

class Generator {

    /** @var Twig_Environment $twig */
	private static $twig;

    private static $baseModelClass = '\\Wave\\DB\\Model';

    /**
     * Generate the base models (and any model stubs if they don't exist) based on the schema
     * from the database
     */
    public static function generate(){
		
		self::initTwig();
		$databases = Wave\DB::getAll();
		
		foreach($databases as $database){
										
			self::createModelDirectory($database);
			$tables = $database->getTables($database);
						
			foreach($tables as $table){
				$base_file = self::getBaseModelPath($database).$table->getClassName().'.php';
				file_put_contents($base_file, self::renderTemplate('base-model', array('table' => $table)));
				
				$stub_file = self::getModelPath($database).$table->getClassName().'.php';
				if(!file_exists($stub_file))
					file_put_contents($stub_file, self::renderTemplate('stub-model', array('table' => $table)));
				
			}
			
		}
	}

    /**
     * @param string $template
     * @param array  $data
     * @param string $template_ext
     *
     * @return string
     */
    private static function renderTemplate($template, $data, $template_ext = '.phpt'){
		
		$loaded_template = self::$twig->loadTemplate($template.$template_ext);
		return $loaded_template->render($data);
		
	}

    private static function initTwig(){
		
		$loader = new Twig_Loader_Filesystem(__DIR__.DS.'Generator'.DS.'Templates');
		self::$twig = new Twig_Environment($loader, array('autoescape' => false));
		self::$twig->addFilter('addslashes', new \Twig_Filter_Function('addslashes'));
        self::$twig->addFilter('export', new \Twig_Filter_Function(function($var){ return var_export($var, true); }));
		self::$twig->addFilter('implode', new \Twig_Filter_Function('implode'));
		self::$twig->addFilter('singularize', new \Twig_Filter_Function('\\Wave\\Inflector::singularize'));
        self::$twig->addFilter('formatType', new \Twig_Filter_Function('\\Wave\\DB\\Generator::formatTypeForSource'));
        self::$twig->addGlobal('baseModelClass', static::$baseModelClass);
	}

    public static function formatTypeForSource($type){

        if(null === $type)
            return "null";
        else if(is_int($type) || is_float($type))
            return "$type";
        else if (is_bool($type))
            return $type ? "true" : "false";
        else
            return "'$type'";

    }

    /**
     * @param Wave\DB $database
     */
    private static function createModelDirectory(Wave\DB $database){
			
		$basedir = self::getBaseModelPath($database);
		
		if(!file_exists($basedir))
			mkdir($basedir, 0775, true);
	}

    /**
     * @param \Wave\DB $database
     *
     * @return string
     */
    private static function getBaseModelPath(Wave\DB $database){
		return self::getModelPath($database).'Base'.DS;
	}

    /**
     * @param \Wave\DB $database
     *
     * @return string
     */
    private static function getModelPath(Wave\DB $database){
	
		$namespace = $database->getNamespace(false);
		$model_directory = Wave\Config::get('wave')->path->models;

		return $model_directory.DS.$namespace.DS;
		
	}

    public static function setBaseModelClass($baseModelClass) {
        self::$baseModelClass = $baseModelClass;
    }

}

?>