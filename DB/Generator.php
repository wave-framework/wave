<?php

class Wave_DB_Generator {

	public static function generate(){
	
		$databases = Wave_DB::getAllDatabases();
		
		foreach($databases as $database){
			
			self::createModelDirectory($database);
			
			$driver_class = $database->getConnection()->getDriverClass();
			
			$tables = $driver_class::getTables($database);
			$columns = $driver_class::getColumns($database);
			$keys = $driver_class::getColumnKeys($database);
			
			foreach($tables as $table){
				self::createBaseClass($database, $table);
				self::createStubClass($database, $table);
			}
					
			foreach($columns as $column){
				self::addClassField($database, $column);
			}			
			
			foreach($tables as $table){
				self::closeBaseFields($database, $table);
			}
			
			foreach($keys as $key){
				self::addClassKey($database, $key);
			}

			foreach($tables as $table){
				self::closeClassKeys($database, $table);
			}
			
			foreach($columns as $column){
				self::addGetterSetter($database, $column);
			}		
			
			foreach($keys as $key){
				self::addRelationship($database, $key);
			}

			foreach($tables as $table){
				self::closeBaseClass($database, $table);
			}

		
		}
		
	}
	
	private static function createModelDirectory($database){
			
		$basedir = self::getModelPath($database).'Base';
				
		if(!file_exists($basedir))
			mkdir($basedir, 0775, true);	
	}
	
