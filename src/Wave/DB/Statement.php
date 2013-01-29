<?php

namespace Wave\DB;

class Statement extends \PDOStatement {


	private $connection;

	protected function __construct(Connection $connection){
	
		$this->setFetchMode(\PDO::FETCH_ASSOC);
		$this->connection = $connection;
		
	}
	
	public function execute($input_parameters = array()){
		
		$start = microtime(true);
		parent::execute($input_parameters);
		
		if(in_array(\Wave\Core::$_MODE, array(\Wave\Core::MODE_DEVELOPMENT)))
			\Wave\Debug::getInstance()->addQuery($time = microtime(true) - $start, $this);
			
	}
	
}

?>