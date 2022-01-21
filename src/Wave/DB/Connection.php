<?php

/**
 *    DB Connection extension class
 *
 * @author Michael michael@calcin.ai
 **/

namespace Wave\DB;

use PDO;
use Wave\Config\Row as ConfigRow;
use Wave\DB;
use Wave\DB\Driver\DriverInterface;

class Connection extends PDO {

    /** @var DriverInterface $driver_class */
    private $driver_class;

    private $cache_enabled;
    private $namespace;
    private $statement_cache = array();

    /**
     * @param \Wave\Config\Row $config
     */
    public function __construct(ConfigRow $config, $namespace) {

        /** @var DriverInterface $driver_class */
        $driver_class = DB::getDriverClass($config->driver);
        $this->driver_class = $driver_class;
        $this->namespace = $namespace;

        $options = array();
        if(isset($config->driver_options)) {
            // driver_options can be in two formats, either a key => value array (native)
            // or an array of [ key, value ], used to preserve types (i.e. integers as keys)
            if(isset($config->driver_options[0])) {
                foreach($config->driver_options as $option) {
                    list($key, $value) = $option;
                    $options[$key] = $value;
                }
            } else
                $options = $config->driver_options->getArrayCopy();
        }

        parent::__construct($driver_class::constructDSN($config), $config->username, $config->password, $options);

        $this->cache_enabled = isset($config->enable_cache) && $config->enable_cache;

        //Override the default PDOStatement
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('\\Wave\\DB\\Statement', array($this)));

    }


    public function prepare($sql, $options = array()) {

        if(!$this->cache_enabled)
            return parent::prepare($sql, $options);

        $hash = md5($sql);

        //double-check that sql is same if it is cached
        if(!isset($this->statement_cache[$hash]) || $this->statement_cache[$hash]->queryString !== $sql) {
            $this->statement_cache[$hash] = parent::prepare($sql, $options);
        }

        return $this->statement_cache[$hash];
    }


    /**
     * @return DriverInterface
     */
    public function getDriverClass() {
        return $this->driver_class;
    }

    /**
     * Return the namespace this connection
     * was established within
     *
     * @return mixed
     */
    public function getNamespace() {
        return $this->namespace;
    }


}

?>