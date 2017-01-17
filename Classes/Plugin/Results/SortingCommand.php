<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

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

use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use ApacheSolrForTypo3\Solr\Plugin\PluginCommand;
use ApacheSolrForTypo3\Solr\Query\LinkBuilder;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Sorting;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * sorting view command
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SortingCommand implements PluginCommand
{

    /**
     * Search instance
     *
     * @var Search
     */
    protected $search;

    /**
     * Parent plugin
     *
     * @var Results
     */
    protected $parentPlugin;

    /**
     * Configuration
     *
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * Constructor.
     *
     * @param CommandPluginBase $parentPlugin Parent plugin object.
     */
    public function __construct(CommandPluginBase $parentPlugin)
    {
        $this->search = GeneralUtility::makeInstance(Search::class);

        $this->parentPlugin = $parentPlugin;
        $this->configuration = $parentPlugin->typoScriptConfiguration;
    }

    /**
     * @return array|null
     */
    public function execute()
    {
        $marker = [];

        if ($this->configuration->getSearchSorting() && $this->search->getNumberOfResults()) {
            $marker['loop_sort|sort'] = $this->getSortingLinks();
        }

        if (count($marker) === 0) {
            // in case we didn't fill any markers - like when there are no
            // search results - we set markers to NULL to signal that we
            // want to have the subpart removed completely
            $marker = null;
        }

        return $marker;
    }

    protected function getSortingLinks()
    {
        $sortHelper = GeneralUtility::makeInstance(Sorting::class,
            $this->configuration->getSearchSortingOptionsConfiguration());

        $query = $this->search->getQuery();

        $queryLinkBuilder = GeneralUtility::makeInstance(LinkBuilder::class, $query);
        $queryLinkBuilder->setLinkTargetPageId($this->parentPlugin->getLinkTargetPageId());

        $sortOptions = [];

        $urlParameters = GeneralUtility::_GP('tx_solr');
        $urlSortParameters = GeneralUtility::trimExplode(',',
            $urlParameters['sort']);

        $configuredSortOptions = $sortHelper->getSortOptions();

        foreach ($configuredSortOptions as $sortOptionName => $sortOption) {
            $sortDirection = $this->configuration->getSearchSortingDefaultOrderBySortOptionName($sortOptionName);
            $sortIndicator = $sortDirection;
            $currentSortOption = '';
            $currentSortDirection = '';
            foreach ($urlSortParameters as $urlSortParameter) {
                $explodedUrlSortParameter = explode(' ', $urlSortParameter);
                if ($explodedUrlSortParameter[0] == $sortOptionName) {
                    list($currentSortOption, $currentSortDirection) = $explodedUrlSortParameter;
                    break;
                }
            }

            // toggle sorting direction for the current sorting field
            if ($currentSortOption == $sortOptionName) {
                switch ($currentSortDirection) {
                    case 'asc':
                        $sortDirection = 'desc';
                        $sortIndicator = 'asc';
                        break;
                    case 'desc':
                        $sortDirection = 'asc';
                        $sortIndicator = 'desc';
                        break;
                }
            }

            $fixedOrder = $this->configuration->getSearchSortingFixedOrderBySortOptionName($sortOptionName);
            if (trim($fixedOrder) !== '') {
                $sortDirection = $fixedOrder;
            }

            $sortParameter = $sortOptionName . ' ' . $sortDirection;

            $temp = array(
                'link' => $queryLinkBuilder->getQueryLink(
                    $sortOption['label'],
                    array('sort' => $sortParameter)
                ),
                'url' => $queryLinkBuilder->getQueryUrl(
                    array('sort' => $sortParameter)
                ),
                'optionName' => $sortOptionName,
                'field' => $sortOption['field'],
                'label' => $sortOption['label'],
                'is_current' => '0',
                'direction' => $sortDirection,
                'indicator' => $sortIndicator,
                'current_direction' => ' '
            );

            // set sort indicator for the current sorting field
            if ($currentSortOption == $sortOptionName) {
                $temp['selected'] = 'selected="selected"';
                $temp['current'] = 'current';
                $temp['is_current'] = '1';
                $temp['current_direction'] = $sortIndicator;
            }

            // special case relevance: just reset the search to normal behavior
            if ($sortOptionName == 'relevance') {
                $temp['link'] = $queryLinkBuilder->getQueryLink(
                    $sortOption['label'],
                    array('sort' => null)
                );
                $temp['url'] = $queryLinkBuilder->getQueryUrl(
                    array('sort' => null)
                );
                unset($temp['direction'], $temp['indicator']);
            }

            $sortOptions[] = $temp;
        }

        return $sortOptions;
    }
}
