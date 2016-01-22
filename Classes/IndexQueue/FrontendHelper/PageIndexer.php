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
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\SolrService;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Index Queue Page Indexer frontend helper to ask the frontend page indexer to
 * index the page.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class PageIndexer extends AbstractFrontendHelper
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
    protected $responseData = array();


    /**
     * Activates a frontend helper by registering for hooks and other
     * resources required by the frontend helper to work.
     */
    public function activate()
    {
        $pageIndexingHookRegistration = '&ApacheSolrForTypo3\\Solr\\IndexQueue\\FrontendHelper\\PageIndexer';

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['initFEuser'][__CLASS__] = $pageIndexingHookRegistration . '->authorizeFrontendUser';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['tslib_fe-PostProc'][__CLASS__] = $pageIndexingHookRegistration . '->disableCaching';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['pageIndexing'][__CLASS__] = $pageIndexingHookRegistration;

        // indexes fields defined in plugin.tx_solr.index.queue.pages.fields
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['ApacheSolrForTypo3\\Solr\\IndexQueue\\FrontendHelper\\PageFieldMappingIndexer'] = 'ApacheSolrForTypo3\\Solr\\IndexQueue\\FrontendHelper\\PageFieldMappingIndexer';

        // making sure this instance is reused when called by the hooks registered before
        // \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction() and \TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj() use
        // these storages while the object was instantiated by
        // ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager before.
        // \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance() also uses a dedicated cache
        $GLOBALS['T3_VAR']['callUserFunction_classPool'][__CLASS__] = $this;
        $GLOBALS['T3_VAR']['getUserObj'][$pageIndexingHookRegistration] = $this;

        $this->registerAuthorizationService();
# Since TypoScript is not available at this point we cannot bind it to some TS configuration option whether to log or not
#		\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Registered Solr Page Indexer authorization service', 'solr', 1, array(
#			'auth services' => $GLOBALS['T3_SERVICES']['auth']
#		));
    }

    /**
     * Returns the status of whether a page was indexed.
     *
     * @return array Page indexed status.
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
     */
    public function authorizeFrontendUser()
    {
        $accessRootline = $this->getAccessRootline();
        $stringAccessRootline = (string)$accessRootline;

        if (empty($stringAccessRootline)) {
            return;
        }

        if (!is_array($GLOBALS['TSFE']->fe_user->user)) {
            $GLOBALS['TSFE']->fe_user->user = array();
        }

        $groups = $accessRootline->getGroups();
        $groupList = implode(',', $groups);

        $GLOBALS['TSFE']->fe_user->user['username'] = AuthorizationService::SOLR_INDEXER_USERNAME;
        $GLOBALS['TSFE']->fe_user->user['usergroup'] = $groupList;

        $this->responseData['authorization'] = array(
            'username' => $GLOBALS['TSFE']->fe_user->user['username'],
            'usergroups' => $GLOBALS['TSFE']->fe_user->user['usergroup']
        );
    }

    /**
     * Gets the access rootline as defined by the request.
     *
     * @return \ApacheSolrForTypo3\Solr\Access\Rootline The access rootline to use for indexing.
     */
    protected function getAccessRootline()
    {
        $stringAccessRootline = '';

        if ($this->request->getParameter('accessRootline')) {
            $stringAccessRootline = $this->request->getParameter('accessRootline');
        }

        $accessRootline = GeneralUtility::makeInstance(
            'ApacheSolrForTypo3\\Solr\\Access\\Rootline',
            $stringAccessRootline
        );

        return $accessRootline;
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
            'ApacheSolrForTypo3\\Solr\\IndexQueue\\FrontendHelper\\AuthorizationService',
            // service key
            array( // service meta data
                'title' => 'Solr Indexer Authorization',
                'description' => 'Authorizes the Solr Index Queue indexer to access protected pages.',

                'subtype' => 'getUserFE,authUserFE,getGroupsFE',

                'available' => true,
                'priority' => $overrulingPriority,
                'quality' => 100,

                'os' => '',
                'exec' => '',

                'classFile' => $GLOBALS['PATH_solr'] . 'Classes/IndexQueue/FrontendHelper/AuthorizationService.php',
                'className' => 'ApacheSolrForTypo3\\Solr\\IndexQueue\\FrontendHelper\\AuthorizationService',
            )
        );
    }

    /**
     * Determines the highest priority of all registered authentication
     * services.
     *
     * @return integer Highest priority of all registered authentication service
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

        $contentObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');

        $typolinkConfiguration = array(
            'parameter' => intval($this->page->id),
            'linkAccessRestrictedPages' => '1'
        );

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
     */
    public function hook_indexContent(TypoScriptFrontendController $page)
    {
        $this->page = $page;
        $configuration = Util::getSolrConfiguration();

        if (!$this->page->config['config']['index_enable']) {
            if ($configuration['logging.']['indexing.']['pageIndexed']) {
                GeneralUtility::devLog('Indexing is disabled. Set config.index_enable = 1 .',
                    'solr', 3);
            }
            return;
        }

        try {
            $indexer = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Typo3PageIndexer',
                $page);
            $indexer->setSolrConnection($this->getSolrConnection());
            $indexer->setPageAccessRootline($this->getAccessRootline());
            $indexer->setPageUrl($this->generatePageUrl());
            $indexer->setMountPointParameter($GLOBALS['TSFE']->MP);

            $this->responseData['pageIndexed'] = (int)$indexer->indexPage();
            $this->responseData['originalPageDocument'] = (array)$indexer->getPageSolrDocument();

            $documentsSentToSolr = $indexer->getDocumentsSentToSolr();
            foreach ($documentsSentToSolr as $document) {
                $this->responseData['documentsSentToSolr'][] = (array)$document;
            }
        } catch (\Exception $e) {
            if ($configuration['tx_solr.']['logging.']['exceptions']) {
                GeneralUtility::devLog('Exception while trying to index page ' . $page->id,
                    'solr', 3, array(
                        $e->__toString()
                    ));
            }
        }

        if ($configuration['logging.']['indexing.']['pageIndexed']) {
            $success = $this->responseData['pageIndexed'] ? 'Success' : 'Failed';
            $severity = $this->responseData['pageIndexed'] ? -1 : 3;

            GeneralUtility::devLog('Page indexed: ' . $success, 'solr',
                $severity, $this->responseData);
        }
    }

    /**
     * Gets the solr connection to use for indexing the page based on the
     * Index Queue item's properties.
     *
     * @return SolrService Solr server connection
     */
    protected function getSolrConnection()
    {
        $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
        $connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager');

        $indexQueueItem = $indexQueue->getItem(
            $this->request->getParameter('item')
        );

        $solrConnection = $connectionManager->getConnectionByRootPageId(
            $indexQueueItem->getRootPageUid(),
            $GLOBALS['TSFE']->sys_language_uid
        );

        // log the Solr connection used and why
        $this->responseData['solrConnection'] = array(
            'rootPage' => $indexQueueItem->getRootPageUid(),
            'sys_language_uid' => $GLOBALS['TSFE']->sys_language_uid,
            'solr' => (string)$solrConnection
        );

        return $solrConnection;
    }
}
