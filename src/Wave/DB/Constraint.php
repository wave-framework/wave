<?php

/**
 *    Class for representing a column of the database.  Used for Model generation.
 *
 * @author Michael michael@calcin.ai
 **/

namespace Wave\DB;

use Wave;


class Constraint
{

    const TYPE_UNKNOWN = 11;
    const TYPE_PRIMARY = 12;
    const TYPE_UNIQUE = 13;
    const TYPE_FOREIGN = 14;

    /** @var Column[] $columns */
    private $columns;
    /** @var string $type */
    private $type;
    /** @var string $name */
    private $name;

    public function __construct(Column $column, $type, $name)
    {
        $this->columns = array($column);
        $this->type = $type;
        $this->name = $name;
    }

    /**
     * @param Column $column
     */
    public function addColumn(Column $column)
    {
        if (!in_array($column, $this->columns))
            $this->columns[] = $column;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return Column[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Provide a representation of this constraint that can be used to calculate a
     * fingerprint for whether it has changed or not.
     */
    public function __serialize()
    {
        return [
            'name' => $this->getName(),
            'type' => $this->getType(),
            'columns' => array_map(fn($column) => $column->getName(), $this->getColumns())
        ];
    }

}