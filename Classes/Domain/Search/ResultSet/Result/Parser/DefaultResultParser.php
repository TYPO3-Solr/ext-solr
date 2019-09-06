<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The DefaultResultParser is able to parse normal(ungroupd results)
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser
 */
class DefaultResultParser extends AbstractResultParser {

    /**
     * @param SearchResultSet $resultSet
     * @param bool $useRawDocuments
     * @return SearchResultSet
     */
    public function parse(SearchResultSet $resultSet, bool $useRawDocuments = true)
    {
        $searchResults = GeneralUtility::makeInstance(SearchResultCollection::class);
        $parsedData = $resultSet->getResponse()->getParsedData();

        // @extensionScannerIgnoreLine
        $resultSet->setMaximumScore($parsedData->response->maxScore ?? 0.0);
        // @extensionScannerIgnoreLine
        $resultSet->setAllResultCount($parsedData->response->numFound ?? 0);

        // @extensionScannerIgnoreLine
        if (!is_array($parsedData->response->docs)) {
            return $resultSet;
        }

        // @extensionScannerIgnoreLine
        $documents = $parsedData->response->docs;
        if (!$useRawDocuments) {
            $documents = $this->documentEscapeService->applyHtmlSpecialCharsOnAllFields($documents);
        }

        foreach ($documents as $searchResult) {
            $searchResultObject = $this->searchResultBuilder->fromApacheSolrDocument($searchResult);
            $searchResults[] = $searchResultObject;
        }

        $resultSet->setSearchResults($searchResults);
        return $resultSet;
    }

    /**
     * @param SearchResultSet $resultSet
     * @return bool
     */
    public function canParse(SearchResultSet $resultSet)
    {
        // This parsers should not be used when grouping is enabled
        $configuration = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration();
        if ($configuration instanceof TypoScriptConfiguration && $configuration->getSearchGrouping())
        {
            return false;
        }

        return true;
    }
}
