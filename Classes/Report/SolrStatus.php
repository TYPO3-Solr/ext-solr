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
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\PingFailedException;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use Throwable;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about whether a connection to the Solr server can
 * be established.
 */
class SolrStatus extends AbstractSolrStatus
{
    /**
     * Site Repository
     */
    protected SiteRepository $siteRepository;

    /**
     * Connection Manager
     */
    protected ConnectionManager $connectionManager;

    /**
     * Access Filter Plugin Status
     */
    protected AccessFilterPluginInstalledStatus $accessFilterPluginInstalledStatus;

    /**
     * Holds the response status
     */
    protected ContextualFeedbackSeverity $responseStatus = ContextualFeedbackSeverity::OK;

    /**
     * Holds the response message build by the checks
     */
    protected string $responseMessage = '';

    /**
     * SolrStatus constructor.
     */
    public function __construct(
        ViewFactoryInterface $viewFactory,
        ?SiteRepository $siteRepository = null,
        ?ConnectionManager $connectionManager = null,
        ?AccessFilterPluginInstalledStatus $accessFilterPluginInstalledStatus = null,
    ) {
        parent::__construct($viewFactory);
        $this->siteRepository = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
        $this->connectionManager = $connectionManager ?? GeneralUtility::makeInstance(ConnectionManager::class);
        $this->accessFilterPluginInstalledStatus = $accessFilterPluginInstalledStatus ?? GeneralUtility::makeInstance(AccessFilterPluginInstalledStatus::class, $viewFactory);
    }

    /**
     * Compiles a collection of status checks against each configured Solr server.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getStatus(): array
    {
        $reports = [];
        foreach ($this->siteRepository->getAvailableSites() as $site) {
            foreach ($site->getAllSolrConnectionConfigurations() as $solrConfiguration) {
                $reports[] = $this->getConnectionStatus($site, $solrConfiguration);
            }
        }

        if (empty($reports)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Apache Solr connection',
                'No Apache Solr connection configured',
                '',
                ContextualFeedbackSeverity::WARNING,
            );
        }

        return $reports;
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'LLL:EXT:solr/Resources/Private/Language/locallang_reports.xlf:status_solr_connectionstatus';
    }

    /**
     * Checks whether a Solr server is available and provides some information.
     *
     * @param Site $site
     * @param array $solrConnection Solr connection parameters
     * @return Status Status of the Solr connection
     */
    protected function getConnectionStatus(Site $site, array $solrConnection): Status
    {
        $header = 'Your site has contacted the Apache Solr server.';
        $this->responseStatus = ContextualFeedbackSeverity::OK;

        $solrAdmin = $this->connectionManager
            ->getSolrConnectionForEndpoints(
                $solrConnection['read'],
                $solrConnection['write'],
                $site->getSolrConfiguration(),
            )
            ->getAdminService();

        $solrVersion = $this->checkSolrVersion($solrAdmin);
        $accessFilter = $this->checkAccessFilter($solrAdmin);
        $pingTime = $this->checkPingTime($solrAdmin);
        $configName = $this->checkSolrConfigName($solrAdmin);
        $schemaName = $this->checkSolrSchemaName($solrAdmin);

        /** @phpstan-ignore-next-line */
        if ($this->responseStatus !== ContextualFeedbackSeverity::OK) {
            $header = 'Failed contacting the Solr server.';
        }

        $variables = [
            'site' => $site->getLabel(),
            'siteLanguage' => $site->getTypo3SiteObject()->getLanguageById($solrConnection['language']),
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
            'Apache Solr Connection',
            $header,
            $report,
            $this->responseStatus,
        );
    }

    /**
     * Checks the solr version and adds it to the report.
     *
     * @return string solr version
     */
    protected function checkSolrVersion(SolrAdminService $solr): string
    {
        try {
            $solrVersion = $this->formatSolrVersion($solr->getSolrServerVersion());
        } catch (Throwable $e) {
            $this->responseStatus = ContextualFeedbackSeverity::ERROR;
            $solrVersion = 'Error getting solr version: ' . $e->getMessage();
        }

        return $solrVersion;
    }

    /**
     * Checks the access filter setup and adds it to the report.
     */
    protected function checkAccessFilter(SolrAdminService $solrAdminService): string
    {
        try {
            $accessFilterPluginVersion = $this->accessFilterPluginInstalledStatus->getInstalledPluginVersion($solrAdminService);
            $accessFilterMessage = $accessFilterPluginVersion;
        } catch (Throwable $e) {
            $this->responseStatus = ContextualFeedbackSeverity::ERROR;
            $accessFilterMessage = 'Error getting access filter: ' . $e->getMessage();
        }
        return $accessFilterMessage;
    }

    /**
     * Checks the ping time and adds it to the report.
     */
    protected function checkPingTime(SolrAdminService $solrAdminService): string
    {
        try {
            $pingQueryTime = $solrAdminService->getPingRoundTripRuntime();
            $pingMessage = (int)$pingQueryTime . ' ms';
        } catch (PingFailedException $e) {
            $this->responseStatus = ContextualFeedbackSeverity::ERROR;
            $pingMessage = 'Ping error: ' . $e->getMessage();
        }
        return $pingMessage;
    }

    /**
     * Checks the solr config name and adds it to the report.
     */
    protected function checkSolrConfigName(SolrAdminService $solrAdminService): string
    {
        try {
            $solrConfigMessage = $solrAdminService->getSolrconfigName();
        } catch (Throwable $e) {
            $this->responseStatus = ContextualFeedbackSeverity::ERROR;
            $solrConfigMessage = 'Error determining solr config: ' . $e->getMessage();
        }

        return $solrConfigMessage;
    }

    /**
     * Checks the solr schema name and adds it to the report.
     */
    protected function checkSolrSchemaName(SolrAdminService $solrAdminService): string
    {
        try {
            $solrSchemaMessage = $solrAdminService->getSchema()->getName();
        } catch (Throwable $e) {
            $this->responseStatus = ContextualFeedbackSeverity::ERROR;
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
