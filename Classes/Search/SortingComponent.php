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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Sortings;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\SortingHelper;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sorting search component
 *
 * TODO maybe merge ApacheSolrForTypo3\Solr\Sorting into ApacheSolrForTypo3\Solr\Search\SortingComponent
 */
class SortingComponent
{
    public function __construct(protected readonly QueryBuilder $queryBuilder)
    {
    }

    /**
     * Sets the sorting query parameters
     */
    public function __invoke(AfterSearchQueryHasBeenPreparedEvent $event): void
    {
        $query = $event->getQuery();
        $this->queryBuilder->startFrom($query);

        $searchConfiguration = $event->getTypoScriptConfiguration()->getSearchConfiguration();
        if (!empty($searchConfiguration['query.']['sortBy'])) {
            $this->queryBuilder->useSortings(Sortings::fromString($searchConfiguration['query.']['sortBy']));
            $query = $this->queryBuilder->getQuery();
        }

        $isSortingEnabled = !empty($searchConfiguration['sorting']) && ((int)$searchConfiguration['sorting']) === 1;
        if (!$isSortingEnabled) {
            return;
        }

        $arguments = $event->getSearchRequest()->getArguments();
        $isSortingPassedAsArgument = !empty($arguments['sort']) && preg_match('/^([a-z0-9_]+ (asc|desc)[, ]*)*([a-z0-9_]+ (asc|desc))+$/i', $arguments['sort']);
        if (!$isSortingPassedAsArgument) {
            return;
        }

        // a passed sorting has always priority an overwrites the configured initial sorting
        $query->clearSorts();
        $sortHelper = GeneralUtility::makeInstance(SortingHelper::class, $searchConfiguration['sorting.']['options.'] ?? []);
        $sortFields = $sortHelper->getSortFieldFromUrlParameter($arguments['sort']);
        $this->queryBuilder->useSortings(Sortings::fromString($sortFields));
        $query = $this->queryBuilder->getQuery();
        $event->setQuery($query);
    }

    /**
     * Checks if the arguments array has a valid sorting.
     */
    protected function hasValidSorting(array $arguments): bool
    {
        return !empty($arguments['sort']) && preg_match('/^([a-z0-9_]+ (asc|desc)[, ]*)*([a-z0-9_]+ (asc|desc))+$/i', $arguments['sort']);
    }
}
