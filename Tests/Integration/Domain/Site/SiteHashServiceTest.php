<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Site;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the SiteHashService class works as expected.
 *
 * The integration test is used to check if we get the expected results with a defined database state.
 *
 * @author Timo Hund <timo.hund.de>
 */
class SiteHashServiceTest extends IntegrationTest
{

    public function setUp() {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @return array
     */
    public function canResolveSiteHashAllowedSitesDataProvider() {
        return [
            'siteHashDisabled' => ['*', '*'],
            'allSitesInSystem' => ['__all', 'testone.site,testtwo.site'],
            'currentSiteOnly' => ['__current_site', 'testone.site']
        ];
    }

    /**
     * @dataProvider canResolveSiteHashAllowedSitesDataProvider
     * @test
     */
    public function canResolveSiteHashAllowedSites($allowedSitesConfiguration , $expectedAllowedSites)
    {
        $this->importDataSetFromFixture('can_resolve_site_hash.xml');
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        $allowedSites = $siteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration(1, $allowedSitesConfiguration);
        $this->assertSame($expectedAllowedSites, $allowedSites, 'resolveSiteHashAllowedSites did not return expected allowed sites');
    }
}