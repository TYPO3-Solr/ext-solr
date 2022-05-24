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
 * Provides a status report about which schema version is used and checks
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
    const RECOMMENDED_SCHEMA_VERSION = 'tx_solr-11-5-0--20211001';

    /**
     * Compiles a collection of schema version checks against each configured
     * Solr server. Only adds an entry if a schema other than the
     * recommended one was found.
     *
     * @throws DBALDriverException
     * @throws Throwable
     *
     * @noinspection PhpMissingReturnTypeInspection see {@link \TYPO3\CMS\Reports\StatusProviderInterface::getStatus()}
     */
    public function getStatus()
    {
        $reports = [];
        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $solrConnections = $connectionManager->getAllConnections();

        foreach ($solrConnections as $solrConnection) {
            $adminService = $solrConnection->getAdminService();
            /** @var $solrConnection SolrConnection */
            if (!$adminService->ping()) {
                $url = $adminService->__toString();
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

            $isWrongSchema = $adminService->getSchema()->getName() != self::RECOMMENDED_SCHEMA_VERSION;
            if ($isWrongSchema) {
                $variables = ['solr' => $adminService, 'recommendedVersion' => self::RECOMMENDED_SCHEMA_VERSION];
                $report = $this->getRenderedReport('SchemaStatus.html', $variables);
                $status = GeneralUtility::makeInstance(
                    Status::class,
                    /** @scrutinizer ignore-type */
                    'Schema Version',
                    /** @scrutinizer ignore-type */
                    'Unsupported Schema',
                    /** @scrutinizer ignore-type */
                    $report,
                    /** @scrutinizer ignore-type */
                    Status::WARNING
                );
                $reports[] = $status;
            }
        }

        return $reports;
    }
}
