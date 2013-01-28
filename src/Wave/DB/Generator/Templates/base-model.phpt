<?php

/**
* Model base class generated by the Wave\DB ORM.
* Changes made to this file WILL BE OVERWRITTEN when database is next generated.
* To add/override functionality, edit the child stub class.
*
* @package:   Models\{{ table.Database.Namespace }}
* @generated: {{ date().format('Y-m-d h:i:s') }}
*
* @schema:    {{ table.Database.Name }}
* @table:     {{ table.Name }}
* @engine:    {{ table.Engine }}
* @collation: {{ table.Collation }}
*
*/

namespace {{ table.Database.Namespace }}\Base;

use Wave,
	Wave\DB;
		
abstract class {{ table.ClassName }} extends Wave\DB\Model {

	//Table name
	protected static $_database = '{{ table.Database.getNamespace(false) }}';
	protected static $_schema_name = '{{ table.Database.Name }}';
	protected static $_table_name = '{{ table.Name }}';
	
	//Fields
	protected static $_fields = array(
	{% for column in table.Columns %}
	
		//{{ column.TypeDescription|raw }} {{ column.Extra }} {{ column.Comment }}
		'{{ column.Name }}' => array(
			'default'	=> {% if column.isNullable and column.Default == '' %}null{% else %}'{{ column.Default }}'{% endif %},
			'data_type'	=> {{ column.DataType }},
			'nullable'	=> {% if column.isNullable %}true{% else %}false{% endif %}

		){% if not loop.last %},{% endif %}
		
	{% endfor %});

	//Indexes
	protected static $_constraints = array(
	{% for constraint in table.Constraints %}{% if constraint.Type == constant('\\Wave\\DB\\Constraint::TYPE_PRIMARY') or constraint.Type == constant('\\Wave\\DB\\Constraint::TYPE_UNIQUE')  %}
		
		'{{ constraint.Name }}' => array(
			'type'	=> {{ constraint.Type }},
			'fields'	=> array({% for column in constraint.Columns %}'{{ column.Name }}'{% if not loop.last %},{% endif %}{% endfor %})

		){% if not loop.last %},{% endif %}
		
	{% endif %}{% endfor %});
	
	//Relations
	protected static $_relations = array(
		{% for relation in table.Relations %}
		
		//{{ relation.Description }}
		'{{ relation.Name }}' => array(
			'relation_type'		=> {{ relation.Type }},
			'local_column'		=> '{{ relation.LocalColumn.Name }}',
			'related_class'		=> '{{ relation.ReferencedColumn.Table.getClassName(true)|addslashes }}',
			'related_column'	=> '{{ relation.ReferencedColumn.Name }}',
			{% if relation.Type ==  constant('\\Wave\\DB\\Relation::MANY_TO_MANY') %}'target_relation'	=> array(
				'local_column'		=> '{{ relation.TargetRelation.LocalColumn.Name }}',
				'related_class'		=> '{{ relation.TargetRelation.ReferencedColumn.Table.getClassName(true)|addslashes }}',
				'related_column'	=> '{{ relation.TargetRelation.ReferencedColumn.Name }}',
			)
			{% endif %}
			
		){% if not loop.last %},{% endif %}

	{% endfor %});

	{% for column in table.Columns %}
	
	//{{ column.TypeDescription|raw }} {{ column.Extra }} {{ column.Comment }}
	public function _get{{ column.Name }}(){
		return $this->_data['{{ column.Name }}'];
	}
	
	public function _set{{ column.Name }}($value){
		$this->_data['{{ column.Name }}'] = $value;
		return $this;
	}
	{% endfor %}
	
	//Relations
	{% for relation in table.Relations %}
	
	{% if relation.Type == constant('\\Wave\\DB\\Relation::ONE_TO_ONE') %}{% include 'relations/one-to-one.phpt' %}
	{% elseif relation.Type == constant('\\Wave\\DB\\Relation::ONE_TO_MANY') %}{% include 'relations/one-to-many.phpt' %}
	{% elseif relation.Type == constant('\\Wave\\DB\\Relation::MANY_TO_ONE') %}{% include 'relations/many-to-one.phpt' %}
	{% elseif relation.Type == constant('\\Wave\\DB\\Relation::MANY_TO_MANY') %}{% include 'relations/many-to-many.phpt' %}
	{% endif %}
		
	{% endfor %}
	
}