<?php

namespace Wave\DB;

use Wave\Hook;

class Statement extends \PDOStatement {


    private $connection;

    protected function __construct(Connection $connection) {

        $this->setFetchMode(\PDO::FETCH_ASSOC);
        $this->connection = $connection;

    }

    public function execute($input_parameters = null): bool {

        $start = microtime(true);
        $result = parent::execute($input_parameters);

        $query_data = [
            'query' => $this->queryString,
            'row_count' => $this->rowCount(),
            'success' => $this->errorCode() === \PDO::ERR_NONE,
            'time' => microtime(true) - $start,
            'params' => $input_parameters
        ];

        Hook::triggerAction('db.after_query', [$query_data]);

        return $result;
    }

}

