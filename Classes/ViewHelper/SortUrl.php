<?php
namespace ApacheSolrForTypo3\Solr\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Creates a solr sorting URL by expanding a ###SOLR_URL:sortOption### marker.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class SortUrl implements ViewHelper
{

    /**
     * Holds the solr configuration
     *
     * @var \ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * An instance of a Solr Search
     *
     * @var Search
     */
    protected $search;

    /**
     * Query Link Builder
     *
     * @var \ApacheSolrForTypo3\Solr\Query\LinkBuilder
     */
    protected $queryLinkBuilder = null;


    /**
     * Constructor
     *
     * @param array $arguments
     */
    public function __construct(array $arguments = array())
    {
        $this->search = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search');

        $this->configuration = Util::getSolrConfiguration();
        $this->queryLinkBuilder = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query\\LinkBuilder',
            $this->search->getQuery());
    }

    /**
     * Returns an URL that switches sorting to the given sort option
     *
     * @param array $arguments
     * @return string
     */
    public function execute(array $arguments = array())
    {
        $sortUrl = '';
        $urlParameters = GeneralUtility::_GP('tx_solr');
        $urlSortParameters = GeneralUtility::trimExplode(',',
            $urlParameters['sort']);
        $sortOptions = GeneralUtility::trimExplode(',', $arguments[0]);
        $currentSortOption = '';

        $sortHelper = GeneralUtility::makeInstance(
            'ApacheSolrForTypo3\\Solr\\Sorting',
            $this->configuration->getValueByPathOrDefaultValue('plugin.tx_solr.search.sorting.options.', array())
        );
        $configuredSortOptions = $sortHelper->getSortOptions();

        $sortParameters = array();
        foreach ($sortOptions as $sortOption) {
            if (isset($configuredSortOptions[$sortOption])) {
                $sortDirection = $this->configuration->getValueByPathOrDefaultValue('plugin.tx_solr.search.sorting.defaultOrder', 'asc');
                if (isset($configuredSortOptions[$sortOption]['fixedOrder'])) {
                    $sortDirection = $configuredSortOptions[$sortOption]['fixedOrder'];
                } elseif (isset($configuredSortOptions[$sortOption]['defaultOrder'])) {
                    $sortDirection = $configuredSortOptions[$sortOption]['defaultOrder'];
                }
                $sortParameter = $sortOption . ' ' . $sortDirection;

                foreach ($urlSortParameters as $urlSortParameter) {
                    $explodedUrlSortParameter = explode(' ', $urlSortParameter);

                    if ($explodedUrlSortParameter[0] == $sortOption) {
                        list($currentSortOption, $currentSortDirection) = $explodedUrlSortParameter;
                        break;
                    }
                }

                if ($currentSortOption == $sortOption) {
                    switch ($currentSortDirection) {
                        case 'asc':
                            $sortDirection = 'desc';
                            break;
                        case 'desc':
                            $sortDirection = 'asc';
                            break;
                    }

                    $fixedOrder = $this->configuration->getValueByPath('plugin.tx_solr.search.sorting.options.' . $sortOption . '.fixedOrder');
                    if (!is_null($fixedOrder)) {
                        $sortDirection = $fixedOrder;
                    }

                    $sortParameter = $sortOption . ' ' . $sortDirection;
                }

                $sortParameters[] = $sortParameter;
            }
        }

        $sortUrl = $this->queryLinkBuilder->getQueryUrl(array(
            'sort' => implode(', ', $sortParameters)
        ));

        return $sortUrl;
    }
}
