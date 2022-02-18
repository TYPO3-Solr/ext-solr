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

namespace ApacheSolrForTypo3\Solr\Domain\Search\LastSearches;

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetProcessor;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes the last searches
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class LastSearchesWriterProcessor implements SearchResultSetProcessor
{

    /**
     * @param SearchResultSet $resultSet
     * @return SearchResultSet
     */
    public function process(SearchResultSet $resultSet) {

        if ($resultSet->getAllResultCount() === 0) {
            // when the search does not produce a result we do not store the last searches
            return $resultSet;
        }

        if (!isset($GLOBALS['TSFE'])) {
            return $resultSet;
        }

        $query = $resultSet->getUsedSearchRequest()->getRawUserQuery();

        if (is_string($query)) {
            $lastSearchesService = $this->getLastSearchesService($resultSet);
            $lastSearchesService->addToLastSearches($query);
        }

        return $resultSet;
    }

    /**
     * @param SearchResultSet $resultSet
     * @return LastSearchesService
     */
    protected function getLastSearchesService(SearchResultSet $resultSet) {
        return GeneralUtility::makeInstance(LastSearchesService::class,
            /** @scrutinizer ignore-type */ $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration());
    }
}
