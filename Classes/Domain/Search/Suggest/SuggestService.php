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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\SuggestQuery;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ParsingUtil;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class SuggestService
 *
 * @author Frans Saris <frans.saris@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
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
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * SuggestService constructor.
     * @param TypoScriptFrontendController $tsfe
     * @param SearchResultSetService $searchResultSetService
     * @param QueryBuilder|null $queryBuilder
     */
    public function __construct(TypoScriptFrontendController $tsfe, SearchResultSetService $searchResultSetService, TypoScriptConfiguration $typoScriptConfiguration, QueryBuilder $queryBuilder = null)
    {
        $this->tsfe = $tsfe;
        $this->searchService = $searchResultSetService;
        $this->typoScriptConfiguration = $typoScriptConfiguration;
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class, /** @scrutinizer ignore-type */ $typoScriptConfiguration);
    }

    /**
     * Build an array structure of the suggestions.
     *
     * @param SearchRequest $searchRequest
     * @param array $additionalFilters
     * @return array
     */
    public function getSuggestions(SearchRequest $searchRequest, array $additionalFilters = []) : array
    {
        $requestId = (int)$this->tsfe->getRequestedId();
        $groupList = Util::getFrontendUserGroupsList();

        $suggestQuery = $this->queryBuilder->buildSuggestQuery($searchRequest->getRawUserQuery(), $additionalFilters, $requestId, $groupList);
        $solrSuggestions = $this->getSolrSuggestions($suggestQuery);

        if ($solrSuggestions === []) {
            return ['status' => false];
        }

        $maxSuggestions = $this->typoScriptConfiguration->getSuggestNumberOfSuggestions();
        $showTopResults = $this->typoScriptConfiguration->getSuggestShowTopResults();
        $suggestions    = $this->getSuggestionArray($suggestQuery, $solrSuggestions, $maxSuggestions);

        if (!$showTopResults) {
            return $this->getResultArray($searchRequest, $suggestions, [], false);
        }

        return $this->addTopResultsToSuggestions($searchRequest, $suggestions, $additionalFilters);
    }

    /**
     * Determines the top results and adds them to the suggestions.
     *
     * @param SearchRequest $searchRequest
     * @param array $suggestions
     * @param array $additionalFilters
     * @return array
     */
    protected function addTopResultsToSuggestions(SearchRequest $searchRequest, $suggestions, array $additionalFilters) : array
    {
        $maxDocuments = $this->typoScriptConfiguration->getSuggestNumberOfTopResults();

        // perform the current search.
        $searchRequest->setResultsPerPage($maxDocuments);
        $searchRequest->setAdditionalFilters($additionalFilters);

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
        $bestSuggestionRequest->setAdditionalFilters($additionalFilters);

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
        $languageId = Util::getLanguageUid();
        $solr = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($pageId, $languageId);
        $search = GeneralUtility::makeInstance(Search::class, /** @scrutinizer ignore-type */ $solr);
        $response = $search->search($suggestQuery, 0, 0);

        $rawResponse = $response->getRawResponse();
        $results = json_decode($rawResponse);
        $suggestConfig = $this->typoScriptConfiguration->getObjectByPath('plugin.tx_solr.suggest.');
        $facetSuggestions = $results->facet_counts->facet_fields->{$suggestConfig['suggestField']};
        $facetSuggestions = ParsingUtil::getMapArrayFromFlatArray($facetSuggestions);

        return $facetSuggestions ?? [];
    }

    /**
     * Extracts the suggestions from solr as array.
     *
     * @param SuggestQuery $suggestQuery
     * @param array $solrSuggestions
     * @param integer $maxSuggestions
     * @return array
     */
    protected function getSuggestionArray(SuggestQuery $suggestQuery, $solrSuggestions, $maxSuggestions) : array
    {
        $queryString = $suggestQuery->getQuery();
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
     * @param SearchResultCollection $documentsToAdd
     * @param integer $maxDocuments
     * @return array
     */
    protected function addDocumentsWhenLimitNotReached(array $documents, SearchResultCollection $documentsToAdd, int $maxDocuments)  : array
    {
        $additionalTopResultsFields = $this->typoScriptConfiguration->getSuggestAdditionalTopResultsFields();
        /** @var SearchResult $document */
        foreach ($documentsToAdd as $document) {
            $documents[] = $this->getDocumentAsArray($document, $additionalTopResultsFields);
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
     * @param array $additionalTopResultsFields
     * @return array
     */
    protected function getDocumentAsArray(SearchResult $document, $additionalTopResultsFields = []) : array
    {
        $fields = [
            'link' => $document->getUrl(),
            'type' => $document['type_stringS'] ? $document['type_stringS'] : $document->getType(),
            'title' => $document->getTitle(),
            'content' => $document->getContent(),
            'group' => $document->getHasGroupItem() ? $document->getGroupItem()->getGroupValue() : '',
            'previewImage' => $document['image'] ? $document['image'] : '',
        ];
        foreach ($additionalTopResultsFields as $additionalTopResultsField) {
            $fields[$additionalTopResultsField] = $document[$additionalTopResultsField] ? $document[$additionalTopResultsField] : '';
        }
        return $fields;
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


}
