<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use InvalidArgumentException;

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
    protected static array $validDirections = [self::DIRECTION_DESC, self::DIRECTION_ASC];

    /**
     * @var string
     */
    protected string $name = '';

    /**
     * @var string
     */
    protected string $field = '';

    /**
     * @var string
     */
    protected string $direction = self::DIRECTION_ASC;

    /**
     * @var string
     */
    protected string $label = '';

    /**
     * @var bool
     */
    protected bool $selected = false;

    /**
     * @var bool
     */
    protected bool $isResetOption = false;

    /**
     * @param SearchResultSet $resultSet
     * @param string $name
     * @param string $field
     * @param string $direction
     * @param string $label
     * @param bool $selected
     * @param bool $isResetOption
     * @throws InvalidArgumentException
     */
    public function __construct(
        SearchResultSet $resultSet,
        string $name,
        string $field,
        string $direction,
        string $label,
        bool $selected = false,
        bool $isResetOption = false
    ) {
        if (!self::getIsValidDirection($direction)) {
            throw new InvalidArgumentException('Invalid sorting direction');
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
    public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * @return bool
     */
    public function getIsAscDirection(): bool
    {
        return $this->direction === self::DIRECTION_ASC;
    }

    /**
     * @return bool
     */
    public function getIsDescDirection(): bool
    {
        return $this->direction === self::DIRECTION_DESC;
    }

    /**
     * Returns the opposite direction of the current assigned direction.
     *
     * @return string
     */
    public function getOppositeDirection(): string
    {
        return self::getOppositeDirectionFromDirection($this->direction);
    }

    /**
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function getSelected(): bool
    {
        return $this->selected;
    }

    /**
     * @param string $direction
     * @return bool
     */
    public static function getIsValidDirection(string $direction): bool
    {
        return in_array($direction, self::$validDirections);
    }

    /**
     * @param string $direction
     * @return string
     */
    public static function getOppositeDirectionFromDirection(string $direction): string
    {
        if ($direction === self::DIRECTION_ASC) {
            return self::DIRECTION_DESC;
        }
        return self::DIRECTION_ASC;
    }

    /**
     * @return bool
     */
    public function getIsResetOption(): bool
    {
        return $this->isResetOption;
    }
}
