<<?php>>

	public function _get<<relation_alias>>($args = array()){
		$key = '<<relation_alias>>';
		if(!empty($args)) $key .= '.' . md5(serialize($args));
		
		if(!isset($this->_data[$key])){
			
			$query = \Wave\DB::get('<<namespace>>')->from('<<namespace>>\\<<Wave\DB::tableNameToClass(referenced_table_name)>>')
										->where('<<Wave\DB::tableNameToClass(referenced_table_name)>>.<<referenced_column_name>>', '=', $this->_data['<<column_name>>']);
			
			foreach($args as $func => $props)
				$query->$func($props);
			
			$this->_data[$key] = $query->fetchRow();
		}
													
		return $this->_data[$key];
	}
	
	public function _set<<relation_alias>>($object){

		return $this->_data['<<relation_alias>>'] = $object;
	}