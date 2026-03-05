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

namespace ApacheSolrForTypo3\Solr\Middleware;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Event\Indexing\AfterPageDocumentIsCreatedForIndexingEvent;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentsAreIndexedEvent;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforePageDocumentIsProcessedForIndexingEvent;
use ApacheSolrForTypo3\Solr\FieldProcessor\Service as FieldProcessorService;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingInstructions;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingResultCollector;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Util;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Unified Solr Indexing Middleware.
 *
 * Positioned after TypoScript loading, before kernel rendering.
 * Handles three actions:
 *
 * 1. indexRecords: Short-circuits. Has full TypoScript context. Iterates items, builds documents, sends to Solr.
 * 2. indexPage: Continues to kernel rendering. Captures content via event listener. Builds document, sends to Solr.
 * 3. findUserGroups: Continues to kernel rendering with access bypassed. Collects fe_groups via UserGroupDetector.
 */
readonly class SolrIndexingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private IndexingResultCollector $resultCollector,
        private ConnectionManager $connectionManager,
        private SolrLogManager $logger,
        private SiteRepository $siteRepository,
        private FrontendEnvironment $frontendEnvironment,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $instructions = $request->getAttribute('solr.indexingInstructions');
        if (!$instructions instanceof IndexingInstructions) {
            // Not an indexing request - pass through normally
            return $handler->handle($request);
        }

        // Disable frontend cache for all indexing requests
        $cacheInstruction = new CacheInstruction();
        $cacheInstruction->disableCache('Apache Solr for TYPO3 Indexing');
        $request = $request->withAttribute('frontend.cache.instruction', $cacheInstruction);

        // Route to the appropriate action handler
        return match ($instructions->getAction()) {
            IndexingInstructions::ACTION_INDEX_RECORDS => $this->handleRecordIndexing($request, $instructions),
            IndexingInstructions::ACTION_INDEX_PAGE => $this->handlePageIndexing($request, $handler, $instructions),
            IndexingInstructions::ACTION_FIND_USER_GROUPS => $this->handleFindUserGroups($request, $handler, $instructions),
            default => new JsonResponse(['error' => 'Unknown indexing action: ' . $instructions->getAction()], 400),
        };
    }

    /**
     * Handle record indexing: short-circuit (no page rendering needed).
     * Has full frontend context (site, language, TypoScript, cObject).
     */
    protected function handleRecordIndexing(
        ServerRequestInterface $request,
        IndexingInstructions $instructions,
    ): ResponseInterface {
        $items = $instructions->getItems();
        $language = $instructions->getLanguage();
        $results = [];

        try {
            foreach ($items as $item) {
                $result = $this->indexRecordItem($item, $language, $request);
                $results[$item->getIndexQueueUid()] = $result;
            }

            $allSuccess = !in_array(false, $results, true);
            return new JsonResponse([
                'success' => $allSuccess,
                'results' => $results,
            ]);
        } catch (Throwable $e) {
            $this->logger->error(
                'Record indexing failed: ' . $e->getMessage(),
                ['exception' => $e->__toString()],
            );
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle page indexing: continue to kernel rendering, capture content, build document.
     */
    protected function handlePageIndexing(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        IndexingInstructions $instructions,
    ): ResponseInterface {
        // Let TYPO3 render the page fully
        $response = $handler->handle($request);

        try {
            $item = $instructions->getFirstItem();
            if (!$item instanceof Item) {
                return new JsonResponse(['success' => false, 'error' => 'No item provided'], 400);
            }

            $pageContent = (string)$response->getBody();

            /** @var PageInformation|null $pageInformation */
            $pageInformation = $request->getAttribute('frontend.page.information');
            /** @var PageArguments|null $pageArguments */
            $pageArguments = $request->getAttribute('routing');
            /** @var SiteLanguage|null $siteLanguage */
            $siteLanguage = $request->getAttribute('language');

            if (
                !$pageInformation instanceof PageInformation
                || !$pageArguments instanceof PageArguments
                || !$siteLanguage instanceof SiteLanguage
            ) {
                return new JsonResponse(['success' => false, 'error' => 'Missing frontend context'], 500);
            }

            // Check config.index_enable
            if (!$this->isIndexingEnabled($request)) {
                return new JsonResponse(['success' => false, 'error' => 'Indexing disabled (config.index_enable)'], 200);
            }

            $solrConnection = $this->connectionManager->getConnectionByRootPageId(
                $item->getRootPageUid(),
                $siteLanguage->getLanguageId(),
            );

            $accessRootline = GeneralUtility::makeInstance(Rootline::class, $instructions->getAccessRootline());

            $pageUrl = $this->generatePageUrl($pageArguments, $pageInformation, $request, $instructions);

            $documentBuilder = GeneralUtility::makeInstance(Builder::class);
            $document = $documentBuilder->fromPage(
                $pageInformation,
                $pageArguments,
                $siteLanguage,
                $pageContent,
                $pageUrl,
                $accessRootline,
                $pageInformation->getMountPoint(),
            );

            $configuration = Util::getSolrConfiguration();
            if ($configuration->isVectorSearchEnabled()) {
                $document->setField('vectorContent', $document['content']);
            }

            // Dispatch AfterPageDocumentIsCreatedForIndexingEvent (PageFieldMappingIndexer listens to this)
            $event = new AfterPageDocumentIsCreatedForIndexingEvent(
                $document,
                $item,
                $pageInformation->getPageRecord(),
                $request,
                $configuration,
            );
            $event = $this->eventDispatcher->dispatch($event);
            $document = $event->getDocument();

            // Dispatch BeforePageDocumentIsProcessedForIndexingEvent
            $processEvent = new BeforePageDocumentIsProcessedForIndexingEvent($document, $item, $request);
            $processEvent = $this->eventDispatcher->dispatch($processEvent);
            $documents = $processEvent->getDocuments();

            // Process field processors
            $this->processDocuments($documents, $configuration);

            // Dispatch BeforeDocumentsAreIndexedEvent
            $indexEvent = new BeforeDocumentsAreIndexedEvent($document, $item, $documents, $request);
            $indexEvent = $this->eventDispatcher->dispatch($indexEvent);
            $documents = $indexEvent->getDocuments();

            // Send to Solr
            $indexed = $this->addDocumentsToSolr($documents, $solrConnection);

            return new JsonResponse([
                'success' => $indexed,
                'pageIndexed' => $indexed,
            ]);
        } catch (Throwable $e) {
            $this->logger->error(
                'Page indexing failed: ' . $e->getMessage(),
                ['exception' => $e->__toString()],
            );
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle findUserGroups: continue to kernel rendering with access bypassed.
     * The UserGroupDetector event listeners collect fe_groups during rendering.
     */
    protected function handleFindUserGroups(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        IndexingInstructions $instructions,
    ): ResponseInterface {
        // The UserGroupDetector's event listeners will detect the
        // solr.indexingInstructions attribute and activate automatically.
        // Let the page render with access bypassed.
        $response = $handler->handle($request);

        // Collect the groups from the result collector
        $groups = $this->resultCollector->getUserGroups();
        if (empty($groups)) {
            $groups = [0]; // public access
        }

        return new JsonResponse([
            'success' => true,
            'userGroups' => $groups,
        ]);
    }

    /**
     * Index a single record item with full frontend context.
     */
    protected function indexRecordItem(Item $item, int $language, ServerRequestInterface $request): bool
    {
        $itemRecord = $this->getFullItemRecord($item, $language);
        if (!is_array($itemRecord)) {
            // No translation available - this is OK, not an error
            return true;
        }

        $solrConnection = $this->connectionManager->getConnectionByRootPageId(
            $item->getRootPageUid(),
            $language,
        );

        // Build base document
        $accessRootline = $this->getRecordAccessRootline($item);
        $documentBuilder = GeneralUtility::makeInstance(Builder::class);
        $document = $documentBuilder->fromRecord(
            $itemRecord,
            $item->getType(),
            $item->getRootPageUid(),
            $accessRootline,
        );

        // Add fields from TypoScript configuration
        $indexingConfiguration = $this->getItemTypeConfiguration($item, $language);
        $abstractIndexer = $this->createFieldMappingIndexer($item->getType());
        // The field mapper needs a ServerRequest; ensure we have the right type
        $serverRequest = $request instanceof ServerRequest
            ? $request
            : new ServerRequest(
                (string)$request->getUri(),
                $request->getMethod(),
                'php://input',
                $request->getHeaders(),
            );
        $document = $abstractIndexer->addDocumentFieldsFromTyposcript(
            $document,
            $indexingConfiguration,
            $itemRecord,
            $serverRequest,
            $language,
        );

        // Dispatch BeforeDocumentIsProcessedForIndexingEvent
        $event = new BeforeDocumentIsProcessedForIndexingEvent($document, $item, $request);
        $event = $this->eventDispatcher->dispatch($event);
        $documents = $event->getDocuments();

        // Process field processors
        $solrConfiguration = $this->siteRepository->getSiteByPageId($item->getRootPageUid())->getSolrConfiguration();
        $this->processDocuments($documents, $solrConfiguration);

        // Dispatch BeforeDocumentsAreIndexedEvent
        $indexEvent = new BeforeDocumentsAreIndexedEvent($document, $item, $documents, $request);
        $indexEvent = $this->eventDispatcher->dispatch($indexEvent);
        $documents = $indexEvent->getDocuments();

        // Send to Solr
        return $this->addDocumentsToSolr($documents, $solrConnection);
    }

    /**
     * Get the full item record with language overlay applied.
     */
    protected function getFullItemRecord(Item $item, int $language): ?array
    {
        $itemRecord = $item->getRecord();
        if (!is_array($itemRecord)) {
            return null;
        }

        $languageField = $GLOBALS['TCA'][$item->getType()]['ctrl']['languageField'] ?? null;
        $l10nParentField = $GLOBALS['TCA'][$item->getType()]['ctrl']['transOrigPointerField'] ?? null;

        // Skip "free content mode" records for other languages
        if ($languageField !== null && $l10nParentField !== null) {
            $languageOfRecord = (int)($itemRecord[$languageField] ?? 0);
            $l10nParentRecordUid = (int)($itemRecord[$l10nParentField] ?? 0);

            // free content mode record
            if ($languageOfRecord > 0 && $l10nParentRecordUid === 0 && $languageOfRecord !== $language) {
                return null;
            }

            // free content mode language
            $site = $item->getSite();
            if ($site instanceof Site && $language > 0 && $language !== -1) {
                $typo3site = $site->getTypo3SiteObject();
                try {
                    $siteLanguage = $typo3site->getLanguageById($language);
                    if ($siteLanguage->getFallbackType() === 'free' && $languageOfRecord !== $language) {
                        return null;
                    }
                } catch (Throwable) {
                    // ignore
                }
            }

            // translated record in default language context within free mode
            if ($language === 0 && $languageOfRecord !== 0) {
                if ($site instanceof Site) {
                    try {
                        $siteLanguage = $site->getTypo3SiteObject()->getLanguageById($languageOfRecord);
                        if ($siteLanguage->getFallbackType() === 'free') {
                            return null;
                        }
                    } catch (Throwable) {
                        // ignore
                    }
                }
            }
        }

        // Apply language overlay
        $site = $item->getSite();
        if (!$site instanceof Site) {
            $itemRecord['__solr_index_language'] = $language;
            return $itemRecord;
        }

        $typo3site = $site->getTypo3SiteObject();
        $typo3siteLanguage = $typo3site->getLanguageById($language);

        $coreContext = clone GeneralUtility::makeInstance(Context::class);
        $coreContext->setAspect(
            'visibility',
            GeneralUtility::makeInstance(VisibilityAspect::class, false, false),
        );
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($typo3siteLanguage);
        $coreContext->setAspect('language', $languageAspect);

        $pageRepository = GeneralUtility::makeInstance(PageRepository::class, $coreContext);
        $overlayedRecord = $pageRepository->getLanguageOverlay(
            $item->getType(),
            $itemRecord,
            $languageAspect,
        );

        if ($overlayedRecord !== null) {
            $overlayedRecord['__solr_index_language'] = $language;
        }

        return $overlayedRecord;
    }

    /**
     * Get access rootline for a record item.
     */
    protected function getRecordAccessRootline(Item $item): string
    {
        $accessRestriction = '0';
        $itemRecord = $item->getRecord();

        if (isset($GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['fe_group'])) {
            $accessRestriction = $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['fe_group']] ?? '';
            if ($accessRestriction === '') {
                $accessRestriction = '0';
            }
        }

        return 'r:' . $accessRestriction;
    }

    /**
     * Get TypoScript field mapping configuration for an item.
     */
    protected function getItemTypeConfiguration(Item $item, int $language): array
    {
        $indexConfigurationName = $item->getIndexingConfigurationName();

        // Try from the record's page first
        $pageId = $item->getType() === 'pages' ? $item->getRecordUid() : $item->getRecordPageId();
        try {
            $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($pageId, $language, $item->getRootPageUid());
            $fields = $solrConfiguration->getIndexQueueFieldsConfigurationByConfigurationName($indexConfigurationName);
            if ($fields !== []) {
                return $fields;
            }
        } catch (Throwable) {
            // Fall through to root page
        }

        // Fallback to root page
        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($item->getRootPageUid(), $language);
        return $solrConfiguration->getIndexQueueFieldsConfigurationByConfigurationName($indexConfigurationName);
    }

    /**
     * Create an inline field mapping helper that exposes the addDocumentFieldsFromTyposcript method.
     */
    protected function createFieldMappingIndexer(string $type): RecordFieldMapper
    {
        return new RecordFieldMapper($type);
    }

    /**
     * Process documents through field processing instructions.
     *
     * @param Document[] $documents
     */
    protected function processDocuments(array $documents, TypoScriptConfiguration $configuration): void
    {
        $processingInstructions = $configuration->getIndexFieldProcessingInstructionsConfiguration();
        if (is_array($processingInstructions) && $processingInstructions !== []) {
            $service = GeneralUtility::makeInstance(FieldProcessorService::class);
            $service->processDocuments($documents, $processingInstructions);
        }
    }

    /**
     * Send documents to Solr.
     *
     * @param Document[] $documents
     */
    protected function addDocumentsToSolr(array $documents, SolrConnection $solrConnection): bool
    {
        if ($documents === []) {
            return true;
        }

        // chunk adds by 20
        $documentChunks = array_chunk($documents, 20);
        foreach ($documentChunks as $chunk) {
            $response = $solrConnection->getWriteService()->addDocuments($chunk);
            if ($response->getHttpStatus() !== 200) {
                $this->logger->error('Solr indexing failed.', [$response->getRawResponse()]);
                return false;
            }
        }

        return true;
    }

    /**
     * Check if indexing is enabled via TypoScript config.index_enable
     */
    protected function isIndexingEnabled(ServerRequestInterface $request): bool
    {
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        if (!$frontendTypoScript instanceof FrontendTypoScript) {
            return false;
        }
        $configArray = $frontendTypoScript->getConfigArray();
        return (bool)($configArray['index_enable'] ?? false);
    }

    /**
     * Generate page URL for the indexed document.
     */
    protected function generatePageUrl(
        PageArguments $pageArguments,
        PageInformation $pageInformation,
        ServerRequestInterface $request,
        IndexingInstructions $instructions,
    ): string {
        $overridePageUrl = $instructions->getParameter('overridePageUrl');
        if (is_string($overridePageUrl) && $overridePageUrl !== '') {
            return $overridePageUrl;
        }

        $parameter = $pageInformation->getPageRecord()['uid'];
        $type = $pageArguments->getPageType();
        if ($type && MathUtility::canBeInterpretedAsInteger($type)) {
            $parameter .= ',' . $type;
        }
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->setRequest($request);
        return $contentObjectRenderer->createUrl([
            'parameter' => $parameter,
            'linkAccessRestrictedPages' => '1',
        ]);
    }
}
