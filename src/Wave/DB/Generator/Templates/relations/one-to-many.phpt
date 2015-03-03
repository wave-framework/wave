{# <?php #}

	/**
	 * {{ relation.Name }} - one-to-many
	 *
	 * @param {{ relation.ReferencedTable.getClassName(true) }} $obj The {{ relation.Name }} to be added
	 * @param bool $create_relation whether to create the relation in the database
	 *
	 * @return void
	**/
	public function add{{ relation.Name|singularize }}({{ relation.ReferencedTable.getClassName(true) }} &$obj, $create_relation = true){
	    return $this->_addRelationObject('{{ relation.Name }}', $obj, $create_relation);
	}

    /**
     * {{ relation.Name }} - one-to-many
     *
     * @param {{ relation.ReferencedTable.getClassName(true) }} $obj The {{ relation.Name }} object to be removed
     * @param bool $delete_object whether to delete the object in the database
     * @return bool
    **/
    public function remove{{ relation.Name|singularize }}({{ relation.ReferencedTable.getClassName(true) }} $obj, $delete_object = true){
        return $this->_removeRelationObject('{{ relation.Name }}', $obj, $delete_object);
    }
	
	/**
	 * {{ relation.Name }} - one-to-many
	 *
	 * @return {{ relation.ReferencedTable.getClassName(true) }}[]
	**/
	public function get{{ relation.Name }}(){
		return $this->_getRelationObjects('{{ relation.Name }}');
	}