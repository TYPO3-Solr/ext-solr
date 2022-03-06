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
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about whether the installed Solr version matches
 * the required version.
 *
 * @author Stefan Sprenger <stefan.sprenger@dkd.de>
 */
class SolrVersionStatus extends AbstractSolrStatus
{
    /**
     * Required Solr version. The version that gets installed when using the
     * provided install-script EXT:solr/Resources/Private/Install/install-solr.sh
     *
     * @var string
     */
    const REQUIRED_SOLR_VERSION = '8.11.1';

    /**
     * Compiles a version check against each configured Solr server.
     *
     * @noinspection PhpMissingReturnTypeInspection see {@link \TYPO3\CMS\Reports\StatusProviderInterface::getStatus()}
     *
     * @throws DBALDriverException
     * @throws Throwable
     */
    public function getStatus()
    {
        $reports = [];
        $solrConnections = GeneralUtility::makeInstance(ConnectionManager::class)->getAllConnections();

        foreach ($solrConnections as $solrConnection) {
            $coreAdmin = $solrConnection->getAdminService();
            /** @var $solrConnection SolrConnection */
            if (!$coreAdmin->ping()) {
                $url = $coreAdmin->__toString();
                $pingFailedMsg = 'Could not ping solr server, can not check version ' . $url;
                $status = GeneralUtility::makeInstance(
                    Status::class,
                    /** @scrutinizer ignore-type */
                    'Apache Solr Version',
                    /** @scrutinizer ignore-type */
                    'Not accessible',
                    /** @scrutinizer ignore-type */
                    $pingFailedMsg,
                    /** @scrutinizer ignore-type */
                    Status::ERROR
                );
                $reports[] = $status;
                continue;
            }

            $solrVersion = $coreAdmin->getSolrServerVersion();
            $isOutdatedVersion = version_compare($this->getCleanSolrVersion($solrVersion), self::REQUIRED_SOLR_VERSION, '<');

            if (!$isOutdatedVersion) {
                continue;
            }

            $formattedVersion = $this->formatSolrVersion($solrVersion);
            $variables = ['requiredVersion' => self::REQUIRED_SOLR_VERSION, 'currentVersion' => $formattedVersion, 'solr' => $coreAdmin];
            $report = $this->getRenderedReport('SolrVersionStatus.html', $variables);
            $status = GeneralUtility::makeInstance(
                Status::class,
                /** @scrutinizer ignore-type */
                'Apache Solr Version',
                /** @scrutinizer ignore-type */
                'Outdated, Unsupported',
                /** @scrutinizer ignore-type */
                $report,
                /** @scrutinizer ignore-type */
                Status::ERROR
            );

            $reports[] = $status;
        }

        return $reports;
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
