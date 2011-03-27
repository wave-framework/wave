<<?php>>

	protected function _get<<relation_alias>>(){
		
		if(!isset($this->_data['<<relation_alias>>'])){
			
			$this->_data['<<relation_alias>>'] = Wave_DB::get('<<namespace>>')->from('<<Wave_DB::tableNameToClass(referenced_table_name)>>')
													->where('<<Wave_DB::tableNameToClass(referenced_table_name)>>.<<referenced_column_name>>', '=', $this->_data['<<column_name>>'])
													->fetchAll();
		}
													
		return $this->_data['<<relation_alias>>'];
	}
	
	public function add<<relation_alias>>($object, $create_relation = true){
		
		if($create_relation){
		
		/* @todo */		
		
		}
		
		$this->_data['<<relation_alias>>'][] = $object;
	}