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

namespace ApacheSolrForTypo3\Solr\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestAware;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsWriterProcessor;
use ApacheSolrForTypo3\Solr\Query\Modifier\Statistics;

/**
 * Statistics search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class StatisticsComponent extends AbstractComponent implements SearchRequestAware
{
    /**
     * @var SearchRequest|null
     */
    protected ?SearchRequest $searchRequest = null;

    /**
     * Provides a component that is aware of the current SearchRequest
     *
     * @param SearchRequest $searchRequest
     */
    public function setSearchRequest(SearchRequest $searchRequest)
    {
        $this->searchRequest = $searchRequest;
    }

    /**
     * Initializes the search component.
     */
    public function initializeSearchComponent()
    {
        $solrConfiguration = $this->searchRequest->getContextTypoScriptConfiguration();

        if ($solrConfiguration->getStatistics()) {
            if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['statistics'])) {
                $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['statistics'] = StatisticsWriterProcessor::class;
            }
            // Only if addDebugData is enabled add Query modifier
            if ($solrConfiguration->getStatisticsAddDebugData()) {
                $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery']['statistics'] = Statistics::class;
            }
        }
    }
}
