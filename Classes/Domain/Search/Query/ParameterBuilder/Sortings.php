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
class Sortings extends AbstractDeactivatable
{
    /**
     * @var array
     */
    protected $sortings = [];

    /**
     * Sortings constructor.
     * @param bool $isEnabled
     * @param array $sortings
     */
    public function __construct($isEnabled = false, $sortings = [])
    {
        $this->isEnabled = $isEnabled;
        $this->setSortings($sortings);
    }

    /**
     * @return Sortings
     */
    public static function getEmpty()
    {
        return new Sortings(false);
    }

    /**
     * @return Sorting[]
     */
    public function getSortings(): array
    {
        return $this->sortings;
    }

    /**
     * @param array $sortings
     */
    public function setSortings(array $sortings)
    {
        $this->sortings = $sortings;
    }

    /**
     * @param Sorting $sorting
     */
    public function addSorting(Sorting $sorting)
    {
        $this->sortings[] = $sorting;
    }

    /**
     * Parses a sortings representation "<fieldName> <direction>,<fieldName> <direction>"
     * @param string $sortingsString
     * @return Sortings
     */
    public static function fromString($sortingsString)
    {
        $sortFields = GeneralUtility::trimExplode(',',$sortingsString);
        $sortings = [];
        foreach($sortFields as $sortField) {
            $sorting = Sorting::fromString($sortField);
            $sortings[] = $sorting;
        }

        return new Sortings(true, $sortings);
    }
}
