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

namespace ApacheSolrForTypo3\Solr\Updates;

use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate;

/**
 * Migrates Plugin subtypes to content elements.
 * See: https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/13.4/Deprecation-105076-PluginContentElementAndPluginSubTypes.html#deprecation-105076-plugin-content-element-and-plugin-sub-types
 */
#[UpgradeWizard('solrSearchCTypeMigration')]
final class SolrSearchCTypeMigration extends AbstractListTypeToCTypeUpdate
{
    protected function getListTypeToCTypeMapping(): array
    {
        return [
            'solr_pi_results' => 'solr_pi_results',
            'solr_pi_search' => 'solr_pi_search',
            'solr_pi_results_search' => 'solr_pi_results_search',
        ];
    }

    public function getTitle(): string
    {
        return 'Migrate "Apache Solr Search" plugins to content elements.';
    }

    public function getDescription(): string
    {
        return 'The "Apache Solr Search" plugins are now registered as content elements. Update migrates existing records and backend user permissions.';
    }
}
