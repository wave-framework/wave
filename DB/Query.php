<?php

class Wave_DB_Query{

	private $database;
	private $fields;
	private $from;
	private $with = array();
	private $join = array();
	private $where = array();
	private $group = array();
	private $order = array();
	private $having;
	private $offset;
	private $limit;
	private $paginate = false;
	private $_statement;
	private $_last_row;
	private $_built = false;
	private $_executed = false;
	private $_params = array();
	
	private static $query_count = 0;
	
	const JOIN_INNER 	= 'INNER JOIN';
	const JOIN_LEFT		= 'LEFT JOIN';
	const JOIN_RIGHT	= 'RIGHT JOIN';
	
	
	const WHERE_AND		= 'AND';
	const WHERE_OR		= 'OR';
	
	const TABLE_ALIAS_SPLIT = '__';
	
	public function __construct($database){
	
		$this->database = $database;
	}
	
	
	public function from($table, $fields = null){
		
		if(is_null($fields))
			$fields = Wave_DB::getFieldsForTable($table, $this->database);
			
		$this->from = Wave_DB::getClassNameForTable($table, $this->database);
		$this->fields = $fields;
		
		return $this;
	}
	
	public function with($table){
	
		$this->with[] = array(
			'table' => $table,
			'class' => Wave_DB::getClassNameForTable(Wave_Inflector::singularize($table), $this->database, true)
		);
		return $this;
	}
	
	public function innerJoin($table, $on, $lookup_class = true){
		
		return $this->join($table, $on, self::JOIN_INNER, $lookup_class);

	}

	public function leftJoin($table, $on, $lookup_class = true){
		
		return $this->join($table, $on, self::JOIN_LEFT, $lookup_class);

	}
	
	public function rightJoin($table, $on, $lookup_class = true){
		
		return $this->join($table, $on, self::JOIN_RIGHT, $lookup_class);
	}
	
	public function join($table, $on, $type, $lookup_class){
		
		$this->join[] = array('table' 	=> $table,
							  'on'		=> ($lookup_class ? $this->checkClassNames($on) : $on),
							  'type'	=> $type);
	
		return $this;
	}
	
	public function where($array_or_field, $mode_or_operator = null, $value = null){
	
		if(is_array($array_or_field)){
			//key => value pair input
			$array = $array_or_field;
			$mode = is_null($mode_or_operator) ? self::WHERE_AND : $mode_or_operator;
			
		} else {
			//single condition
			$array = array(array($array_or_field, $mode_or_operator, $value));
			$mode = false;
		}
		
		$this->where[] = array('conditions' => $array,
							   'mode' => $mode);
				
		return $this;	
	}	
		
	public function groupBy($column){
	
		if(!is_array($column))
			$column = explode(' ', $column);
		
		$this->group = array_merge($this->group, $column);
		
		return $this;
	
	}
	
	public function orderBy($column){

		$this->order = $column;
		
		return $this;
	
	}
	
	
	public function having($having){
	
		$this->having = $having;
		
		return $this;
	}
	

	public function offset($offset){
	
		$this->offset = $offset;
		
		return $this;
	}
	
	
	public function limit($limit){
	
		$this->limit = $limit;
		
		return $this;
	}
	
	public function paginate($offset, $limit){
		
		$this->paginate = true;
		
		$this->offset($offset);
		$this->limit($limit);
		
		return $this;
	}
	
