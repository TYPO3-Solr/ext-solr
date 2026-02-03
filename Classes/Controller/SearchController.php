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

namespace ApacheSolrForTypo3\Solr\Controller;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\InvalidFacetPackageException;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Event\Search\AfterFrequentlySearchHasBeenExecutedEvent;
use ApacheSolrForTypo3\Solr\Event\Search\BeforeSearchFormIsShownEvent;
use ApacheSolrForTypo3\Solr\Event\Search\BeforeSearchResultIsShownEvent;
use ApacheSolrForTypo3\Solr\Mvc\Variable\SolrVariableProvider;
use ApacheSolrForTypo3\Solr\Pagination\ResultsPagination;
use ApacheSolrForTypo3\Solr\Pagination\ResultsPaginator;
use ApacheSolrForTypo3\Solr\System\Solr\SolrUnavailableException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3Fluid\Fluid\View\AbstractTemplateView;

/**
 * Class SearchController
 *
 * @property AbstractTemplateView $view {@link AbstractTemplateView} is used in this scope. Line required by PhpStan.
 */
class SearchController extends AbstractBaseController
{
    /**
     * Provide search query in extbase arguments.
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();
        $this->mapGlobalQueryStringWhenEnabled();
    }

    protected function mapGlobalQueryStringWhenEnabled(): void
    {
        $query = $this->request->getQueryParams()['q'] ?? null;

        $useGlobalQueryString = $query !== null && !$this->typoScriptConfiguration->getSearchIgnoreGlobalQParameter();
        if ($useGlobalQueryString) {
            $this->request = $this->request->withArgument('q', $query);
        }
    }

    public function initializeView(FluidViewAdapter $view): void
    {
        $variableProvider = GeneralUtility::makeInstance(SolrVariableProvider::class);
        $variableProvider->setSource($view->getRenderingContext()->getVariableProvider()->getSource());
        $view->getRenderingContext()->setVariableProvider($variableProvider);
        $view->getRenderingContext()->getVariableProvider()->add(
            'typoScriptConfiguration',
            $this->typoScriptConfiguration,
        );

        $customTemplate = $this->getCustomTemplateFromConfiguration();
        if ($customTemplate === '') {
            return;
        }

        if (str_contains($customTemplate, 'EXT:')) {
            $view->getRenderingContext()
                ->getTemplatePaths()
                ->setTemplatePathAndFilename($customTemplate);
        } else {
            $view->getRenderingContext()
                ->setControllerAction($customTemplate);
        }
    }

    protected function getCustomTemplateFromConfiguration(): string
    {
        $templateKey = str_replace('Action', '', $this->actionMethodName);
        return $this->typoScriptConfiguration->getViewTemplateByFileKey($templateKey);
    }

    /**
     * Results
     *
     * @throws InvalidFacetPackageException
     *
     * @noinspection PhpUnused Is used by plugin.
     */
    public function resultsAction(): ResponseInterface
    {
        if ($this->searchService === null) {
            return $this->handleSolrUnavailable();
        }

        try {
            $arguments = $this->request->getArguments();

            $pageId = $this->request->getAttribute('routing')->getPageId();
            $languageId = $this->request->getAttribute('language')->getLanguageId();
            $searchRequest = $this->getSearchRequestBuilder()->buildForSearch($arguments, $pageId, $languageId);

            $searchResultSet = $this->searchService->search($searchRequest);

            // we pass the search result set to the controller context, to have the possibility
            // to access it without passing it from partial to partial
            $this->view->getRenderingContext()->getVariableProvider()->add('searchResultSet', $searchResultSet);

            $currentPage = $this->request->hasArgument('page') ? (int)$this->request->getArgument('page') : 1;

            // prevent currentPage < 1 (i.e for GET request like &tx_solr[page]=0)
            if ($currentPage < 1) {
                $currentPage = 1;
            }

            $itemsPerPage = ($searchResultSet->getUsedResultsPerPage() ?: $this->typoScriptConfiguration->getSearchResultsPerPage());
            $paginator = GeneralUtility::makeInstance(ResultsPaginator::class, $searchResultSet, $currentPage, $itemsPerPage);
            $pagination = GeneralUtility::makeInstance(ResultsPagination::class, $paginator);
            $pagination->setMaxPageNumbers($this->typoScriptConfiguration->getMaxPaginatorLinks());

            /** @var BeforeSearchResultIsShownEvent $afterSearchEvent */
            $afterSearchEvent = $this->eventDispatcher->dispatch(
                new BeforeSearchResultIsShownEvent(
                    $searchResultSet,
                    $this->getAdditionalFilters(),
                    $this->typoScriptConfiguration->getSearchPluginNamespace(),
                    $arguments,
                    $pagination,
                    $currentPage,
                ),
            );

            $values = [
                'additionalFilters' => $afterSearchEvent->getAdditionalFilters(),
                'resultSet' => $afterSearchEvent->getResultSet(),
                'pluginNamespace' => $afterSearchEvent->getPluginNamespace(),
                'arguments' => $afterSearchEvent->getArguments(),
                'pagination' => $afterSearchEvent->getPagination(),
                'currentPage' => $afterSearchEvent->getCurrentPage(),
                'additionalVariables' => $afterSearchEvent->getAdditionalVariables(),
                'contentObjectData' => $this->request->getAttribute('currentContentObject')?->data,
            ];

            $this->view->assignMultiple($values);
        } catch (SolrUnavailableException) {
            return $this->handleSolrUnavailable();
        }
        return $this->htmlResponse();
    }

