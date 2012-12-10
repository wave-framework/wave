<?php

namespace Wave\DB;

class Statement extends \PDOStatement {


	private $connection;

	protected function __construct(Connection $connection){
	
		$this->setFetchMode(\PDO::FETCH_ASSOC);
		$this->connection = $connection;
	}
	

}

?>