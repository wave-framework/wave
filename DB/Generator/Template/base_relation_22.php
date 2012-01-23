<<?php>>

	protected function _get<<relation_alias>>($args = array()){
		$key = '<<relation_alias>>.' . md5(serialize($args));
		if(!isset($this->_data[$key])){
			
			$query = Wave_DB::get('<<namespace>>')->from('<<namespace>>_<<Wave_DB::tableNameToClass(target_table)>>')
											->innerJoin('<<Wave_DB::tableNameToClass(referenced_table_name)>>', '<<namespace>>_<<Wave_DB::tableNameToClass(referenced_table_name)>>.<<target_table>>_id = <<namespace>>_<<Wave_DB::tableNameToClass(target_table)>>.<<target_table>>_id', false) //@mod by Patrick, was '<<referenced_table_name>>.<<target_table>>_id = <<target_table>>.<<target_table>>_id'
											->where('<<Wave_DB::tableNameToClass(referenced_table_name)>>.<<referenced_column_name>>', '=', $this->_data['<<column_name>>']);
			foreach($args as $func => $props)
				$query->$func($props);
				
			$this->_data[$key] = $query->fetchAll();
		}
													
		return $this->_data[$key];
	}
	
	
	public function add<<target_class>>($object = null, $create_relation = true){
		
		if($create_relation){
		
		/* @todo */		
		
		}
		if($object !== null){
			$this->_data['<<relation_alias>>'][] = $object;
		} else {
			if(!isset($this->_data['<<relation_alias>>']))
				$this->_data['<<relation_alias>>'] = array();
		}
	}
	
	public function remove<<target_class>>($object, $remove_relation = true){
		
		if($remove_relation){
		
		/* @todo */		
		
		}
	}