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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The DefaultResultParser is able to parse normal(ungrouped results)
 */
class DefaultResultParser extends AbstractResultParser
{
    /**
     * @inheritDoc
     */
    public function parse(SearchResultSet $resultSet, bool $useRawDocuments = true): SearchResultSet
    {
        $searchResults = GeneralUtility::makeInstance(SearchResultCollection::class);
        $parsedData = $resultSet->getResponse()->getParsedData();

        // @extensionScannerIgnoreLine
        $resultSet->setMaximumScore($parsedData->response->maxScore ?? 0.0);
        // @extensionScannerIgnoreLine
        $resultSet->setAllResultCount($parsedData->response->numFound ?? 0);

        // @extensionScannerIgnoreLine
        if (!is_array($parsedData->response->docs ?? null)) {
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
     * @inheritDoc
     */
    public function canParse(SearchResultSet $resultSet): bool
    {
        $configuration = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration();
        if ($resultSet->getUsedQuery()->getComponent('grouping') !== null
            && $configuration instanceof TypoScriptConfiguration
            && $configuration->getIsSearchGroupingEnabled()
        ) {
            return false;
        }

        return true;
    }
}
