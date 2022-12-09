{# <?php #}

    /**
     * {{ relation.Name }} - many-to-many
     *
     * @param $obj {{ relation.TargetRelation.ReferencedTable.getClassName(true) }} The {{ relation.TargetRelation.ReferencedTable.getClassName() }} to be added
     * @param bool $create_relation whether to create the relation in the database
     * @param array $join_data additional data to be saved when the relation is created, column => value array
     *
     * @return $this
    **/
    public function add{{ relation.Name|singularize }}({{ relation.TargetRelation.ReferencedTable.getClassName(true) }} &$object, $create_relation = true, array $join_data = array())
    {
        $this->_addRelationObject('{{ relation.Name }}', $object, $create_relation, $join_data);
        return $this;
    }

    /**
     * {{ relation.Name }}
     *
     * @param $obj {{ relation.TargetRelation.ReferencedTable.getClassName(true) }} The {{ relation.TargetRelation.ReferencedTable.getClassName() }} object to be removed
     * @param bool $remove_relation actually remove the relation from the database
     * @return $this
    **/
    public function remove{{ relation.Name|singularize }}({{ relation.TargetRelation.ReferencedTable.getClassName(true) }} &$object, $remove_relation = true)
    {
        $this->_removeRelationObject('{{ relation.Name }}', $object, $remove_relation);
        return $this;
    }

    /**
     * {{ relation.Name }}
     *
     * @param callable $transform_callback
     * @return {{ relation.TargetRelation.ReferencedTable.getClassName(true) }}[]
    **/
    public function get{{ relation.Name }}()
    {
        $transform_callback = func_num_args() >= 1 ? func_get_arg(0) : null;
        return $this->_getRelationObjects('{{ relation.Name }}', $transform_callback);
    }