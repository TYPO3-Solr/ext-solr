<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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
     * @return void
     */
    public function setUp()
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

            /** @var $solrConfigurationStatus  \ApacheSolrForTypo3\Solr\Report\SolrConfigurationStatus */
        $solrConfigurationStatus = GeneralUtility::makeInstance(SolrConfigurationStatus::class);
        $violations = $solrConfigurationStatus->getStatus();
        $this->assertEmpty($violations, 'We did not get an empty response from the solr configuration status report! Something is wrong');
    }

    /**
     * @test
     */
    public function canDetectMissingRootPage()
    {
        $this->importDataSetFromFixture('can_detect_missing_rootpage.xml');

        /** @var $solrConfigurationStatus  \ApacheSolrForTypo3\Solr\Report\SolrConfigurationStatus */
        $solrConfigurationStatus = GeneralUtility::makeInstance(SolrConfigurationStatus::class);
        $violations = $solrConfigurationStatus->getStatus();

        $this->assertCount(1, $violations, 'Asserting to contain only one violation.');

        $firstViolation = array_pop($violations);
        $this->assertContains('No sites', $firstViolation->getValue(), 'Did not get a no sites found violation');
    }

    /**
     * @test
     */
    public function canDetectIndexingDisabled()
    {
        $this->importDataSetFromFixture('can_detect_indexing_disabled.xml');

        /** @var $solrConfigurationStatus  \ApacheSolrForTypo3\Solr\Report\SolrConfigurationStatus */
        $solrConfigurationStatus = GeneralUtility::makeInstance(SolrConfigurationStatus::class);
        $violations = $solrConfigurationStatus->getStatus();

        $this->assertCount(1, $violations, 'Asserting to contain only one violation.');

        $firstViolation = array_pop($violations);
        $this->assertContains('Indexing is disabled', $firstViolation->getValue(), 'Did not get a no sites found violation');
    }
}
