<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\PingFailedException;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * Provides an status report about whether a connection to the Solr server can
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
    protected $siteRepository = null;

    /**
     * Connection Manager
     *
     * @var ConnectionManager
     */
    protected $connectionManager = null;

    /**
     * Holds the response status
     *
     * @var int
     */
    protected $responseStatus = Status::OK;

    /**
     * Holds the response message build by the checks
     *
     * @var string
     */
    protected $responseMessage = '';


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
    protected function getConnectionStatus(array $solrConnection)
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
            'accessFilter' => $accessFilter
        ];

        $report = $this->getRenderedReport('SolrStatus.html', $variables);
        return GeneralUtility::makeInstance(
            Status::class,
            /** @scrutinizer ignore-type */ 'Apache Solr',
            /** @scrutinizer ignore-type */ '',
            /** @scrutinizer ignore-type */ $report,
            /** @scrutinizer ignore-type */ $this->responseStatus
        );
    }

    /**
     * Checks the solr version and adds it to the report.
     *
     * @param SolrAdminService $solr
     * @return string solr version
     */
    protected function checkSolrVersion(SolrAdminService $solr)
    {
        try {
            $solrVersion = $this->formatSolrVersion($solr->getSolrServerVersion());
        } catch (\Exception $e) {
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
    protected function checkAccessFilter(SolrAdminService $solrAdminService)
    {
        try {
            $accessFilterPluginStatus = GeneralUtility::makeInstance(AccessFilterPluginInstalledStatus::class);
            $accessFilterPluginVersion = $accessFilterPluginStatus->getInstalledPluginVersion($solrAdminService);
            $accessFilterMessage = $accessFilterPluginVersion;
        } catch (\Exception $e) {
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
    protected function checkPingTime(SolrAdminService $solrAdminService)
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
    protected function checkSolrConfigName(SolrAdminService $solrAdminService)
    {
        try {
            $solrConfigMessage = $solrAdminService->getSolrconfigName();
        } catch (\Exception $e) {
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
    protected function checkSolrSchemaName(SolrAdminService $solrAdminService)
    {
        try {
            $solrSchemaMessage = $solrAdminService->getSchema()->getName();
        } catch (\Exception $e) {
            $this->responseStatus = Status::ERROR;
            $solrSchemaMessage = 'Error determining schema name: ' . $e->getMessage();
        }

        return $solrSchemaMessage;
    }

    /**
     * Formats the Apache Solr server version number. By default this is going
     * to be the simple major.minor.patch-level version. Custom Builds provide
     * more information though, in case of custom builds, their complete
     * version will be added, too.
     *
     * @param string $solrVersion Unformatted Apache Solr version number as provided by Solr.
     * @return string formatted short version number, in case of custom builds followed by the complete version number
     */
    protected function formatSolrVersion($solrVersion)
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
