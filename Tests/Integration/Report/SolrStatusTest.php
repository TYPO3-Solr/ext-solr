<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Report\SolrStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Integration test for the solr status report
 *
 * @author Timo Hund
 */
class SolrStatusTest extends IntegrationTest
{

    /**
     * @test
     */
    public function allStatusChecksShouldBeOkForValidSolrConnection()
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->importDataSetFromFixture('simple_site.xml');

        /** @var $solrStatus  SolrStatus */
        $solrStatus = GeneralUtility::makeInstance(SolrStatus::class);
        $statusCollection = $solrStatus->getStatus();

        foreach($statusCollection as $status) {
            /** @var $status Status */
            $this->assertSame(Status::OK, $status->getSeverity(), 'Expected that all status objects should be ok');
        }
    }

    /**
     * @test
     */
    public function allStatusChecksShouldFailForInvalidSolrConnection()
    {
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort(null,'invalid', 4711);
        $this->importDataSetFromFixture('simple_site.xml');


        /** @var $solrStatus  SolrStatus */
        $solrStatus = GeneralUtility::makeInstance(SolrStatus::class);
        $statusCollection = $solrStatus->getStatus();

        foreach($statusCollection as $status) {
            /** @var $status Status */
            $this->assertSame(Status::ERROR, $status->getSeverity(), 'Expected that all status objects should indicate an error');
        }
    }
}
