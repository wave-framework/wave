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
		return $this->_setRelationObject('{{ relation.Name }}', $obj, $create_relation);
	}
	
	/**
	 * {{ relation.Name }} - one-to-one
	 *
	 * @return {{ relation.ReferencedTable.getClassName(true) }}
	**/
	public function get{{ relation.Name }}($query_transform_callback){
		return $this->_getRelationObjects('{{ relation.Name }}', $query_transform_callback = null);
	}