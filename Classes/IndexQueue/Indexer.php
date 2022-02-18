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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\FieldProcessor\Service;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Exception;
use RuntimeException;
use Solarium\Exception\HttpException;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * A general purpose indexer to be used for indexing of any kind of regular
 * records like tt_news, tt_address, and so on.
 * Specialized indexers can extend this class to handle advanced stuff like
 * category resolution in tt_news or file indexing.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @copyright  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 */
class Indexer extends AbstractIndexer
{
    /**
     * A Solr service instance to interact with the Solr server
     *
     * @var SolrConnection
     */
    protected $solr;

    /**
     * @var ConnectionManager
     */
    protected $connectionManager;

    /**
     * Holds options for a specific indexer
     *
     * @var array
     */
    protected $options = [];

    /**
     * To log or not to log... #Shakespeare
     *
     * @var bool
     */
    protected $loggingEnabled = false;

    /**
     * @var SolrLogManager
     */
    protected $logger = null;

    /**
     * @var PagesRepository
     */
    protected $pagesRepository;

    /**
     * @var Builder
     */
    protected $documentBuilder;

    /**
     * @var FrontendEnvironment
     */
    protected $frontendEnvironment = null;

    /**
     * Constructor
     *
     * @param array $options array of indexer options
     * @param PagesRepository|null $pagesRepository
     * @param Builder|null $documentBuilder
     * @param SolrLogManager|null $logger
     * @param ConnectionManager|null $connectionManager
     * @param FrontendEnvironment|null $frontendEnvironment
     */
    public function __construct(
        array $options = [],
        PagesRepository $pagesRepository = null,
        Builder $documentBuilder = null,
        SolrLogManager $logger = null,
        ConnectionManager $connectionManager = null,
        FrontendEnvironment $frontendEnvironment = null
    )
    {
        $this->options = $options;
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
        $this->documentBuilder = $documentBuilder ?? GeneralUtility::makeInstance(Builder::class);
        $this->logger = $logger ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
        $this->connectionManager = $connectionManager ?? GeneralUtility::makeInstance(ConnectionManager::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
    }

    /**
     * Indexes an item from the indexing queue.
     *
     * @param Item $item An index queue item
     * @return bool returns true when indexed, false when not
     */
    public function index(Item $item)
    {
        $indexed = true;

        $this->type = $item->getType();
        $this->setLogging($item);

        $solrConnections = $this->getSolrConnectionsByItem($item);
        foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
            $this->solr = $solrConnection;

            if (!$this->indexItem($item, (int)$systemLanguageUid)) {
                /*
                 * A single language voting for "not indexed" should make the whole
                 * item count as being not indexed, even if all other languages are
                 * indexed.
                 * If there is no translation for a single language, this item counts
                 * as TRUE since it's not an error which that should make the item
                 * being reindexed during another index run.
                 */
                $indexed = false;
            }
        }

        return $indexed;
    }

    /**
     * Creates a single Solr Document for an item in a specific language.
     *
     * @param Item $item An index queue item to index.
     * @param int $language The language to use.
     * @return bool TRUE if item was indexed successfully, FALSE on failure
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     */
    protected function indexItem(Item $item, int $language = 0): bool
    {
        $itemIndexed = false;
        $documents = [];


        $itemDocument = $this->itemToDocument($item, $language);
        if (is_null($itemDocument)) {
            /*
             * If there is no itemDocument, this means there was no translation
             * for this record. This should not stop the current item to count as
             * being valid because not-indexing not-translated items is perfectly
             * fine.
             */
            return true;
        }

        $documents[] = $itemDocument;
        $documents = array_merge($documents, $this->getAdditionalDocuments($item, $language, $itemDocument));
        $documents = $this->processDocuments($item, $documents);
        $documents = $this->preAddModifyDocuments($item, $language, $documents);

        try {
            $response = $this->solr->getWriteService()->addDocuments($documents);
            if ($response->getHttpStatus() == 200) {
                $itemIndexed = true;
            }
        } catch (HttpException $e) {
            $response = new ResponseAdapter($e->getBody(), $httpStatus = 500, $e->getStatusMessage());
        }

        $this->log($item, $documents, $response);

        return $itemIndexed;
    }

