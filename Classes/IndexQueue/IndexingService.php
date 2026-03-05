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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Access\RootlineElement;
use ApacheSolrForTypo3\Solr\Access\RootlineElementFormatException;
use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\Exception\InvalidConnectionException;
use ApacheSolrForTypo3\Solr\Exception\RuntimeException;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Http\Application as FrontendApplication;

/**
 * Unified indexing service that processes both pages and records via TYPO3 core sub-requests.
 *
 * Replaces the old split between PageIndexer (HTTP round-trips) and Indexer (direct DB + FrontendAwareEnvironment).
 * All indexing now goes through Application::handle() with IndexingInstructions as a request attribute.
 */
class IndexingService
{
    public function __construct(
        private readonly FrontendApplication $frontendApplication,
        private readonly ConnectionManager $connectionManager,
        private readonly PagesRepository $pagesRepository,
        private readonly SolrLogManager $logger,
        private readonly IndexingResultCollector $resultCollector,
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * Unified entry point: index one or more items sharing the same page context.
     *
     * For pages: expects a single item. Detects access groups via findUserGroups sub-request,
     * then indexes the page for each group.
     *
     * For records: items share the same item_pid (page context). A single sub-request sets up
     * the frontend context, then indexes all records in that context.
     *
     * @param Item[] $items Items to index (same item_pid for records, single item for pages)
     * @return bool TRUE if all items were indexed successfully
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws InvalidConnectionException
     * @throws RootlineElementFormatException
     * @throws SiteNotFoundException
     */
    public function indexItems(array $items): bool
    {
        if ($items === []) {
            return true;
        }

        $firstItem = $items[0];
        $isPageType = $firstItem->getType() === 'pages';

        if ($isPageType) {
            return $this->indexPageItem($firstItem);
        }

        return $this->indexRecordItems($items);
    }

    /**
     * Index a single page item through sub-requests.
     *
     *  1. Determine applicable languages
     *  2. For each language: detect access groups via findUserGroups sub-request
     *  3. For each language+group combination: index the page via indexPage sub-request
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws InvalidConnectionException
     * @throws RootlineElementFormatException
     * @throws SiteNotFoundException
     */
    protected function indexPageItem(Item $item): bool
    {
        if (!$this->isPageEnabled($item->getRecord())) {
            return false;
        }

        $solrConnections = $this->getPageSolrConnections($item);
        if ($solrConnections === []) {
            return false;
        }

        $success = true;
        foreach ($solrConnections as $languageUid => $solrConnection) {
            $accessGroups = $this->findUserGroupsForPage($item, $languageUid);
            foreach ($accessGroups as $userGroup) {
                $accessRootline = $this->buildAccessRootline($item, $languageUid, $userGroup);
                if (!$this->executePageIndexingSubRequest($item, $languageUid, $userGroup, $accessRootline)) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Index multiple record items sharing the same page context via a single sub-request.
     *
     * @param Item[] $items
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws InvalidConnectionException
     * @throws SiteNotFoundException
     */
    protected function indexRecordItems(array $items): bool
    {
        $firstItem = $items[0];
        $site = $firstItem->getSite();
        if ($site === null) {
            return false;
        }

        $solrConnections = $this->getRecordSolrConnections($firstItem);
        if ($solrConnections === []) {
            return false;
        }

        $success = true;
        foreach ($solrConnections as $languageUid => $solrConnection) {
            if (!$this->executeRecordIndexingSubRequest($items, $languageUid)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Sends a findUserGroups sub-request for a page to discover access-restricted content.
     *
     * @return int[] Array of user group IDs found on the page's content
     */
    protected function findUserGroupsForPage(Item $item, int $language): array
    {
        $instructions = new IndexingInstructions(
            items: [$item],
            action: IndexingInstructions::ACTION_FIND_USER_GROUPS,
            language: $language,
            parameters: $this->buildPageParameters($item),
        );

        $response = $this->executeSubRequest($item, $language, $instructions);
        if ($response === null) {
            return [0];
        }

        $responseData = json_decode((string)$response->getBody(), true);
        $groups = $responseData['userGroups'] ?? [0];

        return is_array($groups) ? $groups : [0];
    }

    /**
     * Execute a page indexing sub-request for a specific language and user group.
     */
    protected function executePageIndexingSubRequest(
        Item $item,
        int $language,
        int $userGroup,
        string $accessRootline,
    ): bool {
        $instructions = new IndexingInstructions(
            items: [$item],
            action: IndexingInstructions::ACTION_INDEX_PAGE,
            language: $language,
            userGroup: $userGroup,
            accessRootline: $accessRootline,
            parameters: $this->buildPageParameters($item),
        );

        $response = $this->executeSubRequest($item, $language, $instructions);
        if ($response === null) {
            return false;
        }

        $responseData = json_decode((string)$response->getBody(), true);
        return !empty($responseData['success']);
    }

    /**
     * Execute a record indexing sub-request for all items sharing a page context.
     *
     * @param Item[] $items
     */
    protected function executeRecordIndexingSubRequest(array $items, int $language): bool
    {
        $firstItem = $items[0];

        $instructions = new IndexingInstructions(
            items: $items,
            action: IndexingInstructions::ACTION_INDEX_RECORDS,
            language: $language,
        );

        $response = $this->executeSubRequest($firstItem, $language, $instructions);
        if ($response === null) {
            return false;
        }

        $responseData = json_decode((string)$response->getBody(), true);
        return !empty($responseData['success']);
    }

    /**
     * Build and execute a TYPO3 frontend sub-request with indexing instructions.
     */
    protected function executeSubRequest(
        Item $item,
        int $language,
        IndexingInstructions $instructions,
    ): ?ResponseInterface {
        try {
            $this->resultCollector->reset();

            $request = $this->buildServerRequest($item, $language);
            $request = $request->withAttribute('solr.indexingInstructions', $instructions);

            $response = $this->frontendApplication->handle($request);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->error(
                    'Sub-request returned error status',
                    [
                        'statusCode' => $statusCode,
                        'item' => $item->getIndexQueueUid(),
                        'action' => $instructions->getAction(),
                    ],
                );
                return null;
            }

            return $response;
        } catch (Throwable $e) {
            $this->logger->error(
                'Sub-request failed: ' . $e->getMessage(),
                [
                    'item' => $item->getIndexQueueUid(),
                    'action' => $instructions->getAction(),
                    'exception' => $e->__toString(),
                ],
            );
            return null;
        }
    }

    /**
     * Build a ServerRequest suitable for TYPO3 frontend sub-request processing.
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws InvalidRouteArgumentsException
     * @throws RuntimeException
     * @throws SiteNotFoundException
     */
    protected function buildServerRequest(Item $item, int $language): ServerRequestInterface
    {
        $pageUid = $this->resolvePageUid($item);
        $site = $this->siteFinder->getSiteByPageId($pageUid);
        $siteLanguage = $site->getLanguageById($language);

        $uri = $site->getRouter()->generateUri($pageUid, $language > 0 ? ['_language' => $language] : []);

        if ($uri->getHost() === '') {
            throw new RuntimeException(
                'The site router for "' . $site->getIdentifier() . '" generated a URI without host: '
                . $uri . '. Configure a fully qualified base URL with scheme and host'
                . ' (e.g. https://example.com/) in the site configuration.',
                1741200001,
            );
        }

        $httpHost = $uri->getHost() . ($uri->getPort() ? ':' . $uri->getPort() : '');
        $serverParams = [
            'HTTP_HOST' => $httpHost,
            'SERVER_NAME' => $uri->getHost(),
            'SERVER_PORT' => $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80),
            'REQUEST_URI' => $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : ''),
            'SCRIPT_NAME' => '/index.php',
            'HTTPS' => $uri->getScheme() === 'https' ? 'on' : 'off',
        ];

        $request = new ServerRequest(
            (string)$uri,
            'GET',
            'php://input',
            ['Host' => [$httpHost]],
            $serverParams,
        );

        return $request
            ->withUri($uri)
            ->withAttribute('site', $site)
            ->withAttribute('language', $siteLanguage)
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($serverParams));
    }

    /**
     * Resolve the page UID to use for the sub-request URL generation.
     *
     * For pages: uses the page's own uid (or mount destination).
     * For records: uses the root page uid because records often live in
     * storage folders (doktype=254) that cannot be rendered by the frontend.
     * The sub-request only needs a routable page to set up TypoScript context.
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     */
    protected function resolvePageUid(Item $item): int
    {
        if ($item->getType() === 'pages') {
            if ($item->hasIndexingProperty('isMountedPage')) {
                return (int)$item->getIndexingProperty('mountPageDestination');
            }
            return $item->getRecordUid();
        }

        // For records: always use root page for the sub-request URL.
        // Records frequently live in storage folders (sysfolder, doktype=254)
        // which are not routable by the TYPO3 frontend. The root page provides
        // a valid URL and the correct TypoScript context for field mapping.
        return $item->getRootPageUid();
    }

    /**
     * Build page-specific parameters for indexing instructions.
     */
    protected function buildPageParameters(Item $item): array
    {
        $params = [];

        $feGroupColumn = $GLOBALS['TCA']['pages']['ctrl']['enablecolumns']['fe_group'] ?? '';
        if (!empty($feGroupColumn)) {
            $pageRecord = $item->getRecord();
            if (!empty($pageRecord[$feGroupColumn])) {
                $params['pageUserGroup'] = (int)$pageRecord[$feGroupColumn];
            }
        }

        return $params;
    }

    /**
     * Build the access rootline string for a page.
     *
     * @throws DBALException
     * @throws RootlineElementFormatException
     * @throws InvalidArgumentException
     */
    protected function buildAccessRootline(Item $item, int $language, int $contentAccessGroup): string
    {
        $mountPointParameter = '';
        if ($item->hasIndexingProperty('isMountedPage')) {
            $mountPointParameter = $item->getIndexingProperty('mountPageSource') . '-' . $item->getIndexingProperty('mountPageDestination');
        }

        $accessRootline = Rootline::getAccessRootlineByPageId($item->getRecordUid(), $mountPointParameter);

        $element = GeneralUtility::makeInstance(RootlineElement::class, 'c:' . $contentAccessGroup);
        $accessRootline->push($element);

        return (string)$accessRootline;
    }

    /**
     * Check if a page record is enabled for indexing.
     */
    protected function isPageEnabled(?array $record): bool
    {
        if ($record === null) {
            return false;
        }
        if (isset($GLOBALS['TCA']['pages']['ctrl']['enablecolumns']['disabled'])
            && $record[$GLOBALS['TCA']['pages']['ctrl']['enablecolumns']['disabled']]
        ) {
            return false;
        }
        return true;
    }

    /**
     * Get Solr connections for a page item, filtered by page translation visibility.
     *
     * @return SolrConnection[]
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws InvalidConnectionException
     * @throws SiteNotFoundException
     */
    protected function getPageSolrConnections(Item $item): array
    {
        $solrConnections = $this->getRecordSolrConnections($item);

        $solrConnections = $this->filterConnectionsByPageVisibility(
            $solrConnections,
            $item->getRecord(),
        );

        if ($item->hasIndexingProperty('isMountedPage')) {
            $mountPageId = $item->getIndexingProperty('mountPageDestination');
            $mountPage = BackendUtility::getRecord('pages', $mountPageId);
            if ($mountPage === null) {
                return [];
            }
            $solrConnections = $this->filterConnectionsByPageVisibility($solrConnections, $mountPage, true);
        }

        return $solrConnections;
    }

    /**
     * Get Solr connections for a record item based on available languages.
     *
     * @return SolrConnection[]
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws SiteNotFoundException
     * @throws InvalidConnectionException
     */
    protected function getRecordSolrConnections(Item $item): array
    {
        $site = $item->getSite();
        if ($site === null) {
            return [];
        }

        $rootPageId = $item->getRootPageUid();
        $solrConfigurationsBySite = $site->getAllSolrConnectionConfigurations();
        $siteLanguages = [];
        foreach ($solrConfigurationsBySite as $solrConfiguration) {
            $siteLanguages[] = $solrConfiguration['language'];
        }

        $defaultLanguageUid = 0;
        $rootPageRecord = $site->getRootPageRecord();
        $l18nCfg = (int)($rootPageRecord['l18n_cfg'] ?? 0);
        if (($l18nCfg & 1) == 1) {
            if (count($siteLanguages) == 1 && $siteLanguages[min(array_keys($siteLanguages))] > 0) {
                $defaultLanguageUid = (int)$siteLanguages[min(array_keys($siteLanguages))];
            } elseif (count($siteLanguages) > 1) {
                unset($siteLanguages[array_search('0', $siteLanguages)]);
                $defaultLanguageUid = (int)$siteLanguages[min(array_keys($siteLanguages))];
            }
        }

        $solrConnections = [];
        if ($defaultLanguageUid == 0) {
            try {
                $solrConnections[0] = $this->connectionManager->getConnectionByRootPageId($rootPageId, 0);
            } catch (NoSolrConnectionFoundException) {
                // ignore
            }
        }

        $pageId = $item->getType() === 'pages' ? $item->getRecordUid() : $item->getRecordPageId();
        $translationOverlays = $this->pagesRepository->findTranslationOverlaysByPageId((int)$pageId);
        foreach ($translationOverlays as $overlay) {
            $overlayLanguageId = (int)$overlay['sys_language_uid'];
            if (in_array($overlayLanguageId, $siteLanguages)) {
                try {
                    $solrConnections[$overlayLanguageId] = $this->connectionManager->getConnectionByRootPageId($rootPageId, $overlayLanguageId);
                } catch (NoSolrConnectionFoundException) {
                    // ignore
                }
            }
        }

        // Add fallback languages
        foreach ($siteLanguages as $siteLanguageId) {
            $siteLanguageId = (int)$siteLanguageId;
            if ($siteLanguageId !== 0 && !isset($solrConnections[$siteLanguageId])) {
                $typo3site = $site->getTypo3SiteObject();
                try {
                    $siteLanguageObj = $typo3site->getLanguageById($siteLanguageId);
                    $fallbackChain = LanguageAspectFactory::createFromSiteLanguage($siteLanguageObj)->getFallbackChain();
                    foreach ($fallbackChain as $fallbackLanguageId) {
                        if ($fallbackLanguageId === 0 || isset($solrConnections[$fallbackLanguageId])) {
                            try {
                                $solrConnections[$siteLanguageId] = $this->connectionManager->getConnectionByRootPageId($rootPageId, $siteLanguageId);
                            } catch (NoSolrConnectionFoundException) {
                                // ignore
                            }
                            break;
                        }
                    }
                } catch (Throwable) {
                    // ignore
                }
            }
        }

        return $solrConnections;
    }

    /**
     * Filter Solr connections based on page translation visibility settings (l18n_cfg).
     *
     * @param SolrConnection[] $solrConnections
     * @return SolrConnection[]
     *
     * @throws DBALException
     */
    protected function filterConnectionsByPageVisibility(
        array $solrConnections,
        array $page,
        bool $forceHideTranslationIfNoTranslatedRecordExists = false,
    ): array {
        return $this->pagesRepository->filterSolrConnectionsByPageVisibility(
            $solrConnections,
            $page,
            $forceHideTranslationIfNoTranslatedRecordExists,
        );
    }
}
