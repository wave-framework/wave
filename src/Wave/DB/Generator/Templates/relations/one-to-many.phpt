{# <?php #}

	/**
	 * {{ relation.Name }} - one-to-many
	 *
	 * @param {{ relation.ReferencedTable.getClassName(true) }} $obj The {{ relation.Name }} to be added
	 * @param bool $create_relation whether to create the relation in the database
	 *
	 * @return void
	**/
	public function add{{ relation.Name|singularize }}({{ relation.ReferencedTable.getClassName(true) }} $obj, $create_relation = true){
		$this->_data['{{ relation.Name }}'][] = $obj; //temp reference removal until cache is completed

		if($create_relation){
            {% for column in relation.ReferencedColumns %}$obj->{{ column.getName() }} = $this->_data['{{ relation.LocalColumns[loop.index0].Name }}'];
            {% endfor %}$obj->save();
		}
	}

    /**
     * {{ relation.Name }} - one-to-many
     *
     * @param {{ relation.ReferencedTable.getClassName(true) }} $obj The {{ relation.Name }} object to be removed
     * @param bool $delete_object whether to delete the object in the database
     *
     * @return bool
    **/
    public function remove{{ relation.Name|singularize }}({{ relation.ReferencedTable.getClassName(true) }} $obj, $delete_object = true){
        $index = null;
        if(isset($this->_data['OrderItems'])){
                foreach($this->_data['{{ relation.Name }}'] as $i => $related){
                    if($related->_equals($obj)){
                        $index = $i;
                        break;
                    }
                }
                if($index !== null){
                    array_splice($this->_data['{{ relation.Name }}'], $index, 1);
                }
        }

        if($delete_object){
            return $obj->delete();
        }
        return true;
    }
	
	/**
	 * {{ relation.Name }} - one-to-many
	 *
	 * @return {{ relation.ReferencedTable.getClassName(true) }}[]
	**/
	public function get{{ relation.Name }}(){
			
		if(!isset($this->_data['{{ relation.Name }}'])){
			$this->_data['{{ relation.Name }}'] = Wave\DB::get('{{ relation.ReferencedTable.Database.getNamespace(false) }}')->from('{{ relation.ReferencedTable.ClassName }}', $from_alias)
									{% for column in relation.ReferencedColumns %}->{% if loop.first %}where{% else %}and{% endif %}("$from_alias.{{ column.getName(true)|addslashes }} = ?", $this->_data['{{ relation.LocalColumns[loop.index0].Name }}'])
									{% endfor %}->fetchAll();
		}
		
		return $this->_data['{{ relation.Name }}'];
	}									