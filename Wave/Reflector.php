<?php

namespace Wave;

class Reflector {
	
	const SCAN_DIRECTORY = 1;
	const SCAN_FILE = 2;
	
	const VISIBILITY_PUBLIC = 'public';
	const VISIBILITY_PROTECTED = 'protected';
	const VISIBILITY_PRIVATE = 'private';
	
	const DEFAULT_FILE_FILTER = '.php';
	
	private $_classes = null;
	
	public function __construct($handle, $type = self::SCAN_DIRECTORY, $filter = self::DEFAULT_FILE_FILTER, $recursive = true){
		if(!file_exists($handle))
			throw new \Wave\Exception('Directory '.$handle.' cannot be resolved in \Wave\Refector', 0);
		
		$this->_classes = array();
		if($type == self::SCAN_FILE && is_file($handle)){
			$class = self::fileIsClass($handle);
			if($class !== false)
				$this->_classes[] = $class;
		}
		else if($type == self::SCAN_DIRECTORY && is_dir($handle)){

			if($recursive === true){
				$dir_iterator = new \RecursiveDirectoryIterator($handle);
				$iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
			}
			else {
				$dir_iterator = new \FilesystemIterator($handle);
				$iterator = new \IteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
			}
			foreach ($iterator as $file) {
			    $class = self::fileIsClass($file);
			    if($class !== false)
					$this->_classes[] = $class;
			}
		}

	}
	
	public function execute($include_inherited_members = false){
		
		if(!isset($this->_classes[0]))
			throw new \Wave\Exception('No files to reflect on');
		
		$classes = array();
				
		foreach($this->_classes as $class){
			$reflector = new \ReflectionClass($class['classname']);
			
			$class_annotations = Annotation::parse($reflector->getDocComment(), $class['classname']);
			$parent_class = $reflector->getParentClass();
			
			$c = array(
				'name' 			=> $class['classname'],
				'subclasses'	=> $parent_class instanceof \ReflectionClass ? $parent_class->getName() : '',
				'implements'	=> $reflector->getInterfaceNames(),
				'annotations'	=> $class_annotations
			);
			
			$methods = array();
			foreach($reflector->getMethods() as $method){
				$declaring_class = $method->getDeclaringClass()->getName();
				// don't put inherited methods on here plz
				if($declaring_class != $class['classname'] && !$include_inherited_members)
					continue; 
					
				$annotations = Annotation::parse($method->getDocComment(), $class['classname']);
				$method_annotations = array();
				foreach($annotations as $annotation){
					$method_annotations[] = $annotation;
				}
								
				$method_name = $method->getName();
				$visibility = $method->isPublic() ? self::VISIBILITY_PUBLIC : ($method->isPrivate() ? self::VISIBILITY_PRIVATE : self::VISIBILITY_PROTECTED);
				$methods[$method_name] = array(
					'name' 			=> $method_name,
					'visibility'	=> $visibility,
					'static' 		=> $method->isStatic(),
					'annotations'	=> $method_annotations,
					'declaring_class' => $method->getDeclaringClass()->getName()
				);
			}
			
			$properties = array();
			foreach($reflector->getProperties() as $property){
				$declaring_class = $property->getDeclaringClass()->getName();
				// don't put inherited methods on here plz
				if($declaring_class != $class['classname'] && !$include_inherited_members)
					continue; 
				
				$annotations = Annotation::parse($property->getDocComment(), $class['classname']);
				$property_annotations = array();
				foreach($annotations as $annotation){
					$property_annotations[] = $annotation;
				}
								
				$property_name = $property->getName();
				$visibility = $property->isPublic() ? self::VISIBILITY_PUBLIC : ($property->isPrivate() ? self::VISIBILITY_PRIVATE : self::VISIBILITY_PROTECTED);
				$properties[$property_name] = array(
					'name' 			=> $property_name,
					'visibility'	=> $visibility,
					'static' 		=> $property->isStatic(),
					'annotations'	=> $property_annotations,
					'declaring_class' => $property->getDeclaringClass()->getName()
				);
			}
			
			$classes[$class['classname']] = array('class' => $c, 'methods' => $methods, 'properties' => $properties);
			
		}
		return $classes;
				
	}
	
	private static function fileIsClass($file){
		if(!is_file($file)) return false;
		
		// get the first little bit of a file
		$handle = fopen($file, "r");
		$contents = fread($handle, 256);
		fclose($handle);
			
		preg_match('/class[ ]+(?<classname>[A-Za-z0-9_]+)([ ]+extends[ ]+(?<extends>[A-Za-z0-9_]+))?([ ]+implements[ ]+(?<implements>[A-Za-z0-9_, ]+))?[ ]+{/', $contents, $matches);
		
		unset($contents);
		
		if(isset($matches['classname'])){
			$class =  array(
				'classname' => $matches['classname'],
				'path' => $file
			);
			
			if(isset($matches['extends']))
				$class['extends'] = $matches['extends'];
			if(isset($matches['implements'])){
				$class['implements'] = explode(',', str_replace(' ', '', $matches['implements']));
			}
				
			return $class;
		}
		else
			return false;
	}
	
}

?>