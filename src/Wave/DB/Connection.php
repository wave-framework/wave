<?php

/**
 *	DB Connection extension class
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave;

class Connection extends \PDO {

	private $driver_class;

	public function __construct($config){

		$driver_class = Wave\DB::getDriverClass($config->driver);
	
		$this->driver_class = $driver_class;
				
		parent::__construct($driver_class::constructDSN($config), $config->username, $config->password);
		
		//Override the default PDOStatement 
		$this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('\\Wave\\DB\\Statement', array($this)));
	
	}
	
	/** for catching queries	
	public function prepare($sql){
		echo "$sql\n\n";
		return parent::prepare($sql);
	}
	**/

	public function getDriverClass(){
		return $this->driver_class;
	}


}

?>