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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Boosting search component
 */
class RelevanceComponent
{
    /**
     * Sets minimum match, boost function, boost query and tie-breaker.
     */
    public function __invoke(AfterSearchQueryHasBeenPreparedEvent $event): void
    {
        $typoScriptConfiguration = $event->getTypoScriptConfiguration();
        $queryBuilder = GeneralUtility::makeInstance(QueryBuilder::class, $typoScriptConfiguration);
        $query = $queryBuilder
            ->startFrom($event->getQuery())
            ->useMinimumMatchFromTypoScript()
            ->useBoostFunctionFromTypoScript()
            ->useSlopsFromTypoScript()
            ->useBoostQueriesFromTypoScript()
            ->useTieParameterFromTypoScript()
            ->getQuery();
        $event->setQuery($query);
    }
}