    /**
     * Gets the full item record.
     *
     * This general record indexer simply gets the record from the item. Other
     * more specialized indexers may provide more data for their specific item
     * types.
     *
     * @param Item $item The item to be indexed
     * @param int $language Language Id (sys_language.uid)
     * @return array|NULL The full record with fields of data to be used for indexing or NULL to prevent an item from being indexed
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     */
    protected function getFullItemRecord(Item $item, int $language = 0): ?array
    {
        $itemRecord = $this->getItemRecordOverlayed($item, $language);

        if (!is_null($itemRecord)) {
            $itemRecord['__solr_index_language'] = $language;
        }

        return $itemRecord;
    }

    /**
     * Returns the overlaid item record.
     *
     * @param Item $item
     * @param int $language
     * @return array|mixed|null
     * @throws DBALDriverException
     * @throws FrontendEnvironment\Exception\Exception
     * @throws SiteNotFoundException
     */
    protected function getItemRecordOverlayed(Item $item, int $language): ?array
    {
        $itemRecord = $item->getRecord();
        $languageField = $GLOBALS['TCA'][$item->getType()]['ctrl']['languageField'] ?? null;
        // skip "free content mode"-record for other languages, if item is a "free content mode"-record
        if ($this->isAFreeContentModeItemRecord($item, $language)
            && isset($languageField)
            && (int)($itemRecord[$languageField] ?? null) !== $language
        ) {
            return null;
        }
        // skip fallback for "free content mode"-languages
        if ($this->isLanguageInAFreeContentMode($item, $language)
            && isset($languageField)
            && (int)($itemRecord[$languageField] ?? null) !== $language
        ) {
            return null;
        }

        $pidToUse = $this->getPageIdOfItem($item);

        return GeneralUtility::makeInstance(Tsfe::class)
            ->getTsfeByPageIdAndLanguageId($pidToUse, $language, $item->getRootPageUid())
            ->sys_page->getLanguageOverlay($item->getType(), $itemRecord);
    }

    /**
     * @param Item $item
     * @param int $language
     *
     * @return bool
     */
    protected function isAFreeContentModeItemRecord(Item $item, int $language): bool
    {
        $languageField = $GLOBALS['TCA'][$item->getType()]['ctrl']['languageField'] ?? null;
        $itemRecord = $item->getRecord();
        if (isset($languageField)
            && (int)($itemRecord[$languageField] ?? null) > 0
            && $this->isLanguageInAFreeContentMode($item, (int)($itemRecord[$languageField] ?? null))
        ) {
            return true;
        }
        return false;
    }

    /**
     * Gets the configuration how to process an item's fields for indexing.
     *
     * @param Item $item An index queue item
     * @param int $language Language ID
     * @throws RuntimeException
     * @return array Configuration array from TypoScript
     */
    protected function getItemTypeConfiguration(Item $item, int $language = 0): array
    {
        $indexConfigurationName = $item->getIndexingConfigurationName();
        $fields = $this->getFieldConfigurationFromItemRecordPage($item, $language, $indexConfigurationName);
        if (!$this->isRootPageIdPartOfRootLine($item) || count($fields) === 0) {
            $fields = $this->getFieldConfigurationFromItemRootPage($item, $language, $indexConfigurationName);
            if (count($fields) === 0) {
                throw new RuntimeException('The item indexing configuration "' . $item->getIndexingConfigurationName() .
                    '" on root page uid ' . $item->getRootPageUid() . ' could not be found!', 1455530112);
            }
        }

        return $fields;
    }

