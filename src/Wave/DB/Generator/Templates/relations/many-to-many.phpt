{% if false %}<?php{% endif %}

//{{ relation.Name }} 
	public function add{{ relation.Name|singularize }}(&$obj, $create_relation = true){
	    if(!isset($this->_data['{{ relation.Name }}']))
	        $this->_data['{{ relation.Name }}'] = array();

		$this->_data['{{ relation.Name }}'][] = $obj; //temp reference removal until cache is completed

        //it's many to many so get the relation class
        if($create_relation){
            $rc = new \{{ relation.TargetRelation.ReferencedColumn.Table.getClassName(true) }}();
            $rc->{{ relation.TargetRelation.ReferencedColumn.Name }} = $obj->{{ relation.TargetRelation.ReferencedColumn.Name }};
            $rc->{{ relation.ReferencedColumn.Name }} = $this->_data['{{ relation.LocalColumn.Name }}'];
            $rc->save();

        }
		
	}

	public function remove{{ relation.Name|singularize }}(&$obj, $remove_relation = true){

        if($remove_relation){
            $relation = Wave\DB::get('{{ relation.ReferencedColumn.Table.Database.getNamespace(false) }}')->from('{{ relation.ReferencedColumn.Table.ClassName }}', $from_alias)
                                ->where("$from_alias.{{ relation.LocalColumn.getName(true) }} = ?", $this->_data['{{ relation.LocalColumn.Name }}'])
                                  ->and("$from_alias.{{ relation.TargetRelation.ReferencedColumn.getName(true) }} = ?", $obj->{{ relation.TargetRelation.ReferencedColumn.Name }})
                                ->fetchRow();

            $relation->delete();
        }

	}

	//{{ relation.Name }} 
	public function get{{ relation.Name }}(){
		
		if(!isset($this->_data['{{ relation.Name }}'])){
			$this->_data['{{ relation.Name }}'] = Wave\DB::get('{{ relation.ReferencedColumn.Table.Database.getNamespace(false) }}')->from('{{ relation.TargetRelation.ReferencedColumn.Table.ClassName }}', $from_alias)
									->leftJoin('{{ relation.ReferencedColumn.Table.ClassName }}', $join_alias)
										->on("$join_alias.{{ relation.TargetRelation.LocalColumn.getName(true) }} = $from_alias.{{ relation.TargetRelation.ReferencedColumn.getName(true) }}")
									->where("$join_alias.{{ relation.ReferencedColumn.getName(true) }} = ?", $this->_data['{{ relation.LocalColumn.Name }}'])
									->fetchAll();
		}
		
		return $this->_data['{{ relation.Name }}'];
	}