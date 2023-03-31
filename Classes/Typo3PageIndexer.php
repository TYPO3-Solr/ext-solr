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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\FieldProcessor\Service;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\DebugWriter;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Doctrine\DBAL\Exception as DBALException;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use UnexpectedValueException;

/**
 * Page Indexer to index TYPO3 pages used by the Index Queue.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 * @author Timo Schmidt <schmidt@aoemedia.de>
 */
class Typo3PageIndexer
{
    /**
     * ID of the current page's Solr document.
     */
    protected static string $pageSolrDocumentId = '';

    /**
     * The Solr document generated for the current page.
     */
    protected static Document $pageSolrDocument;

    /**
     * The mount point parameter used in the Frontend controller.
     */
    protected string $mountPointParameter = '';

    /**
     * Solr server connection.
     */
    protected ?SolrConnection $solrConnection = null;

    /**
     * Frontend page object (TSFE).
     */
    protected TypoScriptFrontendController $page;

    /**
     * URL to be indexed as the page's URL
     */
    protected string $pageUrl = '';

    /**
     * The page's access rootline
     */
    protected Rootline $pageAccessRootline;

    /**
     * Documents that have been sent to Solr
     */
    protected array $documentsSentToSolr = [];

    protected TypoScriptConfiguration $configuration;

    protected Item $indexQueueItem;

    protected SolrLogManager $logger;

