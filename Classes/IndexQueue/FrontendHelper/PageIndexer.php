<?php

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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
use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use ApacheSolrForTypo3\Solr\Util;
use Exception;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use UnexpectedValueException;

/**
 * Index Queue Page Indexer frontend helper to ask the frontend page indexer to
 * index the page.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageIndexer extends AbstractFrontendHelper implements SingletonInterface
{

    /**
     * This frontend helper's executed action.
     *
     * @var string
     */
    protected $action = 'indexPage';

    /**
     * the page currently being indexed.
     *
     * @var TypoScriptFrontendController
     */
    protected $page;

    /**
     * Response data
     *
     * @var array
     */
    protected $responseData = [];

    /**
     * @var Logger
     */
    protected $logger = null;

    /**
     * Activates a frontend helper by registering for hooks and other
     * resources required by the frontend helper to work.
     *
     * @noinspection PhpUnused
     */
    public function activate()
    {
        $pageIndexingHookRegistration = PageIndexer::class;

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['pageIndexing'][__CLASS__] = $pageIndexingHookRegistration;

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
    public function getData()
    {
        return $this->responseData;
    }

    #
    # Indexer authorisation for access restricted pages / content
    #

    /**
     * Fakes a logged in user to retrieve access restricted content.
     *
     * @return void
     * @noinspection PhpUnused
     */
    public function authorizeFrontendUser()
    {
        $accessRootline = $this->getAccessRootline();
        $stringAccessRootline = (string)$accessRootline;

        if (empty($stringAccessRootline)) {
            return;
        }

        if (!is_array($GLOBALS['TSFE']->fe_user->user)) {
            $GLOBALS['TSFE']->fe_user->user = [];
        }

        $groups = $accessRootline->getGroups();
        $groupList = implode(',', $groups);

        $GLOBALS['TSFE']->fe_user->user['username'] = AuthorizationService::SOLR_INDEXER_USERNAME;
        $GLOBALS['TSFE']->fe_user->user['usergroup'] = $groupList;

        $this->responseData['authorization'] = [
            'username' => $GLOBALS['TSFE']->fe_user->user['username'],
            'usergroups' => $GLOBALS['TSFE']->fe_user->user['usergroup']
        ];
    }

    /**
     * Gets the access rootline as defined by the request.
     *
     * @return Rootline The access rootline to use for indexing.
     */
    protected function getAccessRootline()
    {
        $stringAccessRootline = '';

        if ($this->request->getParameter('accessRootline')) {
            $stringAccessRootline = $this->request->getParameter('accessRootline');
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return GeneralUtility::makeInstance(Rootline::class, /** @scrutinizer ignore-type */ $stringAccessRootline);
    }

    /**
     * Registers an authentication service to authorize / grant the indexer to
     * access protected pages.
     *
     * @return void
     */
    protected function registerAuthorizationService()
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
    protected function getHighestAuthenticationServicePriority()
    {
        $highestPriority = 0;

        if (is_array($GLOBALS['T3_SERVICES']['auth'])) {
            foreach ($GLOBALS['T3_SERVICES']['auth'] as $service) {
                if ($service['priority'] > $highestPriority) {
                    $highestPriority = $service['priority'];
                }
            }
        }

        return $highestPriority;
    }

    #
    # Indexing
    #

    /**
     * Generates the current page's URL.
     *
     * Uses the provided GET parameters, page id and language id.
     *
     * @return string URL of the current page.
     */
    protected function generatePageUrl()
    {
        if ($this->request->getParameter('overridePageUrl')) {
            return $this->request->getParameter('overridePageUrl');
        }

            /** @var $contentObject ContentObjectRenderer */
        $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $typolinkConfiguration = [
            'parameter' => intval($this->page->id),
            'linkAccessRestrictedPages' => '1'
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
     * Handles the indexing of the page content during post processing of a
     * generated page.
     *
     * @param TypoScriptFrontendController $page TypoScript frontend
     * @noinspection PhpUnused
     */
    public function hook_indexContent(TypoScriptFrontendController $page)
    {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);

        $this->page = $page;
        $configuration = Util::getSolrConfiguration();

        $logPageIndexed = $configuration->getLoggingIndexingPageIndexed();
        if (!$this->page->config['config']['index_enable']) {
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

            /** @var $indexer Typo3PageIndexer */
            $indexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, /** @scrutinizer ignore-type */ $page);
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
                'solr' => (string)$solrConnection->getNode('write')
            ];

            $documentsSentToSolr = $indexer->getDocumentsSentToSolr();
            foreach ($documentsSentToSolr as $document) {
                $this->responseData['documentsSentToSolr'][] = (array)$document;
            }
        } catch (Exception $e) {
            if ($configuration->getLoggingExceptions()) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Exception while trying to index page ' . $page->id,
                    [
                        $e->__toString()
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
     * @param Item $indexQueueItem
     * @return SolrConnection Solr server connection
     * @throws NoSolrConnectionFoundException
     */
    protected function getSolrConnection(Item $indexQueueItem)
    {
        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        return $connectionManager->getConnectionByRootPageId(
            $indexQueueItem->getRootPageUid(),
            Util::getLanguageUid()
        );
    }

    /**
     * This method retrieves the item from the index queue, that is indexed in this request.
     *
     * @return Item
     */
    protected function getIndexQueueItem()
    {
        /** @var $indexQueue Queue */
        $indexQueue = GeneralUtility::makeInstance(Queue::class);
        return $indexQueue->getItem($this->request->getParameter('item'));
    }
}
