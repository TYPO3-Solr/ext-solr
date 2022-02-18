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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository as PagesRepositoryAtExtSolr;
use ApacheSolrForTypo3\Solr\System\Records\SystemLanguage\SystemLanguageRepository;
use ApacheSolrForTypo3\Solr\System\Solr\Node;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use InvalidArgumentException;
use Throwable;
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
     * @param SystemLanguageRepository|null $systemLanguageRepository
     * @param PagesRepositoryAtExtSolr|null $pagesRepositoryAtExtSolr
     * @param SiteRepository|null $siteRepository
     */
    public function __construct(
        SystemLanguageRepository $systemLanguageRepository = null,
        PagesRepositoryAtExtSolr $pagesRepositoryAtExtSolr = null,
        SiteRepository $siteRepository = null
    ) {
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
        if (empty($config['read']) && !empty($config['solrHost'])) {
            throw new InvalidArgumentException('Invalid registry data please re-initialize your solr connections');
        }

        return $this->getSolrConnectionForNodes($config['read'], $config['write']);
    }

    /**
     * Gets a Solr connection for a page ID.
     *
     * @param int $pageId A page ID.
     * @param ?int $language The language ID to get the connection for as the path may differ. Optional, defaults to 0.
     * @param ?string $mount Comma list of MountPoint parameters
     * @return SolrConnection A solr connection.
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionByPageId(int $pageId, int $language = 0, string $mount = ''): SolrConnection
    {
        try {
            $site = $this->siteRepository->getSiteByPageId($pageId, $mount);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);
            $config = $site->getSolrConnectionConfiguration($language);
            return $this->getConnectionFromConfiguration($config);
        } catch (InvalidArgumentException $e) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
        }
    }

    /**
     * Gets a Solr connection for a root page ID.
     *
     * @param int $pageId A root page ID.
     * @param ?int $language The language ID to get the connection for as the path may differ. Optional, defaults to 0.
     * @return SolrConnection A solr connection.
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionByRootPageId(int $pageId, ?int $language = 0): SolrConnection
    {
        try {
            $site = $this->siteRepository->getSiteByRootPageId($pageId);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);
            $config = $site->getSolrConnectionConfiguration($language ?? 0);
            return $this->getConnectionFromConfiguration($config);
        } catch (InvalidArgumentException $e) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
        }
    }

    /**
     * Gets all connections found.
     *
     * @return SolrConnection[] An array of initialized ApacheSolrForTypo3\Solr\System\Solr\SolrConnection connections
     * @throws DBALDriverException
     * @throws Throwable
     */
    public function getAllConnections(): array
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
     */
    public function getConnectionsBySite(Site $site): array
    {
        $connections = [];

        foreach ($site->getAllSolrConnectionConfigurations() as $languageId => $solrConnectionConfiguration) {
            $connections[$languageId] = $this->getConnectionFromConfiguration($solrConnectionConfiguration);
        }

        return $connections;
    }

    /**
     * Creates a human-readable label from the connections' configuration.
     *
     * @param array $connection Connection configuration
     * @return string Connection label
     * @todo Remove, since not used, or take used.
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
     * @param string $message
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
