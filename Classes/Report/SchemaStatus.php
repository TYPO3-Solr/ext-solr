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
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides an status report about which schema version is used and checks
 * whether it fits the recommended version shipping with the extension.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SchemaStatus extends AbstractSolrStatus
{

    /**
     * The schema name property is constructed as follows:
     *
     * tx_solr  - The extension key
     * x-y-z    - The extension version this schema is meant to work with
     * YYYYMMDD - The date the schema file was changed the last time
     *
     * Must be updated when changing the schema.
     *
     * @var string
     */
    const RECOMMENDED_SCHEMA_VERSION = 'tx_solr-8-0-0--20171020';

    /**
     * Compiles a collection of schema version checks against each configured
     * Solr server. Only adds an entry if a schema other than the
     * recommended one was found.
     *
     */
    public function getStatus()
    {
        $reports = [];
        $solrConnections = GeneralUtility::makeInstance(ConnectionManager::class)->getAllConnections();

        foreach ($solrConnections as $solrConnection) {
            $adminService = $solrConnection->getAdminService();
            /** @var $solrConnection SolrConnection */
            if (!$adminService->ping()) {
                $url = $adminService->__toString();
                $pingFailedMsg = 'Could not ping solr server, can not check version ' . (string)$url;
                $status = GeneralUtility::makeInstance(Status::class, 'Apache Solr Version', 'Not accessible', $pingFailedMsg, Status::ERROR);
                $reports[] = $status;
                continue;
            }

            $isWrongSchema = $adminService->getSchema()->getName() != self::RECOMMENDED_SCHEMA_VERSION;
            if ($isWrongSchema) {
                $variables = ['solr' => $adminService, 'recommendedVersion' => self::RECOMMENDED_SCHEMA_VERSION];
                $report = $this->getRenderedReport('SchemaStatus.html', $variables);
                $status = GeneralUtility::makeInstance(Status::class, 'Schema Version', 'Unsupported Schema', $report, Status::WARNING);
                $reports[] = $status;
            }
        }

        return $reports;
    }
}
