<?php

namespace Wave\DB;
use Wave;


class Generator {

	const LOCK_FILE = '.wave_lock';
	
	static $oto_primaries = array();
	static $oto_exclude = array();

	public static function generate(){
		
		$databases = \Wave\DB::getAllDatabases();
		
		foreach($databases as $database){
							
			self::createModelDirectory($database);
	
			$lock_file = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.self::LOCK_FILE;
			if(file_exists($lock_file)){
				sleep(3);
				error_log('Not generating routes, the lock file already exists');
				return;
			}
			touch($lock_file);
				
			$driver_class = $database->getConnection()->getDriverClass();
			
			$tables = $driver_class::getTables($database);
			$columns = $driver_class::getColumns($database);
			$keys = $driver_class::getColumnKeys($database);
			
			foreach($tables as $table){
				self::createBaseClass($database, $table);
				self::createStubClass($database, $table);
			}
					
			foreach($columns as $column){
				//populate one-to-one array with pks that don't reflect the table name
				if(isset($column['column_key']) && $driver_class::translateSQLIndexType($column['column_key']) == \Wave\DB_Column::INDEX_PRIMARY){
					$test_tables = explode('_has_', $column['table_name']);
					
					//is the column name not part of the table name?
					if(!in_array(substr($column['column_name'],0,-3), $test_tables) && !isset(self::$oto_exclude[self::getTableIdentifier($column)])){
						self::$oto_primaries[self::getTableIdentifier($column)] = $column['column_name'];
					} else if(isset(self::$oto_primaries[self::getTableIdentifier($column)])){
						unset(self::$oto_primaries[self::getTableIdentifier($column)]);
						self::$oto_exclude[self::getTableIdentifier($column)] = true;
					}					
				} 
								
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
			
			unlink($lock_file);
		}
	}
	
	private static function createModelDirectory($database){
			
		$basedir = self::getModelPath($database).'Base';
				
		if(!file_exists($basedir))
			mkdir($basedir, 0775, true);	
	}
	
	private static function createBaseClass($database, $table){

		
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($table['table_name']).'.php';
		
		//@todo make dynamic
		$table['base_model'] = '\Wave\DB_Model';
		$table['database_namespace'] = $database->getNamespace();
		$table['class_name'] = $database->getNamespace().'_Base_'.\Wave\DB::tableNameToClass($table['table_name']);
		
		$t = new \Wave\DB_Generator_Template('base_start');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get(), LOCK_EX);
			
	}
	
