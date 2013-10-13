<?php

/**
 *	Class for reprisenting a table in the database.  Used for Model generation.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave;


class Table {

    /** @var \Wave\DB $database */
	private $database;
    /** @var string $table */
	private $table;
    /** @var string $engine */
	private $engine;
    /** @var string $collation */
	private $collation;
    /** @var string $comment */
	private $comment;

    /** @var Column[] $columns */
	private $columns;
    /** @var Relation[] $relations */
	private $relations;
    /** @var Constraint[] $constraints */
	private $constraints;

	public function __construct(Wave\DB $database, $table, $engine = '', $collation = '', $comment = ''){
		
		$this->database = $database;
		$this->table = $table;
		$this->engine = $engine;
		$this->collation = $collation;
		$this->comment = $comment;
	}

    /**
     * @return Column[]
     */
    public function getColumns(){
		
		if(!isset($this->columns)){
			$driver_class = $this->database->getConnection()->getDriverClass();
			$this->columns = $driver_class::getColumns($this);		
		}

		return $this->columns;		
	}

    /**
     * @return Relation[]
     */
    public function getRelations(){
		
		if(!isset($this->relations)){
			$driver_class = $this->database->getConnection()->getDriverClass();
			$this->relations = $driver_class::getRelations($this);		
		}

		return $this->relations;		
	}

    /**
     * @return Constraint[]
     */
    public function getConstraints(){
		
		if(!isset($this->constraints)){
			$driver_class = $this->database->getConnection()->getDriverClass();
			$this->constraints = $driver_class::getConstraints($this);	
		}
		
		return $this->constraints;
		
	}

    /**
     * @return null|Constraint
     */
    public function getPrimaryKey(){
		
		foreach($this->getConstraints() as $constraint)
			if($constraint->getType() === Constraint::TYPE_PRIMARY)
				return $constraint;
				
		return null;
	}

    /**
     * @return \Wave\DB
     */
    public function getDatabase(){
		return $this->database;
	}

    /**
     * @return string
     */
    public function getName(){
		return $this->table;
	}

    /**
     * @return string
     */
    public function getEngine(){
		return $this->engine;
	}

    /**
     * @return string
     */
    public function getCollation(){
		return $this->collation;
	}

    /**
     * @return string
     */
    public function getComment(){
		return $this->comment;
	}

    /**
     * @param bool $with_namespace
     *
     * @return string
     */
    public function getClassName($with_namespace = false){
	
		$prefix = $with_namespace ? '\\'.$this->database->getNamespace().'\\' : '';
		return $prefix . Wave\Inflector::camelize($this->table);
	}
	
}