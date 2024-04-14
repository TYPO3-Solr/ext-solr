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

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchHasBeenExecutedEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes the last searches
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class LastSearchesComponent
{
    /**
     * Processes and returns {@link SearchResultSet} for last searches
     */
    public function __invoke(AfterSearchHasBeenExecutedEvent $event): void
    {
        $resultSet = $event->getSearchResultSet();
        if ($resultSet->getAllResultCount() === 0) {
            // when the search does not produce a result we do not store the last searches
            return;
        }

        if (!isset($GLOBALS['TSFE'])) {
            return;
        }

        $query = $resultSet->getUsedSearchRequest()->getRawUserQuery();

        if (!empty($query) && $event->getTypoScriptConfiguration()->getSearchLastSearchesMode() !== 'disabled') {
            $lastSearchesService = $this->getLastSearchesService($resultSet);
            $lastSearchesService->addToLastSearches($query);
        }

        $event->setSearchResultSet($resultSet);
    }

    protected function getLastSearchesService(SearchResultSet $resultSet): LastSearchesService
    {
        return GeneralUtility::makeInstance(
            LastSearchesService::class,
            $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()
        );
    }
}
