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
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
    public function __construct(
        SystemLanguageRepository $systemLanguageRepository = null,
        PagesRepositoryAtExtSolr $pagesRepositoryAtExtSolr = null,
        SiteRepository $siteRepository = null
    )
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
     * Gets all connections configured for a given site.
     *
     * @param Site $site A TYPO3 site
     * @return SolrConnection[] An array of Solr connection objects (ApacheSolrForTypo3\Solr\System\Solr\SolrConnection)
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionsBySite(Site $site)
    {
        $connections = [];

        foreach ($site->getAllSolrConnectionConfigurations() as $languageId => $solrConnectionConfiguration) {
            $connections[$languageId] = $this->getConnectionFromConfiguration($solrConnectionConfiguration);
        }

        return $connections;
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
