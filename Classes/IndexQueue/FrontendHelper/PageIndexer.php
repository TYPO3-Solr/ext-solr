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

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\Event\Indexing\AfterPageDocumentIsCreatedForIndexingEvent;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentsAreIndexedEvent;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforePageDocumentIsProcessedForIndexingEvent;
use ApacheSolrForTypo3\Solr\Exception as SolrException;
use ApacheSolrForTypo3\Solr\FieldProcessor\Service;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\DebugWriter;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Util;
use Doctrine\DBAL\Exception as DBALException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\CMS\Frontend\Page\PageInformation;
use UnexpectedValueException;

/**
 * Index Queue Page Indexer frontend helper to validate if a page
 * should be used by the Index Queue.
 *
 * Once the FrontendHelper construct is separated, this will be a standalone Indexer.
 */
class PageIndexer implements FrontendHelper, SingletonInterface
{
    public const ACTION_NAME = 'indexPage';

    protected bool $activated = false;

    /**
     * This frontend helper's executed action.
     */
    protected string $action = self::ACTION_NAME;

    /**
     * Index Queue page indexer request.
     */
    protected ?PageIndexerRequest $request = null;

    /**
     * Response data
     */
    protected array $responseData = [];

    /**
     * Solr server connection.
     */
    protected ?SolrConnection $solrConnection = null;

    /**
     * Documents that have been sent to Solr
     */
    protected array $documentsSentToSolr = [];

    protected ?TypoScriptConfiguration $configuration = null;

    protected ?SolrLogManager $logger = null;

    /**
     * Activates a frontend helper by registering for hooks and other
     * resources required by the frontend helper to work.
     *
     * @noinspection PhpUnused
     */
    public function activate(PageIndexerRequest $request): void
    {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
        $this->activated = true;
    }

    /**
     * Gets the access rootline as defined by the request.
     *
     * @return Rootline The access rootline to use for indexing.
     */
    protected function getAccessRootline(): Rootline
    {
        $stringAccessRootline = '';

        if ($this->request?->getParameter('accessRootline')) {
            $stringAccessRootline = $this->request->getParameter('accessRootline');
        }

        return GeneralUtility::makeInstance(Rootline::class, $stringAccessRootline);
    }

    //
    // Indexing
    //

    /**
     * Generates the current page's URL as string.
     * Uses the provided parameters from the request, page id and language id.
     */
    protected function generatePageUrl(PageArguments $pageArguments, PageInformation $pageInformation, ServerRequestInterface $request): string
    {
        if ($this->request?->getParameter('overridePageUrl')) {
            return $this->request->getParameter('overridePageUrl');
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

    /**
     * Handles the indexing of the page content during AfterCacheableContentIsGeneratedEvent of a generated page.
     */
    #[AsEventListener]
    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        $this->request = $event->getRequest()->getAttribute('solr.pageIndexingInstructions');

        if (!$this->request || $this->activated === false) {
            return;
        }
        $this->setupConfiguration();

        $typo3Request = $event->getRequest();
        $GLOBALS['TYPO3_REQUEST'] = $typo3Request;

        /** @var PageInformation|null $pageInformation */
        $pageInformation = $typo3Request->getAttribute('frontend.page.information');
        $pageId = $pageInformation?->getId() ?? 0;

        $logPageIndexed = $this->configuration->getLoggingIndexingPageIndexed();
        if (!$this->isIndexingEnabled($typo3Request)) {
            if ($logPageIndexed) {
                $this->logger->error(
                    'Indexing is disabled. Set config.index_enable = 1 .',
                );
            }
            return;
        }

        // Get the rendered page content from the event
        $pageContent = $event->getContent();

        try {
            $indexQueueItem = $this->getIndexQueueItem();
            if ($indexQueueItem === null) {
                throw new UnexpectedValueException('Can not get index queue item', 1482162337);
            }
            $this->index($indexQueueItem, $typo3Request, $pageContent);
        } catch (Throwable $e) {
            $this->responseData['pageIndexed'] = false;
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->error(
                    'Exception while trying to index page ' . $pageId,
                    [
                        $e->__toString(),
                    ],
                );
            }
        }

        if ($logPageIndexed) {
            $success = $this->responseData['pageIndexed'] ? 'Success' : 'Failed';
            $severity = $this->responseData['pageIndexed'] ? LogLevel::NOTICE : LogLevel::ERROR;

            $this->logger->log(
                $severity,
                'Page indexed: ' . $success,
                $this->responseData,
            );
        }
    }

