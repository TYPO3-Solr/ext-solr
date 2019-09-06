<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\FieldProcessor\Service;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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
     *
     * @var string
     */
    protected static $pageSolrDocumentId = '';
    /**
     * The Solr document generated for the current page.
     *
     * @var Document
     */
    protected static $pageSolrDocument = null;
    /**
     * The mount point parameter used in the Frontend controller.
     *
     * @var string
     */
    protected $mountPointParameter;
    /**
     * Solr server connection.
     *
     * @var SolrConnection
     */
    protected $solrConnection = null;
    /**
     * Frontend page object (TSFE).
     *
     * @var TypoScriptFrontendController
     */
    protected $page = null;
    /**
     * Content extractor to extract content from TYPO3 pages
     *
     * @var Typo3PageContentExtractor
     */
    protected $contentExtractor = null;
    /**
     * URL to be indexed as the page's URL
     *
     * @var string
     */
    protected $pageUrl = '';
    /**
     * The page's access rootline
     *
     * @var Rootline
     */
    protected $pageAccessRootline = null;
    /**
     * Documents that have been sent to Solr
     *
     * @var array
     */
    protected $documentsSentToSolr = [];

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var Item
     */
    protected $indexQueueItem;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * Constructor
     *
     * @param TypoScriptFrontendController $page The page to index
     */
    public function __construct(TypoScriptFrontendController $page)
    {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);

        $this->page = $page;
        $this->pageUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
        $this->configuration = Util::getSolrConfiguration();

        try {
            $this->initializeSolrConnection();
        } catch (\Exception $e) {
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
                        $e->__toString()
                    ]
                );
            }
        }

        $this->pageAccessRootline = GeneralUtility::makeInstance(Rootline::class, /** @scrutinizer ignore-type */ '');
    }

    /**
     * @param Item $indexQueueItem
     */
    public function setIndexQueueItem($indexQueueItem)
    {
        $this->indexQueueItem = $indexQueueItem;
    }

    /**
     * Initializes the Solr server connection.
     *
     * @throws    \Exception when no Solr connection can be established.
     */
    protected function initializeSolrConnection()
    {
        $solr = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($this->page->id, Util::getLanguageUid());

        // do not continue if no server is available
        if (!$solr->getWriteService()->ping()) {
            throw new \Exception(
                'No Solr instance available while trying to index a page.',
                1234790825
            );
        }

        $this->solrConnection = $solr;
    }

    /**
     * Gets the current page's Solr document ID.
     *
     * @return string|NULL The page's Solr document ID or NULL in case no document was generated yet.
     */
    public static function getPageSolrDocumentId()
    {
        return self::$pageSolrDocumentId;
    }

    /**
     * Gets the Solr document generated for the current page.
     *
     * @return Document|NULL The page's Solr document or NULL if it has not been generated yet.
     */
    public static function getPageSolrDocument()
    {
        return self::$pageSolrDocument;
    }

    /**
     * Allows to provide a Solr server connection other than the one
     * initialized by the constructor.
     *
     * @param SolrConnection $solrConnection Solr connection
     * @throws \Exception if the Solr server cannot be reached
     */
    public function setSolrConnection(SolrConnection $solrConnection)
    {
        if (!$solrConnection->getWriteService()->ping()) {
            throw new \Exception(
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
     * @throws \UnexpectedValueException if a page document post processor fails to implement interface ApacheSolrForTypo3\Solr\PageDocumentPostProcessor
     */
    public function indexPage()
    {
        $pageIndexed = false;
        $documents = []; // this will become useful as soon as when starting to index individual records instead of whole pages

        if (is_null($this->solrConnection)) {
            // intended early return as it doesn't make sense to continue
            // and waste processing time if the solr server isn't available
            // anyways
            // FIXME use an exception
            return $pageIndexed;
        }

        $pageDocument = $this->getPageDocument();
        $pageDocument = $this->substitutePageDocument($pageDocument);

        $this->applyIndexPagePostProcessors($pageDocument);

        self::$pageSolrDocument = $pageDocument;
        $documents[] = $pageDocument;
        $documents = $this->getAdditionalDocuments($pageDocument, $documents);
        $this->processDocuments($documents);

        $pageIndexed = $this->addDocumentsToSolrIndex($documents);
        $this->documentsSentToSolr = $documents;

        return $pageIndexed;
    }

    /**
     * Applies the configured post processors (indexPagePostProcessPageDocument)
     *
     * @param Document $pageDocument
     */
    protected function applyIndexPagePostProcessors($pageDocument)
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPagePostProcessPageDocument'])) {
            return;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPagePostProcessPageDocument'] as $classReference) {
            $postProcessor = GeneralUtility::makeInstance($classReference);
            if (!$postProcessor instanceof PageDocumentPostProcessor) {
                throw new \UnexpectedValueException(get_class($pageDocument) . ' must implement interface ' . PageDocumentPostProcessor::class, 1397739154);
            }

            $postProcessor->postProcessPageDocument($pageDocument, $this->page);
        }
    }

    /**
     * Builds the Solr document for the current page.
     *
     * @return Document A document representing the page
     */
    protected function getPageDocument()
    {
        $documentBuilder = GeneralUtility::makeInstance(Builder::class);
        $document = $documentBuilder->fromPage($this->page, $this->pageUrl, $this->pageAccessRootline, (string)$this->mountPointParameter);

        self::$pageSolrDocumentId = $document['id'];

        return $document;
    }


    // Logging
    // TODO replace by a central logger

    /**
     * Gets the mount point parameter that is used in the Frontend controller.
     *
     * @return string
     */
    public function getMountPointParameter()
    {
        return $this->mountPointParameter;
    }

    // Misc

    /**
     * Sets the mount point parameter that is used in the Frontend controller.
     *
     * @param string $mountPointParameter
     */
    public function setMountPointParameter($mountPointParameter)
    {
        $this->mountPointParameter = (string)$mountPointParameter;
    }

    /**
     * Allows third party extensions to replace or modify the page document
     * created by this indexer.
     *
     * @param Document $pageDocument The page document created by this indexer.
     * @return Document An Apache Solr document representing the currently indexed page
     */
    protected function substitutePageDocument(Document $pageDocument)
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'])) {
            return $pageDocument;
        }

        $indexConfigurationName = $this->getIndexConfigurationNameForCurrentPage();
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'] as $classReference) {
            $substituteIndexer = GeneralUtility::makeInstance($classReference);

            if (!$substituteIndexer instanceof SubstitutePageIndexer) {
                $message = get_class($substituteIndexer) . ' must implement interface ' . SubstitutePageIndexer::class;
                throw new \UnexpectedValueException($message, 1310491001);
            }

            if ($substituteIndexer instanceof PageFieldMappingIndexer) {
                $substituteIndexer->setPageIndexingConfigurationName($indexConfigurationName);
            }

            $substituteDocument = $substituteIndexer->getPageDocument($pageDocument);
            if (!$substituteDocument instanceof Document) {
                $message = 'The document returned by ' . get_class($substituteIndexer) . ' is not a valid Document object.';
                throw new \UnexpectedValueException($message, 1310490952);
            }
            $pageDocument = $substituteDocument;
        }

        return $pageDocument;
    }

    /**
     * Retrieves the indexConfigurationName from the related queueItem, or falls back to pages when no queue item set.
     *
     * @return string
     */
    protected function getIndexConfigurationNameForCurrentPage()
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
    protected function getAdditionalDocuments(Document $pageDocument, array $existingDocuments)
    {
        $documents = $existingDocuments;

        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments'])) {
            return $documents;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments'] as $classReference) {
            $additionalIndexer = GeneralUtility::makeInstance($classReference);

            if (!$additionalIndexer instanceof AdditionalPageIndexer) {
                $message = get_class($additionalIndexer) . ' must implement interface ' . AdditionalPageIndexer::class;
                throw new \UnexpectedValueException($message, 1310491024);
            }

            $additionalDocuments = $additionalIndexer->getAdditionalPageDocuments($pageDocument, $documents);
            if (is_array($additionalDocuments)) {
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
     */
    protected function processDocuments(array $documents)
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
    protected function addDocumentsToSolrIndex(array $documents)
    {
        $documentsAdded = false;

        if (!count($documents)) {
            return $documentsAdded;
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
        } catch (\Exception $e) {
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
    public function getPageUrl()
    {
        return $this->pageUrl;
    }

    /**
     * Sets the URL to use for the page document.
     *
     * @param string $url The page's URL.
     */
    public function setPageUrl($url)
    {
        $this->pageUrl = $url;
    }

    /**
     * Gets the page's access rootline.
     *
     * @return Rootline The page's access rootline
     */
    public function getPageAccessRootline()
    {
        return $this->pageAccessRootline;
    }

    /**
     * Sets the page's access rootline.
     *
     * @param Rootline $accessRootline The page's access rootline
     */
    public function setPageAccessRootline(Rootline $accessRootline)
    {
        $this->pageAccessRootline = $accessRootline;
    }

    /**
     * Gets the documents that have been sent to Solr
     *
     * @return array An array of Document objects
     */
    public function getDocumentsSentToSolr()
    {
        return $this->documentsSentToSolr;
    }
}
