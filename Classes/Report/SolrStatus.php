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

namespace ApacheSolrForTypo3\Solr\Report;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\PingFailedException;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about whether a connection to the Solr server can
 * be established.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SolrStatus extends AbstractSolrStatus
{
    /**
     * Site Repository
     *
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * Connection Manager
     *
     * @var ConnectionManager
     */
    protected $connectionManager;

    /**
     * Holds the response status
     *
     * @var int
     */
    protected int $responseStatus = Status::OK;

    /**
     * Holds the response message build by the checks
     *
     * @var string
     */
    protected string $responseMessage = '';

    /**
     * SolrStatus constructor.
     * @param SiteRepository|null $siteRepository
     * @param ConnectionManager|null $connectionManager
     */
    public function __construct(SiteRepository $siteRepository = null, ConnectionManager $connectionManager = null)
    {
        $this->siteRepository = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
        $this->connectionManager = $connectionManager ?? GeneralUtility::makeInstance(ConnectionManager::class);
    }

    /**
     * Compiles a collection of status checks against each configured Solr server.
     *
     * @throws DBALDriverException
     * @throws Throwable
     *
     * @noinspection PhpMissingReturnTypeInspection see {@link \TYPO3\CMS\Reports\StatusProviderInterface::getStatus()}
     */
    public function getStatus()
    {
        $reports = [];
        foreach ($this->siteRepository->getAvailableSites() as $site) {
            foreach ($site->getAllSolrConnectionConfigurations() as $solrConfiguration) {
                $reports[] = $this->getConnectionStatus($solrConfiguration);
            }
        }

        return $reports;
    }

    /**
     * Checks whether a Solr server is available and provides some information.
     *
     * @param array $solrConnection Solr connection parameters
     * @return Status Status of the Solr connection
     */
    protected function getConnectionStatus(array $solrConnection): Status
    {
        $header = 'Your site has contacted the Apache Solr server.';
        $this->responseStatus = Status::OK;

        $solrAdmin = $this->connectionManager
            ->getSolrConnectionForNodes($solrConnection['read'], $solrConnection['write'])
            ->getAdminService();

        $solrVersion = $this->checkSolrVersion($solrAdmin);
        $accessFilter = $this->checkAccessFilter($solrAdmin);
        $pingTime = $this->checkPingTime($solrAdmin);
        $configName = $this->checkSolrConfigName($solrAdmin);
        $schemaName = $this->checkSolrSchemaName($solrAdmin);

        if ($this->responseStatus !== Status::OK) {
            $header = 'Failed contacting the Solr server.';
        }

        $variables = [
            'header' => $header,
            'connection' => $solrConnection,
            'solr' => $solrAdmin,
            'solrVersion' => $solrVersion,
            'pingTime' => $pingTime,
            'configName' => $configName,
            'schemaName' => $schemaName,
            'accessFilter' => $accessFilter,
        ];

        $report = $this->getRenderedReport('SolrStatus.html', $variables);
        return GeneralUtility::makeInstance(
            Status::class,
            /** @scrutinizer ignore-type */
            'Apache Solr',
            /** @scrutinizer ignore-type */
            '',
            /** @scrutinizer ignore-type */
            $report,
            /** @scrutinizer ignore-type */
            $this->responseStatus
        );
    }

    /**
     * Checks the solr version and adds it to the report.
     *
     * @param SolrAdminService $solr
     * @return string solr version
     */
    protected function checkSolrVersion(SolrAdminService $solr): string
    {
        try {
            $solrVersion = $this->formatSolrVersion($solr->getSolrServerVersion());
        } catch (Throwable $e) {
            $this->responseStatus = Status::ERROR;
            $solrVersion = 'Error getting solr version: ' . $e->getMessage();
        }

        return $solrVersion;
    }

    /**
     * Checks the access filter setup and adds it to the report.
     *
     * @param SolrAdminService $solrAdminService
     * @return string
     */
    protected function checkAccessFilter(SolrAdminService $solrAdminService): string
    {
        try {
            $accessFilterPluginStatus = GeneralUtility::makeInstance(AccessFilterPluginInstalledStatus::class);
            $accessFilterPluginVersion = $accessFilterPluginStatus->getInstalledPluginVersion($solrAdminService);
            $accessFilterMessage = $accessFilterPluginVersion;
        } catch (Throwable $e) {
            $this->responseStatus = Status::ERROR;
            $accessFilterMessage = 'Error getting access filter: ' . $e->getMessage();
        }
        return $accessFilterMessage;
    }

    /**
     * Checks the ping time and adds it to the report.
     *
     * @param SolrAdminService $solrAdminService
     * @return string
     */
    protected function checkPingTime(SolrAdminService $solrAdminService): string
    {
        try {
            $pingQueryTime = $solrAdminService->getPingRoundTripRuntime();
            $pingMessage = (int)$pingQueryTime . ' ms';
        } catch (PingFailedException $e) {
            $this->responseStatus = Status::ERROR;
            $pingMessage = 'Ping error: ' . $e->getMessage();
        }
        return $pingMessage;
    }

    /**
     * Checks the solr config name and adds it to the report.
     *
     * @param SolrAdminService $solrAdminService
     * @return string
     */
    protected function checkSolrConfigName(SolrAdminService $solrAdminService): string
    {
        try {
            $solrConfigMessage = $solrAdminService->getSolrconfigName();
        } catch (Throwable $e) {
            $this->responseStatus = Status::ERROR;
            $solrConfigMessage = 'Error determining solr config: ' . $e->getMessage();
        }

        return $solrConfigMessage;
    }

    /**
     * Checks the solr schema name and adds it to the report.
     *
     * @param SolrAdminService $solrAdminService
     * @return string
     */
    protected function checkSolrSchemaName(SolrAdminService $solrAdminService): string
    {
        try {
            $solrSchemaMessage = $solrAdminService->getSchema()->getName();
        } catch (Throwable $e) {
            $this->responseStatus = Status::ERROR;
            $solrSchemaMessage = 'Error determining schema name: ' . $e->getMessage();
        }

        return $solrSchemaMessage;
    }

    /**
     * Formats the Apache Solr server version number. By default, this is going
     * to be the simple major.minor.patch-level version. Custom Builds provide
     * more information though, in case of custom-builds, their complete
     * version will be added, too.
     *
     * @param string $solrVersion Unformatted Apache Solr version number a provided by Solr.
     * @return string formatted short version number, in case of custom-builds followed by the complete version number
     */
    protected function formatSolrVersion(string $solrVersion): string
    {
        $explodedSolrVersion = explode('.', $solrVersion);

        $shortSolrVersion = $explodedSolrVersion[0]
            . '.' . $explodedSolrVersion[1]
            . '.' . $explodedSolrVersion[2];

        $formattedSolrVersion = $shortSolrVersion;

        if ($solrVersion != $shortSolrVersion) {
            $formattedSolrVersion .= ' (' . $solrVersion . ')';
        }

        return $formattedSolrVersion;
    }
}
