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
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Throwable;
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
    const RECOMMENDED_SOLRCONFIG_VERSION = 'tx_solr-11-5-0--20211001';

    /**
     * Compiles a collection of solrconfig version checks against each configured
     * Solr server. Only adds an entry if a solrconfig other than the
     * recommended one was found.
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
            $adminService = $solrConnection->getAdminService();

            if ($adminService->ping() && $adminService->getSolrconfigName() != self::RECOMMENDED_SOLRCONFIG_VERSION) {
                $variables = ['solr' => $adminService, 'recommendedVersion' => self::RECOMMENDED_SOLRCONFIG_VERSION];
                $report = $this->getRenderedReport('SolrConfigStatus.html', $variables);
                $status = GeneralUtility::makeInstance(
                    Status::class,
                    /** @scrutinizer ignore-type */
                    'Solrconfig Version',
                    /** @scrutinizer ignore-type */
                    'Unsupported solrconfig.xml',
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
