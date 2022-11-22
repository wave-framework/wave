{# <?php #}

    /**
     * {{ relation.Name }} - one-to-one
     *
     * @param $obj {{ relation.ReferencedTable.getClassName(true) }} The {{ relation.Name }} to be set
     * @param $create_relation whether to create the relation in the database
     *
     * @return $this
    **/
    public function set{{ relation.Name }}(&$obj = null, $create_relation = true)
    {
        $this->_setRelationObject('{{ relation.Name }}', $obj, $create_relation);
        return $this;
    }

    /**
     * {{ relation.Name }} - one-to-one
     *
     * @param callable $transform_callback
     * @return {{ relation.ReferencedTable.getClassName(true) }}
    **/
    public function get{{ relation.Name }}()
    {
        $transform_callback = func_num_args() >= 1 ? func_get_arg(0) : null;
        return $this->_getRelationObjects('{{ relation.Name }}', $transform_callback);
    }