	private static function closeBaseClass($database, $table){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($table['table_name']).'.php';

		$t = new \Wave\DB_Generator_Template('base_end');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get(), FILE_APPEND | LOCK_EX);
			
	}
	
	
	private static function closeBaseFields($database, $table){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($table['table_name']).'.php';

		$t = new \Wave\DB_Generator_Template('base_field_end');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get(), FILE_APPEND | LOCK_EX);
			
	}
	
	
	private static function createStubClass($database, $table){
	
		$filename = self::getModelPath($database).\Wave\DB::tableNameToClass($table['table_name']).'.php';
		
		$table['base_class_name'] = $database->getNamespace().'_Base_'.\Wave\DB::tableNameToClass($table['table_name']);
		$table['class_name'] = $database->getNamespace().'_'.\Wave\DB::tableNameToClass($table['table_name']);
		
		if(file_exists($filename))
			return false;
		
		$t = new \Wave\DB_Generator_Template('class_stub');
		$t->setData($database, $table);
		
		file_put_contents($filename, $t->get());
		
		//permissions - make them match model dir
		$permissions = stat(\Wave\Config::get('wave')->path->models);
				
		chmod($filename, 0775);
		chown($filename, $permissions['uid']);
		chgrp($filename, $permissions['gid']);
		
		
			
	}
	
	private static function addClassField($database, $column){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($column['table_name']).'.php';
			
		$t = new \Wave\DB_Generator_Template('base_field');
		$t->setData($database, $column);
		
		file_put_contents($filename, $t->get(), FILE_APPEND | LOCK_EX);
	
	}
	
	private static function addClassKey($database, $key){
				
		if(!isset($key['referenced_column_name']) || $key['referenced_column_name'] === '')
			return false;
				
		list($key, $reversed_key) = self::buildRelationshipData($database, $key);

		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($key['table_name']).'.php';
				
		$t = new \Wave\DB_Generator_Template('base_relation');
		$t->setData($database, $key);
		
		file_put_contents($filename, $t->get(), FILE_APPEND | LOCK_EX);
				
		//REVERSE KEY		      
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($reversed_key['table_name']).'.php';
		
		$t = new \Wave\DB_Generator_Template('base_relation');
		$t->setData($database, $reversed_key);
		
		file_put_contents($filename, $t->get(), FILE_APPEND | LOCK_EX);
	
	}
	
	private static function closeClassKeys($database, $table){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($table['table_name']).'.php';

		$t = new \Wave\DB_Generator_Template('base_relation_end');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get(), FILE_APPEND | LOCK_EX);
			
	}
	
	private static function addGetterSetter($database, $table){
	
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($table['table_name']).'.php';

		$t = new \Wave\DB_Generator_Template('base_field_gs');
		$t->setData($database, $table);

		file_put_contents($filename, $t->get(), FILE_APPEND | LOCK_EX);
			
	}
		
	private static function addRelationship($database, $key){
	
		if(!isset($key['referenced_column_name']) || $key['referenced_column_name'] === '')
			return false;
				
		list($key, $reversed_key) = self::buildRelationshipData($database, $key);
						
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($key['table_name']).'.php';
				
		$t = new \Wave\DB_Generator_Template('base_relation_'.$key['relation_type']);
		$t->setData($database, $key);
		
		file_put_contents($filename, $t->get(), FILE_APPEND | LOCK_EX);
		
		
		//print_r($reversed_key);
		//REVERSE KEY		      
		$filename = self::getModelPath($database).'Base'.DIRECTORY_SEPARATOR.\Wave\DB::tableNameToClass($reversed_key['table_name']).'.php';
		
		$t = new \Wave\DB_Generator_Template('base_relation_'.$reversed_key['relation_type']);
		$t->setData($database, $reversed_key);
		
		file_put_contents($filename, $t->get(), FILE_APPEND | LOCK_EX);
		
	}
	
	
	private static function buildRelationshipData($database, $key){
				
		$replacement_start = $key['referenced_table_name'].'_has_';
		$replacement_end = '_has_'.$key['referenced_table_name'];
		$replacement_length = strlen($replacement_end);
		$target_length = strlen($key['table_name']) - $replacement_length;
		
		if(isset(self::$oto_primaries[self::getTableIdentifier($key)]) && self::$oto_primaries[self::getTableIdentifier($key)] === $key['column_name']){
			
			$target_table = '';
			$relation_type = \Wave\DB_Model::RELATION_ONE_TO_ONE;

		} else if(strpos($key['table_name'], $replacement_start) === 0){
		
			$target_table = substr($key['table_name'], $replacement_length);
			$relation_type = \Wave\DB_Model::RELATION_MANY_TO_MANY;

		} else if(strpos($key['table_name'], $replacement_end) === $target_length){
		
			$target_table = substr($key['table_name'], 0, $target_length);
			$relation_type = \Wave\DB_Model::RELATION_MANY_TO_MANY;

		} else {
		
			$target_table = '';
			$relation_type = \Wave\DB_Model::RELATION_ONE_TO_MANY;
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
			'target_class'				=> \Wave\DB::columnToRelationName($key, $target_table),
			'relation_alias_singular'	=> \Wave\DB::columnToRelationName($key, $target_table),
			'relation_alias'			=> \Wave\Inflector::pluralize(\Wave\DB::columnToRelationName($key, $target_table)));
			
		$key['relation_alias'] = \Wave\DB::columnToRelationName($reversed_key);
		$key['relation_type'] = \Wave\DB_Model::RELATION_MANY_TO_ONE;
		$key['target_class'] = \Wave\DB::columnToRelationName($reversed_key);		
		$key['target_table'] = '';
		
		if($reversed_key['relation_type'] == \Wave\DB_Model::RELATION_ONE_TO_MANY && $key['column_name'] !== $key['referenced_column_name']){
			
			$reversed_key['relation_alias'] = \Wave\Inflector::camelize(\Wave\Inflector::pluralize($key['table_name']).'_'.$key['column_name'], '_id');
			$reversed_key['relation_alias_singular'] = \Wave\Inflector::camelize($key['table_name'].'_'.$key['column_name'], '_id');
			$key['relation_alias'] = \Wave\Inflector::camelize($key['column_name'], '_id');
		}
	
		return array($key, $reversed_key);
	}
	
	private static function getModelPath($database){
	
		$namespace = $database->getNamespace();
		$model_directory = \Wave\Config::get('wave')->path->models;

	
		return $model_directory.DIRECTORY_SEPARATOR.$namespace.DIRECTORY_SEPARATOR;
		
	}

	private static function getTableIdentifier($column){
		return $column['table_schema'].'-'.$column['table_name'];
	}
}



?>