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

namespace ApacheSolrForTypo3\Solr\Query\Modifier;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping as GroupingParameter;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestAware;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Modifies a query to add grouping parameters
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Frans Saris <frans@beech.it>
 */
class Grouping implements Modifier, SearchRequestAware
{
    /**
     * @var SearchRequest|null
     */
    protected ?SearchRequest $searchRequest = null;

    /**
     * QueryBuilder
     *
     * @var QueryBuilder
     */
    protected QueryBuilder $queryBuilder;

    /**
     * AccessComponent constructor.
     * @param QueryBuilder|null $queryBuilder
     */
    public function __construct(QueryBuilder $queryBuilder = null)
    {
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class);
    }

    /**
     * @param SearchRequest $searchRequest
     */
    public function setSearchRequest(SearchRequest $searchRequest)
    {
        $this->searchRequest = $searchRequest;
    }

    /**
     * Modifies the given query and adds the parameters necessary
     * for result grouping.
     *
     * @param Query $query The query to modify
     * @return Query The modified query with grouping parameters
     */
    public function modifyQuery(Query $query): Query
    {
        $isGroupingEnabled = $this->searchRequest->getContextTypoScriptConfiguration()->getIsSearchGroupingEnabled();
        if (!$isGroupingEnabled) {
            return $query;
        }

        $grouping = new GroupingParameter(true);

        $groupingConfiguration = $this->searchRequest->getContextTypoScriptConfiguration()->getObjectByPathOrDefault('plugin.tx_solr.search.grouping.', []);

        // since apache solr does not support to set the offset per group we calculate the results perGroup value here to
        // cover the last document
        $highestGroupPage = $this->searchRequest->getHighestGroupPage();
        $highestLimit = $this->searchRequest->getContextTypoScriptConfiguration()->getSearchGroupingHighestGroupResultsLimit();
        $resultsPerGroup = $highestGroupPage * $highestLimit;

        $grouping->setResultsPerGroup($resultsPerGroup);

        if (!empty($groupingConfiguration['numberOfGroups'])) {
            $grouping->setNumberOfGroups((int)$groupingConfiguration['numberOfGroups']);
        }

        $configuredGroups = $groupingConfiguration['groups.'];
        foreach ($configuredGroups as $groupConfiguration) {
            if (!empty($groupConfiguration['field'])) {
                $grouping->addField($groupConfiguration['field']);
            } else {
                // query group
                if (!empty($groupConfiguration['queries.'])) {
                    foreach ((array)$groupConfiguration['queries.'] as $_query) {
                        $grouping->addQuery($_query);
                    }
                }
                if (!empty($groupConfiguration['query'])) {
                    $grouping->addQuery($groupConfiguration['query']);
                }
            }

            if (isset($groupConfiguration['sortBy'])) {
                $grouping->addSorting($groupConfiguration['sortBy']);
            }
        }

        return $this->queryBuilder->startFrom($query)->useGrouping($grouping)->getQuery();
    }
}
