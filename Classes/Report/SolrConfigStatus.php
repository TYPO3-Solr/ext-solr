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
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about which solrconfig version is used and checks
 * whether it fits the recommended version shipping with the extension.
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
     */
    public const RECOMMENDED_SOLRCONFIG_VERSION = 'tx_solr-14-0-0--20260123';

    /**
     * Compiles a collection of solrconfig version checks against each configured
     * Solr server. Only adds an entry if a solrconfig other than the
     * recommended one was found.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws InvalidArgumentException
     */
    public function getStatus(): array
    {
        $reports = [];
        $solrConnections = GeneralUtility::makeInstance(ConnectionManager::class)->getAllConnections();
        if (empty($solrConnections)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Solrconfig Version',
                'No Solr connections configured',
                '',
                ContextualFeedbackSeverity::WARNING,
            );

            return $reports;
        }

        /** @var SolrConnection $solrConnection */
        foreach ($solrConnections as $solrConnection) {
            $adminService = $solrConnection->getAdminService();
            if (!$adminService->ping()) {
                $reports[] = GeneralUtility::makeInstance(
                    Status::class,
                    'Solrconfig Version',
                    'Couldn\'t connect to ' . $adminService->__toString(),
                    '',
                    ContextualFeedbackSeverity::WARNING,
                );

                continue;
            }

            if ($adminService->getSolrconfigName() != self::RECOMMENDED_SOLRCONFIG_VERSION) {
                $variables = ['solr' => $adminService, 'recommendedVersion' => self::RECOMMENDED_SOLRCONFIG_VERSION];
                $report = $this->getRenderedReport('SolrConfigStatus.html', $variables);
                $status = GeneralUtility::makeInstance(
                    Status::class,
                    'Solrconfig Version',
                    'Unsupported solrconfig.xml',
                    $report,
                    ContextualFeedbackSeverity::WARNING,
                );

                $reports[] = $status;
            }
        }

        if (empty($reports)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Solrconfig Version',
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
        return 'LLL:EXT:solr/Resources/Private/Language/locallang_reports.xlf:status_solr_solrconfig';
    }
}
