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
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;

/**
 * Class Sorting
 */
class Sorting
{
    public const DIRECTION_DESC = 'desc';

    public const DIRECTION_ASC = 'asc';

    protected static array $validDirections = [self::DIRECTION_DESC, self::DIRECTION_ASC];

    protected string $name = '';

    protected string $field = '';

    protected string $direction = self::DIRECTION_ASC;

    protected string $label = '';

    protected bool $selected = false;

    protected bool $isResetOption = false;

    public function __construct(
        public readonly SearchResultSet $resultSet,
        string $name,
        string $field,
        string $direction,
        string $label,
        bool $selected = false,
        bool $isResetOption = false,
    ) {
        if (!self::getIsValidDirection($direction)) {
            throw new InvalidArgumentException(
                'Invalid sorting direction',
                8919514853,
            );
        }
        $this->name = $name;
        $this->direction = $direction;
        $this->field = $field;
        $this->label = $label;
        $this->selected = $selected;
        $this->isResetOption = $isResetOption;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getIsAscDirection(): bool
    {
        return $this->direction === self::DIRECTION_ASC;
    }

    public function getIsDescDirection(): bool
    {
        return $this->direction === self::DIRECTION_DESC;
    }

    /**
     * Returns the opposite direction of the current assigned direction.
     */
    public function getOppositeDirection(): string
    {
        return self::getOppositeDirectionFromDirection($this->direction);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSelected(): bool
    {
        return $this->selected;
    }

    public static function getIsValidDirection(string $direction): bool
    {
        return in_array($direction, self::$validDirections);
    }

    public static function getOppositeDirectionFromDirection(string $direction): string
    {
        if ($direction === self::DIRECTION_ASC) {
            return self::DIRECTION_DESC;
        }
        return self::DIRECTION_ASC;
    }

    public function getIsResetOption(): bool
    {
        return $this->isResetOption;
    }
}
