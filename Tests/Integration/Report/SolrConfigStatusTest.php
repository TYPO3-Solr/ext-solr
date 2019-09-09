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

use ApacheSolrForTypo3\Solr\Report\SolrConfigStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the config status report
 *
 * @author Timo Hund
 */
class SolrConfigStatusTest extends IntegrationTest
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
    public function canGetAGreenSolrConfigStatusAgainstTestServer()
    {

        /** @var $schemaStatus  SolrConfigStatus */
        $schemaStatus = GeneralUtility::makeInstance(SolrConfigStatus::class);
        $violations = $schemaStatus->getStatus();
        $this->assertEmpty($violations, 'We expect to get no violations against the test solr server');
    }
}
