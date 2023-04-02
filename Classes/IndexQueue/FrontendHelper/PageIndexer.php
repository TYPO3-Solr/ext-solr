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
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Logging\DebugWriter;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use ApacheSolrForTypo3\Solr\Util;
use Doctrine\DBAL\Exception as DBALException;
use Throwable;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use UnexpectedValueException;

/**
 * Index Queue Page Indexer frontend helper to ask the frontend page indexer to
 * index the page.
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
     * the page currently being indexed.
     */
    protected TypoScriptFrontendController $page;

    /**
     * Response data
     */
    protected array $responseData = [];

    /**
     * Activates a frontend helper by registering for hooks and other
     * resources required by the frontend helper to work.
     *
     * @noinspection PhpUnused
     */
    public function activate(): void
    {
        $this->isActivated = true;

        // indexes fields defined in plugin.tx_solr.index.queue.pages.fields
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][PageFieldMappingIndexer::class] = PageFieldMappingIndexer::class;

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

        if ($this->request->getParameter('accessRootline')) {
            $stringAccessRootline = $this->request->getParameter('accessRootline');
        }

        return GeneralUtility::makeInstance(Rootline::class, /** @scrutinizer ignore-type */ $stringAccessRootline);
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

                'classFile' => ExtensionManagementUtility::extPath('solr') . 'Classes/IndexQueue/FrontendHelper/AuthorizationService.php',
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
     * Uses the provided GET parameters, page id and language id.
     */
    protected function generatePageUrl(): string
    {
        if ($this->request->getParameter('overridePageUrl')) {
            return $this->request->getParameter('overridePageUrl');
        }

        /* @var ContentObjectRenderer $contentObject */
        $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $typolinkConfiguration = [
            'parameter' => $this->page->id,
            'linkAccessRestrictedPages' => '1',
        ];

        $language = GeneralUtility::_GET('L');
        if (!empty($language)) {
            $typolinkConfiguration['additionalParams'] = '&L=' . $language;
        }

        $url = $contentObject->typoLink_URL($typolinkConfiguration);

        // clean up
        if ($url == '') {
            $url = '/';
        }

        return $url;
    }

    /**
     * Handles the indexing of the page content during AfterCacheableContentIsGeneratedEvent of a generated page.
     */
    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        if (!$this->isActivated) {
            return;
        }

        $this->logger = new SolrLogManager(__CLASS__, GeneralUtility::makeInstance(DebugWriter::class));

        $this->page = $event->getController();
        $configuration = Util::getSolrConfiguration();

        $logPageIndexed = $configuration->getLoggingIndexingPageIndexed();
        if (!$this->page->config['config']['index_enable'] ?? false) {
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
            if (is_null($indexQueueItem)) {
                throw new UnexpectedValueException('Can not get index queue item', 1482162337);
            }

            $solrConnection = $this->getSolrConnection($indexQueueItem);

            /* @var Typo3PageIndexer $indexer */
            $indexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, /** @scrutinizer ignore-type */ $this->page);
            $indexer->setSolrConnection($solrConnection);
            $indexer->setPageAccessRootline($this->getAccessRootline());
            $indexer->setPageUrl($this->generatePageUrl());
            $indexer->setMountPointParameter($GLOBALS['TSFE']->MP);
            $indexer->setIndexQueueItem($indexQueueItem);

            $this->responseData['pageIndexed'] = (int)$indexer->indexPage();
            $this->responseData['originalPageDocument'] = (array)$indexer->getPageSolrDocument();
            $this->responseData['solrConnection'] = [
                'rootPage' => $indexQueueItem->getRootPageUid(),
                'sys_language_uid' => Util::getLanguageUid(),
                'solr' => (string)$solrConnection->getNode('write'),
            ];

            $documentsSentToSolr = $indexer->getDocumentsSentToSolr();
            foreach ($documentsSentToSolr as $document) {
                $this->responseData['documentsSentToSolr'][] = (array)$document;
            }
        } catch (Throwable $e) {
            if ($configuration->getLoggingExceptions()) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Exception while trying to index page ' . $this->page->id,
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
     * Gets the solr connection to use for indexing the page based on the
     * Index Queue item's properties.
     *
     * @throws AspectNotFoundException
     * @throws NoSolrConnectionFoundException
     * @throws DBALException
     */
    protected function getSolrConnection(Item $indexQueueItem): SolrConnection
    {
        /* @var ConnectionManager $connectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        return $connectionManager->getConnectionByRootPageId(
            $indexQueueItem->getRootPageUid(),
            Util::getLanguageUid()
        );
    }

    /**
     * This method retrieves the item from the index queue, that is indexed in this request.
     *
     * @throws DBALException
     */
    protected function getIndexQueueItem(): ?Item
    {
        /* @var Queue $indexQueue */
        $indexQueue = GeneralUtility::makeInstance(Queue::class);
        return $indexQueue->getItem($this->request->getParameter('item'));
    }
}
