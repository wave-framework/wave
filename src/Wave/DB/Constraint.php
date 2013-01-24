<?php

/**
 *	Class for representing a column of the database.  Used for Model generation.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave;


class Constraint {
	
	const TYPE_UNKNOWN		= 11;
	const TYPE_PRIMARY		= 12;
	const TYPE_UNIQUE		= 13;
	const TYPE_FOREIGN		= 14;
	
	private $columns;
	private $type;
	private $name;
									
	public function __construct(Column $column, $type, $name){
		$this->columns = array($column);
		$this->type = $type;
		$this->name = $name;
	}
	
	public function addColumn(Column $column){
		$this->columns[] = $column;
	}
	
	public function getName(){
		return $this->name;
	}
	
	public function getType(){
		return $this->type;
	}
	
	public function getColumns(){
		return $this->columns;
	}
	
}