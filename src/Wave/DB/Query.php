<?php

/**
 *	Query class for constructing a query.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;

use \Wave;

/**
 * @method \Wave\DB\Query and() and($condition, array $params = array())
 * @method \Wave\DB\Query or() or($condition, array $params = array())
 */
class Query {
	
	private $escape_character;
	
	private $class_aliases	= array();
	private $alias_counter;

    /** @var Wave\DB $database */
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

    /**
     * Sets the Model class this query will result in.
     *
     * @param string      $from
     * @param string|null $alias   [optional] Provide a custom alias to use in the query, or null to have one
     *                             generated. This property is passed by reference so it can be used to get a
     *                             handle on the generated alias.
     * @param array|null  $fields  [optional] An array of fields to select rather than the automatically
     *                             detected fields from the Model class.
     *
     * @return Query
     */
    public function from($from, &$alias = null, array $fields = null){
		
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

    /**
     * Return the specified relation objects with the primary object
     *
     * @param string      $relation  The class name of the relation to join to the primary object
     * @param string|null $alias     [optional] Provide a custom alias to use for the joined object, or null to have one
     *                               generated. This property is passed by reference so it can be used to get a
     *                               handle on the generated alias.
     *
     * @return Query
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

    /**
     * Perform a left join
     * @see join
     */
    public function leftJoin($class, &$alias = null, &$target_alias = null){
		return $this->join('LEFT JOIN', $class, $alias, $target_alias);
	}

    /**
     * Perform an inner join
     * @see join
     */
    public function innerJoin($class, &$alias = null, &$target_alias = null){
		return $this->join('INNER JOIN', $class, $alias, $target_alias);
	}

    /**
     * Perform a right join
     * @see join
     */
	public function rightJoin($class, &$alias = null, &$target_alias = null){
		return $this->join('RIGHT JOIN', $class, $alias, $target_alias);
	}

    /**
     * Join another model onto the current query. This function is designed to have ->on() called directly
     * afterwards with the condition for the join.
     *
     * @param string       $type          The type of join to perform
     * @param string       $class         The model class to join on
     * @param string|null  $alias         [optional] Provide a custom alias to use for the joining table or null to
     *                                    have one generated. This property is passed by reference so it can be used
     *                                    to get a handle on the generated alias.
     * @param string|null  $target_alias  [optional] Set the alias of the object this row is to be joined on to. By
     *                                    default this is the primary object. This property is passed by reference
     *                                    so it can be used to get a handle on the generated alias.
     *
     * @return Query
     */
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

    /**
     * Sets the condition for a join. This should be called directly after the use of
     * a ->*Join() method.
     *
     * @param $condition
     *
     * @return Query
     */
    public function on($condition){
		
		$this->addJoinCondition("ON $condition");
		return $this;
	}

    /**
     * Defines a using clause for a join. This should be called directly after the use of
     * a ->*Join() method.
     *
     * @param $fields
     *
     * @return Query
     */
	public function using($fields){
		
		if(!is_array($fields))
			$fields = array($fields);
		
		$this->addJoinCondition(printf('USING(%s)', implode(',', $fields)));
		return $this;
	}

    /**
     * Checks a join has been defined prior to either ->on or ->using being called
     *
     * @param $condition
     *
     * @throws \Wave\Exception
     */
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

    /**
     * Defines a where sub-clause for this query. This function should only be called once, with subsequent
     * clauses being defined using ->and, ->or, ->andWhere or ->orWhere.
     *
     * @param string $condition The clause condition, should be formatted with a '?' character as placeholders
     *                          for any parameters.
     * @param array  $params    The parameters to substitute in to the query in place of '?' characters in the condition
     *
     * @return Query
     * @throws \Wave\Exception when called using the legacy version of the function
     */
    public function where($condition, $params = array()){
		if(func_num_args() > 2) throw new \Wave\Exception("Invalid use of Query::where() function");
        return $this->andWhere($condition, $params);
	}

    /**
     * Start a new where subclause using an OR between the last sub-clause
     *
     * @see where
     */
    public function orWhere($condition, $params = array()){
		$this->addWhereCondition($condition, $params, 'OR', true);
		return $this;
	}

    /**
     * Start a new where subclause using an AND between the last sub-clause
     *
     * @see where
     */
	public function andWhere($condition, $params = array()){
		$this->addWhereCondition($condition, $params, 'AND', true);
		return $this;
	}

    /**
     * Add a condition to the current subclause using OR between the conditions. This is an alias function
     * because or is a reserved word and cannot be used to declare a method (but can be used at runtime).
     * The equivilent function (without the prefixing underscore) is defined via magic methods).
     *
     * @see where
     */
	public function _or($condition, $params = array()){
		$this->addWhereCondition($condition, $params, 'OR');
		return $this;
	}

    /**
     * Add a condition to the current subclause using AND between the conditions. This is an alias function
     * because or is a reserved word and cannot be used to declare a method (but can be used at runtime).
     * The equivilent function (without the prefixing underscore) is defined via magic methods).
     *
     * @see where
     */
	public function _and($condition, $params = array()){
		$this->addWhereCondition($condition, $params, 'AND');
		return $this;
	}

    /**
     * Adds a where condition to the current query
     *
     * @param string  $condition The clause condition, should be formatted with a '?' character as placeholders
     *                           for any parameters.
     * @param array   $params    The parameters to substitute into the query
     * @param string  $type      The type of condition to add (AND or OR)
     * @param bool    $create    Whether to create a new sub-clause or append this condition to the existing clause
     *
     * @throws \Wave\Exception
     */
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

    /**
     * Converts where conditions into SQL compatible statements. Transforms done are:
     *  - where a parameter is NULL and '=' is replaced with 'IS NULL'.
     *  - where a parameter is an array the '?' is replaced with the number of elements in the array
     *    and the '=' is replaced with IN()
     *
     * @param string $condition The condition to parse.
     * @param array  $params    The parameters array. This is passed by reference.
     *
     * @return string the transformed condition
     */
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

    /**
     * Add a GROUP BY statement
     * @param $column
     *
     * @return Query
     */
    public function groupBy($column){
	
		if(!is_array($column))
			$column = array($column);
		
		$this->group = array_merge($this->group, $column);
		return $this;
	}

    /**
     * Add an ORDER BY
     * @param $column
     *
     * @return Query
     */
    public function orderBy($column){

		$this->order = $column;
		return $this;
 	}

    /**
     * Add a HAVING clause
     * @param $having
     *
     * @return Query
     */
    public function having($having){
	
		$this->having[] = $having;
		return $this;
	}

    /**
     * Set the limit offset (used when paginating)
     * @param $offset
     *
     * @return Query
     */
    public function offset($offset){
	
		$this->offset = $offset;
		return $this;
	}

    /**
     * Set the limit of results to fetch
     * @param $limit
     *
     * @return Query
     */
    public function limit($limit){
	
		$this->limit = $limit;
		return $this;
	}

    /**
     * Set the query into paginating mode
     * @param $offset
     * @param $limit
     *
     * @return Query
     */
    public function paginate($offset, $limit){
		
		$this->paginate = true;
		
		$this->offset($offset);
		$this->limit($limit);
		
		return $this;
	}

    /**
     * Build this query object into a valid SQL statement
     *
     * @return string
     */
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

        /** @var Model $from_class  */
		$from_class = $this->unaliasClass($this->from_alias);
		$query .= sprintf("FROM %s.%s AS %s\n", $this->escape($from_class::_getDatabaseName()), $this->escape($from_class::_getTableName()), $this->from_alias);
		
		//joins (includes withs)
		foreach($this->joins as $join){
            /** @var Model $join_class */
			$join_class = $join['class'];
			$query .= sprintf("%s %s.%s AS %s %s\n", $join['type'], $this->escape($join_class::_getDatabaseName()), $this->escape($join_class::_getTableName()), $join['table_alias'], $join['condition']);
		}
		
		foreach($this->where as $index => $where){
			$query .= ($index === 0 ? 'WHERE' : $where['type']);
			$query .= sprintf(" (%s)\n", $where['condition']);
			
			if($where['params'] !== null)
				$this->_params = array_merge($this->_params, $where['params']);
		}
		
						
		if (isset($this->group[0])) $query .= 'GROUP BY ' . implode(',', $this->group)."\n";
		if (isset($this->having))	$query .= 'HAVING ' . implode('AND ', $this->having) . "\n";
		if (isset($this->order[0])) $query .= 'ORDER BY ' . $this->order . "\n";
				
		if (isset($this->limit)){
			$query .= 'LIMIT '.$this->limit;
			if (isset($this->offset))
				$query .= ' OFFSET '.$this->offset;
		}

        $this->_params = array_map(array($this->database->getConnection()->getDriverClass(), 'valueToSQL'), $this->_params);

        $this->_built = true;

		return $query;
	
	}

