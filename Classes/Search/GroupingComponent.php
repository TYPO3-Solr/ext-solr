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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping as GroupingParameter;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;

/**
 * GroupingComponent
 */
class GroupingComponent
{
    public function __construct(protected readonly QueryBuilder $queryBuilder)
    {
    }

    /**
     * Triggers grouping if activated
     */
    public function __invoke(AfterSearchQueryHasBeenPreparedEvent $event): void
    {
        $searchConfiguration = $event->getTypoScriptConfiguration()->getSearchConfiguration();
        if ((int)($searchConfiguration['grouping'] ?? 0) !== 1) {
            return;
        }

        $query = $this->modifyQuery($event->getSearchRequest(), $event->getQuery());
        $event->setQuery($query);
    }

    /**
     * Modifies the given query and adds the parameters necessary
     * for result grouping.
     */
    protected function modifyQuery(SearchRequest $searchRequest, Query $query): Query
    {
        $configuration = $searchRequest->getContextTypoScriptConfiguration();
        $isGroupingEnabled = $configuration->getIsSearchGroupingEnabled();

        if ($configuration->getIsGroupingGetParameterSwitchEnabled()
            && ($searchRequest->getArguments()['grouping'] ?? 'on') === 'off'
        ) {
            $isGroupingEnabled = false;
        }

        if (!$isGroupingEnabled) {
            $query->removeComponent($query->getGrouping());
            return $query;
        }

        $grouping = new GroupingParameter(true);

        $groupingConfiguration = $searchRequest->getContextTypoScriptConfiguration()->getObjectByPathOrDefault('plugin.tx_solr.search.grouping.');

        // since apache solr does not support to set the offset per group we calculate the results perGroup value here to
        // cover the last document
        $highestGroupPage = $searchRequest->getHighestGroupPage();
        $highestLimit = $searchRequest->getContextTypoScriptConfiguration()->getSearchGroupingHighestGroupResultsLimit();
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