    /**
     * Constructor
     *
     * @param TypoScriptFrontendController $page The page to index
     */
    public function __construct(TypoScriptFrontendController $page)
    {
        $this->logger = new SolrLogManager(__CLASS__, GeneralUtility::makeInstance(DebugWriter::class));

        $this->page = $page;
        $this->pageUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
        $this->configuration = Util::getSolrConfiguration();

        try {
            $this->initializeSolrConnection();
        } catch (Throwable $e) {
            $this->logger->log(
                SolrLogManager::ERROR,
                $e->getMessage() . ' Error code: ' . $e->getCode()
            );

            // TODO extract to a class "ExceptionLogger"
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Exception while trying to index a page',
                    [
                        $e->__toString(),
                    ]
                );
            }
        }

        $this->pageAccessRootline = GeneralUtility::makeInstance(Rootline::class, '');
    }

    public function setIndexQueueItem(Item $indexQueueItem): void
    {
        $this->indexQueueItem = $indexQueueItem;
    }

    /**
     * Initializes the Solr server connection.
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     * @throws Exception
     * @throws NoSolrConnectionFoundException
     */
    protected function initializeSolrConnection(): void
    {
        $solr = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($this->page->id, Util::getLanguageUid());

        // do not continue if no server is available
        if (!$solr->getWriteService()->ping()) {
            throw new Exception(
                'No Solr instance available while trying to index a page.',
                1234790825
            );
        }

        $this->solrConnection = $solr;
    }

    /**
     * Gets the current page's Solr document ID.
     *
     * @return string The page's Solr document ID or empty string in case no document was generated yet.
     */
    public static function getPageSolrDocumentId(): string
    {
        return self::$pageSolrDocumentId;
    }

    /**
     * Gets the Solr document generated for the current page.
     *
     * @return Document|null The page's Solr document or NULL if it has not been generated yet.
     */
    public static function getPageSolrDocument(): ?Document
    {
        return self::$pageSolrDocument;
    }

    /**
     * Allows to provide a Solr server connection other than the one
     * initialized by the constructor.
     *
     * @param SolrConnection $solrConnection Solr connection
     * @throws Exception if the Solr server cannot be reached
     */
    public function setSolrConnection(SolrConnection $solrConnection): void
    {
        if (!$solrConnection->getWriteService()->ping()) {
            throw new Exception(
                'Could not connect to Solr server.',
                1323946472
            );
        }

        $this->solrConnection = $solrConnection;
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
    public function indexPage(): bool
    {
        $documents = []; // this will become useful as soon as when starting to index individual records instead of whole pages

        if (is_null($this->solrConnection)) {
            // intended early return as it doesn't make sense to continue
            // and waste processing time if the solr server isn't available
            // anyways
            // FIXME use an exception
            return false;
        }

        $pageDocument = $this->getPageDocument();
        $pageDocument = $this->substitutePageDocument($pageDocument);

        self::$pageSolrDocument = $pageDocument;
        $documents[] = $pageDocument;
        $documents = $this->getAdditionalDocuments($pageDocument, $documents);
        $this->processDocuments($documents);
        $documents = Indexer::preAddModifyDocuments(
            $this->indexQueueItem,
            $this->page->getLanguage()->getLanguageId(),
            $documents
        );

        $pageIndexed = $this->addDocumentsToSolrIndex($documents);
        $this->documentsSentToSolr = $documents;

        return $pageIndexed;
    }

    /**
     * Builds the Solr document for the current page.
     *
     * @return Document A document representing the page
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     */
    protected function getPageDocument(): Document
    {
        $documentBuilder = GeneralUtility::makeInstance(Builder::class);
        $document = $documentBuilder->fromPage($this->page, $this->pageUrl, $this->pageAccessRootline, $this->mountPointParameter);

        self::$pageSolrDocumentId = $document['id'];

        return $document;
    }

    /**
     * Gets the mount point parameter that is used in the Frontend controller.
     */
    public function getMountPointParameter(): string
    {
        return $this->mountPointParameter;
    }

    // Misc

    /**
     * Sets the mount point parameter that is used in the Frontend controller.
     */
    public function setMountPointParameter(string $mountPointParameter): void
    {
        $this->mountPointParameter = $mountPointParameter;
    }

    /**
     * Allows third party extensions to replace or modify the page document
     * created by this indexer.
     *
     * @param Document $pageDocument The page document created by this indexer.
     * @return Document An Apache Solr document representing the currently indexed page
     */
    protected function substitutePageDocument(Document $pageDocument): Document
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'] ?? null)) {
            return $pageDocument;
        }

        $indexConfigurationName = $this->getIndexConfigurationNameForCurrentPage();
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'] as $classReference) {
            $substituteIndexer = GeneralUtility::makeInstance($classReference);

            if (!$substituteIndexer instanceof SubstitutePageIndexer) {
                $message = get_class($substituteIndexer) . ' must implement interface ' . SubstitutePageIndexer::class;
                throw new UnexpectedValueException($message, 1310491001);
            }

            if ($substituteIndexer instanceof PageFieldMappingIndexer) {
                $substituteIndexer->setPageIndexingConfigurationName($indexConfigurationName);
            }

            $substituteDocument = $substituteIndexer->getPageDocument($pageDocument);
            $pageDocument = $substituteDocument;
        }

        return $pageDocument;
    }

    /**
     * Retrieves the indexConfigurationName from the related queueItem, or falls back to pages when no queue item set.
     */
    protected function getIndexConfigurationNameForCurrentPage(): string
    {
        return isset($this->indexQueueItem) ? $this->indexQueueItem->getIndexingConfigurationName() : 'pages';
    }

    /**
     * Allows third party extensions to provide additional documents which
     * should be indexed for the current page.
     *
     * @param Document $pageDocument The main document representing this page.
     * @param Document[] $existingDocuments An array of documents already created for this page.
     * @return array An array of additional Document objects to index
     */
    protected function getAdditionalDocuments(Document $pageDocument, array $existingDocuments): array
    {
        $documents = $existingDocuments;

        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments'] ?? null)) {
            return $documents;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments'] as $classReference) {
            $additionalIndexer = GeneralUtility::makeInstance($classReference);

            if (!$additionalIndexer instanceof AdditionalPageIndexer) {
                $message = get_class($additionalIndexer) . ' must implement interface ' . AdditionalPageIndexer::class;
                throw new UnexpectedValueException($message, 1310491024);
            }

            $additionalDocuments = $additionalIndexer->getAdditionalPageDocuments($pageDocument, $documents);
            if (!empty($additionalDocuments)) {
                $documents = array_merge($documents, $additionalDocuments);
            }
        }

        return $documents;
    }

    /**
     * Sends the given documents to the field processing service which takes
     * care of manipulating fields as defined in the field's configuration.
     *
     * @param array $documents An array of documents to manipulate
     * @throws DBALException
     * @throws Exception
     */
    protected function processDocuments(array $documents): void
    {
        $processingInstructions = $this->configuration->getIndexFieldProcessingInstructionsConfiguration();
        if (count($processingInstructions) > 0) {
            $service = GeneralUtility::makeInstance(Service::class);
            $service->processDocuments($documents, $processingInstructions);
        }
    }

    /**
     * Adds the collected documents to the Solr index.
     *
     * @param array $documents An array of Document objects.
     * @return bool TRUE if documents were added successfully, FALSE otherwise
     */
    protected function addDocumentsToSolrIndex(array $documents): bool
    {
        $documentsAdded = false;

        if (!count($documents)) {
            return false;
        }

        try {
            $this->logger->log(SolrLogManager::INFO, 'Adding ' . count($documents) . ' documents.', $documents);

            // chunk adds by 20
            $documentChunks = array_chunk($documents, 20);
            foreach ($documentChunks as $documentChunk) {
                $response = $this->solrConnection->getWriteService()->addDocuments($documentChunk);
                if ($response->getHttpStatus() != 200) {
                    throw new RuntimeException('Solr Request failed.', 1331834983);
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
     * Gets the current page's URL.
     *
     * @return string URL of the current page.
     */
    public function getPageUrl(): string
    {
        return $this->pageUrl;
    }

    /**
     * Sets the URL to use for the page document.
     *
     * @param string $url The page's URL.
     */
    public function setPageUrl(string $url): void
    {
        $this->pageUrl = $url;
    }

    /**
     * Gets the page's access rootline.
     *
     * @return Rootline The page's access rootline
     */
    public function getPageAccessRootline(): Rootline
    {
        return $this->pageAccessRootline;
    }

    /**
     * Sets the page's access rootline.
     *
     * @param Rootline $accessRootline The page's access rootline
     */
    public function setPageAccessRootline(Rootline $accessRootline): void
    {
        $this->pageAccessRootline = $accessRootline;
    }

    /**
     * Gets the documents that have been sent to Solr
     *
     * @return array An array of Document objects
     */
    public function getDocumentsSentToSolr(): array
    {
        return $this->documentsSentToSolr;
    }
}
