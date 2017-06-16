<?php
namespace ApacheSolrForTypo3\Solr\Controller\Frontend;

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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\TemplateView;

/**
 * Class SearchController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\Controller
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

        $query = GeneralUtility::_GET('q');
        if ($query !== null) {
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
            $view->setTemplatePathAndFilename($customTemplate);
        }
    }

    /**
     * @return string
     */
    protected function getCustomTemplateFromConfiguration()
    {
        $templateKey = str_replace('Action', '', $this->actionMethodName);
        $customTemplate = $this->typoScriptConfiguration->getTemplateByFileKey($templateKey);
        return $customTemplate;
    }

    /**
     * Results
     */
    public function resultsAction()
    {
        if (!$this->searchService->getIsSolrAvailable()) {
            $this->forward('solrNotAvailable');
        }

        $searchRequest = $this->buildSearchRequest();
        $searchResultSet = $this->searchService->search($searchRequest);

        // we pass the search result set to the controller context, to have the possibility
        // to access it without passing it from partial to partial
        $this->controllerContext->setSearchResultSet($searchResultSet);

        $this->view->assignMultiple(
            [
                'hasSearched' => $this->searchService->getHasSearched(),
                'additionalFilters' => $this->searchService->getAdditionalFilters(),
                'resultSet' => $searchResultSet
            ]
        );
    }

    /**
     * @return SearchRequest
     */
    protected function buildSearchRequest()
    {
        $rawUserQuery = null;
        if ($this->request->hasArgument('q')) {
            $rawUserQuery = $this->request->getArgument('q');
        }

        $arguments = $this->request->getArguments();
        $page = isset($arguments['page']) ? $arguments['page'] - 1 : 0;
        $arguments['page'] = max($page, 0);

        /** @var $searchRequest SearchRequest */
        $searchRequest = $this->getRequest(['q' => $rawUserQuery, 'tx_solr' => $arguments]);

        return $searchRequest;
    }

    /**
     * @param array $requestArguments
     * @return SearchRequest
     */
    private function getRequest(array $requestArguments = [])
    {
        $searchRequest = GeneralUtility::makeInstance(
            SearchRequest::class,
            $requestArguments,
            $this->typoScriptFrontendController->getRequestedId(),
            $this->typoScriptFrontendController->sys_language_uid,
            $this->typoScriptConfiguration);
        return $searchRequest;
    }

    /**
     * Form
     */
    public function formAction()
    {
        $this->view->assignMultiple(
            [
                'search' => $this->searchService->getSearch(),
                'additionalFilters' => $this->searchService->getAdditionalFilters()
            ]
        );
    }

    /**
     * Frequently Searched
     */
    public function frequentlySearchedAction()
    {
        /** @var  $searchResultSet SearchResultSet */
        $searchResultSet = GeneralUtility::makeInstance(SearchResultSet::class);
        $searchResultSet->setUsedSearchRequest($this->getRequest());
        $this->controllerContext->setSearchResultSet($searchResultSet);

        $this->view->assignMultiple(
            [
                'hasSearched' => $this->searchService->getHasSearched(),
                'additionalFilters' => $this->searchService->getAdditionalFilters(),
                'resultSet' => $searchResultSet
            ]
        );
    }

    /**
     * This action allows to render a detailView with data from solr.
     *
     * @param string $documentId
     */
    public function detailAction($documentId = '')
    {
        if (!$this->searchService->getIsSolrAvailable()) {
            $this->forward('solrNotAvailable');
        }

        $document = $this->searchService->getDocumentById($documentId);
        $this->view->assign('document', $document);
    }

    /**
     * Rendered when no search is available.
     * @return string
     */
    public function solrNotAvailableAction()
    {
        if ($this->response instanceof Response) {
            $this->response->setStatus(503);
        }
    }
}
