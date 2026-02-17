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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Report;

use ApacheSolrForTypo3\Solr\Report\SolrConfigurationStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Integration test for the Solr configuration status report
 */
class SolrConfigurationStatusTest extends IntegrationTestBase
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

    #[Test]
    public function canGetGreenReportAgainstTestServer(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_get_green_solr_configuration_status_report.csv');

        /** @var SolrConfigurationStatus $solrConfigurationStatus */
        $solrConfigurationStatus = $this->get(SolrConfigurationStatus::class);
        $results = $solrConfigurationStatus->getStatus();
        self::assertCount(2, $results);
        self::assertEquals(
            $results[0]->getSeverity(),
            ContextualFeedbackSeverity::OK,
            'We expect to get no violations concerning root page configurations',
        );
        self::assertEquals(
            $results[1]->getSeverity(),
            ContextualFeedbackSeverity::OK,
            'We expect to get no violations concerning index enable flags',
        );
    }

    #[Test]
    public function canDetectMissingRootPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_detect_missing_rootpage.csv');

        /** @var SolrConfigurationStatus $solrConfigurationStatus */
        $solrConfigurationStatus = $this->get(SolrConfigurationStatus::class);
        $results = $solrConfigurationStatus->getStatus();

        self::assertCount(1, $results);

        $firstViolation = array_pop($results);
        self::assertStringContainsString('No sites', $firstViolation->getValue(), 'Did not get a no sites found violation');
    }

    #[Test]
    public function canDetectIndexingDisabled(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_detect_indexing_disabled.csv');

        /** @var SolrConfigurationStatus $solrConfigurationStatus   */
        $solrConfigurationStatus = $this->get(SolrConfigurationStatus::class);
        $results = $solrConfigurationStatus->getStatus();

        self::assertCount(2, $results, 'Two test status are expected to be returned.');
        self::assertEquals(
            $results[0]->getSeverity(),
            ContextualFeedbackSeverity::OK,
            'We expect to get no violations concerning root page configurations',
        );
        self::assertStringContainsString(
            'Indexing is disabled',
            $results[1]->getValue(),
            'Did not get an indexing disabled violation',
        );
    }
}
