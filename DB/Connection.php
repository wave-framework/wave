<?php


class Wave_DB_Connection extends PDO {

	private $driver_class;

	public function __construct($config){

		$driver_class = Wave_DB::getDriverClass($config->driver);
	
		$this->driver_class = $driver_class;
				
		parent::__construct($driver_class::constructDSN($config), $config->username, $config->password);
		
		//Override the default PDOStatement 
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('Wave_DB_Statement', array($this)));
	
	}

	public function getDriverClass(){
		return $this->driver_class;
	}


}

?>