<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * Class Sorting
 */
class Sorting
{
    const DIRECTION_DESC = 'desc';

    const DIRECTION_ASC = 'asc';

    /**
     * @var array
     */
    protected static $validDirections = [self::DIRECTION_DESC, self::DIRECTION_ASC];

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $field = '';

    /**
     * @var string
     */
    protected $direction = self::DIRECTION_ASC;

    /**
     * @var string
     */
    protected $label = '';

    /**
     * @var bool
     */
    protected $selected = false;

    /**
     * @var bool
     */
    protected $isResetOption = false;

    /**
     * @param SearchResultSet $resultSet
     * @param string $name
     * @param string $field
     * @param string $direction
     * @param string $label
     * @param boolean $selected
     * @param boolean $isResetOption
     * @throws \InvalidArgumentException
     */
    public function __construct(SearchResultSet $resultSet, $name, $field, $direction, $label, $selected, $isResetOption)
    {
        if (!self::getIsValidDirection($direction)) {
            throw new \InvalidArgumentException("Invalid sorting direction");
        }
        $this->name = $name;
        $this->direction = $direction;
        $this->field = $field;
        $this->label = $label;
        $this->selected = $selected;
        $this->isResetOption = $isResetOption;
    }

    /**
     * @return string
     */
    public function getDirection()
    {
        return $this->direction;
    }

    /**
     * @return bool
     */
    public function getIsAscDirection()
    {
        return $this->direction === self::DIRECTION_ASC;
    }

    /**
     * @return bool
     */
    public function getIsDescDirection()
    {
        return $this->direction === self::DIRECTION_DESC;
    }

    /**
     * Returns the opposite direction of the current assigned direction.
     *
     * @return string
     */
    public function getOppositeDirection()
    {
        return self::getOppositeDirectionFromDirection($this->direction);
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return boolean
     */
    public function getSelected()
    {
        return $this->selected;
    }

    /**
     * @param string $direction
     * @return bool
     */
    public static function getIsValidDirection($direction)
    {
        return in_array($direction, self::$validDirections);
    }

    /**
     * @param string $direction
     * @return string
     */
    public static function getOppositeDirectionFromDirection($direction)
    {
        if ($direction === self::DIRECTION_ASC) {
            return self::DIRECTION_DESC;
        } else {
            return self::DIRECTION_ASC;
        }
    }

    /**
     * @return boolean
     */
    public function getIsResetOption()
    {
        return $this->isResetOption;
    }
}