	public function buildSQL(){
			
		$from = $this->from;
	
		$select_fields = array();
		foreach($this->fields as $field => $data){
			if(is_int($field)){
				$select_fields[] = $data;
			} else {
				$select_fields[] = $from.'.'.$field.' AS '.$from.self::TABLE_ALIAS_SPLIT.$field;			
			}	
		}
			
		
		$with_joins = '';
		if(isset($this->with[0])){
			foreach($this->with as $key => $with){
				$relation = $from::_getRelationByName($with['table']);	
				if(!$relation)
					throw new Wave_DB_Exception('Relationship not found for '.$with['table']);
				switch($relation['relation_type']){
					
					case Wave_DB_Model::RELATION_MANY_TO_ONE:
					case Wave_DB_Model::RELATION_ONE_TO_MANY:
						$foreign_class = Wave_DB::getClassNameForTable($relation['foreign_table'], $this->database, true);
						$join_class = '';
						
						foreach($foreign_class::_getFields() as $field => $data)
							$select_fields[] = $with['class'].'.'.$field.' AS '.$with['class'].self::TABLE_ALIAS_SPLIT.$field;
										
						$with_joins .= self::JOIN_LEFT.' '.$relation['foreign_schema'].'.'.$relation['foreign_table'].' AS '.$with['class'].' ';
						$with_joins .= 'ON '.$with['class'].'.'.$relation['foreign_column'].' = '.$from.'.'.$relation['column_name']."\n";	
						break;
						
					case Wave_DB_Model::RELATION_MANY_TO_MANY:
						$join_class = Wave_DB::getClassNameForTable($relation['foreign_table'], $this->database, true);
						$foreign_class = Wave_DB::getClassNameForTable($relation['target_table'], $this->database, true);
						
						
						foreach($join_class::_getFields() as $field => $data)
							$select_fields[] = $join_class.'.'.$field.' AS '.$join_class.self::TABLE_ALIAS_SPLIT.$field;
							
						$with_joins .= self::JOIN_LEFT.' '.$relation['foreign_schema'].'.'.$relation['foreign_table'].' AS '.$join_class.' ';
						$with_joins .= 'ON '.$join_class.'.'.$relation['foreign_column'].' = '.$from.'.'.$relation['column_name']."\n";						
						
						foreach($foreign_class::_getFields() as $field => $data)
							$select_fields[] = $with['class'].'.'.$field.' AS '.$with['class'].self::TABLE_ALIAS_SPLIT.$field;
						
						$join_relation = $join_class::_getRelationByName($relation['target_class']);
						$with_joins .= self::JOIN_LEFT.' '.$join_relation['foreign_schema'].'.'.$join_relation['foreign_table'].' AS '.$with['class'].' ';
						$with_joins .= 'ON '.$with['class'].'.'.$join_relation['foreign_column'].' = '.$join_class.'.'.$join_relation['column_name']."\n";	
						
						break;
				}
				$this->with[$key]['relation'] = $relation;
				$this->with[$key]['foreign_class'] = $foreign_class;
				$this->with[$key]['join_class'] = $join_class;
			}
		}
		
		$manual_joins = '';
		foreach($this->join as $join){
			$table = Wave_DB::getClassNameForTable($join['table'], $this->database);
			$manual_joins .= $join['type'].' '.$table::_getTableName().' AS '.$table.' ON '.$join['on']."\n";
			
			foreach($table::_getFields() as $field => $data)
				$select_fields[] = $table.'.'.$field.' AS '.$table.self::TABLE_ALIAS_SPLIT.$field;

		}
		
		$query = 'SELECT ' . ($this->paginate ? 'SQL_CALC_FOUND_ROWS ' : '');
		$query .= implode(',',$select_fields).' FROM `'.$from::_getTableName().'` AS '.$from."\n";
		$query .= $with_joins;
		$query .= $manual_joins;
		
		
		if(isset($this->where[0])){
			$where_rows = array();
			
			foreach($this->where as $where){
				$where_conditions = array();
				foreach($where['conditions'] as $condition){
				
					//if nothing in array, eg. WHERE IN array(), ingnore clause.
					if(is_array($condition[2]) && count($condition[2]) === 0) continue;

					//Determine whether there is more than one condition for the prepred stmt.
					$is_in = is_array($condition[2]);
					$value = $is_in ? '('.implode(',', array_fill(0, count($condition[2]), '?')).')' : '?';
					//Special NULL handling
					if(!is_null($condition[2])){
						//Add the values to the prep params and cast if needed.
						$this->_params = array_merge($this->_params, (array) $condition[2]);
						if($is_in)
							$where_conditions[] = $condition[0].' '.($condition[1] == '=' ? 'IN' : 'NOT IN').' '.$value;
						else
							$where_conditions[] = $condition[0].' '.$condition[1].' '.$value;
					} else {
						$where_conditions[] = $condition[0].' '.($condition[1] == '=' ? 'IS' : 'IS NOT').' NULL';
					}

				}
				$where_rows[] = '( '.implode(' '.$where['mode'].' ', $where_conditions).' )';		
			}
			$query .= $this->checkClassNames('WHERE '.implode(' '. self::WHERE_AND .' ', $where_rows)."\n");
		}
		
		if (isset($this->group[0])) $query .= 'GROUP BY ' . implode(',', $this->group)."\n";
		if (isset($this->order[0])) $query .= $this->checkClassNames('ORDER BY ' . $this->order . "\n");
		if (isset($this->having))	$query .= $this->checkClassNames('HAVING ' . $this->having . "\n");
				
		if (isset($this->limit)){
			$query .= 'LIMIT '.$this->limit;
			if (isset($this->offset))
				$query .= ' OFFSET '.$this->offset;
		}
		
		$this->_built = true;
				
		return $query;
	
	}
	
	
	public function execute($debug = false){
	
		$sql = $this->buildSQL();

		$statement = $this->database->getConnection()->prepare($sql);		
		$statement->execute( $this->_params );
		
		if($debug)
			$statement->debugDumpParams();
		
		self::$query_count++;
		
		$this->_statement = $statement;
		$this->_executed = true; 
	
	}
	
	
	public function fetchAll($parse_objects = true, $debug = false){
				
		$result_set = array();			
		//fetch all rows.
		while($row = $this->fetchRow($parse_objects, $debug))
			$result_set[] = $row;
		
		return $result_set;

	
	}
	
	
	public function fetchRow($parse_objects = true, $debug = false){
		
		if(!$this->_executed)
			$this->execute($debug);
		
		if($parse_objects){
		
			$primary_object = $this->from;
			
			if(!isset($this->_last_row))
				$this->_last_row = $this->_statement->fetch(Wave_DB_Connection::FETCH_ASSOC);
			if($this->_last_row === false) return null;			
			$row = new $primary_object($this->_last_row, $primary_object.self::TABLE_ALIAS_SPLIT);

			for(;;) {				
				//Load in relations.
				foreach($this->with as $with){
							
					$joined_object = new $with['foreign_class']($this->_last_row, $with['class'].self::TABLE_ALIAS_SPLIT);
					
					// if the joined bit is empty, dont bother
					if($joined_object->pkNull()) continue;
					
					if($joined_object->_isLoaded()){
					
						switch($with['relation']['relation_type']){
							case Wave_DB_Model::RELATION_MANY_TO_ONE:
								$row->{$with['table']} = $joined_object;
								break;
							case Wave_DB_Model::RELATION_ONE_TO_MANY:
								$row->{'add'.$with['relation']['target_class']}($joined_object, false);
								break;
							case Wave_DB_Model::RELATION_MANY_TO_MANY:
								//Object that holds the m2m join
								$join_object = new $with['join_class']($this->_last_row, $with['join_class'].self::TABLE_ALIAS_SPLIT);
								$joined_object->_addRelationObject($with['relation']['target_class'], $join_object);
								$row->{'add'.$with['relation']['target_class']}($joined_object, false, $join_object);
								break;		
								
						}			
					} else {
						$row->{'add'.$with['relation']['target_class']}();
					}				
				}
				
				foreach($this->join as $join){
					$join_object = new $join['table']($this->_last_row, Wave_DB::getClassNameForTable($join['table']).self::TABLE_ALIAS_SPLIT);
					if(!$join_object->pkNull()){

						$row->_addRelationObject($join['table'], $join_object);					
					}
				
				}
				
				$new_row = $this->_statement->fetch(Wave_DB_Connection::FETCH_ASSOC);
				//Kill loop when all join rows are taken out.
				foreach($primary_object::_getKeys(Wave_DB_Column::INDEX_PRIMARY) as $pk){
					$index = $primary_object.self::TABLE_ALIAS_SPLIT.$pk;
					if($this->_last_row[$index] !== $new_row[$index])
						break 2; //kill for(;;) loop
				}
				$this->_last_row = $new_row;
			};
			
			$this->_last_row = $new_row;

		} else {
			$row = $this->_statement->fetch(Wave_DB_Connection::FETCH_ASSOC);
		}		
		
		
		return $row === false ? null : $row;
	
	}
	
