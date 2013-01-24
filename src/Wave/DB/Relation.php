<?php

/**
 *	For representing a relation in the database.  Used for Model generation.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave;


class Relation {

	const RELATION_UNKNOWN = 00;
	
	const ONE_TO_ONE = 11;
	const ONE_TO_MANY = 12;
	const MANY_TO_ONE = 21;
	const MANY_TO_MANY = 22;
	
	static $instance_names = array();
	
	private $local_column;
	private $referenced_column;
	private $target_relation = null;
	
	private $is_reverse_relation;
	private $type;
	
	private function __construct(Column $local_column, Column $referenced_column, $is_reverse_relation){
		
		$this->local_column = $is_reverse_relation ? $referenced_column : $local_column;
		$this->referenced_column = $is_reverse_relation ? $local_column : $referenced_column;
		$this->is_reverse_relation = $is_reverse_relation;
		$this->type = $this->determineRelationType();
		
	}
	
	public static function create(Column $local_column, Column $referenced_column, $is_reverse_relation){
		
		$instance_name = sprintf('%s.%s.%s__%s.%s.%s__%s', $local_column->getTable()->getDatabase()->getName(), $local_column->getTable()->getName(), $local_column->getName(),
													   $referenced_column->getTable()->getDatabase()->getName(), $referenced_column->getTable()->getName(), $referenced_column->getName(),
													   $is_reverse_relation ? 'reverse' : 'forward');
		
		if(in_array($instance_name, self::$instance_names))
			return null;
		
		self::$instance_names[] = $instance_name;
		
		return new self($local_column, $referenced_column, $is_reverse_relation);
		
	}
	
	private function determineRelationType(){

		if($this->local_column->isPrimaryKey() && $this->referenced_column->isPrimaryKey()){
			//will either be a one-one relation or a many-many join table

			if($this->local_column->getTable()->getPrimaryKey() !== null && count($this->local_column->getTable()->getPrimaryKey()->getColumns()) > 1){
				//if local table has more than one PK it's modt likely a join table.
				$type = self::MANY_TO_ONE;
			} elseif($this->referenced_column->getTable()->getPrimaryKey() !== null && 1 < $num_ref_column = count($this->referenced_column->getTable()->getPrimaryKey()->getColumns())){
				//if referencing a table with dual PK, it's most likely a m2m join table that has more to load.
				if($num_ref_column === 2){
					$type = self::MANY_TO_MANY;
					//go back and find the other relation
					//need to iterte to find the one that's not this.
					foreach($this->referenced_column->getTable()->getRelations() as $relation)
						if($relation->getReferencedColumn() != $this->local_column)
							$this->target_relation = $relation;
							
					} else {
					//if not 2 PKS it is not reliable enough to assume what's going on.
					$type = self::RELATION_UNKNOWN;
				}
			} else {
				//Otherwise o2o
				$type = self::ONE_TO_ONE;
			}
			
		} elseif($this->is_reverse_relation){
			$type = self::ONE_TO_MANY;
		} else {
			$type = self::MANY_TO_ONE;
		}
			

		return $type;			
	}
	
	public function getLocalColumn(){
		return $this->local_column;
	}
	
	public function getReferencedColumn(){
		return $this->referenced_column;
	}
	
	public function getTargetRelation(){
		return $this->target_relation;
	}
		
	public function getType(){
		return $this->type;	
	}
	
	/**
	* Returns the name of the relation.  It needs to be based on the column name as if there
	* is more than one relation to the same table, the relation won't have a unique name. 
	* If the column ends with '_id', it will be removed.
	**/
	public function getName(){
	
		//$local_column		
		switch($this->type){
			case self::ONE_TO_ONE:
				$name = $this->referenced_column->getTable()->getName();
				break;
			case self::MANY_TO_ONE:
				//in this case we need to name the relation based on the column, trimming off _id (if it exists)
				$name = $this->local_column->getName();
				if(substr($name, -3) === '_id')
					$name = substr($name, 0, -3);
				break;
			case self::ONE_TO_MANY:
				//slightly more complex to remove collisions between m2m names
				$name = Wave\Inflector::pluralize($this->referenced_column->getTable()->getName());
				$ref_name = $this->referenced_column->getName();
				if(substr($ref_name, -3) === '_id')
					$ref_name = substr($ref_name, 0, -3);
				if($ref_name !== $this->local_column->getTable()->getName())
					$name .= '_'.$ref_name;	
				break;			
			case self::MANY_TO_MANY:
				$name = Wave\Inflector::pluralize($this->target_relation->getReferencedColumn()->getTable()->getName());
				break;
		}
		
		return Wave\Inflector::camelize($name);
			
	}
		
	
}