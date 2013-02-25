<?php

/**
 *	DB Connection extension class
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave,
    Wave\DB\Driver\DriverInterface,
    Wave\Config\Row as ConfigRow;

class Connection extends \PDO {

    /** @var DriverInterface $driver_class */
	private $driver_class;

    /**
     * @param \Wave\Config\Row $config
     */
    public function __construct(ConfigRow $config){

        /** @var DriverInterface $driver_class  */
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

    /**
     * @return DriverInterface
     */
    public function getDriverClass(){
		return $this->driver_class;
	}


}

?>