    /**
     * Execute this query against the current database instance
     * @param bool $debug
     */
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

    /**
     * Fetch the results of this query as an array.
     *
     * @param bool $parse_objects If true the array is filled with Model objects, otherwise the result is an array
     *                            of associative arrays.
     * @param bool $debug         Used to print the query to STDOUT before being executed
     *
     * @return Model[]|array
     */
    public function fetchAll($parse_objects = true, $debug = false){

        $rows = array();
        while($row = $this->fetchRow($parse_objects, $debug)){
            $rows[] = $row;
        }

        return $rows;

    }

    /**
     * Fetch a single result of this query. Builds a Model object if the $parse_objects flag is set. This will
     * also attach any joined or with objects to their associated targets.
     *
     * @param bool $parse_objects If true the array is filled with Model objects, otherwise the result is an array
     *                            of associative arrays.
     * @param bool $debug         Used to print the query to STDOUT before being executed
     *
     * @return Model|array|null
     */
    public function fetchRow($parse_objects = true, $debug = false){
		
		$object_instances = array();
		
		if(!$this->_executed)
			$this->execute($debug);
		
		for(;;){
					
			if($this->_last_row === false)
				$this->_last_row = $this->_statement->fetch();
			
			//if still false, return null (no rows left in set)
			if($this->_last_row === false)
				break;
							
			//if not bothering to parse object
			if(!$parse_objects){
			
				$output = array();
				foreach($this->class_aliases as $alias => $class_details){
					$output[$alias] = array();
					foreach($class_details['columns'] as $column => $column_alias)
						$output[$alias][$column] = $this->_last_row[$column_alias];
				}
				$this->_last_row = false;
				return $output;
			}
				
			
			//if there's no instance of the main class, create a new one.
			if(!isset($object_instances[$this->from_alias]))
				$object_instances[$this->from_alias] = $this->buildClassInstance($this->from_alias);
			
			//if there are joins, check that the current row still has the same $from_instance, if it doesn't, break.
			//otherwise build the related objects and put them on it.
			if(isset($this->joins[0])){
				
				//if the from instance is different, break and leave it for the next call to fetchRow().
				foreach($object_instances[$this->from_alias]->_getIdentifyingData() as $property => $value){
                    $class = $this->class_aliases[$this->from_alias]['class'];
					$alias = $this->class_aliases[$this->from_alias]['columns'][$property];
                    $cast_value = $this->database->valueFromSQL($this->_last_row[$alias], $class::_getField($property));

                    if($cast_value !== $value)
                        return isset($object_instances[$this->from_alias]) ? $object_instances[$this->from_alias] : null; // break 2;
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
						$object_instances[$join['target_alias']]->addJoinedObject($object_instances[$join['table_alias']], $join['table_alias'], $this->unaliasClass($join['table_alias']));
					
				}
							
				//set the _last_row to false as it has been processed and this will force the next call to get another one.
				$this->_last_row = false;
			} else {
				//if no joins, just break from loop and return the $from_instance.
				break;
			}
		}

        // reset the last row pointer since we've finished with this row now.
        $this->_last_row = false;

		return isset($object_instances[$this->from_alias]) ? $object_instances[$this->from_alias] : null;
;
	
	}


