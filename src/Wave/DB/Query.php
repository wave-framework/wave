<?php

/**
 *	Query class for constructing a query.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;

use \Wave;

class Query {
	
	private $escape_character;
	
	private $class_aliases	= array();
	private $alias_counter;
	
	private $database;
	private $from_alias;
	private $fields	= array();
	private $with	= array();
	private $joins	= array();
	private $where	= array();
	private $group	= array();
	private $order	= array();
	private $having;
	private $offset;
	private $limit;
	private $paginate = false;
	private $_statement;
	private $_last_row = false;
	private $_built = false;
	private $_executed = false;
	private $_params = array();
	
	private $relation_tables = array();
		
	public function __construct($database){
		$this->database = $database;
		$this->alias_counter = 'a';
	}
	
	
	public function from($from, &$alias = null, $fields = null){
		
		//if a relative reference, add the ns prefix - will be most of the time
		$this->resolveNamespace($from);
				
		//populate table fields if not specified.
		if($fields === null){
			$this->manual_select = false;
			$this->addFieldsToSelect($from, $alias);
		} else {
			$this->fields = $fields;
			$this->manual_select = true;
			$this->aliasClass($from, $alias);
		}
		
		$this->from_alias = $alias;
		
		return $this;
	}
	
	
	/*
	*	START JOIN METHODS
	*/
	
	public function with($relation, &$alias = null){
		
		$from_class = $this->unaliasClass($this->from_alias);
		$relation_data = $from_class::_getRelation($relation);

				
		//if it's many to many, load the related class and flag the first join to be attached as relation data
		if($relation_data['relation_type'] === Wave\DB\Relation::MANY_TO_MANY){
			
			$this->leftJoin($relation_data['related_class'], $related_alias, $target_alias)
				 ->on(sprintf('%s.%s = %s.%s', $related_alias, $this->escape($relation_data['related_column']), 
				 							   $this->from_alias, $this->escape($relation_data['local_column'])));
			
			$this->leftJoin($relation_data['target_relation']['related_class'], $alias)
				 ->on(sprintf('%s.%s = %s.%s', $alias, $this->escape($relation_data['target_relation']['related_column']), 
				 							   $related_alias, $this->escape($relation_data['target_relation']['local_column'])));
			//go back and set the target alias to the joined row
			$target_alias = $alias;

		} else {
			//any other type of join is a simple table-table join
			$this->leftJoin($relation_data['related_class'], $alias)
				 ->on(sprintf('%s.%s = %s.%s', $alias, $this->escape($relation_data['related_column']), 
			 								   $this->from_alias, $this->escape($relation_data['local_column'])));
		}
		
		//this needs recording so the object can be added as a relation, not a join
		$this->with[$alias] = array('relation_type'	=> $relation_data['relation_type'],
									'relation_name'	=> Wave\Inflector::singularize($relation));
		
		return $this;
		
	}
	
	public function leftJoin($class, &$alias = null, &$target_alias = null){
		return $this->join('LEFT JOIN', $class, $alias, $target_alias);
	}
	
	public function innerJoin($class, &$alias = null, &$target_alias = null){
		return $this->join('INNER JOIN', $class, $alias, $target_alias);
	}
	
	public function rightJoin($class, &$alias = null, &$target_alias = null){
		return $this->join('RIGHT JOIN', $class, $alias, $target_alias);
	}
	
	//designed to be used with the 'on' or 'using' methods
	public function join($type, $class, &$alias = null, &$target_alias = null){
		
		//this is where to stick the joined object
		if($target_alias === null)
			$target_alias = $this->from_alias;
		
		$this->resolveNamespace($class);
		
		//@patrick, should this not actually select these rows? Should it be an extra parameter in the function constructor?
		//$join_table_alias = $this->addFieldsToSelect($class) could be replaced with:
		//$join_table_alias = $this->aliasClass($class); // so it didn't add the fields but still aliased the table
		if(!$this->manual_select)
			$this->addFieldsToSelect($class, $alias);
		else
			$this->aliasClass($class, $alias);

		$this->joins[] = array('type' => $type,
							   'class'		=> $class,
							   'table_alias' => $alias,
							   'target_alias' => &$target_alias, //for many to many, this won't be known till the target table is joined
							   'condition' => null);
		
		return $this;
	}
		
	//designed to be used with ->*Join methods
	public function on($condition){
		
		$this->addJoinCondition("ON $condition");
		return $this;
	}
	
	//designed to be used with ->*Join methods
	public function using($fields){
		
		if(!is_array($fields))
			$fields = array($fields);
		
		$this->addJoinCondition(printf('USING(%s)', implode(',', $fields)));
		return $this;
	}
	
	//fucntion for adding condition to the last join and check that there was a join prior to using on/using
	private function addJoinCondition($condition){
		
		$last_index = count($this->joins) -1;
		if($last_index === -1)
			throw new Wave\Exception('Wave\DB\Query::on and ::using may only be used following a join.');
		
		$this->joins[$last_index]['condition'] = $condition;
	}
	
	/*
	*	END JOIN METHODS
	*/

	/*
	*	START WHERE METHODS
	*/
	
	//default to and
	public function where($condition, $params = null){
		return $this->andWhere($condition, $params);
	}
	
	//can have the ->and or ->or methods used on the result
	public function orWhere($condition, $params){
		$this->addWhereCondition($condition, $params, 'OR', true);
		return $this;
	}
	
	//can have the ->and or ->or methods used on the result
	public function andWhere($condition, $params){
		$this->addWhereCondition($condition, $params, 'AND', true);
		return $this;
	}
	
	//designed to be used after ->*Where(), with underscore because 'or' is reserved
	public function _or($condition, $params = null){
		$this->addWhereCondition($condition, $params, 'OR');
		return $this;
	}
	
	//designed to be used after ->*Where(), with underscore because 'and' is reserved
	public function _and($condition, $params = null){
		$this->addWhereCondition($condition, $params, 'AND');
		return $this;
	}

	private function addWhereCondition($condition, $params, $type, $create = false){
		
		$current_index = count($this->where);
				
		if($create){
			$this->where[] = array('type'		=> $type,
								   'condition'	=> self::parseWhereCondition($condition, $params), 
								   'params'		=> array());
		} else {
			if($current_index-- === 0)
				throw new Wave\Exception('Wave\DB\Query::and and ::or may only be used following a where.');
			
			$this->where[$current_index]['condition'] .= sprintf(' %s %s', $type, self::parseWhereCondition($condition, $params));
		}
		
		if($params !== null)
			$this->where[$current_index]['params'] = array_merge($this->where[$current_index]['params'], $params);
		
	}
	
	public static function parseWhereCondition($condition, &$params){
		
		if(stripos($condition, ' AND ') !== false || stripos($condition, ' OR ') !== false)
			trigger_error('You should be using ->or() or ->and() to add multiple criteria to a where clause.');
		
		$num_placeholders = substr_count($condition, '?');
		
		//if there are no placeholders, no transformation can be done on the params.
		if($num_placeholders === 0)
			return $condition;
			
		if(is_array($params)){
			$num_params = count($params);
			if($num_placeholders < $num_params){
				//otherwise change the parameters to matcht he num in the array and make it an 'IN' clause.
				$condition = str_replace('?', '('.implode(',', array_fill(0, $num_params, '?')).')', $condition);
				return str_replace(array('<>', '!=', '='), array('NOT IN', 'NOT IN', 'IN'), $condition);
			} 
		}

		if($num_placeholders === 1){
			if($params === null)
				$condition = str_replace(array('<>', '!=', '=', '?'), array('IS NOT', 'IS NOT', 'IS', 'NULL'), $condition);
			else if(!is_array($params))
				$params = array($params);
		} 

		return $condition;
	}
	
	
	/*
	*	END WHERE METHODS
	*/
	
	public function groupBy($column){
	
		if(!is_array($column))
			$column = array($column);
		
		$this->group = array_merge($this->group, $column);
		return $this;
	}
	
	public function orderBy($column){

		$this->order = $column;
		return $this;
 	}
	
	
	public function having($having){
	
		$this->having[] = $having;
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
				
		$query = 'SELECT ' . ($this->paginate ? 'SQL_CALC_FOUND_ROWS ' : '');
		
		$fields = array();
		foreach($this->fields as $alias => $field){
			//if the alias isn't a number, add it to the field
			if(!is_int($alias))
				$field .= ' AS '.$alias;
			$fields[] = $field;
		}
		
		$query .= implode(',', $fields)."\n";
		
		$from_class = $this->unaliasClass($this->from_alias);
		$query .= sprintf("FROM %s.%s AS %s\n", $this->escape($from_class::_getDatabaseName()), $this->escape($from_class::_getTableName()), $this->from_alias);
		
		//joins (includes withs)
		foreach($this->joins as $join){
			$join_class = $join['class'];
			$query .= sprintf("%s %s.%s AS %s %s\n", $join['type'], $this->escape($join_class::_getDatabaseName()), $this->escape($join_class::_getTableName()), $join['table_alias'], $join['condition']);
		}
		
		foreach($this->where as $index => $where){
			$query .= ($index === 0 ? 'WHERE' : $where['type']);
			$query .= sprintf(" (%s)\n", $where['condition']);
			
			if($where['params'] !== null)
				$this->_params = array_merge($this->_params, $where['params']);
		}
		
						
		if (isset($this->group[0])) $query .= $this->checkClassNames('GROUP BY ' . implode(',', $this->group)."\n");
		if (isset($this->having))	$query .= $this->checkClassNames('HAVING ' . implode('AND ', $this->having) . "\n");
		if (isset($this->order[0])) $query .= $this->checkClassNames('ORDER BY ' . $this->order . "\n");
				
		if (isset($this->limit)){
			$query .= 'LIMIT '.$this->limit;
			if (isset($this->offset))
				$query .= ' OFFSET '.$this->offset;
		}

        $this->_params = array_map(array($this->database->getConnection()->getDriverClass(), 'valueToSQL'), $this->_params);

        $this->_built = true;

		return $query;
	
	}
	
	
	public function execute($debug = false){

		$sql = $this->buildSQL();

		if($debug){
		    echo "QUERY: $sql\n";
            echo "PARAMS: \n";
            print_r($this->_params);

        }

		$statement = $this->database->getConnection()->prepare($sql);	
		
		$statement->execute( $this->_params );
		
		$this->_statement = $statement;
		$this->_executed = true; 
	
	}

    public function fetchAll($parse_objects = true, $debug = false){

        $rows = array();
        while($row = $this->fetchRow($parse_objects, $debug)){
            $rows[] = $row;
        }

        return $rows;

    }
	
	
	public function fetchRow($parse_objects = true, $debug = false){
		
		$object_instances = array();
		
		if(!$this->_executed)
			$this->execute($debug);
		
		for(;;){
					
			if($this->_last_row === false)
				$this->_last_row = $this->_statement->fetch();
			
			//if still false, return null (no rows left in set)
			if($this->_last_row === false)
				return isset($object_instances[$this->from_alias]) ? $object_instances[$this->from_alias] : null;
			
			//if not bothering to parse object
			if(!$parse_objects)
				return $this->_last_row;
			
			//if there's no instance of the main class, create a new one.
			if(!isset($object_instances[$this->from_alias]))
				$object_instances[$this->from_alias] = $this->buildClassInstance($this->from_alias);
			
			//if there are joins, check that the current row still has the same $from_instance, if it doesn't, break.
			//otherwise build the related objects and put them on it.
			if(isset($this->joins[0])){
				
				//if the from instance is different, break and leave it for the next call to fetchRow().
				foreach($object_instances[$this->from_alias]->_getIdentifyingData() as $property => $value){
					$alias = $this->class_aliases[$this->from_alias]['columns'][$property];
					if($this->_last_row[$alias] !== $value)
						break 2; //break parent loop
				}				
				
				//otherwise build the child rows
				foreach($this->joins as $join){
					$object_instances[$join['table_alias']] = $this->buildClassInstance($join['table_alias']);
				}
				
				//then check non-null child rows and add them to their parent rows
				foreach($this->joins as $join){
					if($object_instances[$join['table_alias']] === null)
						continue;

					//find if is a join or a with
					if(isset($this->with[$join['table_alias']])){
						switch($this->with[$join['table_alias']]['relation_type']){
							case Wave\DB\Relation::ONE_TO_ONE:
							case Wave\DB\Relation::MANY_TO_ONE:
								$object_instances[$join['target_alias']]->{'set'.$this->with[$join['table_alias']]['relation_name']}($object_instances[$join['table_alias']], false);
								break;
							case Wave\DB\Relation::ONE_TO_MANY:
							case Wave\DB\Relation::MANY_TO_MANY:
								$object_instances[$join['target_alias']]->{'add'.$this->with[$join['table_alias']]['relation_name']}($object_instances[$join['table_alias']], false);
								break;
						}
					} else
						$object_instances[$join['target_alias']]->addJoinedObject($object_instances[$join['table_alias']], $join['table_alias']);
					
				}
							
				//set the _last_row to false as it has been processed and this will force the next call to get another one.
				$this->_last_row = false;
			} else {
				//if no joins, just break from loop and return the $from_instance.
				break;
			}
		}

        // clear the row out so it can fetch another object if called again
        $this->_last_row = false;

		return $object_instances[$this->from_alias];
	
	}
	
	
	//builds an instance of the class from _last_row and the supplied alias
	private function buildClassInstance($class_alias){
				
		$class = $this->unaliasClass($class_alias);
		$columns = $this->class_aliases[$class_alias]['columns'];

		$build_array = array();
		foreach($columns as $column_name => $column_alias)
			$build_array[$column_name] = $this->_last_row[$column_alias];
				
		$instance = $class::createFromArray($build_array);
		
		if($instance !== null)
			$instance->_setLoaded();
		
		return $instance;
	}
	
	
	//This method will not work if the last query contained a join
	//@todo do a seperate count on the last query.
	public function fetchRowCount(){
		
		if($this->paginate === false)
			throw new \Wave\Exception('Wave\DB::fetchRowCount can only be used when paginating');
		
		$sql = 'SELECT FOUND_ROWS() AS row_count;';
	
		$statement = $this->database->getConnection()->prepare($sql);		
		$statement->execute();
		
		self::$query_count++;
		
		$rslt = $statement->fetch(Connection::FETCH_ASSOC);
		
		return $rslt['row_count'];
	}

	
	//checks if it's a full class with namespace, if not adds the default one.
	private function resolveNamespace(&$class){
		
		if(strpos($class, '\\') === false)
			$class = $this->database->getNamespace().'\\'.$class;
		
		return $class;
	}
	
	//Adds all the class fields to select fields and aliases them
	private function addFieldsToSelect($class, &$class_alias = null){
		
		$this->aliasClass($class, $class_alias);
		foreach($class::_getFields() as $field)
			$this->aliasColumn($class_alias, $field);
			
		//this is so the correct alias can be used in joins etc.
		return $class_alias;
	}
	
	private function aliasClass($class, &$alias = null){
		
		if($alias === null)
			$alias = $this->getAlias();
		
		$this->class_aliases[$alias] = array('class'	=> $class,
											 'columns'	=> array());

		return $alias;	
	}
	
	private function unaliasClass($alias){
		return $this->class_aliases[$alias]['class'];
	}
	
	
	private function aliasColumn($table, $column){
		
		$alias = $this->getAlias();
		$column_alias = sprintf('%s.%s', $table, $this->escape($column));
		
		$this->fields[$alias] = $column_alias;
		$this->class_aliases[$table]['columns'][$column] = $alias;
		
		return $alias;
	}
	
	private function unaliasColumn($alias){
		
		$column_alias = $this->fields[$alias];
		list($class_alias, $column) = explode('.', $column_alias);
		$column = $this->unescape($column);
		
		return array('column' => $column,
					 'class_alias' => $class_alias);
	}
	
	//underscores so two letter aliases don't collide with keywords eg. ON, AS, IF, IS, OR, BY
	//AS and BY are the only realistic problems as the others are so far through alphabet.
	private function getAlias(){
		return '_' . $this->alias_counter++;
	}	
	
	private function escape($text){
		return $this->database->escape($text);
	}
	
	private function unescape($text){
		return trim($text, $this->escape_character);
	}
	
	
	//overload is necessary as I can't define methds named 'or' or 'and'
	//If you don't like it, use '_or' and '_and' directly.
	public function __call($method, $args){
		
		if($method != 'or' && $method != 'and')
			throw new Wave\Exception("[$method] does not exist");
		
		$method = '_'.$method;
		
		switch(count($args)){
			case 1:
				return $this->$method($args[0]);
			case 2:
				return $this->$method($args[0], $args[1]);

		}
		
		
	}
}