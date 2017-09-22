<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\Suggest;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Franz Saris <frans.saris@beech.it>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\SuggestQuery;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class SuggestService
 *
 * @author Frans Saris <frans.saris@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Suggest
 */
class SuggestService {

    /**
     * @var TypoScriptFrontendController
     */
    protected $tsfe;

    /**
     * @var SearchResultSetService
     */
    protected $searchService;

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration;

    /**
     * SuggestService constructor.
     * @param TypoScriptFrontendController $tsfe
     * @param SearchResultSetService $searchResultSetService
     */
    public function __construct(TypoScriptFrontendController $tsfe, SearchResultSetService $searchResultSetService, TypoScriptConfiguration $typoScriptConfiguration)
    {
        $this->tsfe = $tsfe;
        $this->searchService = $searchResultSetService;
        $this->typoScriptConfiguration = $typoScriptConfiguration;
    }

    /**
     * Build an array structure of the suggestions.
     *
     * @param SearchRequest $searchRequest
     * @param string $additionalFilters
     * @return array
     */
    public function getSuggestions(SearchRequest $searchRequest, $additionalFilters) : array
    {
        $suggestQuery = $this->buildSuggestQuery($searchRequest->getRawUserQuery(), $additionalFilters);
        $solrSuggestions = $this->getSolrSuggestions($suggestQuery);

        if ($solrSuggestions === []) {
            return ['status' => false];
        }

        $maxSuggestions = $this->typoScriptConfiguration->getSuggestNumberOfSuggestions();
        $showTopResults = $this->typoScriptConfiguration->getSuggestShowTopResults();
        $suggestions    = $this->getSuggestionArray($suggestQuery->getKeywords(), $solrSuggestions, $maxSuggestions);

        if (!$showTopResults) {
            return $this->getResultArray($searchRequest, $suggestions, [], false);
        }

        return $this->addTopResultsToSuggestions($searchRequest, $suggestions);
    }

    /**
     * Determines the top results and adds them to the suggestions.
     *
     * @param SearchRequest $searchRequest
     * @param array $suggestions
     * @return array
     */
    protected function addTopResultsToSuggestions(SearchRequest $searchRequest, $suggestions) : array
    {
        $maxDocuments = $this->typoScriptConfiguration->getSuggestNumberOfTopResults();

        // perform the current search.
        $searchRequest->setResultsPerPage($maxDocuments);

        $didASecondSearch = false;
        $documents = [];

        $searchResultSet = $this->doASearch($searchRequest);
        $results = $searchResultSet->getSearchResults();
        if (count($results) > 0) {
            $documents = $this->addDocumentsWhenLimitNotReached($documents, $results, $maxDocuments);
        }

        $suggestionKeys = array_keys($suggestions);
        $bestSuggestion = reset($suggestionKeys);
        $bestSuggestionRequest = $searchRequest->getCopyForSubRequest();
        $bestSuggestionRequest->setRawQueryString($bestSuggestion);
        $bestSuggestionRequest->setResultsPerPage($maxDocuments);

        // No results found, use first proposed suggestion to perform the search
        if (count($documents) === 0 && !empty($suggestions) && ($searchResultSet = $this->doASearch($bestSuggestionRequest)) && count($searchResultSet->getSearchResults()) > 0) {
            $didASecondSearch = true;
            $documentsToAdd = $searchResultSet->getSearchResults();
            $documents = $this->addDocumentsWhenLimitNotReached($documents, $documentsToAdd, $maxDocuments);
        }

        return $this->getResultArray($searchRequest, $suggestions, $documents, $didASecondSearch);
    }

    /**
     * Retrieves the suggestions from the solr server.
     *
     * @param SuggestQuery $suggestQuery
     * @return array
     */
    protected function getSolrSuggestions(SuggestQuery $suggestQuery) : array
    {
        $pageId = $this->tsfe->getRequestedId();
        $languageId = $this->tsfe->sys_language_uid;
        $solr = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($pageId, $languageId);
        $search = GeneralUtility::makeInstance(Search::class, $solr);
        $response = $search->search($suggestQuery, 0, 0);

        $rawResponse = $response->getRawResponse();
        $results = json_decode($rawResponse);
        $suggestConfig = $this->typoScriptConfiguration->getObjectByPath('plugin.tx_solr.suggest.');
        $facetSuggestions = $results->facet_counts->facet_fields->{$suggestConfig['suggestField']};
        $facetSuggestions = get_object_vars($facetSuggestions);

        return is_null($facetSuggestions) ? [] : $facetSuggestions;
    }

