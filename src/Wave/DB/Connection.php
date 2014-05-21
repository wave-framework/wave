<?php

/**
 *	DB Connection extension class
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use PDO;
use Wave\DB\Driver\DriverInterface;
use Wave\Config\Row as ConfigRow;
use Wave\DB;

class Connection extends PDO {

    /** @var DriverInterface $driver_class */
	private $driver_class;

    /**
     * @param \Wave\Config\Row $config
     */
    public function __construct(ConfigRow $config){

        /** @var DriverInterface $driver_class  */
		$driver_class = DB::getDriverClass($config->driver);
		$this->driver_class = $driver_class;

        $options = array();
        if(isset($config->driver_options)){
            // driver_options can be in two formats, either a key => value array (native)
            // or an array of [ key, value ], used to preserve types (i.e. integers as keys)
            if(isset($config->driver_options[0])){
                foreach($config->driver_options as $option){
                    list($key, $value) = $option;
                    $options[$key] = $value;
                }
            }
            else
                $options = $config->driver_options->getArrayCopy();
        }

		parent::__construct($driver_class::constructDSN($config), $config->username, $config->password, $options);
		
		//Override the default PDOStatement 
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('\\Wave\\DB\\Statement', array($this)));
	
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