    /**
     * Checks if indexing is enabled via TypoScript config.index_enable
     */
    protected function isIndexingEnabled(ServerRequestInterface $request): bool
    {
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        if ($frontendTypoScript === null) {
            return false;
        }
        $configArray = $frontendTypoScript->getConfigArray();
        return (bool)($configArray['index_enable'] ?? false);
    }

    /**
     * Index item
     *
     * @throws DBALException
     * @throws SolrException
     * @throws Throwable
     */
    protected function index(
        Item $indexQueueItem,
        ServerRequestInterface $request,
        string $pageContent,
    ): void {
        /** @var PageArguments $pageArguments */
        $pageArguments = $request->getAttribute('routing');
        $pageInformation = $request->getAttribute('frontend.page.information');
        $siteLanguage = $request->getAttribute('language');
        $this->solrConnection = $this->getSolrConnection($indexQueueItem, $siteLanguage, $this->configuration->getLoggingExceptions());

        $document = $this->getPageDocument(
            $pageContent,
            $pageInformation,
            $pageArguments,
            $siteLanguage,
            $this->generatePageUrl($pageArguments, $pageInformation, $request),
            $this->getAccessRootline(),
            $pageInformation->getMountPoint(),
        );
        if ($this->configuration?->isVectorSearchEnabled()) {
            $document->setField('vectorContent', $document['content']);
        }

        $document = $this->substitutePageDocument(
            $document,
            $pageInformation->getPageRecord(),
            $indexQueueItem,
            $request,
        );

        $this->responseData['pageIndexed'] = (int)$this->indexPage($document, $indexQueueItem, $request);
        $this->responseData['originalPageDocument'] = (array)$document;
        $this->responseData['solrConnection'] = [
            'rootPage' => $indexQueueItem->getRootPageUid(),
            'sys_language_uid' => $siteLanguage->getLanguageId(),
            'solr' => $this->solrConnection->getEndpoint('write')->getCoreBaseUri(),
        ];

        foreach ($this->documentsSentToSolr as $document) {
            $this->responseData['documentsSentToSolr'][] = (array)$document;
        }
    }

    /**
     * Gets the solr connection to use for indexing the page based on the
     * Index Queue item's properties.
     *
     * @throws DBALException
     * @throws NoSolrConnectionFoundException
     * @throws SolrException
     * @throws Throwable
     */
    protected function getSolrConnection(Item $indexQueueItem, SiteLanguage $siteLanguage, bool $logExceptions): SolrConnection
    {
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        try {
            $solrConnection = $connectionManager->getConnectionByRootPageId($indexQueueItem->getRootPageUid(), $siteLanguage->getLanguageId());
            if (!$solrConnection->getWriteService()->ping()) {
                throw new SolrException(
                    'Could not connect to Solr server.',
                    1323946472,
                );
            }
            return $solrConnection;
        } catch (Throwable $e) {
            $this->logger->error(
                $e->getMessage() . ' Error code: ' . $e->getCode(),
            );

            // TODO extract to a class "ExceptionLogger"
            if ($logExceptions) {
                $this->logger->error(
                    'Exception while trying to index a page',
                    [
                        $e->__toString(),
                    ],
                );
            }
            throw $e;
        }
    }

    /**
     * This method retrieves the item from the index queue, that is indexed in this request.
     *
     * @throws DBALException
     */
    protected function getIndexQueueItem(): ?Item
    {
        /** @var Queue $indexQueue */
        $indexQueue = GeneralUtility::makeInstance(Queue::class);
        return $indexQueue->getItem($this->request->getParameter('item'));
    }

    /**
     * Allows third party extensions to replace or modify the page document
     * created by this indexer.
     *
     * @param Document $pageDocument The page document created by this indexer.
     * @return Document An Apache Solr document representing the currently indexed page
     */
    protected function substitutePageDocument(
        Document $pageDocument,
        array $pageRecord,
        Item $indexQueueItem,
        ServerRequestInterface $request,
    ): Document {
        $event = new AfterPageDocumentIsCreatedForIndexingEvent(
            $pageDocument,
            $indexQueueItem,
            $pageRecord,
            $request,
            $this->configuration,
        );
        $event = $this->getEventDispatcher()->dispatch($event);
        return $event->getDocument();
    }

