<?php

namespace Wave\DB\Generator;
use Wave;

class Template {

	private $template;
	private $data;
	private $database;
	
	public function __construct($template){
		
		$this->template = file_get_contents(self::getTemplateDirectory().$template.'.php');
	
	}
	
	public function setData($database, $data){
	
		//default data
		$data['namespace'] = $database->getNamespace();
		$data['current_timestamp'] = date('Y/m/d h:m:s');
		$data['?php'] = '';
				
		$this->data = $data;
		$this->database = $database;
		
		$this->template = preg_replace_callback('/<<((?<function>[^>]+)\()?(?<variable>[\w\?]+)\)?>>/', 'self::substituteData', $this->template);
		
	}
	
	public function get(){
	
		return $this->template;
	
	}
	
	
	private function substituteData($match){
			
		if(!isset($this->data[$match['variable']]))
			return '';
		
		$variable = $this->data[$match['variable']];
		
		if($match['function'] !== '' && $function = $match['function']){
			$driver_class = $this->database->getConnection()->getDriverClass();
			return eval("return $function('$variable');");
		} else {
			return $variable;
		}
	
	}
	
	
	public static function getTemplateDirectory(){
		
		return dirname(__FILE__).DIRECTORY_SEPARATOR.'Template'.DIRECTORY_SEPARATOR;
	
	}


}