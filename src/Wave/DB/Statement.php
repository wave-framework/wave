<?php

namespace Wave\DB;

class Statement extends \PDOStatement
{


    private $connection;

    protected function __construct(Connection $connection)
    {

        $this->setFetchMode(\PDO::FETCH_ASSOC);
        $this->connection = $connection;

    }

    public function execute($input_parameters = null): bool
    {

        $start = microtime(true);
        $result = parent::execute($input_parameters);

        \Wave\Debug::getInstance()->addQuery($time = microtime(true) - $start, $this);

        //printf("%s: %s: %s\n", microtime(true), $time, implode(' ', explode("\n", $this->queryString)));

        return $result;
    }

}