    /**
     * Extracts the suggestions from solr as array.
     *
     * @param string $queryString
     * @param array $solrSuggestions
     * @param integer $maxSuggestions
     * @return array
     */
    protected function getSuggestionArray($queryString, $solrSuggestions, $maxSuggestions) : array
    {
        $suggestionCount = 0;
        $suggestions = [];
        foreach ($solrSuggestions as $string => $count) {
            $suggestion = trim($queryString . ' ' . $string);
            $suggestions[$suggestion] = $count;
            $suggestionCount++;
            if ($suggestionCount === $maxSuggestions) {
                return $suggestions;
            }
        }

        return $suggestions;
    }

    /**
     * Adds documents from a collection to the result collection as soon as the limit is not reached.
     *
     * @param array $documents
     * @param \Apache_Solr_Document[] $documentsToAdd
     * @param integer $maxDocuments
     * @return array
     */
    protected function addDocumentsWhenLimitNotReached(array $documents, array $documentsToAdd, int $maxDocuments)  : array
    {
        /** @var SearchResult $document */
        foreach ($documentsToAdd as $document) {
            $documents[] = $this->getDocumentAsArray($document);
            if (count($documents) >= $maxDocuments) {
                return $documents;
            }
        }

        return $documents;
    }

    /**
     * Creates an array representation of the result and returns it.
     *
     * @param SearchResult $document
     * @return array
     */
    protected function getDocumentAsArray(SearchResult $document) : array
    {
        return [
            'link' => $document->getUrl(),
            'type' => $document->getField('type_stringS') ? $document->getField('type_stringS')['value'] : $document->getType(),
            'title' => $document->getTitle(),
            'content' => $document->getContent(),
            'previewImage' => $document->getField('previewImage_stringS') ? $document->getField('previewImage_stringS')['value'] : '',
        ];
    }

    /**
     * Runs a search and returns the results.
     *
     * @param SearchRequest $searchRequest
     * @return \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet
     */
    protected function doASearch($searchRequest) : SearchResultSet
    {
        return $this->searchService->search($searchRequest);
    }

    /**
     * Creates an result array with the required fields.
     *
     * @param SearchRequest $searchRequest
     * @param array $suggestions
     * @param array $documents
     * @param boolean $didASecondSearch
     * @return array
     */
    protected function getResultArray(SearchRequest $searchRequest, $suggestions, $documents, $didASecondSearch) : array
    {
        return ['suggestions' => $suggestions, 'suggestion' => $searchRequest->getRawUserQuery(), 'documents' => $documents, 'didSecondSearch' => $didASecondSearch];
    }

    /**
     * Builds a SuggestQuery with all applied filters.
     *
     * @param string $queryString
     * @param string $additionalFilters
     * @return SuggestQuery
     */
    protected function buildSuggestQuery(string $queryString ,string $additionalFilters) : SuggestQuery
    {
        $suggestQuery = GeneralUtility::makeInstance(SuggestQuery::class, $queryString);

        $allowedSitesConfig = $this->typoScriptConfiguration->getObjectByPathOrDefault('plugin.tx_solr.search.query.', []);
        $siteService = GeneralUtility::makeInstance(SiteHashService::class);
        $allowedSites = $siteService->getAllowedSitesForPageIdAndAllowedSitesConfiguration($this->tsfe->getRequestedId(), $allowedSitesConfig['allowedSites']);
        $suggestQuery->setUserAccessGroups(explode(',', $this->tsfe->gr_list));
        $suggestQuery->setSiteHashFilter($allowedSites);
        $suggestQuery->setOmitHeader();

        if (!empty($allowedSitesConfig['filter.'])) {
            foreach ($allowedSitesConfig['filter.'] as $additionalFilter) {
                $suggestQuery->addFilter($additionalFilter);
            }
        }

        if (!empty($additionalFilters)) {
            $additionalFilters = json_decode($additionalFilters);
            foreach ($additionalFilters as $additionalFilter) {
                $suggestQuery->addFilter($additionalFilter);
            }
        }

        return $suggestQuery;
    }
}