    /**
     * Builds the Solr document for the current page.
     *
     * @return Document A document representing the page
     */
    protected function getPageDocument(string $pageContent, PageInformation $pageInformation, PageArguments $pageArguments, SiteLanguage $siteLanguage, string $url, Rootline $pageAccessRootline, string $mountPointParameter): Document
    {
        $documentBuilder = GeneralUtility::makeInstance(Builder::class);
        return $documentBuilder->fromPage($pageInformation, $pageArguments, $siteLanguage, $pageContent, $url, $pageAccessRootline, $mountPointParameter);
    }

    /**
     * Indexes a page.
     *
     * @return bool TRUE after successfully indexing the page, FALSE on error
     *
     * @throws DBALException
     * @throws SolrException
     */
    protected function indexPage(
        Document $pageDocument,
        Item $indexQueueItem,
        ServerRequestInterface $request,
    ): bool {
        $event = new BeforePageDocumentIsProcessedForIndexingEvent($pageDocument, $indexQueueItem, $request);
        $event = $this->getEventDispatcher()->dispatch($event);
        $documents = $event->getDocuments();

        $this->processDocuments($documents);

        $event = new BeforeDocumentsAreIndexedEvent($pageDocument, $indexQueueItem, $documents, $request);
        $event = $this->getEventDispatcher()->dispatch($event);
        $documents = $event->getDocuments();

        $pageIndexed = $this->addDocumentsToSolrIndex($documents);
        $this->documentsSentToSolr = $documents;

        return $pageIndexed;
    }

    /**
     * Sends the given documents to the field processing service which takes
     * care of manipulating fields as defined in the field's configuration.
     *
     * @param Document[] $documents An array of documents to manipulate
     *
     * @throws SolrException
     * @throws DBALException
     */
    protected function processDocuments(array $documents): void
    {
        $processingInstructions = $this->configuration?->getIndexFieldProcessingInstructionsConfiguration();
        if (is_array($processingInstructions) && !empty($processingInstructions)) {
            $service = GeneralUtility::makeInstance(Service::class);
            $service->processDocuments($documents, $processingInstructions);
        }
    }

    /**
     * Adds the collected documents to the Solr index.
     *
     * @param Document[] $documents An array of Document objects.
     * @return bool TRUE if documents were added successfully, FALSE otherwise
     */
    protected function addDocumentsToSolrIndex(array $documents): bool
    {
        $documentsAdded = false;

        if ($documents === []) {
            return false;
        }

        try {
            $this->logger->info('Adding ' . count($documents) . ' documents.', $documents);

            // chunk adds by 20
            $documentChunks = array_chunk($documents, 20);
            foreach ($documentChunks as $documentChunk) {
                $response = $this->solrConnection->getWriteService()->addDocuments($documentChunk);
                if ($response->getHttpStatus() != 200) {
                    $this->logger->error('Solr could not index page.', [$response->getRawResponse()]);
                    throw new RuntimeException('Solr Request failed.', 1331834983);
                }
            }

            $documentsAdded = true;
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage() . ' Error code: ' . $e->getCode());

            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->error('Exception while adding documents', [$e->__toString()]);
            }
        }

        return $documentsAdded;
    }

    /**
     * Initialize PageIndexer
     *
     * As the Solr configuration initialization might affect the request
     * we cannot initialize the configuration directly on activation
     */
    protected function setupConfiguration(): void
    {
        $this->logger = new SolrLogManager(__CLASS__, GeneralUtility::makeInstance(DebugWriter::class));
        $this->configuration = Util::getSolrConfiguration();
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Adds the status of whether a page was indexed to the pageIndexer Response.
     */
    public function deactivate(PageIndexerResponse $response): void
    {
        if ($this->activated) {
            if (!isset($this->responseData['pageIndexed'])) {
                $this->responseData['pageIndexed'] = false;

                $this->setupConfiguration();
                if ($this->configuration->getLoggingExceptions()) {
                    $this->logger->error(
                        'Unknown exception while trying to index page',
                    );
                }
            }
            $response->addActionResult($this->action, $this->responseData);
        }
        $this->activated = false;
    }
}
