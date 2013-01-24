<?php

/**
 *	DB Genration class. Creates models.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave;

class Generator {

	private static $twig;

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
	
	private static function renderTemplate($template, $data, $template_ext = '.phpt'){
		
		$loaded_template = self::$twig->loadTemplate($template.$template_ext);
		return $loaded_template->render($data);
		
	}
	
	private static function initTwig(){
		
		$loader = new \Twig_Loader_Filesystem(dirname(__FILE__).DS.'Generator'.DS.'Templates');
		self::$twig = new \Twig_Environment($loader);
		self::$twig->addFilter('addslashes', new \Twig_Filter_Function('addslashes'));
		self::$twig->addFilter('implode', new \Twig_Filter_Function('implode'));
		self::$twig->addFilter('singularize', new \Twig_Filter_Function('\Wave\Inflector::singularize'));


	}
	
	
	private static function createModelDirectory($database){
			
		$basedir = self::getBaseModelPath($database);
		
		if(!file_exists($basedir))
			mkdir($basedir, 0775, true);	
	}
	
	private static function getBaseModelPath($database){
		return self::getModelPath($database).'Base'.DS;
	}
	
	private static function getModelPath($database){
	
		$namespace = $database->getNamespace(false);
		$model_directory = Wave\Config::get('wave')->path->models;

		return $model_directory.DS.$namespace.DS;
		
	}
	
}

?>