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

use ApacheSolrForTypo3\Solr\Report\SolrVersionStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the solr version test
 *
 * @author Timo Hund
 */
class SolrVersionStatusTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canGetAGreenSolrConfigStatusAgainstTestServer()
    {
        $this->importDataSetFromFixture('can_check_solr_version_status.xml');

        /* @var SolrVersionStatus $solrVersionStatus */
        $solrVersionStatus = GeneralUtility::makeInstance(SolrVersionStatus::class);
        $violations = $solrVersionStatus->getStatus();
        self::assertEmpty($violations, 'We expect to get no violations against the test solr server');
    }
}
