<?php

/**
 *	Class for representing a column of the database.  Used for Model generation.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave;


class Column {

	const TYPE_UNKNOWN 		= 0;
	const TYPE_INT 			= 1;
	const TYPE_STRING 		= 2;
	const TYPE_BOOL			= 3;
	const TYPE_TIMESTAMP	= 4;
	const TYPE_FLOAT 		= 5;
	const TYPE_DATE			= 6;
	
	private $table;
	private $name;
	private $nullable;
	private $data_type;
	private $default;
	private $type_desc;
	private $extra;
	private $comment;
									
	public function __construct($table, $name, $nullable, $data_type, $default, $type_desc = '', $extra = '', $comment = ''){
		
		$this->table = $table;
		$this->name = $name;
		$this->nullable = $nullable;
		$this->data_type = $data_type;
		$this->default = $default;
		$this->type_desc = $type_desc;
		$this->extra = $extra;
		$this->comment = $comment;
	
	}
	
	public function getTable(){
		return $this->table;
	}

	public function getName($escape = false){
		return $escape ? $this->table->getDatabase()->escape($this->name) : $this->name;
	}
	
	public function isNullable(){
		return $this->nullable;
	}
	
	public function getDataType(){
		return $this->data_type;
	}
	
	public function getDefault(){
		return $this->default;
	}
	
	public function getTypeDescription(){
		return $this->type_desc;
	}
	
	public function getExtra(){
		return $this->extra;
	}
	
	public function getComment(){
		return $this->comment;
	}
	
	public function isPrimaryKey(){
		$pk = $this->table->getPrimaryKey();
		
		return $pk === null ? false : in_array($this, $pk->getColumns());
	}
	
	
}