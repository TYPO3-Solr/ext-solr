<?php
namespace ApacheSolrForTypo3\Solr\Controller;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Pagination\ResultsPagination;
use ApacheSolrForTypo3\Solr\Pagination\ResultsPaginator;
use ApacheSolrForTypo3\Solr\System\Solr\SolrUnavailableException;
use ApacheSolrForTypo3\Solr\Util;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Response;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\TemplateView;

/**
 * Class SearchController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchController extends AbstractBaseController
{
    /**
     * @var TemplateView
     */
    protected $view;

    /**
     * Provide search query in extbase arguments.
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        $this->mapGlobalQueryStringWhenEnabled();
    }

    /**
     * @return void
     */
    protected function mapGlobalQueryStringWhenEnabled()
    {
        $query = GeneralUtility::_GET('q');

        $useGlobalQueryString = $query !== null && !$this->typoScriptConfiguration->getSearchIgnoreGlobalQParameter();

        if ($useGlobalQueryString) {
            $this->request->setArgument('q', $query);
        }
    }

    /**
     * @param ViewInterface $view
     */
    public function initializeView(ViewInterface $view)
    {
        if($view instanceof TemplateView) {
            $customTemplate = $this->getCustomTemplateFromConfiguration();
            if($customTemplate === '') {
                return;
            }

            if(strpos($customTemplate, 'EXT:') !== false) {
                $view->setTemplatePathAndFilename($customTemplate);
            } else {
                $view->setTemplate($customTemplate);
            }
        }
    }

    /**
     * @return string
     */
    protected function getCustomTemplateFromConfiguration()
    {
        $templateKey = str_replace('Action', '', $this->actionMethodName);
        $customTemplate = $this->typoScriptConfiguration->getViewTemplateByFileKey($templateKey);
        return $customTemplate;
    }

    /**
     * Results
     */
    public function resultsAction(): ResponseInterface
    {
        try {
            $arguments = (array)$this->request->getArguments();
            $pageId = $this->typoScriptFrontendController->getRequestedId();
            $languageId = Util::getLanguageUid();
            $searchRequest = $this->getSearchRequestBuilder()->buildForSearch($arguments, $pageId, $languageId);

            $searchResultSet = $this->searchService->search($searchRequest);

            // we pass the search result set to the controller context, to have the possibility
            // to access it without passing it from partial to partial
            $this->controllerContext->setSearchResultSet($searchResultSet);

            $currentPage = $this->request->hasArgument('page') ? (int)$this->request->getArgument('page') : 1;
            $itemsPerPage = $searchResultSet->getUsedResultsPerPage();
            $paginator = GeneralUtility::makeInstance(ResultsPaginator::class, $searchResultSet, $currentPage, $itemsPerPage);
            $pagination = GeneralUtility::makeInstance(ResultsPagination::class, $paginator);
            $pagination->setMaxPageNumbers((int)$this->typoScriptConfiguration->getMaxPaginatorLinks(0));

            $values = [
                'additionalFilters' => $this->getAdditionalFilters(),
                'resultSet' => $searchResultSet,
                'pluginNamespace' => $this->typoScriptConfiguration->getSearchPluginNamespace(),
                'arguments' => $arguments,
                'pagination' => $pagination,
                'currentPage' => $currentPage,
            ];

            $values = $this->emitActionSignal(__CLASS__, __FUNCTION__, [$values]);

            $this->view->assignMultiple($values);
        } catch (SolrUnavailableException $e) {
            return $this->handleSolrUnavailable();
        }
        return $this->htmlResponse();
    }

    /**
     * Form
     */
    public function formAction(): ResponseInterface
    {

        $values = [
            'search' => $this->searchService->getSearch(),
            'additionalFilters' => $this->getAdditionalFilters(),
            'pluginNamespace' => $this->typoScriptConfiguration->getSearchPluginNamespace()
        ];
        $values = $this->emitActionSignal(__CLASS__, __FUNCTION__, [$values]);

        $this->view->assignMultiple($values);
        return $this->htmlResponse();
    }

    /**
     * Frequently Searched
     */
    public function frequentlySearchedAction(): ResponseInterface
    {
        /** @var  $searchResultSet SearchResultSet */
        $searchResultSet = GeneralUtility::makeInstance(SearchResultSet::class);

        $pageId = $this->typoScriptFrontendController->getRequestedId();
        $languageId = Util::getLanguageUid();
        $searchRequest = $this->getSearchRequestBuilder()->buildForFrequentSearches($pageId, $languageId);
        $searchResultSet->setUsedSearchRequest($searchRequest);

        $this->controllerContext->setSearchResultSet($searchResultSet);

        $values = [
            'additionalFilters' => $this->getAdditionalFilters(),
            'resultSet' => $searchResultSet
        ];
        $values = $this->emitActionSignal(__CLASS__, __FUNCTION__, [$values]);

        $this->view->assignMultiple($values);
        return $this->htmlResponse();
    }

    /**
     * This action allows to render a detailView with data from solr.
     *
     * @param string $documentId
     */
    public function detailAction($documentId = ''): ResponseInterface
    {
        try {
            $document = $this->searchService->getDocumentById($documentId);
            $this->view->assign('document', $document);
        } catch (SolrUnavailableException $e) {
            $this->handleSolrUnavailable();
        }
        return $this->htmlResponse();
    }

    /**
     * Rendered when no search is available.
     * @return string
     */
    public function solrNotAvailableAction(): ResponseInterface
    {
        if ($this->response instanceof Response) {
            $this->response->setStatus(503);
        }
        return $this->htmlResponse();
    }

    /**
     * Called when the solr server is unavailable.
     */
    protected function handleSolrUnavailable()
    {
        parent::handleSolrUnavailable();
        return new ForwardResponse('solrNotAvailable');
    }

    /**
     * This method can be overwritten to add additionalFilters for the autosuggest.
     * By default the suggest controller will apply the configured filters from the typoscript configuration.
     *
     * @return array
     */
    protected function getAdditionalFilters()
    {
        return [];
    }
}
