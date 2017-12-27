<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Stefan Sprenger <stefan.sprenger@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

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
     * provided install script EXT:solr/Resources/Private/Install/install-solr.sh
     *
     * @var string
     */
    const REQUIRED_SOLR_VERSION = '6.3.0';

    /**
     * Compiles a version check against each configured Solr server.
     *
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
                $pingFailedMsg = 'Could not ping solr server, can not check version ' . (string)$url;
                $status = GeneralUtility::makeInstance(Status::class, 'Apache Solr Version', 'Not accessible', $pingFailedMsg, Status::ERROR);
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
            $status = GeneralUtility::makeInstance(Status::class, 'Apache Solr Version', 'Outdated, Unsupported', $report, Status::ERROR);

            $reports[] = $status;
        }

        return $reports;
    }

    /**
     * Gets the clean Solr version in case of a custom build which may have
     * additional information in the version string.
     *
     * @param string $solrVersion Unformatted Apache Solr version number as provided by Solr.
     * @return string Clean Solr version number: mayor.minor.patchlevel
     */
    protected function getCleanSolrVersion($solrVersion)
    {
        $explodedSolrVersion = explode('.', $solrVersion);

        $shortSolrVersion = $explodedSolrVersion[0]
            . '.' . $explodedSolrVersion[1]
            . '.' . $explodedSolrVersion[2];

        return $shortSolrVersion;
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
        $shortSolrVersion = $this->getCleanSolrVersion($solrVersion);
        $formattedSolrVersion = $shortSolrVersion;

        if ($solrVersion != $shortSolrVersion) {
            $formattedSolrVersion .= ' (' . $solrVersion . ')';
        }

        return $formattedSolrVersion;
    }
}