    /**
     * Form
     *
     * @noinspection PhpUnused Is used by plugin.
     */
    public function formAction(): ResponseInterface
    {
        if ($this->searchService === null) {
            return $this->handleSolrUnavailable();
        }

        /** @var BeforeSearchFormIsShownEvent $formEvent */
        $formEvent = $this->eventDispatcher->dispatch(
            new BeforeSearchFormIsShownEvent(
                $this->searchService->getSearch(),
                $this->getAdditionalFilters(),
                $this->typoScriptConfiguration->getSearchPluginNamespace(),
            ),
        );
        $values = [
            'search' => $formEvent->getSearch(),
            'additionalFilters' => $formEvent->getAdditionalFilters(),
            'pluginNamespace' => $formEvent->getPluginNamespace(),
            'additionalVariables' => $formEvent->getAdditionalVariables(),
            'contentObjectData' => $this->request->getAttribute('currentContentObject')?->data,
        ];

        $this->view->assignMultiple($values);
        return $this->htmlResponse();
    }

    /**
     * Frequently Searched
     *
     * @noinspection PhpUnused Is used by plugin.
     */
    public function frequentlySearchedAction(): ResponseInterface
    {
        /** @var SearchResultSet $searchResultSet */
        $searchResultSet = GeneralUtility::makeInstance(SearchResultSet::class);

        $pageId = $this->request->getAttribute('routing')->getPageId();
        $languageId = $this->request->getAttribute('language')->getLanguageId();
        $searchRequest = $this->getSearchRequestBuilder()->buildForFrequentSearches($pageId, $languageId);
        $searchResultSet->setUsedSearchRequest($searchRequest);

        $this->view->getRenderingContext()->getVariableProvider()->add('searchResultSet', $searchResultSet);

        /** @var AfterFrequentlySearchHasBeenExecutedEvent $afterFrequentlySearchedEvent*/
        $afterFrequentlySearchedEvent = $this->eventDispatcher->dispatch(
            new AfterFrequentlySearchHasBeenExecutedEvent(
                $searchResultSet,
                $this->getAdditionalFilters(),
            ),
        );
        $values = [
            'additionalFilters' => $afterFrequentlySearchedEvent->getAdditionalFilters(),
            'resultSet' => $afterFrequentlySearchedEvent->getResultSet(),
            'contentObjectData' => $this->request->getAttribute('currentContentObject')?->data,
        ];
        $this->view->assignMultiple($values);
        return $this->htmlResponse();
    }

    /**
     * This action allows to render a detailView with data from solr.
     *
     * @noinspection PhpUnused Is used by plugin.
     */
    public function detailAction(string $documentId = ''): ResponseInterface
    {
        if ($this->searchService === null) {
            return $this->handleSolrUnavailable();
        }

        try {
            $document = $this->searchService->getDocumentById($documentId);
            $values = [
                'document' => $document,
                'contentObjectData' => $this->request->getAttribute('currentContentObject')?->data,
            ];
            $this->view->assignMultiple($values);
        } catch (SolrUnavailableException) {
            return $this->handleSolrUnavailable();
        }
        return $this->htmlResponse();
    }

    /**
     * Rendered when no search is available.
     *
     * @noinspection PhpUnused Is used by {@link self::handleSolrUnavailable()}
     */
    public function solrNotAvailableAction(): ResponseInterface
    {
        return $this->htmlResponse()
            ->withStatus(503, self::STATUS_503_MESSAGE);
    }

    /**
     * Called when the solr server is unavailable.
     */
    protected function handleSolrUnavailable(): ResponseInterface
    {
        parent::logSolrUnavailable();
        return new ForwardResponse('solrNotAvailable');
    }

    /**
     * This method can be overwritten to add additionalFilters for the auto-suggest.
     * By default, suggest controller will apply the configured filters from the typoscript configuration.
     */
    protected function getAdditionalFilters(): array
    {
        return [];
    }
}