	public function fetchRowCount(){
		
		if($this->paginate === false)
			throw new Wave_Exception('Wave_DB::fetchRowCount can only be used when paginating');
		
		$sql = 'SELECT FOUND_ROWS() AS row_count;';
	
		$statement = $this->database->getConnection()->prepare($sql);		
		$statement->execute();
		
		self::$query_count++;
		
		$rslt = $statement->fetch(Wave_DB_Connection::FETCH_ASSOC);
		
		return $rslt['row_count'];
	}
	
	
	private function checkClassNames($sql){
	
		$sql_exploded = explode('.', $sql);
		
		//Remove last index
		array_pop($sql_exploded);
		
		foreach($sql_exploded as $part){
			$index_of_class = strrpos($part, ' ');
			$class_name = trim(substr($part, $index_of_class === false ? 0 : $index_of_class+1), '`');

			//If class wasn't correct, try to append default ns
			if(strpos($class_name, Wave_DB::NS_SEPARATOR) === false || !class_exists($class_name))
				$sql = str_replace(' '.$class_name, ' '.Wave_DB::getClassNameForTable($class_name, $this->database), ' '.$sql);
				
		}
		
		return $sql;
	
	}
	
	public static function getQueryCount(){
		return self::$query_count;
	}
	
	/*
	* Function to return new Wave_DB_Query object. 
	* Enables users to chain SQL construction.
	*
	*/
	public static function create($database){
		return new self($database);
	}

}


?>