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

use ApacheSolrForTypo3\Solr\Report\SiteHandlingStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Integration test for the site handling status report
 *
 */
class SiteHandlingStatusTest extends IntegrationTest
{
    /**
     * @test
     */
    public function allStatusChecksShouldBeOkForFirstTestSite()
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->importDataSetFromFixture('simple_site.xml');

        /** @var $siteHandlingStatus  siteHandlingStatus */
        $siteHandlingStatus = GeneralUtility::makeInstance(SiteHandlingStatus::class);
        $statusCollection = $siteHandlingStatus->getStatus();

        foreach($statusCollection as $status) {
            /** @var $status Status */
            $this->assertSame(Status::OK, $status->getSeverity(), 'Expected that all status checks for site handling configuration of first test site should be ok');
        }
    }

    /**
     * @test
     */
    public function statusCheckShouldFailIfSchemeIsNotDefined()
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->mergeSiteConfiguration('integration_tree_one', [
            'base' => 'authorityOnly.example.com'
        ]);
        $this->importDataSetFromFixture('simple_site.xml');


        /** @var $siteHandlingStatus  SiteHandlingStatus */
        $siteHandlingStatus = GeneralUtility::makeInstance(SiteHandlingStatus::class);
        $statusCollection = $siteHandlingStatus->getStatus();

        foreach($statusCollection as $status) {
            /** @var $status Status */
            $this->assertSame(Status::ERROR, $status->getSeverity(), 'Expected that status checks for site handling configuration should indicate an error if scheme in "Entry Point[base]" is not defined.');
            $this->assertRegExp('~.*are empty or invalid\: &quot;scheme&quot;~', $status->getMessage());
        }
    }

    /**
     * @test
     */
    public function statusCheckShouldFailIfAuthorityIsNotDefined()
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->mergeSiteConfiguration('integration_tree_one', [
            'base' => '/'
        ]);
        $this->importDataSetFromFixture('simple_site.xml');


        /** @var $siteHandlingStatus  SiteHandlingStatus */
        $siteHandlingStatus = GeneralUtility::makeInstance(SiteHandlingStatus::class);
        $statusCollection = $siteHandlingStatus->getStatus();

        foreach($statusCollection as $status) {
            /** @var $status Status */
            $this->assertSame(Status::ERROR, $status->getSeverity(), 'Expected that status checks for site handling configuration should indicate an error if authority in "Entry Point[base]" is not defined.');
            $this->assertRegExp('~.*are empty or invalid\: &quot;scheme, host&quot;~', $status->getMessage());
        }
    }

    /**
     * @test
     */
    public function statusCheckShouldFailIfBaseIsSetWrongInLanguages()
    {
        $this->writeDefaultSolrTestSiteConfiguration();

        // mergeSiteConfiguration() do not work recursively
        $siteConfiguration = new SiteConfiguration($this->instancePath . '/typo3conf/sites/');
        $configuration = $siteConfiguration->load('integration_tree_one');
        $configuration['languages'][1]['base'] = 'authorityOnly.example.com';

        $this->mergeSiteConfiguration('integration_tree_one', $configuration);
        $this->importDataSetFromFixture('simple_site.xml');

        /** @var $siteHandlingStatus  SiteHandlingStatus */
        $siteHandlingStatus = GeneralUtility::makeInstance(SiteHandlingStatus::class);
        $statusCollection = $siteHandlingStatus->getStatus();

        foreach($statusCollection as $status) {
            /** @var $status Status */
            $this->assertSame(Status::ERROR, $status->getSeverity(), 'Expected that status checks for site handling configuration should indicate an error if authority in "Entry Point[base]" is not defined.');
            $this->assertRegExp('~.*is not valid URL\. Following parts of defined URL are empty or invalid\: &quot;scheme&quot;~', $status->getMessage());
        }
    }
}
