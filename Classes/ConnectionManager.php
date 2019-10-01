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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository as PagesRepositoryAtExtSolr;
use ApacheSolrForTypo3\Solr\System\Records\SystemLanguage\SystemLanguageRepository;
use ApacheSolrForTypo3\Solr\System\Solr\Node;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;
use function json_encode;

/**
 * ConnectionManager is responsible to create SolrConnection objects.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class ConnectionManager implements SingletonInterface
{

    /**
     * @var array
     */
    protected static $connections = [];

    /**
     * @var SystemLanguageRepository
     */
    protected $systemLanguageRepository;

    /**
     * @var PagesRepositoryAtExtSolr
     */
    protected $pagesRepositoryAtExtSolr;

    /**
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @param SystemLanguageRepository $systemLanguageRepository
     * @param PagesRepositoryAtExtSolr|null $pagesRepositoryAtExtSolr
     * @param SiteRepository $siteRepository
     */
    public function __construct(SystemLanguageRepository $systemLanguageRepository = null, PagesRepositoryAtExtSolr $pagesRepositoryAtExtSolr = null, SiteRepository $siteRepository = null)
    {
        $this->systemLanguageRepository = $systemLanguageRepository ?? GeneralUtility::makeInstance(SystemLanguageRepository::class);
        $this->siteRepository           = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
        $this->pagesRepositoryAtExtSolr = $pagesRepositoryAtExtSolr ?? GeneralUtility::makeInstance(PagesRepositoryAtExtSolr::class);
    }

    /**
     * Creates a solr connection for read and write endpoints
     *
     * @param array $readNodeConfiguration
     * @param array $writeNodeConfiguration
     * @return SolrConnection|object
     */
    public function getSolrConnectionForNodes(array $readNodeConfiguration, array $writeNodeConfiguration)
    {
        $connectionHash = md5(json_encode($readNodeConfiguration) .  json_encode($writeNodeConfiguration));
        if (!isset(self::$connections[$connectionHash])) {
            $readNode = Node::fromArray($readNodeConfiguration);
            $writeNode = Node::fromArray($writeNodeConfiguration);
            self::$connections[$connectionHash] = GeneralUtility::makeInstance(SolrConnection::class, $readNode, $writeNode);
        }
        return self::$connections[$connectionHash];
    }

    /**
     * Creates a solr configuration from the configuration array and returns it.
     *
     * @param array $config The solr configuration array
     * @return SolrConnection
     */
    public function getConnectionFromConfiguration(array $config)
    {
        if(empty($config['read']) && !empty($config['solrHost'])) {
            throw new InvalidArgumentException('Invalid registry data please re-initialize your solr connections');
        }

        return $this->getSolrConnectionForNodes($config['read'], $config['write']);
    }

    /**
     * Gets a Solr configuration for a page ID.
     *
     * @param int $pageId A page ID.
     * @param int $language The language ID to get the connection for as the path may differ. Optional, defaults to 0.
     * @param string $mount Comma list of MountPoint parameters
     * @deprecated will be removed in v11, use Site object/SiteRepository directly
     * @return array A solr configuration.
     * @throws NoSolrConnectionFoundException
     */
    public function getConfigurationByPageId(int $pageId, int $language = 0, string $mount = '')
    {
        trigger_error('solr:deprecation: Method getConfigurationByPageId is deprecated since EXT:solr 10 and will be removed in v11, use Site object/SiteRepository directly.', E_USER_DEPRECATED);

        try {
            $site = $this->siteRepository->getSiteByPageId($pageId, $mount);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);
            return $site->getSolrConnectionConfiguration($language);
        } catch(InvalidArgumentException $e) {
            /* @var NoSolrConnectionFoundException $noSolrConnectionException */
            $noSolrConnectionException = $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
            throw $noSolrConnectionException;
        }
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
        try {
            $site = $this->siteRepository->getSiteByPageId($pageId, $mount);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);
            $config = $site->getSolrConnectionConfiguration($language);
            $solrConnection = $this->getConnectionFromConfiguration($config);
            return $solrConnection;
        } catch(InvalidArgumentException $e) {
            $noSolrConnectionException = $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
            throw $noSolrConnectionException;
        }
    }

    /**
     * Gets a Solr configuration for a root page ID.
     *
     * @param int $pageId A root page ID.
     * @param int $language The language ID to get the configuration for as the path may differ. Optional, defaults to 0.
     * @return array A solr configuration.
     * @throws NoSolrConnectionFoundException
     * @deprecated will be removed in v11, use Site object/SiteRepository directly
     */
    public function getConfigurationByRootPageId($pageId, $language = 0)
    {
        trigger_error('solr:deprecation: Method getConfigurationByRootPageId is deprecated since EXT:solr 10 and will be removed in v11, use Site object/SiteRepository directly.', E_USER_DEPRECATED);

        try {
            $site = $this->siteRepository->getSiteByRootPageId($pageId);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);

            return $site->getSolrConnectionConfiguration($language);
        } catch(InvalidArgumentException $e) {
            /* @var NoSolrConnectionFoundException $noSolrConnectionException */
            $noSolrConnectionException = $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
            throw $noSolrConnectionException;
        }
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
        try {
            $site = $this->siteRepository->getSiteByRootPageId($pageId);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);
            $config = $site->getSolrConnectionConfiguration($language);
            $solrConnection = $this->getConnectionFromConfiguration($config);
            return $solrConnection;
        } catch (InvalidArgumentException $e) {
            /* @var NoSolrConnectionFoundException $noSolrConnectionException */
            $noSolrConnectionException = $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
            throw $noSolrConnectionException;
        }
    }

    /**
     * Gets all connection configurations found.
     *
     * @return array An array of connection configurations.
     * @throws NoSolrConnectionFoundException
     * @deprecated will be removed in v11, use SiteRepository
     */
    public function getAllConfigurations()
    {
        trigger_error('solr:deprecation: Method getAllConfigurations is deprecated since EXT:solr 10 and will be removed in v11, use Site object/SiteRepository directly.', E_USER_DEPRECATED);

        $solrConfigurations = [];
        foreach ($this->siteRepository->getAvailableSites() as $site) {
            foreach ($site->getAllSolrConnectionConfigurations() as $solrConfiguration) {
                $solrConfigurations[] = $solrConfiguration;
            }
        }

        return $solrConfigurations;
    }

    /**
     * Stores the connections in the registry.
     *
     * @param array $solrConfigurations
     * @deprecated will be removed in v11, use SiteRepository
     */
    protected function setAllConfigurations(array $solrConfigurations)
    {
        trigger_error('solr:deprecation: Method setAllConfigurations is deprecated since EXT:solr 10 and will be removed in v11, use Site object/SiteRepository directly.', E_USER_DEPRECATED);

        /** @var $registry Registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $registry->set('tx_solr', 'servers', $solrConfigurations);
    }

    /**
     * Gets all connections found.
     *
     * @return SolrConnection[] An array of initialized ApacheSolrForTypo3\Solr\System\Solr\SolrConnection connections
     * @throws NoSolrConnectionFoundException
     */
    public function getAllConnections()
    {
        $solrConnections = [];
        foreach ($this->siteRepository->getAvailableSites() as $site) {
            foreach ($site->getAllSolrConnectionConfigurations() as $solrConfiguration) {
                $solrConnections[] = $this->getConnectionFromConfiguration($solrConfiguration);
            }
        }

        return $solrConnections;
    }

    /**
     * Gets all connection configurations for a given site.
     *
     * @param Site $site A TYPO3 site
     * @return array An array of Solr connection configurations for a site
     * @throws NoSolrConnectionFoundException
     * @deprecated will be removed in v11, use $site->getAllSolrConnectionConfigurations()
     */
    public function getConfigurationsBySite(Site $site)
    {
        trigger_error('solr:deprecation: Method getConfigurationsBySite is deprecated since EXT:solr 10 and will be removed in v11, use $site->getAllSolrConnectionConfigurations()', E_USER_DEPRECATED);

        return $site->getAllSolrConnectionConfigurations();
    }

    /**
     * Gets all connections configured for a given site.
     *
     * @param Site $site A TYPO3 site
     * @return SolrConnection[] An array of Solr connection objects (ApacheSolrForTypo3\Solr\System\Solr\SolrConnection)
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionsBySite(Site $site)
    {
        $connections = [];

        foreach ($site->getAllSolrConnectionConfigurations() as $solrConnectionConfiguration) {
            $connections[] = $this->getConnectionFromConfiguration($solrConnectionConfiguration);
        }

        return $connections;
    }

    // updates

    /**
     * Updates the connections in the registry.
     *
     * @deprecated will be removed in v11, use SiteRepository
     */
    public function updateConnections()
    {
        trigger_error('solr:deprecation: Method updateConnections is deprecated since EXT:solr 10 and will be removed in v11, use sitehandling instead', E_USER_DEPRECATED);

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
     * @throws NoSolrConnectionFoundException
     * @deprecated Use TYPO3 site config to configure site/connection info
     */
    public function updateConnectionByRootPageId($rootPageId)
    {
        trigger_error('solr:deprecation: Method updateConnectionByRootPageId is deprecated since EXT:solr 10 and will be removed in v11, use sitehandling instead', E_USER_DEPRECATED);

        $systemLanguages = $this->systemLanguageRepository->findSystemLanguages();
        /* @var SiteRepository $siteRepository */
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
     * @deprecated will be removed in v11, use SiteRepository
     */
    protected function getConfiguredSolrConnections()
    {
        trigger_error('solr:deprecation: Method getConfiguredSolrConnections is deprecated since EXT:solr 10 and will be removed in v11, use sitehandling instead', E_USER_DEPRECATED);

        $configuredSolrConnections = [];
        // find website roots and languages for this installation
        $rootPages = $this->pagesRepositoryAtExtSolr->findAllRootPages();
        $languages = $this->systemLanguageRepository->findSystemLanguages();

        // find solr configurations and add them as function menu entries
        foreach ($rootPages as $rootPage) {
            foreach ($languages as $languageId) {
                $connection = $this->getConfiguredSolrConnectionByRootPage($rootPage, $languageId);

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
     * @deprecated will be removed in v11, use SiteRepository
     */
    protected function getConfiguredSolrConnectionByRootPage(array $rootPage, $languageId)
    {
        trigger_error('solr:deprecation: Method getConfiguredSolrConnectionByRootPage is deprecated since EXT:solr 10 and will be removed in v11, use sitehandling instead', E_USER_DEPRECATED);

        $connection = [];

        $languageId = (int)$languageId;
        GeneralUtility::_GETset($languageId, 'L');
        $connectionKey = $rootPage['uid'] . '|' . $languageId;

        $pageSelect = GeneralUtility::makeInstance(PageRepository::class);

        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $rootPage['uid']);
        try {
            $rootLine = $rootlineUtility->get();
        } catch (RuntimeException $e) {
            $rootLine = [];
        }

        $tmpl = GeneralUtility::makeInstance(ExtendedTemplateService::class);
        $tmpl->tt_track = false; // Do not log time-performance information
        $tmpl->init();
        $tmpl->runThroughTemplates($rootLine); // This generates the constants/config + hierarchy info for the template.

        // fake micro TSFE to get correct condition parsing
        $GLOBALS['TSFE'] = new stdClass();
        $GLOBALS['TSFE']->tmpl = new stdClass();
        $GLOBALS['TSFE']->cObjectDepthCounter = 50;
        $GLOBALS['TSFE']->tmpl->rootLine = $rootLine;
        // @extensionScannerIgnoreLine
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
            'read' => [
                'scheme' => $configuration->getSolrScheme(),
                'host' => $configuration->getSolrHost(),
                'port' => $configuration->getSolrPort(),
                'path' => $configuration->getSolrPath(),
                'username' => $configuration->getSolrUsername(),
                'password' => $configuration->getSolrPassword(),
                'timeout' => $configuration->getSolrTimeout()
            ],
            'write' => [
                'scheme' => $configuration->getSolrScheme('http', 'write'),
                'host' => $configuration->getSolrHost('localhost', 'write'),
                'port' => $configuration->getSolrPort(8983, 'write'),
                'path' => $configuration->getSolrPath('/solr/core_en/', 'write'),
                'username' => $configuration->getSolrUsername('', 'write'),
                'password' => $configuration->getSolrPassword('', 'write'),
                'timeout' => $configuration->getSolrTimeout(0, 'write')
            ],

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
            . ') - Read node: '
            . $connection['read']['host'] . ':'
            . $connection['read']['port']
            . $connection['read']['path']
            .' - Write node: '
            . $connection['write']['host'] . ':'
            . $connection['write']['port']
            . $connection['write']['path'];
    }

    /**
     * Filters duplicate connections. When detecting the configured connections
     * this is done with a little brute force by simply combining all root pages
     * with all languages, this method filters out the duplicates.
     *
     * @param array $connections An array of unfiltered connections, containing duplicates
     * @return array An array with connections, no duplicates.
     * @deprecated will be removed in v11, use SiteRepository
     */
    protected function filterDuplicateConnections(array $connections)
    {
        trigger_error('solr:deprecation: Method filterDuplicateConnections is deprecated since EXT:solr 10 and will be removed in v11, use sitehandling instead', E_USER_DEPRECATED);

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

    /**
     * @param $pageId
     * @param $language
     * @return NoSolrConnectionFoundException
     */
    protected function buildNoConnectionExceptionForPageAndLanguage($pageId, $language): NoSolrConnectionFoundException
    {
        $message = 'Could not find a Solr connection for page [' . $pageId . '] and language [' . $language . '].';
        $noSolrConnectionException = $this->buildNoConnectionException($message);

        $noSolrConnectionException->setLanguageId($language);
        return $noSolrConnectionException;
    }

    /**
     * Throws a no connection exception when no site was passed.
     *
     * @param Site|null $site
     * @param $message
     * @throws NoSolrConnectionFoundException
     */
    protected function throwExceptionOnInvalidSite(?Site $site, string $message)
    {
        if (!is_null($site)) {
            return;
        }

        throw $this->buildNoConnectionException($message);
    }

    /**
     * Build a NoSolrConnectionFoundException with the passed message.
     * @param string $message
     * @return NoSolrConnectionFoundException
     */
    protected function buildNoConnectionException(string $message): NoSolrConnectionFoundException
    {
        /* @var NoSolrConnectionFoundException $noSolrConnectionException */
        $noSolrConnectionException = GeneralUtility::makeInstance(
            NoSolrConnectionFoundException::class,
            /** @scrutinizer ignore-type */
            $message,
            /** @scrutinizer ignore-type */
            1575396474
        );
        return $noSolrConnectionException;
    }
}
