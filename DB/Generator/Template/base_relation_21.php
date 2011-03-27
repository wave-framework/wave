<<?php>>

	protected function _get<<relation_alias>>(){
		if(!isset($this->_data['<<relation_alias>>'])){
			
			$this->_data['<<relation_alias>>'] = Wave_DB::get('<<namespace>>')->from('<<Wave_DB::tableNameToClass(referenced_table_name)>>')
										->where('<<Wave_DB::tableNameToClass(referenced_table_name)>>.<<referenced_column_name>>', '=', $this->_data['<<column_name>>'])
										->fetchRow();
		}
													
		return $this->_data['<<relation_alias>>'];
	}
	
	protected function _set<<relation_alias>>($object){

		return $this->_data['<<relation_alias>>'] = $object;
	}