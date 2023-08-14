<?php

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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Sorting ParameterProvider is responsible to build the solr query parameters
 * that are needed for the sorting.
 */
class Sorting extends AbstractDeactivatable
{
    public const SORT_ASC = 'ASC';

    public const SORT_DESC = 'DESC';

    protected string $fieldName = '';

    protected string $direction = self::SORT_ASC;

    /**
     * Debug constructor.
     */
    public function __construct($isEnabled = false, $fieldName = '', $direction = self::SORT_ASC)
    {
        $this->isEnabled = $isEnabled;
        $this->setFieldName($fieldName);
        $this->setDirection($direction);
    }

    public static function getEmpty(): Sorting
    {
        return new Sorting(false);
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): void
    {
        $this->fieldName = $fieldName;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): void
    {
        $this->direction = $direction;
    }

    /**
     * Parses a sorting representation "<fieldName> <direction>"
     */
    public static function fromString(string $sortingString): Sorting
    {
        $parts = GeneralUtility::trimExplode(' ', $sortingString);
        return new Sorting(true, $parts[0], $parts[1]);
    }
}
