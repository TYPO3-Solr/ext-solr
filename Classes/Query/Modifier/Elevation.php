<?php
namespace ApacheSolrForTypo3\Solr\Query\Modifier;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestAware;
use ApacheSolrForTypo3\Solr\Plugin\Search\Search;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Util;

/**
 * Enables query elevation
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Elevation implements Modifier, SearchRequestAware
{

    /**
     * @var SearchRequest
     */
    protected $searchRequest;

    /**
     * @param SearchRequest $searchRequest
     */
    public function setSearchRequest(SearchRequest $searchRequest) {
        $this->searchRequest = $searchRequest;
    }

    /**
     * Enables the query's elevation mode.
     *
     * @param Query $query The query to modify
     * @return Query The modified query with enabled elevation mode
     */
    public function modifyQuery(Query $query)
    {
        $configuration = $this->searchRequest->getContextTypoScriptConfiguration();
        $query->setQueryElevation(
            $configuration->getSearchElevation(),
            $configuration->getSearchElevationForceElevation(),
            $configuration->getSearchElevationMarkElevatedResults()
        );

        return $query;
    }
}