    /**
     * The method retrieves the field configuration of the items record page id (pid).
     *
     * @param Item $item
     * @param integer $language
     * @param string $indexConfigurationName
     * @return array
     */
    protected function getFieldConfigurationFromItemRecordPage(Item $item, int $language, string $indexConfigurationName): array
    {
        try {
            $pageId = $this->getPageIdOfItem($item);
            $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($pageId, $language, $item->getRootPageUid());
            return $solrConfiguration->getIndexQueueFieldsConfigurationByConfigurationName($indexConfigurationName, []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param Item $item
     * @return int
     */
    protected function getPageIdOfItem(Item $item): int
    {
        if ($item->getType() === 'pages') {
            return $item->getRecordUid();
        }
        return $item->getRecordPageId();
    }

    /**
     * The method returns the field configuration of the items root page id (uid of the related root page).
     *
     * @param Item $item
     * @param integer $language
     * @param string $indexConfigurationName
     * @return array
     */
    protected function getFieldConfigurationFromItemRootPage(Item $item, int $language, string $indexConfigurationName): array
    {
        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($item->getRootPageUid(), $language);

        return $solrConfiguration->getIndexQueueFieldsConfigurationByConfigurationName($indexConfigurationName, []);
    }

    /**
     * In case of additionalStoragePid config recordPageId can be outside of siteroot.
     * In that case we should not read TS config of foreign siteroot.
     *
     * @param Item $item
     * @return bool
     */
    protected function isRootPageIdPartOfRootLine(Item $item): bool
    {
        $rootPageId = (int)$item->getRootPageUid();
        $buildRootlineWithPid = $this->getPageIdOfItem($item);
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $buildRootlineWithPid);
        $rootline = $rootlineUtility->get();

        $pageInRootline = array_filter($rootline, function($page) use ($rootPageId) {
            return (int)$page['uid'] === $rootPageId;
        });
        return !empty($pageInRootline);
    }

    /**
     * Converts an item array (record) to a Solr document by mapping the
     * record's fields onto Solr document fields as configured in TypoScript.
     *
     * @param Item $item An index queue item
     * @param int $language Language Id
     *
     * @return Document|null The Solr document converted from the record
     *
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     * @throws Exception
     */
    protected function itemToDocument(Item $item, int $language = 0): ?Document
    {
        $document = null;

        $itemRecord = $this->getFullItemRecord($item, $language);
        if (!is_null($itemRecord)) {
            $itemIndexingConfiguration = $this->getItemTypeConfiguration($item, $language);
            $document = $this->getBaseDocument($item, $itemRecord);
            $pidToUse = $this->getPageIdOfItem($item);
            $tsfe = GeneralUtility::makeInstance(Tsfe::class)->getTsfeByPageIdAndLanguageId($pidToUse, $language, $item->getRootPageUid());
            $document = $this->addDocumentFieldsFromTyposcript($document, $itemIndexingConfiguration, $itemRecord, $tsfe);
        }

        return $document;
    }

    /**
     * Creates a Solr document with the basic / core fields set already.
     *
     * @param Item $item The item to index
     * @param array $itemRecord The record to use to build the base document
     * @return Document A basic Solr document
     */
    protected function getBaseDocument(Item $item, array $itemRecord): Document
    {
        $type = $item->getType();
        $rootPageUid = $item->getRootPageUid();
        $accessRootLine = $this->getAccessRootline($item);
        return $this->documentBuilder->fromRecord($itemRecord, $type, $rootPageUid, $accessRootLine);
    }

    /**
     * Generates an Access Rootline for an item.
     *
     * @param Item $item Index Queue item to index.
     * @return string The Access Rootline for the item
     */
    protected function getAccessRootline(Item $item)
    {
        $accessRestriction = '0';
        $itemRecord = $item->getRecord();

        // TODO support access restrictions set on storage page

        if (isset($GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['fe_group'])) {
            $accessRestriction = $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['fe_group']];

            if (empty($accessRestriction)) {
                // public
                $accessRestriction = '0';
            }
        }

        return 'r:' . $accessRestriction;
    }

    /**
     * Sends the documents to the field processing service which takes care of
     * manipulating fields as defined in the field's configuration.
     *
     * @param Item $item An index queue item
     * @param array $documents An array of \ApacheSolrForTypo3\Solr\System\Solr\Document\Document objects to manipulate.
     * @return Document[] array Array of manipulated Document objects.
     */
    protected function processDocuments(Item $item, array $documents)
    {
        // needs to respect the TS settings for the page the item is on, conditions may apply
        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($item->getRootPageUid());

        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $solrConfiguration = $siteRepository->getSiteByPageId($item->getRootPageUid())->getSolrConfiguration();
        $fieldProcessingInstructions = $solrConfiguration->getIndexFieldProcessingInstructionsConfiguration();

        // same as in the FE indexer
        if (is_array($fieldProcessingInstructions)) {
            $service = GeneralUtility::makeInstance(Service::class);
            $service->processDocuments($documents, $fieldProcessingInstructions);
        }

        return $documents;
    }

    /**
     * Allows third party extensions to provide additional documents which
     * should be indexed for the current item.
     *
     * @param Item $item The item currently being indexed.
     * @param int $language The language uid currently being indexed.
     * @param Document $itemDocument The document representing the item for the given language.
     * @return Document[] array An array of additional Document objects to index.
     */
    protected function getAdditionalDocuments(Item $item, int $language, Document $itemDocument)
    {
        $documents = [];

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'] as $classReference) {
                if (!class_exists($classReference)) {
                    throw new \InvalidArgumentException('Class does not exits' . $classReference, 1490363487);
                }
                $additionalIndexer = GeneralUtility::makeInstance($classReference);
                if ($additionalIndexer instanceof AdditionalIndexQueueItemIndexer) {
                    $additionalDocuments = $additionalIndexer->getAdditionalItemDocuments($item, $language, $itemDocument);

                    if (is_array($additionalDocuments)) {
                        $documents = array_merge($documents,
                            $additionalDocuments);
                    }
                } else {
                    throw new \UnexpectedValueException(
                        get_class($additionalIndexer) . ' must implement interface ' . AdditionalIndexQueueItemIndexer::class,
                        1326284551
                    );
                }
            }
        }
        return $documents;
    }

    /**
     * Provides a hook to manipulate documents right before they get added to
     * the Solr index.
     *
     * @param Item $item The item currently being indexed.
     * @param int $language The language uid of the documents
     * @param array $documents An array of documents to be indexed
     * @return array An array of modified documents
     */
    protected function preAddModifyDocuments(Item $item, int $language, array $documents)
    {
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['preAddModifyDocuments'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['preAddModifyDocuments'] as $classReference) {
                $documentsModifier = GeneralUtility::makeInstance($classReference);

                if ($documentsModifier instanceof PageIndexerDocumentsModifier) {
                    $documents = $documentsModifier->modifyDocuments($item, $language, $documents);
                } else {
                    throw new RuntimeException(
                        'The class "' . get_class($documentsModifier)
                        . '" registered as document modifier in hook
							preAddModifyDocuments must implement interface
							ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerDocumentsModifier',
                        1309522677
                    );
                }
            }
        }

        return $documents;
    }

    // Initialization

    /**
     * Gets the Solr connections applicable for an item.
     *
     * The connections include the default connection and connections to be used
     * for translations of an item.
     *
     * @param Item $item An index queue item
     * @return array An array of ApacheSolrForTypo3\Solr\System\Solr\SolrConnection connections, the array's keys are the sys_language_uid of the language of the connection
     * @throws NoSolrConnectionFoundException
     */
    protected function getSolrConnectionsByItem(Item $item): array
    {
        $solrConnections = [];

        $rootPageId = $item->getRootPageUid();
        if ($item->getType() === 'pages') {
            $pageId = $item->getRecordUid();
        } else {
            $pageId = $item->getRecordPageId();
        }

        // Solr configurations possible for this item
        $site = $item->getSite();
        $solrConfigurationsBySite = $site->getAllSolrConnectionConfigurations();
        $siteLanguages = [];
        foreach ($solrConfigurationsBySite as $solrConfiguration) {
            $siteLanguages[] = $solrConfiguration['language'];
        }

        $defaultLanguageUid = $this->getDefaultLanguageUid($item, $site->getRootPage(), $siteLanguages);
        $translationOverlays = $this->getTranslationOverlaysWithConfiguredSite((int)$pageId, $site, (array)$siteLanguages);

        $defaultConnection = $this->connectionManager->getConnectionByPageId($rootPageId, $defaultLanguageUid, $item->getMountPointIdentifier() ?? '');
        $translationConnections = $this->getConnectionsForIndexableLanguages($translationOverlays);

        if ($defaultLanguageUid == 0) {
            $solrConnections[0] = $defaultConnection;
        }

        foreach ($translationConnections as $systemLanguageUid => $solrConnection) {
            $solrConnections[$systemLanguageUid] = $solrConnection;
        }
        return $solrConnections;
    }

    /**
     * @param int $pageId
     * @param Site $site
     * @param array $siteLanguages
     * @return array
     */
    protected function getTranslationOverlaysWithConfiguredSite(int $pageId, Site $site, array $siteLanguages): array
    {
        $translationOverlays = $this->pagesRepository->findTranslationOverlaysByPageId($pageId);
        $translatedLanguages = [];
        foreach ($translationOverlays as $key => $translationOverlay) {
            if (!in_array($translationOverlay['sys_language_uid'], $siteLanguages)) {
                unset($translationOverlays[$key]);
            } else {
                $translatedLanguages[] = (int)$translationOverlay['sys_language_uid'];
            }
        }

        if (count($translationOverlays) + 1 !== count($siteLanguages)) {
            // not all Languages are translated
            // add Language Fallback
            foreach ($siteLanguages as $languageId) {
                if ($languageId !== 0 && !in_array((int)$languageId, $translatedLanguages, true)) {
                    $fallbackLanguageIds = $this->getFallbackOrder($site, (int)$languageId, (int)$pageId);
                    foreach ($fallbackLanguageIds as $fallbackLanguageId) {
                        if ($fallbackLanguageId === 0 || in_array((int)$fallbackLanguageId, $translatedLanguages, true)) {
                            $translationOverlay = [
                                'pid' => $pageId,
                                'sys_language_uid' => $languageId,
                                'l10n_parent' => $pageId
                            ];
                            $translationOverlays[] = $translationOverlay;
                            continue 2;
                        }
                    }
                }
            }
        }
        return $translationOverlays;
    }

    /**
     * @param Site $site
     * @param int $languageId
     * @param int $pageId
     * @return array
     */
    protected function getFallbackOrder(Site $site,  int $languageId, int $pageId): array
    {
        $fallbackChain = [];
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByRootPageId($site->getRootPageId());
            $languageAspect = LanguageAspectFactory::createFromSiteLanguage($site->getLanguageById($languageId));
            $fallbackChain = $languageAspect->getFallbackChain();
        } catch (SiteNotFoundException $e) {

        }
        return $fallbackChain;
    }

    /**
     * @param Item $item An index queue item
     * @param array $rootPage
     * @param array $siteLanguages
     *
     * @return int
     * @throws RuntimeException
     */
    protected function getDefaultLanguageUid(Item $item, array $rootPage, array $siteLanguages)
    {
        $defaultLanguageUid = 0;
        if (($rootPage['l18n_cfg'] & 1) == 1 && count($siteLanguages) == 1 && $siteLanguages[min(array_keys($siteLanguages))] > 0) {
            $defaultLanguageUid = $siteLanguages[min(array_keys($siteLanguages))];
        } elseif (($rootPage['l18n_cfg'] & 1) == 1 && count($siteLanguages) > 1) {
            unset($siteLanguages[array_search('0', $siteLanguages)]);
            $defaultLanguageUid = $siteLanguages[min(array_keys($siteLanguages))];
        } elseif (($rootPage['l18n_cfg'] & 1) == 1 && count($siteLanguages) == 1) {
            $message = 'Root page ' . (int)$item->getRootPageUid() . ' is set to hide default translation, but no other language is configured!';
            throw new RuntimeException($message);
        }

        return $defaultLanguageUid;
    }

    /**
     * Checks for which languages connections have been configured and returns
     * these connections.
     *
     * @param array $translationOverlays An array of translation overlays to check for configured connections.
     * @return array An array of ApacheSolrForTypo3\Solr\System\Solr\SolrConnection connections.
     */
    protected function getConnectionsForIndexableLanguages(array $translationOverlays)
    {
        $connections = [];

        foreach ($translationOverlays as $translationOverlay) {
            $pageId = $translationOverlay['l10n_parent'];
            $languageId = $translationOverlay['sys_language_uid'];

            try {
                $connection = $this->connectionManager->getConnectionByPageId($pageId, $languageId);
                $connections[$languageId] = $connection;
            } catch (NoSolrConnectionFoundException $e) {
                // ignore the exception as we seek only those connections
                // actually available
            }
        }

        return $connections;
    }

    // Utility methods

    // FIXME extract log() and setLogging() to ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer
    // FIXME extract an interface Tx_Solr_IndexQueue_ItemInterface

    /**
     * Enables logging dependent on the configuration of the item's site
     *
     * @param Item $item An item being indexed
     * @return    void
     */
    protected function setLogging(Item $item)
    {
        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($item->getRootPageUid());
        $this->loggingEnabled = $solrConfiguration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack(
            $item->getIndexingConfigurationName()
        );
    }

    /**
     * Logs the item and what document was created from it
     *
     * @param Item $item The item that is being indexed.
     * @param array $itemDocuments An array of Solr documents created from the item's data
     * @param ResponseAdapter $response The Solr response for the particular index document
     */
    protected function log(Item $item, array $itemDocuments, ResponseAdapter $response)
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $message = 'Index Queue indexing ' . $item->getType() . ':' . $item->getRecordUid() . ' - ';

        // preparing data
        $documents = [];
        foreach ($itemDocuments as $document) {
            $documents[] = (array)$document;
        }

        $logData = ['item' => (array)$item, 'documents' => $documents, 'response' => (array)$response];

        if ($response->getHttpStatus() == 200) {
            $severity = SolrLogManager::NOTICE;
            $message .= 'Success';
        } else {
            $severity = SolrLogManager::ERROR;
            $message .= 'Failure';

            $logData['status'] = $response->getHttpStatus();
            $logData['status message'] = $response->getHttpStatusMessage();
        }

        $this->logger->log($severity, $message, $logData);
    }

    /**
     * Returns the language field from given table or null
     *
     * @param string $tableName
     * @return string|null
     */
    protected function getLanguageFieldFromTable(string $tableName): ?string
    {
        $tableControl = $GLOBALS['TCA'][$tableName]['ctrl'] ?? [];

        if (!empty($tableControl['languageField'])) {
            return $tableControl['languageField'];
        }

        return null;
    }

    /**
     * Checks the given language, if it is in "free" mode.
     *
     * @param Item $item
     * @param int $language
     * @return bool
     */
    protected function isLanguageInAFreeContentMode(Item $item, int $language): bool
    {
        if ($language === 0) {
            return false;
        }
        $typo3site = $item->getSite()->getTypo3SiteObject();
        $typo3siteLanguage = $typo3site->getLanguageById($language);
        $typo3siteLanguageFallbackType = $typo3siteLanguage->getFallbackType();
        if ($typo3siteLanguageFallbackType === 'free') {
            return true;
        }
        return false;
    }
}
