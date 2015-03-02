{# <?php #}

	/**
	 * {{ relation.Name }} - many-to-one
	 *
     * @param $obj {{ relation.ReferencedTable.getClassName(true) }} The {{ relation.Name }} to be added
     * @param bool $create_relation whether to create the relation in the database
	 *
	 * @return void
	**/
	public function set{{ relation.Name }}({{ relation.ReferencedTable.getClassName(true) }} &$obj = null, $create_relation = true){
		return $this->_setRelationObject('{{ relation.Name }}', $obj, $create_relation);
	}
	
	/**
	 * {{ relation.Name }} - many-to-one
	 *
	 * @return {{ relation.ReferencedTable.getClassName(true) }}
	**/
	public function get{{ relation.Name }}(){
		return $this->_getRelationObjects('{{ relation.Name }}');
	}