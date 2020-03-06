<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Sorting ParameterProvider is responsible to build the solr query parameters
 * that are needed for the sorting.
 */
class Sorting extends AbstractDeactivatable
{
    const SORT_ASC = 'ASC';
    const SORT_DESC = 'DESC';

    /**
     * @var string
     */
    protected $fieldName = '';

    /**
     * @var string
     */
    protected $direction = self::SORT_ASC;

    /**
     * Debug constructor.
     *
     * @param bool $isEnabled
     * @param string $fieldName
     * @param string $direction
     */
    public function __construct($isEnabled = false, $fieldName = '', $direction = self::SORT_ASC)
    {
        $this->isEnabled = $isEnabled;
        $this->setFieldName($fieldName);
        $this->setDirection($direction);
    }

    /**
     * @return Sorting
     */
    public static function getEmpty()
    {
        return new Sorting(false);
    }

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     */
    public function setFieldName(string $fieldName)
    {
        $this->fieldName = $fieldName;
    }

    /**
     * @return string
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * @param string $direction
     */
    public function setDirection(string $direction)
    {
        $this->direction = $direction;
    }

    /**
     * Parses a sorting representation "<fieldName> <direction>"
     * @param string $sortingString
     * @return Sorting
     */
    public static function fromString($sortingString)
    {
        $parts = GeneralUtility::trimExplode(' ', $sortingString);
        return new Sorting(true, $parts[0], $parts[1]);
    }
}
