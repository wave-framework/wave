{% if false %}<?php{% endif %}
//{{ relation.Name }} 
	public function add{{ relation.Name|singularize }}($obj, $create_relation = true){
		$this->_data['{{ relation.Name }}'][] = $obj; //temp reference removal until cache is completed
		
	}
	
	//{{ relation.Name }} 
	public function _get{{ relation.Name }}(){
		
		if(!isset($this->_data['{{ relation.Name }}'])){
			$this->_data['{{ relation.Name }}'] = Wave\DB::get('{{ relation.ReferencedColumn.Table.Database.getNamespace(false) }}')->from('{{ relation.ReferencedColumn.Table.ClassName }}', $from_alias)
									->where("$from_alias.{{ relation.ReferencedColumn.getName(true) }} = ?", $this->_data['{{ relation.LocalColumn.Name }}'])
									->fetchAll();
		}
		
		return $this->_data['{{ relation.Name }}'];
	}