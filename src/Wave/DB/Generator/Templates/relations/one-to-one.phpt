{# <?php #}

    /**
	 * {{ relation.Name }} - one-to-one
	 *
	 * @param $obj {{ relation.ReferencedTable.getClassName(true) }} The {{ relation.Name }} to be set
	 * @param $create_relation whether to create the relation in the database
	 *
	 * @return void
	**/
	public function set{{ relation.Name }}(&$obj = null, $create_relation = true){
		$this->_data['{{ relation.Name }}'] = &$obj;
	}
	
	/**
	 * {{ relation.Name }} - one-to-one
	 *
	 * @return {{ relation.ReferencedTable.getClassName(true) }}
	**/
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