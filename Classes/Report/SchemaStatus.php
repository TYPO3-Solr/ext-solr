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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about which schema version is used and checks
 * whether it fits the recommended version shipping with the extension.
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
     */
    public const RECOMMENDED_SCHEMA_VERSION = 'tx_solr-14-0-0--20260123';

    /**
     * Compiles a collection of schema version checks against each configured
     * Solr server. Only adds an entry if a schema other than the
     * recommended one was found.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getStatus(): array
    {
        $reports = [];
        /** @var ConnectionManager $connectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $solrConnections = $connectionManager->getAllConnections();

        if (empty($solrConnections)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Apache Solr Version / Schema Version',
                'No Solr connections configured',
                '',
                ContextualFeedbackSeverity::WARNING,
            );

            return $reports;
        }

        foreach ($solrConnections as $solrConnection) {
            $adminService = $solrConnection->getAdminService();
            /** @var SolrConnection $solrConnection */
            if (!$adminService->ping()) {
                $url = $adminService->__toString();
                $pingFailedMsg = 'Could not ping solr server, can not check version ' . $url;
                $status = GeneralUtility::makeInstance(
                    Status::class,
                    'Apache Solr Version',
                    'Not accessible',
                    $pingFailedMsg,
                    ContextualFeedbackSeverity::ERROR,
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
                    'Schema Version',
                    'Unsupported Schema',
                    $report,
                    ContextualFeedbackSeverity::WARNING,
                );
                $reports[] = $status;
            }
        }

        if (empty($reports)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Apache Solr Version / Schema Version',
                'OK',
                '',
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
        return 'LLL:EXT:solr/Resources/Private/Language/locallang_reports.xlf:status_solr_schema';
    }
}
