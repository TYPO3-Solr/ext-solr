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
use ApacheSolrForTypo3\Solr\Exception;
use ApacheSolrForTypo3\Solr\FieldProcessor\Service;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
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
use Throwable;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use UnexpectedValueException;

/**
 * Index Queue Page Indexer frontend helper to validate if a page
 * should be used by the Index Queue.
 *
 * Once the FrontendHelper construct is separated, this will be a standalone Indexer.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageIndexer extends AbstractFrontendHelper
{
    /**
     * This frontend helper's executed action.
     */
    protected string $action = 'indexPage';

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

    /**
     * Activates a frontend helper by registering for hooks and other
     * resources required by the frontend helper to work.
     *
     * @noinspection PhpUnused
     */
    public function activate(): void
    {
        $this->isActivated = true;
        $this->registerAuthorizationService();
    }

    /**
     * Returns the status of whether a page was indexed.
     *
     * @return array Page indexed status.
     * @noinspection PhpUnused
     */
    public function getData(): array
    {
        return $this->responseData;
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

    /**
     * Registers an authentication service to authorize / grant the indexer to
     * access protected pages.
     */
    protected function registerAuthorizationService(): void
    {
        $overrulingPriority = $this->getHighestAuthenticationServicePriority() + 1;

        ExtensionManagementUtility::addService(
            'solr', // extension key
            'auth', // service type
            AuthorizationService::class,
            // service key
            [// service meta data
                'title' => 'Solr Indexer Authorization',
                'description' => 'Authorizes the Solr Index Queue indexer to access protected pages.',

                'subtype' => 'getUserFE,authUserFE,getGroupsFE',

                'available' => true,
                'priority' => $overrulingPriority,
                'quality' => 100,

                'os' => '',
                'exec' => '',
                'className' => AuthorizationService::class,
            ]
        );
    }

    /**
     * Determines the highest priority of all registered authentication
     * services.
     *
     * @return int Highest priority of all registered authentication service
     */
    protected function getHighestAuthenticationServicePriority(): int
    {
        $highestPriority = 0;

        if (is_array($GLOBALS['T3_SERVICES']['auth'] ?? null)) {
            foreach ($GLOBALS['T3_SERVICES']['auth'] as $service) {
                if ($service['priority'] > $highestPriority) {
                    $highestPriority = $service['priority'];
                }
            }
        }

        return $highestPriority;
    }

    //
    // Indexing
    //

    /**
     * Generates the current page's URL as string.
     * Uses the provided parameters from TSFE, page id and language id.
     */
    protected function generatePageUrl(TypoScriptFrontendController $controller): string
    {
        if ($this->request?->getParameter('overridePageUrl')) {
            return $this->request->getParameter('overridePageUrl');
        }

        $parameter = $controller->page['uid'];
        $type = $controller->getPageArguments()->getPageType();
        if ($type && MathUtility::canBeInterpretedAsInteger($type)) {
            $parameter .= ',' . $type;
        }
        return $controller->cObj->createUrl([
            'parameter' => $parameter,
            'linkAccessRestrictedPages' => '1',
        ]);
    }

    /**
     * Handles the indexing of the page content during AfterCacheableContentIsGeneratedEvent of a generated page.
     */
    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        if (!$this->isActivated) {
            return;
        }
        $this->setupConfiguration();
        $tsfe = $event->getController();

        $logPageIndexed = $this->configuration->getLoggingIndexingPageIndexed();
        if (!($tsfe->config['config']['index_enable'] ?? false)) {
            if ($logPageIndexed) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Indexing is disabled. Set config.index_enable = 1 .'
                );
            }
            return;
        }

        try {
            $indexQueueItem = $this->getIndexQueueItem();
            if ($indexQueueItem === null) {
                throw new UnexpectedValueException('Can not get index queue item', 1482162337);
            }
            $this->index($indexQueueItem, $tsfe);
        } catch (Throwable $e) {
            $this->responseData['pageIndexed'] = false;
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Exception while trying to index page ' . $tsfe->id,
                    [
                        $e->__toString(),
                    ]
                );
            }
        }

        if ($logPageIndexed) {
            $success = $this->responseData['pageIndexed'] ? 'Success' : 'Failed';
            $severity = $this->responseData['pageIndexed'] ? SolrLogManager::NOTICE : SolrLogManager::ERROR;

            $this->logger->log(
                $severity,
                'Page indexed: ' . $success,
                $this->responseData
            );
        }
    }

    /**
     * @internal currently only public for tests
     */
    public function index(Item $indexQueueItem, TypoScriptFrontendController $tsfe): void
    {
        $this->solrConnection = $this->getSolrConnection($indexQueueItem, $tsfe->getLanguage(), $this->configuration->getLoggingExceptions());

        $document = $this->getPageDocument($tsfe, $this->generatePageUrl($tsfe), $this->getAccessRootline(), $tsfe->MP);
        $document = $this->substitutePageDocument($document, $tsfe->getSite(), $tsfe->getLanguage(), $tsfe->page, $indexQueueItem);

        $this->responseData['pageIndexed'] = (int)$this->indexPage($document, $indexQueueItem, $tsfe->getSite(), $tsfe->getLanguage());
        $this->responseData['originalPageDocument'] = (array)$document;
        $this->responseData['solrConnection'] = [
            'rootPage' => $indexQueueItem->getRootPageUid(),
            'sys_language_uid' => $tsfe->getLanguage()->getLanguageId(),
            'solr' => (string)$this->solrConnection->getNode('write'),
        ];

        foreach ($this->documentsSentToSolr as $document) {
            $this->responseData['documentsSentToSolr'][] = (array)$document;
        }
    }

    /**
     * Gets the solr connection to use for indexing the page based on the
     * Index Queue item's properties.
     *
     * @throws NoSolrConnectionFoundException
     * @throws DBALException
     */
    protected function getSolrConnection(Item $indexQueueItem, SiteLanguage $siteLanguage, bool $logExceptions): SolrConnection
    {
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        try {
            $solrConnection = $connectionManager->getConnectionByRootPageId($indexQueueItem->getRootPageUid(), $siteLanguage->getLanguageId());
            if (!$solrConnection->getWriteService()->ping()) {
                throw new Exception(
                    'Could not connect to Solr server.',
                    1323946472
                );
            }
            return $solrConnection;
        } catch (Throwable $e) {
            $this->logger->log(
                SolrLogManager::ERROR,
                $e->getMessage() . ' Error code: ' . $e->getCode()
            );

            // TODO extract to a class "ExceptionLogger"
            if ($logExceptions) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Exception while trying to index a page',
                    [
                        $e->__toString(),
                    ]
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
    protected function substitutePageDocument(Document $pageDocument, Site $site, SiteLanguage $siteLanguage, array $pageRecord, Item $indexQueueItem): Document
    {
        $event = new AfterPageDocumentIsCreatedForIndexingEvent($pageDocument, $indexQueueItem, $site, $siteLanguage, $pageRecord, $this->configuration);
        $event = $this->getEventDispatcher()->dispatch($event);
        return $event->getDocument();
    }

    /**
     * Builds the Solr document for the current page.
     *
     * @return Document A document representing the page
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     */
    protected function getPageDocument(TypoScriptFrontendController $tsfe, string $url, Rootline $pageAccessRootline, string $mountPointParameter): Document
    {
        $documentBuilder = GeneralUtility::makeInstance(Builder::class);
        return $documentBuilder->fromPage($tsfe, $url, $pageAccessRootline, $mountPointParameter);
    }

    /**
     * Indexes a page.
     *
     * @return bool TRUE after successfully indexing the page, FALSE on error
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     * @throws Exception
     */
    public function indexPage(Document $pageDocument, Item $indexQueueItem, Site $site, SiteLanguage $siteLanguage): bool
    {
        $event = new BeforePageDocumentIsProcessedForIndexingEvent($pageDocument, $site, $siteLanguage, $indexQueueItem);
        $event = $this->getEventDispatcher()->dispatch($event);
        $documents = $event->getDocuments();

        $this->processDocuments($documents);

        $event = new BeforeDocumentsAreIndexedEvent($pageDocument, $site, $siteLanguage, $indexQueueItem, $documents);
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
     * @throws DBALException
     * @throws Exception
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
            $this->logger->log(SolrLogManager::INFO, 'Adding ' . count($documents) . ' documents.', $documents);

            // chunk adds by 20
            $documentChunks = array_chunk($documents, 20);
            foreach ($documentChunks as $documentChunk) {
                $response = $this->solrConnection->getWriteService()->addDocuments($documentChunk);
                if ($response->getHttpStatus() != 200) {
                    throw new \RuntimeException('Solr Request failed.', 1331834983);
                }
            }

            $documentsAdded = true;
        } catch (Throwable $e) {
            $this->logger->log(SolrLogManager::ERROR, $e->getMessage() . ' Error code: ' . $e->getCode());

            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->log(SolrLogManager::ERROR, 'Exception while adding documents', [$e->__toString()]);
            }
        }

        return $documentsAdded;
    }

    /**
     * @internal only used for tests
     */
    public function setupConfiguration(): void
    {
        // currently needed for tests, will be separated.
        $this->logger = new SolrLogManager(__CLASS__, GeneralUtility::makeInstance(DebugWriter::class));
        $this->configuration = Util::getSolrConfiguration();
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }
}
