{# <?php #}
//{{ relation.Name }} 
	public function set{{ relation.Name }}(&$obj, $create_relation = true){
		$this->_data['{{ relation.Name }}'] = &$obj;
	}
	
	//{{ relation.Name }} 
	public function get{{ relation.Name }}(){
		
		if(!isset($this->_data['{{ relation.Name }}'])){
			
			if($this->_data['{{ relation.LocalColumns[0].Name }}'] === null)
				return null;
			
			$this->_data['{{ relation.Name }}'] = Wave\DB::get('{{ relation.ReferencedTable.Database.getNamespace(false) }}')->from('{{ relation.ReferencedTable.ClassName }}', $from_alias)
									{% for column in relation.ReferencedColumns %}->{% if loop.first %}where{% else %}and{% endif %}("$from_alias.{{ column.getName(true)|addslashes }} = ?", $this->_data['{{ relation.LocalColumns[loop.index0].Name }}'])
									{% endfor %}->fetchRow();
		}
		
		return $this->_data['{{ relation.Name }}'];
	}