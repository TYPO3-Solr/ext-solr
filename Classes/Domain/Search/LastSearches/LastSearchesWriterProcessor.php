<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\LastSearches;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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
            $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration());
    }
}
