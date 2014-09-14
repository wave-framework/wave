<?php

/**
 *	Class for representing a column of the database.  Used for Model generation.
 *
 *	@author Michael michael@calcin.ai
**/

namespace Wave\DB;
use Wave;


class Column {

	const TYPE_UNKNOWN 		= 0;
	const TYPE_INT 			= 1;
	const TYPE_STRING 		= 2;
	const TYPE_BOOL			= 3;
	const TYPE_TIMESTAMP	= 4;
	const TYPE_FLOAT 		= 5;
	const TYPE_DATE			= 6;

    /** @var Table $table */
    private $table;
    /** @var string $name */
	private $name;
    /** @var bool $nullable */
	private $nullable;
    /** @var int $data_type */
	private $data_type;
    /** @var mixed $default */
	private $default;
    /** @var boolean $is_serial */
    private $is_serial;
    /** @var string $type_desc */
	private $type_desc;
    /** @var string $extra */
	private $extra;
    /** @var string $comment */
	private $comment;
    /** @var  array $metadata */
    private $metadata = array();
									
	public function __construct(Table $table, $name, $nullable, $data_type, $default_value, $is_serial = false, $type_desc = '', $extra = '', $comment = ''){
		
		$this->table = $table;
		$this->name = $name;
		$this->nullable = $nullable;
		$this->data_type = $data_type;
		$this->default = $default_value;
		$this->is_serial = $is_serial;
		$this->type_desc = $type_desc;
		$this->extra = $extra;
		$this->comment = $comment;
	
        $this->parseMetadata($comment);
	}

    /**
     * @return Table
     */
    public function getTable(){
		return $this->table;
	}

    /**
     * @param bool $escape
     *
     * @return string
     */
    public function getName($escape = false){
		return $escape ? $this->table->getDatabase()->escape($this->name) : $this->name;
	}

    /**
     * @return bool
     */
    public function isNullable(){
		return $this->nullable;
	}

    /**
     * @return boolean
     */
    public function isSerial() {
        return $this->is_serial;
    }

    /**
     * @return int
     */
    public function getDataType(){
		return $this->data_type;
	}

    /**
     * Return the datatype for this column as a PHP compatible type
     * @return string
     */
    public function getDataTypeAsPHPType(){
        switch ($this->data_type) {
            case self::TYPE_INT:
                return 'int';
            case self::TYPE_STRING:
                return 'string';
            case self::TYPE_BOOL:
                return 'bool';
            case self::TYPE_DATE:
            case self::TYPE_TIMESTAMP:
                return '\\DateTime';
            case self::TYPE_FLOAT:
                return 'float';
            default:
                return 'mixed';
        }
    }

    /**
     * @return mixed
     */
    public function getDefault(){
		return $this->default;
	}

    /**
     * @return string
     */
    public function getTypeDescription(){
		return $this->type_desc;
	}

    /**
     * @return string
     */
    public function getExtra(){
		return $this->extra;
	}

    /**
     * @return string
     */
    public function getComment(){
		return $this->comment;
	}

    /**
     * @param $key
     * @return null
     */
    public function getMetadata($key = null){
        if($key !== null){
            if(array_key_exists($key, $this->metadata))
                return $this->metadata[$key];
            else
                return null;
        }
        return $this->metadata;
    }

    /**
     * @return bool
     */
    public function isPrimaryKey(){
		$pk = $this->table->getPrimaryKey();
		
		return $pk === null ? false : in_array($this, $pk->getColumns());
	}

    private function parseMetadata($raw){

        $parsed = json_decode($raw, true);
        if(json_last_error() === JSON_ERROR_NONE && is_array($parsed)){
            $this->metadata = $parsed;
        }

    }
	
}