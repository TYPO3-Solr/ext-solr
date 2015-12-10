<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Backend\Toolbar\ClearCacheActionsHookInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Imaging\Icon;

/**
 * A class to easily create a connection to a Solr server.
 *
 * Internally keeps track of already existing connections and makes sure that no
 * duplicate connections are created.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class ConnectionManager implements SingletonInterface, ClearCacheActionsHookInterface
{

    /**
     * @var array
     */
    protected static $connections = array();

    /**
     * Gets a Solr connection.
     *
     * Instead of generating a new connection with each call, connections are
     * kept and checked whether the requested connection already exists. If a
     * connection already exists, it's reused.
     *
     * @param string $host Solr host (optional)
     * @param integer $port Solr port (optional)
     * @param string $path Solr path (optional)
     * @param string $scheme Solr scheme, defaults to http, can be https (optional)
     * @return SolrService A solr connection.
     */
    public function getConnection(
        $host = '',
        $port = 8080,
        $path = '/solr/',
        $scheme = 'http'
    ) {
        $connection = null;

        if (empty($host)) {
            GeneralUtility::devLog(
                'ApacheSolrForTypo3\Solr\ConnectionManager::getConnection() called with empty
				host parameter. Using configuration from TSFE, might be
				inaccurate. Always provide a host or use the getConnectionBy*
				methods.',
                'solr',
                2
            );

            $solrConfiguration = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.'];

            $host = $solrConfiguration['host'];
            $port = $solrConfiguration['port'];
            $path = $solrConfiguration['path'];
            $scheme = $solrConfiguration['scheme'];
        }

        $connectionHash = md5($scheme . '://' . $host . $port . $path);

        if (!isset(self::$connections[$connectionHash])) {
            $connection = GeneralUtility::makeInstance(
                'ApacheSolrForTypo3\\Solr\\SolrService',
                $host,
                $port,
                $path,
                $scheme
            );

            self::$connections[$connectionHash] = $connection;
        }

        return self::$connections[$connectionHash];
    }

    /**
     * Gets a Solr configuration for a page ID.
     *
     * @param integer $pageId A page ID.
     * @param integer $language The language ID to get the connection for as the path may differ. Optional, defaults to 0.
     * @param string $mount Comma list of MountPoint parameters
     * @return array A solr configuration.
     * @throws NoSolrConnectionFoundException
     */
    public function getConfigurationByPageId(
        $pageId,
        $language = 0,
        $mount = ''
    ) {
        $solrConfiguration = array();

        // find the root page
        $pageSelect = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
        $rootLine = $pageSelect->getRootLine($pageId, $mount);
        $siteRootPageId = $this->getSiteRootPageIdFromRootLine($rootLine);

        try {
            $solrConfiguration = $this->getConfigurationByRootPageId($siteRootPageId,
                $language);
        } catch (NoSolrConnectionFoundException $nscfe) {
            /* @var $noSolrConnectionException NoSolrConnectionFoundException */
            $noSolrConnectionException = GeneralUtility::makeInstance(
                'ApacheSolrForTypo3\\Solr\\NoSolrConnectionFoundException',
                $nscfe->getMessage() . ' Initial page used was [' . $pageId . ']',
                1275399922
            );
            $noSolrConnectionException->setPageId($pageId);

            throw $noSolrConnectionException;
        }

        return $solrConfiguration;
    }

    /**
     * Gets a Solr connection for a page ID.
     *
     * @param integer $pageId A page ID.
     * @param integer $language The language ID to get the connection for as the path may differ. Optional, defaults to 0.
     * @param string $mount Comma list of MountPoint parameters
     * @return SolrService A solr connection.
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionByPageId($pageId, $language = 0, $mount = '')
    {
        $solrConnection = null;

        $solrServer = $this->getConfigurationByPageId($pageId, $language,
            $mount);
        $solrConnection = $this->getConnection(
            $solrServer['solrHost'],
            $solrServer['solrPort'],
            $solrServer['solrPath'],
            $solrServer['solrScheme']
        );

        return $solrConnection;
    }

    /**
     * Gets a Solr configuration for a root page ID.
     *
     * @param integer $pageId A root page ID.
     * @param integer $language The language ID to get the configuration for as the path may differ. Optional, defaults to 0.
     * @return array A solr configuration.
     * @throws NoSolrConnectionFoundException
     */
    public function getConfigurationByRootPageId($pageId, $language = 0)
    {
        $solrConfiguration = false;
        $connectionKey = $pageId . '|' . $language;

        $registry = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
        $solrServers = $registry->get('tx_solr', 'servers');

        if (isset($solrServers[$connectionKey])) {
            $solrConfiguration = $solrServers[$connectionKey];
        } else {
            /* @var $noSolrConnectionException NoSolrConnectionFoundException */
            $noSolrConnectionException = GeneralUtility::makeInstance(
                'ApacheSolrForTypo3\\Solr\\NoSolrConnectionFoundException',
                'Could not find a Solr connection for root page ['
                . $pageId . '] and language [' . $language . '].',
                1275396474
            );
            $noSolrConnectionException->setRootPageId($pageId);
            $noSolrConnectionException->setLanguageId($language);

            throw $noSolrConnectionException;
        }

        return $solrConfiguration;
    }

    /**
     * Gets a Solr connection for a root page ID.
     *
     * @param integer $pageId A root page ID.
     * @param integer $language The language ID to get the connection for as the path may differ. Optional, defaults to 0.
     * @return SolrService A solr connection.
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionByRootPageId($pageId, $language = 0)
    {
        $solrConnection = null;

        $solrServer = $this->getConfigurationByRootPageId($pageId, $language);
        $solrConnection = $this->getConnection(
            $solrServer['solrHost'],
            $solrServer['solrPort'],
            $solrServer['solrPath'],
            $solrServer['solrScheme']
        );

        return $solrConnection;
    }

    /**
     * Gets all connection configurations found.
     *
     * @return array An array of connection configurations.
     */
    public function getAllConfigurations()
    {
        $registry = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
        $solrConfigurations = $registry->get('tx_solr', 'servers', array());

        return $solrConfigurations;
    }

    /**
     * Gets all connections found.
     *
     * @return SolrService[] An array of initialized ApacheSolrForTypo3\Solr\SolrService connections
     */
    public function getAllConnections()
    {
        $connections = array();

        $solrServers = $this->getAllConfigurations();
        foreach ($solrServers as $solrServer) {
            $connections[] = $this->getConnection(
                $solrServer['solrHost'],
                $solrServer['solrPort'],
                $solrServer['solrPath'],
                $solrServer['solrScheme']
            );
        }

        return $connections;
    }

    /**
     * Gets all connection configurations for a given site.
     *
     * @param Site $site A TYPO3 site
     * @return array An array of Solr connection configurations for a site
     */
    public function getConfigurationsBySite(Site $site)
    {
        $solrConfigurations = array();

        $allConfigurations = $this->getAllConfigurations();
        foreach ($allConfigurations as $configuration) {
            if ($configuration['rootPageUid'] == $site->getRootPageId()) {
                $solrConfigurations[] = $configuration;
            }
        }

        return $solrConfigurations;
    }

    /**
     * Gets all connections configured for a given site.
     *
     * @param Site $site A TYPO3 site
     * @return SolrService[] An array of Solr connection objects (ApacheSolrForTypo3\Solr\SolrService)
     */
    public function getConnectionsBySite(Site $site)
    {
        $connections = array();

        $solrServers = $this->getConfigurationsBySite($site);
        foreach ($solrServers as $solrServer) {
            $connections[] = $this->getConnection(
                $solrServer['solrHost'],
                $solrServer['solrPort'],
                $solrServer['solrPath'],
                $solrServer['solrScheme']
            );
        }

        return $connections;
    }


    // updates


    /**
     * Adds a menu entry to the clear cache menu to detect Solr connections.
     *
     * @param array $cacheActions Array of CacheMenuItems
     * @param array $optionValues Array of AccessConfigurations-identifiers (typically  used by userTS with options.clearCache.identifier)
     */
    public function manipulateCacheActions(&$cacheActions, &$optionValues)
    {
        if ($GLOBALS['BE_USER']->isAdmin()) {
            $title = 'Initialize Solr connections';
            $iconFactory = GeneralUtility::makeInstance('TYPO3\CMS\Core\Imaging\IconFactory');

            $cacheActions[] = array(
                'id' => 'clearSolrConnectionCache',
                'title' => $title,
                'href' => BackendUtility::getAjaxUrl('solr::clearSolrConnectionCache'),
                'icon' => $iconFactory->getIcon('extensions-solr-module-initsolrconnections', Icon::SIZE_SMALL)
            );
            $optionValues[] = 'clearSolrConnectionCache';
        }
    }

    /**
     * Updates the connections in the registry.
     *
     */
    public function updateConnections()
    {
        $solrConnections = $this->getConfiguredSolrConnections();
        $solrConnections = $this->filterDuplicateConnections($solrConnections);

        if (!empty($solrConnections)) {
            $registry = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
            $registry->set('tx_solr', 'servers', $solrConnections);
        }
    }

    /**
     * Updates the Solr connections for a specific root page ID / site.
     *
     * @param integer $rootPageId A site root page id
     */
    public function updateConnectionByRootPageId($rootPageId)
    {
        $systemLanguages = $this->getSystemLanguages();
        $rootPage = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Site',
            $rootPageId)->getRootPage();

        $updatedSolrConnections = array();
        foreach ($systemLanguages as $languageId) {
            $connection = $this->getConfiguredSolrConnectionByRootPage($rootPage,
                $languageId);

            if (!empty($connection)) {
                $updatedSolrConnections[$connection['connectionKey']] = $connection;
            }
        }

        $registry = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
        $solrConnections = $registry->get('tx_solr', 'servers', array());

        $solrConnections = array_merge($solrConnections,
            $updatedSolrConnections);
        $solrConnections = $this->filterDuplicateConnections($solrConnections);

        $registry->set('tx_solr', 'servers', $solrConnections);
    }

    /**
     * Finds the configured Solr connections. Also respects multi-site
     * environments.
     *
     * @return array An array with connections, each connection with keys rootPageTitle, rootPageUid, solrHost, solrPort, solrPath
     */
    protected function getConfiguredSolrConnections()
    {
        $configuredSolrConnections = array();

        // find website roots and languages for this installation
        $rootPages = $this->getRootPages();
        $languages = $this->getSystemLanguages();

        // find solr configurations and add them as function menu entries
        foreach ($rootPages as $rootPage) {
            foreach ($languages as $languageId) {
                $connection = $this->getConfiguredSolrConnectionByRootPage($rootPage,
                    $languageId);

                if (!empty($connection)) {
                    $configuredSolrConnections[$connection['connectionKey']] = $connection;
                }
            }
        }

        return $configuredSolrConnections;
    }

    /**
     * Gets the configured Solr connection for a specific root page and language ID.
     *
     * @param array $rootPage A root page record with at least title and uid
     * @param integer $languageId ID of a system language
     * @return array A solr connection configuration.
     */
    protected function getConfiguredSolrConnectionByRootPage(
        array $rootPage,
        $languageId
    ) {
        $connection = array();

        $languageId = intval($languageId);
        GeneralUtility::_GETset($languageId, 'L');
        $connectionKey = $rootPage['uid'] . '|' . $languageId;

        $pageSelect = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
        $rootLine = $pageSelect->getRootLine($rootPage['uid']);

        $tmpl = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\TypoScript\\ExtendedTemplateService');
        $tmpl->tt_track = false; // Do not log time-performance information
        $tmpl->init();
        $tmpl->runThroughTemplates($rootLine); // This generates the constants/config + hierarchy info for the template.

        // fake micro TSFE to get correct condition parsing
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->tmpl = new \stdClass();
        $GLOBALS['TSFE']->tmpl->rootLine = $rootLine;
        $GLOBALS['TSFE']->sys_page = $pageSelect;
        $GLOBALS['TSFE']->id = $rootPage['uid'];
        $GLOBALS['TSFE']->page = $rootPage;

        $tmpl->generateConfig();

        list($solrSetup) = $tmpl->ext_getSetup($tmpl->setup,
            'plugin.tx_solr.solr');
        list(, $solrEnabled) = $tmpl->ext_getSetup($tmpl->setup,
            'plugin.tx_solr.enabled');
        $solrEnabled = !empty($solrEnabled) ? true : false;

        if (!empty($solrSetup) && $solrEnabled) {
            $solrPath = trim($solrSetup['path'], '/');
            $solrPath = '/' . $solrPath . '/';

            $connection = array(
                'connectionKey' => $connectionKey,

                'rootPageTitle' => $rootPage['title'],
                'rootPageUid' => $rootPage['uid'],

                'solrScheme' => $solrSetup['scheme'],
                'solrHost' => $solrSetup['host'],
                'solrPort' => $solrSetup['port'],
                'solrPath' => $solrPath,

                'language' => $languageId
            );
            $connection['label'] = $this->buildConnectionLabel($connection);
        }

        return $connection;
    }

    /**
     * Gets the language name for a given language ID.
     *
     * @param integer $languageId language ID
     * @return string Language name
     */
    protected function getLanguageName($languageId)
    {
        $languageName = '';

        $language = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid, title',
            'sys_language',
            'uid = ' . (integer)$languageId
        );

        if (count($language)) {
            $languageName = $language[0]['title'];
        } elseif ($languageId == 0) {
            $languageName = 'default';
        }

        return $languageName;
    }

    /**
     * Creates a human readable label from the connections' configuration.
     *
     * @param array $connection Connection configuration
     * @return string Connection label
     */
    protected function buildConnectionLabel(array $connection)
    {
        $connectionLabel = $connection['rootPageTitle']
            . ' (pid: ' . $connection['rootPageUid']
            . ', language: ' . $this->getLanguageName($connection['language'])
            . ') - '
#			. $connection['solrScheme'] . '://'
            . $connection['solrHost'] . ':'
            . $connection['solrPort']
            . $connection['solrPath'];

        return $connectionLabel;
    }

    /**
     * Filters duplicate connections. When detecting the configured connections
     * this is done with a little brute force by simply combining all root pages
     * with all languages, this method filters out the duplicates.
     *
     * @param array $connections An array of unfiltered connections, containing duplicates
     * @return array An array with connections, no duplicates.
     */
    protected function filterDuplicateConnections(array $connections)
    {
        $hashedConnections = array();
        $filteredConnections = array();

        // array_unique() doesn't work on multi dimensional arrays, so we need to flatten it first
        foreach ($connections as $key => $connection) {
            unset($connection['language']);
            $connectionHash = md5(implode('|', $connection));
            $hashedConnections[$key] = $connectionHash;
        }

        $hashedConnections = array_unique($hashedConnections);

        foreach ($hashedConnections as $key => $hash) {
            $filteredConnections[$key] = $connections[$key];
        }

        return $filteredConnections;
    }

    /**
     * Finds the system's configured languages.
     *
     * @return array An array of language IDs
     */
    protected function getSystemLanguages()
    {
        $languages = array(0);

        $languageRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid',
            'sys_language',
            'hidden = 0'
        );

        if (is_array($languageRecords)) {
            foreach ($languageRecords as $languageRecord) {
                $languages[] = $languageRecord['uid'];
            }
        }

        return $languages;
    }

    /**
     * Gets the site's root pages. The "Is root of website" flag must be set,
     * which usually is the case for pages with pid = 0.
     *
     * @return array An array of (partial) root page records, containing the uid and title fields
     */
    protected function getRootPages()
    {
        $rootPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid, title',
            'pages',
            'is_siteroot = 1 AND deleted = 0 AND hidden = 0 AND pid != -1'
        );

        return $rootPages;
    }

    /**
     * Finds the page Id of the page marked as "Is site root" even if it's not
     * on the root level (pid = 0).
     *
     * @param array $rootLine A root line as generated by \TYPO3\CMS\Frontend\Page\PageRepository::getRootLine()
     * @return integer The site root's page Id
     */
    protected function getSiteRootPageIdFromRootLine(array $rootLine)
    {
        $siteRootPageId = 0;

        foreach ($rootLine as $page) {
            if ($page['is_siteroot']) {
                $siteRootPageId = $page['uid'];
                break;
            }
        }

        return $siteRootPageId;
    }
}
