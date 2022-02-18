<?php

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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Report;

use ApacheSolrForTypo3\Solr\Report\SolrConfigurationStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration testcase to test the results plugin.
 *
 * @author Timo Schmidt
 */
class SolrConfigurationStatusTest extends IntegrationTest
{

    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canGetGreenReportAgainstTestServer()
    {
        $this->importDataSetFromFixture('can_get_green_solr_configuration_status_report.xml');

        /** @var $solrConfigurationStatus  SolrConfigurationStatus */
        $solrConfigurationStatus = GeneralUtility::makeInstance(SolrConfigurationStatus::class);
        $violations = $solrConfigurationStatus->getStatus();
        self::assertEmpty($violations, 'We did not get an empty response from the solr configuration status report! Something is wrong');
    }

    /**
     * @test
     */
    public function canDetectMissingRootPage()
    {
        $this->importDataSetFromFixture('can_detect_missing_rootpage.xml');

        /** @var $solrConfigurationStatus  SolrConfigurationStatus */
        $solrConfigurationStatus = GeneralUtility::makeInstance(SolrConfigurationStatus::class);
        $violations = $solrConfigurationStatus->getStatus();

        self::assertCount(1, $violations, 'Asserting to contain only one violation.');

        $firstViolation = array_pop($violations);
        self::assertStringContainsString('No sites', $firstViolation->getValue(), 'Did not get a no sites found violation');
    }

    /**
     * @test
     */
    public function canDetectIndexingDisabled()
    {
        $this->importDataSetFromFixture('can_detect_indexing_disabled.xml');

        /* @var SolrConfigurationStatus $solrConfigurationStatus   */
        $solrConfigurationStatus = GeneralUtility::makeInstance(SolrConfigurationStatus::class);
        $violations = $solrConfigurationStatus->getStatus();

        self::assertCount(1, $violations, 'Asserting to contain only one violation.');

        $firstViolation = array_pop($violations);
        self::assertStringContainsString('Indexing is disabled', $firstViolation->getValue(), 'Did not get a no sites found violation');
    }
}
