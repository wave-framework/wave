<<?php>>

	public function _get<<relation_alias>>($args = array()){
		$key = '<<relation_alias>>';
		if(!empty($args)) $key .= '.' . md5(serialize($args));
		
		if(!isset($this->_data[$key])){
			
			$query = \Wave\DB::get('<<namespace>>')->from('<<namespace>>\\<<Wave\DB::tableNameToClass(target_table)>>')
											->innerJoin('<<Wave\DB::tableNameToClass(referenced_table_name)>>', '<<namespace>>\\<<Wave\DB::tableNameToClass(referenced_table_name)>>.<<target_table>>_id = <<namespace>>\\<<Wave\DB::tableNameToClass(target_table)>>.<<target_table>>_id', false)
											->where('<<Wave\DB::tableNameToClass(referenced_table_name)>>.<<referenced_column_name>>', '=', $this->_data['<<column_name>>']);
			foreach($args as $func => $props)
				$query->$func($props);
				
			$this->_data[$key] = $query->fetchAll();
		}
													
		return $this->_data[$key];
	}
	
	
	public function add<<target_class>>(&$object = null, $create_relation = true){
		
		if($object !== null){
			if(!isset($this->_data['<<relation_alias>>']))
				$this->_data['<<relation_alias>>'] = array();

			$this->_data['<<relation_alias>>'][] = $object;
		
			//it's many to many so get the relation class
			if($create_relation){
				$rc = new <<Wave\DB::tableNameToClass(referenced_table_name)>>();
				$rc-><<target_table>>_id = $object-><<target_table>>_id;
				$rc-><<referenced_column_name>> = $this-><<referenced_column_name>>;
				$rc->save();
				
				$object->_addRelationObject(null, $rc);
			}
		} 		
		
	}
	
	public function remove<<target_class>>($object, $remove_relation = true){
		
		if($remove_relation){
				
			$sql = 'DELETE FROM `<<referenced_table_name>>` WHERE `<<target_table>>_id` = ? AND `<<referenced_column_name>>` = ? LIMIT 1;';

			$conn = \Wave\DB::get('<<namespace>>')->getConnection();		
			$conn->prepare($sql)->execute(array($object-><<target_table>>_id, $this-><<referenced_column_name>>));
			
			$object->_removeRelationObject(null, $object);	
		}
	}