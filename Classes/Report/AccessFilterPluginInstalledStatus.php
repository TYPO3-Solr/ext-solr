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
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AccessFilterPluginInstalledStatus extends AbstractSolrStatus
{
    /**
     * Solr Access Filter plugin version.
     *
     * Must be updated when changing the plugin.
     */
    public const RECOMMENDED_PLUGIN_VERSION = '3.0.0';

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
                $versionStatus = $this->checkPluginVersion($adminService);

                if (!is_null($installationStatus)) {
                    $reports[] = $installationStatus;
                }

                if (!is_null($versionStatus)) {
                    $reports[] = $versionStatus;
                }
            }
        }

        return $reports;
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'solr/access-filter';
    }

    /**
     * Checks whether the Solr plugin is installed.
     */
    protected function checkPluginInstallationStatus(SolrAdminService $adminService): ?Status
    {
        if ($this->isPluginInstalled($adminService)) {
            return null;
        }

        $variables = ['solr' => $adminService, 'recommendedVersion' => self::RECOMMENDED_PLUGIN_VERSION];

        $report = $this->getRenderedReport('AccessFilterPluginInstalledStatusNotInstalled.html', $variables);
        return GeneralUtility::makeInstance(
            Status::class,
            'Access Filter Plugin',
            'Not Installed',
            $report,
            ContextualFeedbackSeverity::WARNING
        );
    }

    /**
     * Checks whether the Solr plugin version is up-to-date.
     */
    protected function checkPluginVersion(SolrAdminService $adminService): ?Status
    {
        if (!($this->isPluginInstalled($adminService) && $this->isPluginOutdated($adminService))) {
            return null;
        }

        $version = $this->getInstalledPluginVersion($adminService);
        $variables = ['solr' => $adminService, 'installedVersion' => $version, 'recommendedVersion' => self::RECOMMENDED_PLUGIN_VERSION];
        $report = $this->getRenderedReport('AccessFilterPluginInstalledStatusIsOutDated.html', $variables);

        return GeneralUtility::makeInstance(
            Status::class,
            'Access Filter Plugin',
            'Outdated',
            $report,
            ContextualFeedbackSeverity::WARNING
        );
    }

    /**
     * Checks whether the Access Filter Query Parser Plugin is installed for
     * the given Solr server instance.
     *
     * @return bool True if the plugin is installed, FALSE otherwise.
     */
    protected function isPluginInstalled(SolrAdminService $adminService): bool
    {
        $accessFilterQueryParserPluginInstalled = false;

        $pluginsInformation = $adminService->getPluginsInformation();
        if (isset($pluginsInformation->plugins->OTHER->{self::PLUGIN_CLASS_NAME})) {
            $accessFilterQueryParserPluginInstalled = true;
        }

        return $accessFilterQueryParserPluginInstalled;
    }

    /**
     * Checks whether the installed plugin is current.
     *
     * @return bool True if the plugin is outdated, FALSE if it meets the current version recommendation.
     */
    protected function isPluginOutdated(SolrAdminService $adminService): bool
    {
        $pluginVersion = $this->getInstalledPluginVersion($adminService);
        return version_compare($pluginVersion, self::RECOMMENDED_PLUGIN_VERSION, '<');
    }

    /**
     * Gets the version of the installed plugin.
     *
     * @return string The installed plugin's version number.
     */
    public function getInstalledPluginVersion(SolrAdminService $adminService): string
    {
        $pluginsInformation = $adminService->getPluginsInformation();

        $description = $pluginsInformation->plugins->OTHER->{self::PLUGIN_CLASS_NAME}->description;
        $matches = [];
        preg_match_all('/.*\(Version: (?<version>[^\)]*)\)/ums', $description, $matches);
        $rawVersion = $matches['version'][0] ?? '';

        $explodedRawVersion = explode('-', $rawVersion);
        return $explodedRawVersion[0] ?? '';
    }
}
