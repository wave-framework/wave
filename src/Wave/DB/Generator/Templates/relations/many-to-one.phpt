{# <?php #}

    /**
     * {{ relation.Name }} - many-to-one
     *
     * @param $object {{ relation.ReferencedTable.getClassName(true) }} The {{ relation.Name }} to be added
     * @param bool $create_relation whether to create the relation in the database
     *
     * @return $this
    **/
    public function set{{ relation.Name }}(?{{ relation.ReferencedTable.getClassName(true) }} &$object = null, $create_relation = true)
    {
        $this->_setRelationObject('{{ relation.Name }}', $object, $create_relation);
        return $this;
    }

    /**
     * {{ relation.Name }} - many-to-one
     *
     * @param callable $transform_callback
     * @return {{ relation.ReferencedTable.getClassName(true) }}
    **/
    public function get{{ relation.Name }}()
    {
        $transform_callback = func_num_args() >= 1 ? func_get_arg(0) : null;
        return $this->_getRelationObjects('{{ relation.Name }}', $transform_callback);
    }