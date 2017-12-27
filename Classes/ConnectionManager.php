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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Page\Rootline;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository as PagesRepositoryAtExtSolr;
use ApacheSolrForTypo3\Solr\System\Records\SystemLanguage\SystemLanguageRepository;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Toolbar\ClearCacheActionsHookInterface;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * A class to easily create a connection to a Solr server.
 *
 * Internally keeps track of already existing connections and makes sure that no
 * duplicate connections are created.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class ConnectionManager implements SingletonInterface, ClearCacheActionsHookInterface
{

    /**
     * @var array
     */
    protected static $connections = [];

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Records\SystemLanguage\SystemLanguageRepository
     */
    protected $systemLanguageRepository;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * @var PagesRepositoryAtExtSolr
     */
    protected $pagesRepositoryAtExtSolr;

    /**
     * @param SystemLanguageRepository $systemLanguageRepository
     * @param PagesRepositoryAtExtSolr|null $pagesRepositoryAtExtSolr
     * @param SolrLogManager $solrLogManager
     */
    public function __construct(SystemLanguageRepository $systemLanguageRepository = null, PagesRepositoryAtExtSolr $pagesRepositoryAtExtSolr = null, SolrLogManager $solrLogManager = null)
    {
        $this->systemLanguageRepository = isset($systemLanguageRepository) ? $systemLanguageRepository : GeneralUtility::makeInstance(SystemLanguageRepository::class);
        $this->pagesRepositoryAtExtSolr = isset($pagesRepositoryAtExtSolr) ? $pagesRepositoryAtExtSolr : GeneralUtility::makeInstance(PagesRepositoryAtExtSolr::class);
        $this->logger                   = isset($solrLogManager) ? $solrLogManager : GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
    }

    /**
     * Gets a Solr connection.
     *
     * Instead of generating a new connection with each call, connections are
     * kept and checked whether the requested connection already exists. If a
     * connection already exists, it's reused.
     *
     * @param string $host Solr host (optional)
     * @param int $port Solr port (optional)
     * @param string $path Solr path (optional)
     * @param string $scheme Solr scheme, defaults to http, can be https (optional)
     * @param string $username Solr user name (optional)
     * @param string $password Solr password (optional)
     * @return SolrConnection A solr connection.
     */
    public function getConnection($host = '', $port = 8983, $path = '/solr/', $scheme = 'http', $username = '', $password = '')
    {
        if (empty($host)) {
            $this->logger->log(
                SolrLogManager::WARNING,
                'ApacheSolrForTypo3\Solr\ConnectionManager::getConnection() called with empty host parameter. Using configuration from TSFE, might be inaccurate. Always provide a host or use the getConnectionBy* methods.'
            );

            $configuration = Util::getSolrConfiguration();
            $host = $configuration->getSolrHost();
            $port = $configuration->getSolrPort();
            $path = $configuration->getSolrPath();
            $scheme = $configuration->getSolrScheme();
            $username = $configuration->getSolrUsername();
            $password = $configuration->getSolrPassword();
        }

        $connectionHash = md5($scheme . '://' . $host . $port . $path . $username . $password);
        if (!isset(self::$connections[$connectionHash])) {
            $connection = $this->buildSolrConnection($host, $port, $path, $scheme, $username, $password);
            self::$connections[$connectionHash] = $connection;
        }

        return self::$connections[$connectionHash];
    }

    /**
     * Create a Solr Service instance from the passed connection configuration.
     *
     * @param string $host
     * @param int $port
     * @param string $path
     * @param string $scheme
     * @param string $username
     * @param string $password
     * @return SolrConnection|object
     */
    protected function buildSolrConnection($host, $port, $path, $scheme, $username = '', $password = '')
    {
        return GeneralUtility::makeInstance(SolrConnection::class, $host, $port, $path, $scheme, $username, $password);
    }

    /**
     * Creates a solr configuration from the configuration array and returns it.
     *
     * @param array $config The solr configuration array
     * @return SolrConnection
     */
    protected function getConnectionFromConfiguration(array $config)
    {
        return $this->getConnection(
            $config['solrHost'],
            $config['solrPort'],
            $config['solrPath'],
            $config['solrScheme'],
            $config['solrUsername'],
            $config['solrPassword']
        );
    }

    /**
     * Gets a Solr configuration for a page ID.
     *
     * @param int $pageId A page ID.
     * @param int $language The language ID to get the connection for as the path may differ. Optional, defaults to 0.
     * @param string $mount Comma list of MountPoint parameters
     * @return array A solr configuration.
     * @throws NoSolrConnectionFoundException
     */
    public function getConfigurationByPageId($pageId, $language = 0, $mount = '')
    {
        // find the root page
        $pageSelect = GeneralUtility::makeInstance(PageRepository::class);

        /** @var Rootline $rootLine */
        $rootLine = GeneralUtility::makeInstance(Rootline::class, $pageSelect->getRootLine($pageId, $mount));
        $siteRootPageId = $rootLine->getRootPageId();

        try {
            $solrConfiguration = $this->getConfigurationByRootPageId($siteRootPageId, $language);
        } catch (NoSolrConnectionFoundException $nscfe) {
            /* @var $noSolrConnectionException NoSolrConnectionFoundException */
            $noSolrConnectionException = GeneralUtility::makeInstance(
                NoSolrConnectionFoundException::class,
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
     * @param int $pageId A page ID.
     * @param int $language The language ID to get the connection for as the path may differ. Optional, defaults to 0.
     * @param string $mount Comma list of MountPoint parameters
     * @return SolrConnection A solr connection.
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionByPageId($pageId, $language = 0, $mount = '')
    {
        $solrServer = $this->getConfigurationByPageId($pageId, $language, $mount);
        $solrConnection = $this->getConnectionFromConfiguration($solrServer);
        return $solrConnection;
    }

    /**
     * Gets a Solr configuration for a root page ID.
     *
     * @param int $pageId A root page ID.
     * @param int $language The language ID to get the configuration for as the path may differ. Optional, defaults to 0.
     * @return array A solr configuration.
     * @throws NoSolrConnectionFoundException
     */
    public function getConfigurationByRootPageId($pageId, $language = 0)
    {
        $connectionKey = $pageId . '|' . $language;
        $solrServers = $this->getAllConfigurations();

        if (isset($solrServers[$connectionKey])) {
            $solrConfiguration = $solrServers[$connectionKey];
        } else {
            /* @var $noSolrConnectionException NoSolrConnectionFoundException */
            $noSolrConnectionException = GeneralUtility::makeInstance(
                NoSolrConnectionFoundException::class,
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
     * @param int $pageId A root page ID.
     * @param int $language The language ID to get the connection for as the path may differ. Optional, defaults to 0.
     * @return SolrConnection A solr connection.
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionByRootPageId($pageId, $language = 0)
    {
        $config = $this->getConfigurationByRootPageId($pageId, $language);
        $solrConnection = $this->getConnectionFromConfiguration($config);

        return $solrConnection;
    }

    /**
     * Gets all connection configurations found.
     *
     * @return array An array of connection configurations.
     */
    public function getAllConfigurations()
    {
        /** @var $registry Registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $solrConfigurations = $registry->get('tx_solr', 'servers', []);

        return $solrConfigurations;
    }

    /**
     * Stores the connections in the registry.
     *
     * @param array $solrConfigurations
     */
    protected function setAllConfigurations(array $solrConfigurations)
    {
        /** @var $registry Registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $registry->set('tx_solr', 'servers', $solrConfigurations);
    }

    /**
     * Gets all connections found.
     *
     * @return SolrConnection[] An array of initialized ApacheSolrForTypo3\Solr\System\Solr\SolrConnection connections
     */
    public function getAllConnections()
    {
        $solrConnections = [];

        $solrConfigurations = $this->getAllConfigurations();
        foreach ($solrConfigurations as $solrConfiguration) {
            $solrConnections[] = $this->getConnectionFromConfiguration($solrConfiguration);
        }

        return $solrConnections;
    }

    /**
     * Gets all connection configurations for a given site.
     *
     * @param Site $site A TYPO3 site
     * @return array An array of Solr connection configurations for a site
     */
    public function getConfigurationsBySite(Site $site)
    {
        $solrConfigurations = [];

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
     * @return SolrConnection[] An array of Solr connection objects (ApacheSolrForTypo3\Solr\System\Solr\SolrConnection)
     */
    public function getConnectionsBySite(Site $site)
    {
        $connections = [];

        $solrServers = $this->getConfigurationsBySite($site);
        foreach ($solrServers as $solrServer) {
            $connections[] = $this->getConnectionFromConfiguration($solrServer);
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
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $optionValues[] = 'clearSolrConnectionCache';
            $cacheActions[] = [
                'id' => 'clearSolrConnectionCache',
                'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:cache_initialize_solr_connections',
                'href' => $uriBuilder->buildUriFromRoute('ajax_solr_updateConnections'),
                'iconIdentifier' => 'extensions-solr-module-initsolrconnections'
            ];
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
            $this->setAllConfigurations($solrConnections);
        }
    }

    /**
     * Updates the Solr connections for a specific root page ID / site.
     *
     * @param int $rootPageId A site root page id
     */
    public function updateConnectionByRootPageId($rootPageId)
    {
        $systemLanguages = $this->systemLanguageRepository->findSystemLanguages();
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByRootPageId($rootPageId);
        $rootPage = $site->getRootPage();

        $updatedSolrConnections = [];
        foreach ($systemLanguages as $languageId) {
            $connection = $this->getConfiguredSolrConnectionByRootPage($rootPage, $languageId);

            if (!empty($connection)) {
                $updatedSolrConnections[$connection['connectionKey']] = $connection;
            }
        }

        $solrConnections = $this->getAllConfigurations();
        $solrConnections = array_merge($solrConnections, $updatedSolrConnections);
        $solrConnections = $this->filterDuplicateConnections($solrConnections);
        $this->setAllConfigurations($solrConnections);
    }

    /**
     * Finds the configured Solr connections. Also respects multi-site
     * environments.
     *
     * @return array An array with connections, each connection with keys rootPageTitle, rootPageUid, solrHost, solrPort, solrPath
     */
    protected function getConfiguredSolrConnections()
    {
        $configuredSolrConnections = [];
        // find website roots and languages for this installation
        $rootPages = $this->pagesRepositoryAtExtSolr->findAllRootPages();
        $languages = $this->systemLanguageRepository->findSystemLanguages();

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
     * @param int $languageId ID of a system language
     * @return array A solr connection configuration.
     */
    protected function getConfiguredSolrConnectionByRootPage(array $rootPage, $languageId)
    {
        $connection = [];

        $languageId = (int)$languageId;
        GeneralUtility::_GETset($languageId, 'L');
        $connectionKey = $rootPage['uid'] . '|' . $languageId;

        $pageSelect = GeneralUtility::makeInstance(PageRepository::class);
        $rootLine = $pageSelect->getRootLine($rootPage['uid']);

        $tmpl = GeneralUtility::makeInstance(ExtendedTemplateService::class);
        $tmpl->tt_track = false; // Do not log time-performance information
        $tmpl->init();
        $tmpl->runThroughTemplates($rootLine); // This generates the constants/config + hierarchy info for the template.

        // fake micro TSFE to get correct condition parsing
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->tmpl = new \stdClass();
        $GLOBALS['TSFE']->cObjectDepthCounter = 50;
        $GLOBALS['TSFE']->tmpl->rootLine = $rootLine;
        $GLOBALS['TSFE']->sys_page = $pageSelect;
        $GLOBALS['TSFE']->id = $rootPage['uid'];
        $GLOBALS['TSFE']->page = $rootPage;

        $tmpl->generateConfig();
        $GLOBALS['TSFE']->tmpl->setup = $tmpl->setup;

        $configuration = Util::getSolrConfigurationFromPageId($rootPage['uid'], false, $languageId);

        $solrIsEnabledAndConfigured = $configuration->getEnabled() && $configuration->getSolrHasConnectionConfiguration();
        if (!$solrIsEnabledAndConfigured) {
            return $connection;
        }

        $connection = [
            'connectionKey' => $connectionKey,
            'rootPageTitle' => $rootPage['title'],
            'rootPageUid' => $rootPage['uid'],
            'solrScheme' => $configuration->getSolrScheme(),
            'solrHost' => $configuration->getSolrHost(),
            'solrPort' => $configuration->getSolrPort(),
            'solrPath' => $configuration->getSolrPath(),
            'solrUsername' => $configuration->getSolrUsername(),
            'solrPassword' => $configuration->getSolrPassword(),

            'language' => $languageId
        ];

        $connection['label'] = $this->buildConnectionLabel($connection);
        return $connection;
    }



    /**
     * Creates a human readable label from the connections' configuration.
     *
     * @param array $connection Connection configuration
     * @return string Connection label
     */
    protected function buildConnectionLabel(array $connection)
    {
        return $connection['rootPageTitle']
            . ' (pid: ' . $connection['rootPageUid']
            . ', language: ' . $this->systemLanguageRepository->findOneLanguageTitleByLanguageId($connection['language'])
            . ') - '
//            . $connection['solrScheme'] . '://'
            . $connection['solrHost'] . ':'
            . $connection['solrPort']
            . $connection['solrPath'];
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
        $hashedConnections = [];
        $filteredConnections = [];

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
}
