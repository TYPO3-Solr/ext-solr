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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentsAreIndexedEvent;
use ApacheSolrForTypo3\Solr\Exception as EXTSolrException;
use ApacheSolrForTypo3\Solr\FieldProcessor\Service;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\FrontendEnvironment\Exception\Exception as FrontendEnvironmentException;
use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\IndexQueue\Exception\IndexingException;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * A general purpose indexer to be used for indexing of any kind of regular
 * records like news records, tt_address, and so on.
 * Specialized indexers can extend this class to handle advanced stuff like
 * category resolution in news records or file indexing.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @copyright  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 */
class Indexer extends AbstractIndexer
{
    /**
     * A Solr service instance to interact with the Solr server
     */
    protected ?SolrConnection $currentlyUsedSolrConnection;

    protected ConnectionManager $connectionManager;

    /**
     * Holds options for a specific indexer
     */
    protected array $options = [];

    protected PagesRepository $pagesRepository;

    protected Builder $documentBuilder;

    protected FrontendEnvironment $frontendEnvironment;

    /**
     * To log or not to log... #Shakespeare
     */
    protected bool $loggingEnabled = false;

    protected SolrLogManager $logger;
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        array $options = [],
        PagesRepository $pagesRepository = null,
        Builder $documentBuilder = null,
        ConnectionManager $connectionManager = null,
        FrontendEnvironment $frontendEnvironment = null,
        SolrLogManager $logger = null,
        EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->options = $options;
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
        $this->documentBuilder = $documentBuilder ?? GeneralUtility::makeInstance(Builder::class);
        $this->connectionManager = $connectionManager ?? GeneralUtility::makeInstance(ConnectionManager::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
        $this->logger = $logger ?? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Indexes an item from the indexing queue and returns true when indexed, false when not
     *
     * @throws DBALException
     * @throws EXTSolrException
     * @throws FrontendEnvironmentException
     * @throws NoSolrConnectionFoundException
     * @throws SiteNotFoundException
     * @throws IndexingException
     */
    public function index(Item $item): bool
    {
        $indexed = true;

        $this->type = $item->getType();
        $this->setLogging($item);

        $solrConnections = $this->getSolrConnectionsByItem($item);
        foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
            $this->currentlyUsedSolrConnection = $solrConnection;

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
     *
     * @return bool TRUE if item was indexed successfully, FALSE on failure
     *
     * @throws DBALException
     * @throws EXTSolrException
     * @throws FrontendEnvironmentException
     * @throws IndexingException
     * @throws SiteNotFoundException
     */
    protected function indexItem(Item $item, int $language = 0): bool
    {
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

        $documents = $this->getAdditionalDocuments($itemDocument, $item, $language);

        $documents = $this->processDocuments($item, $documents);

        $event = new BeforeDocumentsAreIndexedEvent($itemDocument, $item->getSite()->getTypo3SiteObject(), $item->getSite()->getTypo3SiteObject()->getLanguageById($language), $item, $documents);
        $event = $this->eventDispatcher->dispatch($event);
        $documents = $event->getDocuments();

        $response = $this->currentlyUsedSolrConnection->getWriteService()->addDocuments($documents);
        if ($response->getHttpStatus() !== 200) {
            $responseData = json_decode($response->getRawResponse() ?? '', true);
            throw new IndexingException(
                $response->getHttpStatusMessage() . ': ' . ($responseData['error']['msg'] ?? $response->getHttpStatus()),
                1678693955
            );
        }

        $this->log($item, $documents, $response);

        return true;
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
     *
     * @return array|null The full record with fields of data to be used for indexing or NULL to prevent an item from being indexed
     *
     * @throws DBALException
     * @throws FrontendEnvironmentException
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
     * @throws DBALException
     * @throws FrontendEnvironmentException
     * @throws SiteNotFoundException
     */
    protected function getItemRecordOverlayed(Item $item, int $language): ?array
    {
        $itemRecord = $item->getRecord();
        $languageField = $GLOBALS['TCA'][$item->getType()]['ctrl']['languageField'] ?? null;
        // skip "free content mode"-record for other languages, if item is a "free content mode"-record
        if ($this->isAFreeContentModeItemRecord($item)
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

    protected function isAFreeContentModeItemRecord(Item $item): bool
    {
        $languageField = $GLOBALS['TCA'][$item->getType()]['ctrl']['languageField'] ?? null;
        $itemRecord = $item->getRecord();

        $l10nParentField = $GLOBALS['TCA'][$item->getType()]['ctrl']['transOrigPointerField'] ?? null;
        if ($languageField === null || $l10nParentField === null) {
            return true;
        }
        $languageOfRecord = (int)($itemRecord[$languageField] ?? null);
        $l10nParentRecordUid = (int)($itemRecord[$l10nParentField] ?? null);

        return $languageOfRecord > 0 && $l10nParentRecordUid === 0;
    }

    /**
     * Gets the configuration how to process an item's fields for indexing.
     *
     * @param Item $item An index queue item
     * @param int $language Language ID
     *
     * @return array Configuration array from TypoScript
     *
     * @throws DBALException
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
     */
    protected function getFieldConfigurationFromItemRecordPage(Item $item, int $language, string $indexConfigurationName): array
    {
        try {
            $pageId = $this->getPageIdOfItem($item);
            $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($pageId, $language, $item->getRootPageUid());
            return $solrConfiguration->getIndexQueueFieldsConfigurationByConfigurationName($indexConfigurationName);
        } catch (Throwable) {
            return [];
        }
    }

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
     * @throws DBALException
     */
    protected function getFieldConfigurationFromItemRootPage(Item $item, int $language, string $indexConfigurationName): array
    {
        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($item->getRootPageUid(), $language);

        return $solrConfiguration->getIndexQueueFieldsConfigurationByConfigurationName($indexConfigurationName);
    }

    /**
     * In case of additionalStoragePid config recordPageId can be outside siteroot.
     * In that case we should not read TS config of foreign siteroot.
     */
    protected function isRootPageIdPartOfRootLine(Item $item): bool
    {
        $rootPageId = (int)$item->getRootPageUid();
        $buildRootlineWithPid = $this->getPageIdOfItem($item);
        /** @var RootlineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $buildRootlineWithPid);
        $rootline = $rootlineUtility->get();

        $pageInRootline = array_filter($rootline, static function ($page) use ($rootPageId) {
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
     * @throws FrontendEnvironmentException
     * @throws SiteNotFoundException
     * @throws DBALException
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
     *
     * @return Document A basic Solr document
     *
     * @throws DBALException
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
     *
     * @return string The Access Rootline for the item
     */
    protected function getAccessRootline(Item $item): string
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
     * Adds the document to the list of all documents (done in the event constructor),
     * and allows to add more documents before processing all of them.
     *
     * @return Document[]
     */
    protected function getAdditionalDocuments(Document $itemDocument, Item $item, int $language): array
    {
        $event = new BeforeDocumentIsProcessedForIndexingEvent(
            $itemDocument,
            $item->getSite()->getTypo3SiteObject(),
            $item->getSite()->getTypo3SiteObject()->getLanguageById($language),
            $item
        );
        $event = $this->eventDispatcher->dispatch($event);
        return $event->getDocuments();
    }

    /**
     * Sends the documents to the field processing service which takes care of
     * manipulating fields as defined in the field's configuration.
     *
     * @param Item $item An index queue item
     * @param array $documents An array of {@link Document} objects to manipulate.
     *
     * @return Document[] An array of manipulated Document objects.
     *
     * @throws DBALException
     * @throws EXTSolrException
     */
    protected function processDocuments(Item $item, array $documents): array
    {
        //        // needs to respect the TS settings for the page the item is on, conditions may apply
        //        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($item->getRootPageUid());

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

    // Initialization

    /**
     * Gets the Solr connections applicable for an item.
     *
     * The connections include the default connection and connections to be used
     * for translations of an item.
     *
     * @return SolrConnection[] An array of connections, the array's keys are the sys_language_uid of the language of the connection
     *
     * @throws NoSolrConnectionFoundException
     * @throws DBALException
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

        $defaultLanguageUid = $this->getDefaultLanguageUid($item, $site->getRootPageRecord(), $siteLanguages);
        $translationOverlays = $this->getTranslationOverlaysWithConfiguredSite((int)$pageId, $site, $siteLanguages);

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
     * Returns the translation overlay
     *
     * @throws DBALException
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
                    $fallbackLanguageIds = $this->getFallbackOrder($site, (int)$languageId);
                    foreach ($fallbackLanguageIds as $fallbackLanguageId) {
                        if ($fallbackLanguageId === 0 || in_array((int)$fallbackLanguageId, $translatedLanguages, true)) {
                            $translationOverlay = [
                                'pid' => $pageId,
                                'sys_language_uid' => $languageId,
                                'l10n_parent' => $pageId,
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
     * Returns the fallback order for sites language
     */
    protected function getFallbackOrder(Site $site, int $languageId): array
    {
        $fallbackChain = [];
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByRootPageId($site->getRootPageId());
            $languageAspect = LanguageAspectFactory::createFromSiteLanguage($site->getLanguageById($languageId));
            $fallbackChain = $languageAspect->getFallbackChain();
        } catch (SiteNotFoundException) {
        }
        return $fallbackChain;
    }

    /**
     * Returns default language id for given root page record and available languages.
     *
     * @throws RuntimeException
     */
    protected function getDefaultLanguageUid(Item $item, array $rootPageRecord, array $siteLanguages): int
    {
        $defaultLanguageUid = 0;
        if (($rootPageRecord['l18n_cfg'] & 1) == 1 && count($siteLanguages) == 1 && $siteLanguages[min(array_keys($siteLanguages))] > 0) {
            $defaultLanguageUid = $siteLanguages[min(array_keys($siteLanguages))];
        } elseif (($rootPageRecord['l18n_cfg'] & 1) == 1 && count($siteLanguages) > 1) {
            unset($siteLanguages[array_search('0', $siteLanguages)]);
            $defaultLanguageUid = $siteLanguages[min(array_keys($siteLanguages))];
        } elseif (($rootPageRecord['l18n_cfg'] & 1) == 1 && count($siteLanguages) == 1) {
            $message = 'Root page ' . (int)$item->getRootPageUid() . ' is set to hide default translation, but no other language is configured!';
            throw new RuntimeException($message);
        }

        return $defaultLanguageUid;
    }

    /**
     * Checks for which languages connections have been configured for translation overlays and returns these connections.
     *
     * @return SolrConnection[]
     *
     * @throws DBALException
     */
    protected function getConnectionsForIndexableLanguages(array $translationOverlays): array
    {
        $connections = [];

        foreach ($translationOverlays as $translationOverlay) {
            $pageId = $translationOverlay['l10n_parent'];
            $languageId = $translationOverlay['sys_language_uid'];

            try {
                $connection = $this->connectionManager->getConnectionByPageId($pageId, $languageId);
                $connections[$languageId] = $connection;
            } catch (NoSolrConnectionFoundException) {
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
     * @throws DBALException
     */
    protected function setLogging(Item $item): void
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
     * @param Document[] $itemDocuments An array of Solr documents created from the item's data
     * @param ResponseAdapter $response The Solr response for the particular index document
     */
    protected function log(Item $item, array $itemDocuments, ResponseAdapter $response): void
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
     * Checks the given language, if it is in "free" mode.
     *
     * @throws DBALException
     */
    protected function isLanguageInAFreeContentMode(Item $item, int $language): bool
    {
        if ($language === 0) {
            return false;
        }
        $typo3site = $item->getSite()->getTypo3SiteObject();
        $typo3siteLanguage = $typo3site->getLanguageById($language);
        $typo3siteLanguageFallbackType = $typo3siteLanguage->getFallbackType();

        return $typo3siteLanguageFallbackType === 'free';
    }
}
