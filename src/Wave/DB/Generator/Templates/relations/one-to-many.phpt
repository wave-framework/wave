{# <?php #}

    /**
     * {{ relation.Name }} - one-to-many
     *
     * @param {{ relation.ReferencedTable.getClassName(true) }} $obj The {{ relation.Name }} to be added
     * @param bool $create_relation whether to create the relation in the database
     *
     * @return $this
    **/
    public function add{{ relation.Name|singularize }}({{ relation.ReferencedTable.getClassName(true) }} &$object, $create_relation = true){
        $this->_addRelationObject('{{ relation.Name }}', $object, $create_relation);
        return $this;
    }

    /**
     * {{ relation.Name }} - one-to-many
     *
     * @param {{ relation.ReferencedTable.getClassName(true) }} $obj The {{ relation.Name }} object to be removed
     * @param bool $delete_object whether to delete the object in the database
     * @return $this
    **/
    public function remove{{ relation.Name|singularize }}({{ relation.ReferencedTable.getClassName(true) }} $object, $delete_object = true){
        $this->_removeRelationObject('{{ relation.Name }}', $object, $delete_object);
        return $this;
    }

    /**
     * {{ relation.Name }} - one-to-many
     *
     * @param callable $transform_callback
     * @return {{ relation.ReferencedTable.getClassName(true) }}[]
    **/
    public function get{{ relation.Name }}(){
        $transform_callback = func_num_args() >= 1 ? func_get_arg(0) : null;
        return $this->_getRelationObjects('{{ relation.Name }}', $transform_callback);
    }