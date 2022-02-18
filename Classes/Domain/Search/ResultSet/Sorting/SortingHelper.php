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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting;

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
