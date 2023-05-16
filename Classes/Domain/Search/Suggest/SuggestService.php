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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Suggest;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\SuggestQuery;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\InvalidFacetPackageException;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ParsingUtil;
use ApacheSolrForTypo3\Solr\Util;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class SuggestService
 *
 * @author Frans Saris <frans.saris@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SuggestService
{
    protected TypoScriptFrontendController $tsfe;

    protected SearchResultSetService $searchService;

    protected TypoScriptConfiguration $typoScriptConfiguration;

    protected QueryBuilder $queryBuilder;

    public function __construct(
        TypoScriptFrontendController $tsfe,
        SearchResultSetService $searchResultSetService,
        TypoScriptConfiguration $typoScriptConfiguration,
        QueryBuilder $queryBuilder = null
    ) {
        $this->tsfe = $tsfe;
        $this->searchService = $searchResultSetService;
        $this->typoScriptConfiguration = $typoScriptConfiguration;
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(
            QueryBuilder::class,
            /** @scrutinizer ignore-type */
            $typoScriptConfiguration
        );
    }

    /**
     * Build an array structure of the suggestions.
     *
     * @throws AspectNotFoundException
     * @throws InvalidFacetPackageException
     * @throws NoSolrConnectionFoundException
     * @throws DBALException
     */
    public function getSuggestions(SearchRequest $searchRequest, array $additionalFilters = []): array
    {
        $requestId = $this->tsfe->getRequestedId();
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
     * @throws InvalidFacetPackageException
     */
    protected function addTopResultsToSuggestions(SearchRequest $searchRequest, array $suggestions, array $additionalFilters): array
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
        $bestSuggestion = (string)reset($suggestionKeys);
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
     * @throws NoSolrConnectionFoundException
     * @throws AspectNotFoundException
     * @throws DBALException
     */
    protected function getSolrSuggestions(SuggestQuery $suggestQuery): array
    {
        $pageId = $this->tsfe->getRequestedId();
        $languageId = Util::getLanguageUid();
        $solr = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($pageId, $languageId);
        $search = GeneralUtility::makeInstance(Search::class, $solr);
        $response = $search->search($suggestQuery, 0, 0);

        $rawResponse = $response->getRawResponse();
        if ($rawResponse === null) {
            return [];
        }
        $results = json_decode($rawResponse);
        $suggestConfig = $this->typoScriptConfiguration->getObjectByPath('plugin.tx_solr.suggest.');
        $facetSuggestions = isset($suggestConfig['suggestField']) ? $results->facet_counts->facet_fields->{$suggestConfig['suggestField']} ?? [] : [];
        return ParsingUtil::getMapArrayFromFlatArray($facetSuggestions);
    }

    /**
     * Extracts the suggestions from solr as array.
     */
    protected function getSuggestionArray(
        SuggestQuery $suggestQuery,
        array $solrSuggestions,
        int $maxSuggestions
    ): array {
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
     */
    protected function addDocumentsWhenLimitNotReached(
        array $documents,
        SearchResultCollection $documentsToAdd,
        int $maxDocuments,
    ): array {
        $additionalTopResultsFields = $this->typoScriptConfiguration->getSuggestAdditionalTopResultsFields();
        /* @var SearchResult $document */
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
     */
    protected function getDocumentAsArray(SearchResult $document, array $additionalTopResultsFields = []): array
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
     * @throws InvalidFacetPackageException
     */
    protected function doASearch(SearchRequest $searchRequest): SearchResultSet
    {
        return $this->searchService->search($searchRequest);
    }

    /**
     * Creates a result array with the required fields.
     */
    protected function getResultArray(
        SearchRequest $searchRequest,
        array $suggestions,
        array $documents,
        bool $didASecondSearch
    ): array {
        return [
            'suggestions' => $suggestions,
            'suggestion' => $searchRequest->getRawUserQuery(),
            'documents' => $documents,
            'didSecondSearch' => $didASecondSearch,
        ];
    }
}
