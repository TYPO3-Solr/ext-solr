<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about which solrconfig version is used and checks
 * whether it fits the recommended version shipping with the extension.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SolrConfigStatus extends AbstractSolrStatus
{

    /**
     * The config name property is constructed as follows:
     *
     * tx_solr    - The extension key
     * x-y-z    - The extension version this config is meant to work with
     * YYYYMMDD    - The date the config file was changed the last time
     *
     * Must be updated when changing the solrconfig.
     *
     * @var string
     */
    const RECOMMENDED_SOLRCONFIG_VERSION = 'tx_solr-10-0-0--20191010';

    /**
     * Compiles a collection of solrconfig version checks against each configured
     * Solr server. Only adds an entry if a solrconfig other than the
     * recommended one was found.
     *
     */
    public function getStatus()
    {
        $reports = [];
        $solrConnections = GeneralUtility::makeInstance(ConnectionManager::class)->getAllConnections();

        foreach ($solrConnections as $solrConnection) {
            $adminService = $solrConnection->getAdminService();

            if ($adminService->ping() && $adminService->getSolrconfigName() != self::RECOMMENDED_SOLRCONFIG_VERSION) {
                $variables = ['solr' => $adminService, 'recommendedVersion' => self::RECOMMENDED_SOLRCONFIG_VERSION];
                $report = $this->getRenderedReport('SolrConfigStatus.html', $variables);
                $status = GeneralUtility::makeInstance(
                    Status::class,
                    /** @scrutinizer ignore-type */ 'Solrconfig Version',
                    /** @scrutinizer ignore-type */ 'Unsupported solrconfig.xml',
                    /** @scrutinizer ignore-type */ $report,
                    /** @scrutinizer ignore-type */ Status::WARNING
                );

                $reports[] = $status;
            }
        }

        return $reports;
    }
}
