<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2018 Timo Hund <timo.hund@dkd.de>
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
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Class SortingHelper
 */
class SortingHelper {

    /**
     * @var array
     */
    protected $configuration = [];

    /**
     * Constructor
     *
     * @param array $sortingConfiguration Raw configuration from plugin.tx_solr.search.sorting.options
     */
    public function __construct(array $sortingConfiguration)
    {
        $this->configuration = $sortingConfiguration;
    }

    /**
     * Takes the tx_solr[sort] URL parameter containing the option names and
     * directions to sort by and resolves it to the actual sort fields and
     * directions as configured through TypoScript. Makes sure that only
     * configured sorting options get applied to the query.
     *
     * @param string $urlParameters tx_solr[sort] URL parameter.
     * @return string The actual index field configured to sort by for the given sort option name
     * @throws \InvalidArgumentException if the given sort option is not configured
     */
    public function getSortFieldFromUrlParameter($urlParameters)
    {
        $sortFields = [];
        $sortParameters = GeneralUtility::trimExplode(',', $urlParameters);

        $removeTsKeyDot = function($sortingKey) { return trim($sortingKey, '.'); };
        $configuredSortingName = array_map($removeTsKeyDot, array_keys($this->configuration));

        foreach ($sortParameters as $sortParameter) {
            list($sortOption, $sortDirection) = explode(' ', $sortParameter);

            if (!in_array($sortOption, $configuredSortingName)) {
                throw new \InvalidArgumentException('No sorting configuration found for option name ' . $sortOption, 1316187644);
            }

            $sortField = $this->configuration[$sortOption . '.']['field'];
            $sortFields[] = $sortField . ' ' . $sortDirection;
        }

        return implode(', ', $sortFields);
    }
}