	private static function createBaseClass($database, $table){

		
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($table['table_name']).'.php';
		
		//@todo make dynamic
		$table['base_model'] = 'Wave_DB_Model';
		$table['database_namespace'] = $database->getNamespace();
		$table['class_name'] = $database->getNamespace().'_Base_'.Wave_DB::tableNameToClass($table['table_name']);
		
		$t = new Wave_DB_Generator_Template('base_start');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get());
			
	}
	
	private static function closeBaseClass($database, $table){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($table['table_name']).'.php';

		$t = new Wave_DB_Generator_Template('base_end');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get(), FILE_APPEND);
			
	}
	
	
	private static function closeBaseFields($database, $table){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($table['table_name']).'.php';

		$t = new Wave_DB_Generator_Template('base_field_end');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get(), FILE_APPEND);
			
	}
	
	
	private static function createStubClass($database, $table){
	
		$filename = self::getModelPath($database).Wave_DB::tableNameToClass($table['table_name']).'.php';
		
		$table['base_class_name'] = $database->getNamespace().'_Base_'.Wave_DB::tableNameToClass($table['table_name']);
		$table['class_name'] = $database->getNamespace().'_'.Wave_DB::tableNameToClass($table['table_name']);
		
		if(file_exists($filename))
			return false;
		
		$t = new Wave_DB_Generator_Template('class_stub');
		$t->setData($database, $table);
		
		file_put_contents($filename, $t->get());
		
		//permissions - make them match model dir
		$permissions = stat(Wave_Config::get('wave')->path->models);
				
		chmod($filename, 0775);
		chown($filename, $permissions['uid']);
		chgrp($filename, $permissions['gid']);
		
		
			
	}
	
	private static function addClassField($database, $column){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($column['table_name']).'.php';
			
		$t = new Wave_DB_Generator_Template('base_field');
		$t->setData($database, $column);
		
		file_put_contents($filename, $t->get(), FILE_APPEND);
	
	}
	
	private static function addClassKey($database, $key){
		
		if(!isset($key['referenced_column_name']) || $key['referenced_column_name'] === '')
			return false;
				
		list($key, $reversed_key) = self::buildRelationshipData($database, $key);

		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($key['table_name']).'.php';
				
		$t = new Wave_DB_Generator_Template('base_relation');
		$t->setData($database, $key);
		
		file_put_contents($filename, $t->get(), FILE_APPEND);
				
		//REVERSE KEY		      
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($reversed_key['table_name']).'.php';
		
		$t = new Wave_DB_Generator_Template('base_relation');
		$t->setData($database, $reversed_key);
		
		file_put_contents($filename, $t->get(), FILE_APPEND);
	
	}
	
	private static function closeClassKeys($database, $table){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($table['table_name']).'.php';

		$t = new Wave_DB_Generator_Template('base_relation_end');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get(), FILE_APPEND);
			
	}
	
	private static function addGetterSetter($database, $table){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($table['table_name']).'.php';

		$t = new Wave_DB_Generator_Template('base_field_gs');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get(), FILE_APPEND);
			
	}
		
	private static function addRelationship($database, $key){
	
		if(!isset($key['referenced_column_name']) || $key['referenced_column_name'] === '')
			return false;
				
		list($key, $reversed_key) = self::buildRelationshipData($database, $key);
						
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($key['table_name']).'.php';
				
		$t = new Wave_DB_Generator_Template('base_relation_'.$key['relation_type']);
		$t->setData($database, $key);
		
		file_put_contents($filename, $t->get(), FILE_APPEND);
		
		
		//print_r($reversed_key);
		//REVERSE KEY		      
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.Wave_DB::tableNameToClass($reversed_key['table_name']).'.php';
		
		$t = new Wave_DB_Generator_Template('base_relation_'.$reversed_key['relation_type']);
		$t->setData($database, $reversed_key);
		
		file_put_contents($filename, $t->get(), FILE_APPEND);
		
	}
	
	
	private static function buildRelationshipData($database, $key){
		
		$replacement_start = $key['referenced_table_name'].'_has_';
		$replacement_end = '_has_'.$key['referenced_table_name'];
		$replacement_length = strlen($replacement_end);
		$target_length = strlen($key['table_name']) - $replacement_length;
				  
		if(strpos($key['table_name'], $replacement_start) === 0){
		
			$target_table = substr($key['table_name'], $replacement_length);
			$relation_type = Wave_DB_Model::RELATION_MANY_TO_MANY;

		} else if(strpos($key['table_name'], $replacement_end) === $target_length){
		
			$target_table = substr($key['table_name'], 0, $target_length);
			$relation_type = Wave_DB_Model::RELATION_MANY_TO_MANY;

		} else {
		
			$target_table = '';
			$relation_type = Wave_DB_Model::RELATION_ONE_TO_MANY;
		}
					
		$reversed_key = array(
			'table_schema'				=> $key['referenced_table_schema'],
			'table_name'				=> $key['referenced_table_name'],
			'column_name'				=> $key['referenced_column_name'],
			'referenced_table_schema'	=> $key['table_schema'],
			'referenced_table_name'		=> $key['table_name'],
			'referenced_column_name'	=> $key['column_name'],
			'constraint_name' 			=> $key['constraint_name'],
			'relation_type' 			=> $relation_type,
			'target_table'				=> $target_table,
			'target_class'				=> Wave_DB::columnToRelationName($key, $target_table),
			'relation_alias_singular'	=> Wave_DB::columnToRelationName($key, $target_table),
			'relation_alias'			=> Wave_Inflector::pluralize(Wave_DB::columnToRelationName($key, $target_table)));
			
		$key['relation_alias'] = Wave_DB::columnToRelationName($reversed_key);
		$key['relation_type'] = Wave_DB_Model::RELATION_MANY_TO_ONE;
		$key['target_class'] = Wave_DB::columnToRelationName($reversed_key);		
		$key['target_table'] = '';
		
		if($reversed_key['relation_type'] == Wave_DB_Model::RELATION_ONE_TO_MANY && $key['column_name'] !== $key['referenced_column_name']){
			
			$reversed_key['relation_alias'] = Wave_Inflector::camelize(Wave_Inflector::pluralize($key['table_name']).'_'.$key['column_name'], '_id');
			$reversed_key['relation_alias_singular'] = Wave_Inflector::camelize($key['table_name'].'_'.$key['column_name'], '_id');
			$key['relation_alias'] = Wave_Inflector::camelize($key['column_name'], '_id');
		}
	
		return array($key, $reversed_key);
	}
	
	private static function getModelPath($database){
	
		$namespace = $database->getNamespace();
		$model_directory = Wave_Config::get('wave')->path->models;

	
		return $model_directory.DIRECTORY_SEPARATOR.$namespace.DIRECTORY_SEPARATOR;
		
	}

}



?>