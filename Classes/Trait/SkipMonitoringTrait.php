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

namespace ApacheSolrForTypo3\Solr\Trait;

use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait SkipMonitoringTrait
{
    /**
     * Check if the provided table is explicitly configured for monitoring
     */
    protected function skipMonitoringOfTable(string $table): bool
    {
        static $configurationMonitorTables;

        if (empty($configurationMonitorTables)) {
            $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            $configurationMonitorTables = $configuration->getIsUseConfigurationMonitorTables();
        }

        // No explicit configuration => all tables should be monitored
        if (empty($configurationMonitorTables)) {
            return false;
        }

        return !in_array($table, $configurationMonitorTables);
    }
}