    /**
     * Builds an instance of the class from _last_row and the supplied alias
     *
     * @param string $class_alias
     *
     * @return null|Model
     */
	private function buildClassInstance($class_alias){

        /** @var Model $class */
		$class = $this->unaliasClass($class_alias);
		$columns = $this->class_aliases[$class_alias]['columns'];

		$build_array = array();
		foreach($columns as $column_name => $column_alias){
            //$field = $class::_getField($column_name);
            //$cast_value = $this->database->valueFromSQL($this->_last_row[$column_alias], $field);
			$build_array[$column_name] = $this->_last_row[$column_alias];
        }
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


    /**
     * Checks if the supplied class a full class with namespace, if not adds the default one.
     * @param $class
     *
     * @return string
     */
    private function resolveNamespace(&$class){
		
		if(strpos($class, '\\') === false)
			$class = '\\'.$this->database->getNamespace().'\\'.$class;
		
		return $class;
	}

    /**
     * Adds all the class fields to select fields and aliases them
     *
     * @param string $class
     * @param null $class_alias
     *
     * @return null
     */
    private function addFieldsToSelect($class, &$class_alias = null){
		/** @var Model $class */
		$this->aliasClass($class, $class_alias);
		foreach($class::_getFields() as $field)
			$this->aliasColumn($class_alias, $field);
			
		//this is so the correct alias can be used in joins etc.
		return $class_alias;
	}

    /**
     * Generate an alias for the given class
     * @param string      $class
     * @param string|null $alias
     *
     * @return string
     */
    private function aliasClass($class, &$alias = null){
		
		if($alias === null)
			$alias = $this->getAlias();
		
		$this->class_aliases[$alias] = array('class'	=> $class,
											 'columns'	=> array());

		return $alias;	
	}

    /**
     * Convert an alias back into the original class name
     * @param $alias
     *
     * @return mixed
     */
    private function unaliasClass($alias){
		return $this->class_aliases[$alias]['class'];
	}

    /**
     * Generate an alias for the given column
     * @param string $table
     * @param string $column
     *
     * @return string
     */
    private function aliasColumn($table, $column){
		
		$alias = $this->getAlias();
		$column_alias = sprintf('%s.%s', $table, $this->escape($column));
		
		$this->fields[$alias] = $column_alias;
		$this->class_aliases[$table]['columns'][$column] = $alias;
		
		return $alias;
	}

    /**
     * Convert a column alias back the original column name
     * @param $alias
     *
     * @return array
     */
    private function unaliasColumn($alias){
		
		$column_alias = $this->fields[$alias];
		list($class_alias, $column) = explode('.', $column_alias);
		$column = $this->unescape($column);
		
		return array('column' => $column,
					 'class_alias' => $class_alias);
	}
	

    /**
     * Returns the next available alias and increments the alias counter.
     * Underscores so two letter aliases don't collide with keywords eg. ON, AS, IF, IS, OR, BY.
     * AS and BY are the only realistic problems as the others are so far through alphabet.
     *
     * @return string
     */
    private function getAlias(){
		return '_' . $this->alias_counter++;
	}

    /**
     * Escape a value using the PDO escaper for the current connection
     * @param $text
     *
     * @return string
     */
    private function escape($text){
		return $this->database->escape($text);
	}

    /**
     * Remove escaping characters
     * @param $text
     *
     * @return string
     */
    private function unescape($text){
		return trim($text, $this->escape_character);
	}

    /**
     * Required to make the ->and and ->or methods available since they can't be declared without
     * the compiler complaining.
     *
     * This doesn't preclude the ->_or and ->_and methods being called directly
     *
     * @param $method
     * @param $args
     *
     * @return Query
     * @throws \Wave\Exception
     */
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