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
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about whether the Access Filter Query Parser Plugin
 * is installed on the Solr server.
 */
class AccessFilterPluginInstalledStatus extends AbstractSolrStatus
{
    /**
     * The plugin's Java class name.
     */
    public const PLUGIN_CLASS_NAME = 'org.typo3.solr.search.AccessFilterQParserPlugin';

    /**
     * Compiles a collection of solrconfig.xml checks against each configured
     * Solr server. Only adds an entry if the Access Filter Query Parser Plugin
     * is not configured.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getStatus(): array
    {
        $reports = [];
        $solrConnections = GeneralUtility::makeInstance(ConnectionManager::class)->getAllConnections();

        foreach ($solrConnections as $solrConnection) {
            $adminService = $solrConnection->getAdminService();
            if ($adminService->ping()) {
                $installationStatus = $this->checkPluginInstallationStatus($adminService);

                if (!is_null($installationStatus)) {
                    $reports[] = $installationStatus;
                }
            }
        }

        if (empty($reports)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Solr Access Filter Plugin',
                'OK',
                'Solr Access Filter Plugin is installed, please always use the version supplied with EXT:solr.',
                ContextualFeedbackSeverity::OK,
            );
        }

        return $reports;
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'LLL:EXT:solr/Resources/Private/Language/locallang_reports.xlf:status_solr_access-filter';
    }

    /**
     * Checks whether the Solr plugin is installed.
     */
    protected function checkPluginInstallationStatus(SolrAdminService $adminService): ?Status
    {
        if ($this->isPluginInstalled($adminService)) {
            return null;
        }

        $report = $this->getRenderedReport(
            'AccessFilterPluginInstalledStatusNotInstalled.html',
            ['solr' => $adminService],
        );
        return GeneralUtility::makeInstance(
            Status::class,
            'Solr Access Filter Plugin',
            'Not Installed',
            $report,
            ContextualFeedbackSeverity::WARNING,
        );
    }

    /**
     * Checks whether the Access Filter Query Parser Plugin is installed for
     * the given Solr server instance.
     *
     * @return bool True if the plugin is installed, FALSE otherwise.
     */
    public function isPluginInstalled(SolrAdminService $adminService): bool
    {
        $accessFilterQueryParserPluginInstalled = false;

        $typo3accessPlugin = $adminService->getCoreConfiguration()->config->queryParser->typo3access ?? null;
        if (($typo3accessPlugin->class ?? '') === self::PLUGIN_CLASS_NAME) {
            $accessFilterQueryParserPluginInstalled = true;
        }

        return $accessFilterQueryParserPluginInstalled;
    }
}
