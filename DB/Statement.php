<?php

class Wave_DB_Statement extends PDOStatement {


	private $connection;

	protected function __construct(Wave_DB_Connection $connection){
	
		$this->setFetchMode(PDO::FETCH_ASSOC);
		$this->connection = $connection;
	}
	

}

?>