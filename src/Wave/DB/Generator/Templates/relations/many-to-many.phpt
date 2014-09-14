{# <?php #}

    /**
     * {{ relation.Name }} - many-to-many
     *
     * @param $obj {{ relation.TargetRelation.ReferencedTable.getClassName(true) }} The {{ relation.TargetRelation.ReferencedTable.getClassName() }} to be added
     * @param bool $create_relation whether to create the relation in the database
     * @param array $join_data additional data to be saved when the relation is created, column => value array
     *
     * @return bool
    **/
	public function add{{ relation.Name|singularize }}({{ relation.TargetRelation.ReferencedTable.getClassName(true) }} &$obj, $create_relation = true, array $join_data = array()){
	    if(!isset($this->_data['{{ relation.Name }}']))
	        $this->_data['{{ relation.Name }}'] = array();

        $rc = true;

        //it's many to many so get the relation class
        if($create_relation){
            $rc = new {{ relation.ReferencedTable.getClassName(true) }}();
            {% for column in relation.TargetRelation.ReferencedColumns %}$rc->{{ column.Name }} = $obj->{{ column.Name }};
            {% endfor %}{% for column in relation.ReferencedColumns %}$rc->{{ column.Name }} = $this->_data['{{ relation.LocalColumns[loop.index0].Name }}'];
            {% endfor %}foreach($join_data as $key => $value){
                $rc->{$key} = $value;
            }

            $rc->save();
        }

        $this->_data['{{ relation.Name }}'][] = $obj; //temp reference removal until cache is completed

        return $rc;
	}

    /**
     * {{ relation.Name }}
     *
     * @param $obj {{ relation.TargetRelation.ReferencedTable.getClassName(true) }} The {{ relation.TargetRelation.ReferencedTable.getClassName() }} object to be removed
     * @param bool $remove_relation actually remove the relation from the database
     * @return bool
    **/
	public function remove{{ relation.Name|singularize }}({{ relation.TargetRelation.ReferencedTable.getClassName(true) }} &$obj, $remove_relation = true){

        if($remove_relation){
            $relation = Wave\DB::get('{{ relation.ReferencedTable.Database.getNamespace(false) }}')->from('{{ relation.ReferencedTable.ClassName }}', $from_alias)
                                {% for column in relation.LocalColumns %}->{% if loop.first %}where{% else %}and{% endif %}("$from_alias.{{ column.getName(true)|addslashes }} = ?", $this->_data['{{ relation.LocalColumns[loop.index0].Name }}'])
                                  {% endfor %}{% for column in relation.TargetRelation.ReferencedColumns %}->and("$from_alias.{{ column.getName(true)|addslashes }} = ?", $obj->{{ column.Name }})
                                {% endfor %}->fetchRow();

            if($relation instanceof \Wave\DB\Model){
                try{
                    Wave\DB::delete($relation);
                    return true;
                }
                catch(\PDOException $e){}
            }
        }
        return false;
	}

	/**
	 * {{ relation.Name }}
	 *
	 * @return {{ relation.TargetRelation.ReferencedTable.getClassName(true) }}[]
	**/
	public function get{{ relation.Name }}(){
		//down here can be a little bit messy with multiple column relations
		if(!isset($this->_data['{{ relation.Name }}'])){
			$this->_data['{{ relation.Name }}'] = Wave\DB::get('{{ relation.ReferencedTable.Database.getNamespace(false) }}')->from('{{ relation.TargetRelation.ReferencedTable.ClassName }}', $from_alias)
									->leftJoin('{{ relation.ReferencedTable.ClassName }}', $join_alias)
										{% for column in relation.TargetRelation.LocalColumns %}->{% if loop.first %}on{% else %}and{% endif %}("$join_alias.{{ column.getName(true)|addslashes }} = $from_alias.{{ relation.TargetRelation.ReferencedColumns[loop.index0].getName(true)|addslashes }}")
									{% endfor %}{% for column in relation.ReferencedColumns %}->{% if loop.first %}where{% else %}and{% endif %}("$join_alias.{{ column.getName(true)|addslashes }} = ?", $this->_data['{{ relation.LocalColumns[loop.index0].Name }}'])
									{% endfor %}->fetchAll();
		}
		
		return $this->_data['{{ relation.Name }}'];
	}