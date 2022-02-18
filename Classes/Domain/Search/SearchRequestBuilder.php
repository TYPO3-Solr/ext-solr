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

namespace ApacheSolrForTypo3\Solr\Domain\Search;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Session\FrontendUserSession;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The SearchRequestBuilder is responsible to build a valid SearchRequest.
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchRequestBuilder
{

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration;

    /**
     * @var FrontendUserSession
     */
    protected $session = null;

    /**
     * SearchRequestBuilder constructor.
     * @param TypoScriptConfiguration $typoScriptConfiguration
     * @param FrontendUserSession $frontendUserSession
     */
    public function __construct(TypoScriptConfiguration $typoScriptConfiguration, FrontendUserSession $frontendUserSession = null)
    {
        $this->typoScriptConfiguration = $typoScriptConfiguration;
        $this->session = $frontendUserSession ?? GeneralUtility::makeInstance(FrontendUserSession::class);
    }

    /**
     * @param array $controllerArguments
     * @param int $pageId
     * @param int $languageId
     * @return SearchRequest
     */
    public function buildForSearch(array $controllerArguments, $pageId, $languageId)
    {
        $controllerArguments = $this->adjustPageArgumentToPositiveInteger($controllerArguments);

        /** @var $searchRequest SearchRequest */
        $argumentsNamespace = $this->typoScriptConfiguration->getSearchPluginNamespace();
        $searchRequest = $this->getRequest([$argumentsNamespace => $controllerArguments], $pageId, $languageId);
        $searchRequest = $this->applyPassedResultsPerPage($searchRequest);

        return $searchRequest;
    }

    /**
     * Checks if the passed resultsPerPageValue is valid and applies it. If the perPage value was changed it is stored in
     * the session and the current page is set to 0, since the pagination should start from the beginning then.
     *
     * @param SearchRequest $searchRequest
     * @return SearchRequest
     */
    protected function applyPassedResultsPerPage(SearchRequest $searchRequest): SearchRequest
    {
        $requestedPerPage = $searchRequest->getResultsPerPage();

        $perPageSwitchOptions = $this->typoScriptConfiguration->getSearchResultsPerPageSwitchOptionsAsArray();
        if (isset($requestedPerPage) && in_array($requestedPerPage, $perPageSwitchOptions)) {
            $this->session->setPerPage($requestedPerPage);
            $searchRequest->setPage(0);
        }

        $defaultResultsPerPage = $this->typoScriptConfiguration->getSearchResultsPerPage();
        $currentNumberOfResultsShown = $defaultResultsPerPage;
        if ($this->session->getHasPerPage()) {
            $sessionResultPerPage = $this->session->getPerPage();
            if (in_array($sessionResultPerPage, $perPageSwitchOptions)) {
                $currentNumberOfResultsShown = (int)$sessionResultPerPage;
            }
        }

        if ($this->shouldHideResultsFromInitialSearch($searchRequest)) {
            // initialize search with an empty query, which would by default return all documents
            // anyway, tell Solr to not return any result documents
            // Solr will still return facets though
            $currentNumberOfResultsShown = 0;
        }

        $searchRequest->setResultsPerPage($currentNumberOfResultsShown);

        return $searchRequest;
    }

    /**
     * Checks it the results should be hidden in the response.
     *
     * @param SearchRequest $searchRequest
     * @return bool
     */
    protected function shouldHideResultsFromInitialSearch(SearchRequest $searchRequest)
    {
        return ($this->typoScriptConfiguration->getSearchInitializeWithEmptyQuery() ||
            $this->typoScriptConfiguration->getSearchInitializeWithQuery()) &&
            !$this->typoScriptConfiguration->getSearchShowResultsOfInitialEmptyQuery() &&
            !$this->typoScriptConfiguration->getSearchShowResultsOfInitialQuery() &&
            $searchRequest->getRawUserQueryIsNull();
    }

    /**
     * @param int $pageId
     * @param int $languageId
     * @return SearchRequest
     */
    public function buildForFrequentSearches($pageId, $languageId)
    {
        /** @var $searchRequest SearchRequest */
        $searchRequest = $this->getRequest([], $pageId, $languageId);
        return $searchRequest;
    }

    /**
     * @param array $controllerArguments
     * @param string $rawUserQuery
     * @param int $pageId
     * @param int $languageId
     * @return SearchRequest
     */
    public function buildForSuggest(array $controllerArguments, $rawUserQuery, $pageId, $languageId)
    {
        $controllerArguments['page'] = 0;
        $controllerArguments['q'] = $rawUserQuery;
        $argumentsNamespace = $this->typoScriptConfiguration->getSearchPluginNamespace();

        return $this->getRequest(['q' => $rawUserQuery, $argumentsNamespace => $controllerArguments], $pageId, $languageId);
    }

    /**
     * Creates an instance of the SearchRequest.
     *
     * @param array $requestArguments
     * @param int $pageId
     * @param int $languageId
     * @return SearchRequest
     */
    protected function getRequest(array $requestArguments = [], int $pageId = 0, int $languageId = 0): SearchRequest
    {
        return GeneralUtility::makeInstance(
            SearchRequest::class,
            /** @scrutinizer ignore-type */ $requestArguments,
            /** @scrutinizer ignore-type */ $pageId,
            /** @scrutinizer ignore-type */ $languageId,
            /** @scrutinizer ignore-type */ $this->typoScriptConfiguration
        );
    }

    /**
     * This methods sets the page argument to an expected positive integer value in the arguments array.
     *
     * @param array $arguments
     * @return array
     */
    protected function adjustPageArgumentToPositiveInteger(array $arguments): array
    {
        $page = isset($arguments['page']) ? intval($arguments['page']) : 0;
        $arguments['page'] = max($page, 0);

        return $arguments;
    }
}
