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

use ApacheSolrForTypo3\Solr\Report\SchemaStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Integration test for the schema status report
 *
 * @author Timo Hund
 */
class SchemaStatusTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canGetAGrennSchemaStatusAgainstTestServer()
    {
        /** @var $schemaStatus  SchemaStatus */
        $schemaStatus = GeneralUtility::makeInstance(SchemaStatus::class);
        $violations = $schemaStatus->getStatus();

        self::assertEmpty($violations, 'We expect to get no violations against the test solr server');
    }
}
