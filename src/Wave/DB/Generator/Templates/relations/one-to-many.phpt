{# <?php #}
//{{ relation.Name }} 
	public function add{{ relation.Name|singularize }}($obj, $create_relation = true){
		$this->_data['{{ relation.Name }}'][] = $obj; //temp reference removal until cache is completed
		
	}
	
	//{{ relation.Name }} 
	public function get{{ relation.Name }}(){
		
		if(!isset($this->_data['{{ relation.Name }}'])){
			$this->_data['{{ relation.Name }}'] = Wave\DB::get('{{ relation.ReferencedTable.Database.getNamespace(false) }}')->from('{{ relation.ReferencedTable.ClassName }}', $from_alias)
									{% for column in relation.ReferencedColumns %}->{% if loop.first %}where{% else %}and{% endif %}("$from_alias.{{ column.getName(true) }} = ?", $this->_data['{{ relation.LocalColumns[loop.index0].Name }}'])
									{% endfor %}->fetchAll();
		}
		
		return $this->_data['{{ relation.Name }}'];
	}									