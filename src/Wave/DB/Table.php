<?php

/**
 *	Class for reprisenting a table in the database.  Used for Model generation.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave;


class Table {
	
	private $database;
	private $table;
	private $engine;
	private $collation;
	private $comment;
	
	private $columns;
	private $relations;
	private $constraints;

	public function __construct(Wave\DB $database, $table, $engine = '', $collation = '', $comment = ''){
		
		$this->database = $database;
		$this->table = $table;
		$this->engine = $engine;
		$this->collation = $collation;
		$this->comment = $comment;
	}
	
	public function getColumns(){
		
		if(!isset($this->columns)){
			$driver_class = $this->database->getConnection()->getDriverClass();
			$this->columns = $driver_class::getColumns($this);		
		}

		return $this->columns;		
	}
	
	public function getRelations(){
		
		if(!isset($this->relations)){
			$driver_class = $this->database->getConnection()->getDriverClass();
			$this->relations = $driver_class::getRelations($this);		
		}

		return $this->relations;		
	}
	
	public function getConstraints(){
		
		if(!isset($this->constraints)){
			$driver_class = $this->database->getConnection()->getDriverClass();
			$this->constraints = $driver_class::getConstraints($this);	
		}
		
		return $this->constraints;
		
	}
	
	public function getPrimaryKey(){
		
		foreach($this->getConstraints() as $constraint)
			if($constraint->getType() === Constraint::TYPE_PRIMARY)
				return $constraint;
				
		return null;
	}
	
	public function getDatabase(){
		return $this->database;
	}

	public function getName(){
		return $this->table;
	}

	public function getEngine(){
		return $this->engine;
	}

	public function getCollation(){
		return $this->collation;
	}
	
	public function getComment(){
		return $this->comment;
	}
	
	public function getClassName($with_namespace = false){
	
		$prefix = $with_namespace ? $this->database->getNamespace().'\\' : '';
		return $prefix . Wave\Inflector::camelize($this->table);
	}
	
}