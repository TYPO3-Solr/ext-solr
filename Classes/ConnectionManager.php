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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\Exception\InvalidConnectionException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository as PagesRepositoryAtExtSolr;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use Doctrine\DBAL\Exception as DBALException;
use Solarium\Core\Client\Endpoint;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function json_encode;

/**
 * ConnectionManager is responsible to create SolrConnection objects.
 */
class ConnectionManager implements SingletonInterface
{
    /**
     * @var SolrConnection[]
     */
    protected static array $connections = [];

    protected PagesRepositoryAtExtSolr $pagesRepositoryAtExtSolr;

    protected SiteRepository $siteRepository;

    public function __construct(
        ?PagesRepositoryAtExtSolr $pagesRepositoryAtExtSolr = null,
        ?SiteRepository $siteRepository = null,
    ) {
        $this->siteRepository = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
        $this->pagesRepositoryAtExtSolr = $pagesRepositoryAtExtSolr ?? GeneralUtility::makeInstance(PagesRepositoryAtExtSolr::class);
    }

    /**
     * Creates a Solr connection for read and write endpoints
     *
     * @throw InvalidConnectionException
     */
    public function getSolrConnectionForEndpoints(
        array $readEndpointConfiguration,
        array $writeEndpointConfiguration,
        ?TypoScriptConfiguration $typoScriptConfiguration = null,
    ): SolrConnection {
        $connectionHash = md5(json_encode($readEndpointConfiguration) . json_encode($writeEndpointConfiguration));
        if (!isset(self::$connections[$connectionHash])) {
            $readEndpoint = new Endpoint($readEndpointConfiguration);
            if (!$this->isValidEndpoint($readEndpoint)) {
                throw new InvalidConnectionException('Invalid read endpoint');
            }

            $writeEndpoint = new Endpoint($writeEndpointConfiguration);
            if (!$this->isValidEndpoint($writeEndpoint)) {
                throw new InvalidConnectionException('Invalid write endpoint');
            }

            self::$connections[$connectionHash] = GeneralUtility::makeInstance(
                SolrConnection::class,
                $readEndpoint,
                $writeEndpoint,
                $typoScriptConfiguration
            );
        }

        return self::$connections[$connectionHash];
    }

    /**
     * Checks if endpoint is valid
     */
    protected function isValidEndpoint(Endpoint $endpoint): bool
    {
        return
            !empty($endpoint->getHost())
            && !empty($endpoint->getPort())
            && !empty($endpoint->getCore())
        ;
    }

    /**
     * Creates a solr configuration from the configuration array and returns it.
     */
    public function getConnectionFromConfiguration(
        array $solrConfiguration,
        ?TypoScriptConfiguration $typoScriptConfiguration = null,
    ): SolrConnection {
        return $this->getSolrConnectionForEndpoints(
            $solrConfiguration['read'],
            $solrConfiguration['write'],
            $typoScriptConfiguration
        );
    }

    /**
     * Gets a Solr connection for a page ID.
     *
     * @throws DBALException
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionByPageId(int $pageId, int $language = 0, string $mountPointParametersList = ''): SolrConnection
    {
        try {
            $site = $this->siteRepository->getSiteByPageId($pageId, $mountPointParametersList);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);
            $config = $site->getSolrConnectionConfiguration($language);
            return $this->getConnectionFromConfiguration(
                $config,
                $site->getSolrConfiguration()
            );
        } catch (InvalidArgumentException) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
        }
    }

    /**
     * Gets a Solr connection for a TYPO3 site and language
     *
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionByTypo3Site(Typo3Site $typo3Site, int $languageUid = 0): SolrConnection
    {
        $config = SiteUtility::getSolrConnectionConfiguration($typo3Site, $languageUid);
        if ($config === null) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage(
                $typo3Site->getRootPageId(),
                $languageUid
            );
        }

        try {
            return $this->getConnectionFromConfiguration($config);
        } catch (InvalidArgumentException) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage(
                $typo3Site->getRootPageId(),
                $languageUid
            );
        }
    }

    /**
     * Gets a Solr connection for a root page ID.
     *
     * @throws DBALException
     * @throws NoSolrConnectionFoundException
     */
    public function getConnectionByRootPageId(int $pageId, ?int $language = 0): SolrConnection
    {
        try {
            $site = $this->siteRepository->getSiteByRootPageId($pageId);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);
            $config = $site->getSolrConnectionConfiguration($language ?? 0);
            return $this->getConnectionFromConfiguration(
                $config,
                $site->getSolrConfiguration()
            );
        } catch (InvalidArgumentException) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
        }
    }

    /**
     * Gets all connections found.
     *
     * @return SolrConnection[] An array of initialized {@link SolrConnection} connections
     *
     * @throws UnexpectedTYPO3SiteInitializationException
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
     * @return SolrConnection[] An array of Solr connection objects {@link SolrConnection}
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
     * Builds and returns the exception instance of {@link NoSolrConnectionFoundException}
     */
    protected function buildNoConnectionExceptionForPageAndLanguage(int $pageId, int $language): NoSolrConnectionFoundException
    {
        $message = 'Could not find a Solr connection for page [' . $pageId . '] and language [' . $language . '].';
        $noSolrConnectionException = $this->buildNoConnectionException($message);

        $noSolrConnectionException->setLanguageId($language);
        return $noSolrConnectionException;
    }

    /**
     * Throws a no connection exception when no site was passed.
     *
     * @throws NoSolrConnectionFoundException
     */
    protected function throwExceptionOnInvalidSite(?Site $site, string $message): void
    {
        if (!is_null($site)) {
            return;
        }

        throw $this->buildNoConnectionException($message);
    }

    /**
     * Build a NoSolrConnectionFoundException with the passed message.
     */
    protected function buildNoConnectionException(string $message): NoSolrConnectionFoundException
    {
        return GeneralUtility::makeInstance(
            NoSolrConnectionFoundException::class,
            $message,
            1575396474
        );
    }
}
