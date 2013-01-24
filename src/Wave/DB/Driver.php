<?php

namespace Wave\DB;

abstract class Driver {
	
	
	public static function valueToSQL($value){
	
		switch(true){
		
			case $value instanceof \DateTime:
				return $value->format('Y-m-d H:i:s');

			default:
				return $value;
		}
	}
	
	public static function valueFromSQL($value, $field_data){
	
		if($value === null)
			return null;
			
		switch($field_data['data_type']){

			case Column::TYPE_BOOL:
				return (bool) $value;
		
			case Column::TYPE_INT:
				return (int) $value;
			
			case Column::TYPE_FLOAT:
				return (float) $value;
				
			case Column::TYPE_STRING:
				return (string) $value;
				
			case Column::TYPE_DATE:
			case Column::TYPE_TIMESTAMP:
				if($value == 'CURRENT_TIMESTAMP')
					$value = 'now';
				return new \DateTime($value);
		
			default:
				return $value;
		}
	}
	
}

interface IDriver {

	public static function constructDSN($config);
	public static function getDriverName();
	public static function getTables($database);
	
}

?>