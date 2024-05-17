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
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about whether the installed Solr version matches
 * the required version.
 */
class SolrVersionStatus extends AbstractSolrStatus
{
    /**
     * Compiles a version check against each configured Solr server.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getStatus(): array
    {
        $reports = [];
        $solrConnections = GeneralUtility::makeInstance(ConnectionManager::class)->getAllConnections();

        foreach ($solrConnections as $solrConnection) {
            $coreAdmin = $solrConnection->getAdminService();
            /** @var SolrConnection $solrConnection */
            if (!$coreAdmin->ping()) {
                $url = $coreAdmin->__toString();
                $pingFailedMsg = 'Could not ping Solr server, can not check version ' . $url;
                $status = GeneralUtility::makeInstance(
                    Status::class,
                    'Apache Solr Version',
                    'Not accessible',
                    $pingFailedMsg,
                    ContextualFeedbackSeverity::ERROR
                );
                $reports[] = $status;
                continue;
            }

            $solrVersion = $coreAdmin->getSolrServerVersion();
            $supportedSolrVersions = $this->getSupportedSolrVersions();
            $isSupported = in_array($this->getCleanSolrVersion($solrVersion), $supportedSolrVersions);

            if ($isSupported) {
                $reports[] = GeneralUtility::makeInstance(
                    Status::class,
                    'Apache Solr Version',
                    'OK',
                    'Version of ' . $coreAdmin->__toString() . ' is ok: ' . $solrVersion,
                    ContextualFeedbackSeverity::OK
                );
                continue;
            }

            $formattedVersion = $this->formatSolrVersion($solrVersion);
            $variables = [
                'supportedSolrVersions' => $supportedSolrVersions,
                'currentVersion' => $formattedVersion,
                'solr' => $coreAdmin,
            ];
            $report = $this->getRenderedReport('SolrVersionStatus.html', $variables);
            $status = GeneralUtility::makeInstance(
                Status::class,
                'Apache Solr Version',
                'Unsupported',
                $report,
                ContextualFeedbackSeverity::ERROR
            );

            $reports[] = $status;
        }

        return $reports;
    }

    protected function getSupportedSolrVersions(): array
    {
        $composerContents = file_get_contents(ExtensionManagementUtility::extPath('solr') . 'composer.json');
        $composerConfiguration = json_decode($composerContents, true, 25, JSON_OBJECT_AS_ARRAY);
        return $composerConfiguration['extra']['TYPO3-Solr']['version-matrix']['Apache-Solr'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'LLL:EXT:solr/Resources/Private/Language/locallang_reports.xlf:status_solr_solrversion';
    }

    /**
     * Gets the clean Solr version in case of a custom build which may have
     * additional information in the version string.
     *
     * @param string $solrVersion Unformatted Apache Solr version number a provided by Solr.
     * @return string Clean Solr version number: mayor.minor.patch-level
     */
    protected function getCleanSolrVersion(string $solrVersion): string
    {
        $explodedSolrVersion = explode('.', $solrVersion);

        return $explodedSolrVersion[0]
            . '.' . $explodedSolrVersion[1]
            . '.' . $explodedSolrVersion[2];
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
        $shortSolrVersion = $this->getCleanSolrVersion($solrVersion);
        $formattedSolrVersion = $shortSolrVersion;

        if ($solrVersion != $shortSolrVersion) {
            $formattedSolrVersion .= ' (' . $solrVersion . ')';
        }

        return $formattedSolrVersion;
    }
}
