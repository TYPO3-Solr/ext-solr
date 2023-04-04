<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Site;

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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase to check if the SiteHashService class works as expected.
 *
 * The unit test is used to make sure that the SiteHashService works as expected when the calls to Site:: are mocked
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SiteHashServiceTest extends UnitTest
{

    /**
     * @return array
     */
    public function canResolveSiteHashAllowedSitesDataProvider()
    {
        return [
            'siteHashDisabled' => ['*', '*'],
            'allSitesInSystem' => ['__all', 'solrtesta.local,solrtestb.local'],
            'currentSiteOnly' => ['__current_site', 'solrtesta.local'],
            'emptyIsFallingBackToCurrentSiteOnly' => ['', 'solrtesta.local'],
            'nullIsFallingBackToCurrentSiteOnly' => [null, 'solrtesta.local'],
        ];
    }

    /**
     * @dataProvider canResolveSiteHashAllowedSitesDataProvider
     * @test
     */
    public function canResolveSiteHashAllowedSites($allowedSitesConfiguration, $expectedAllowedSites)
    {
        $siteA = $this->getDumbMock(Site::class);
        $siteA->expects(self::any())->method('getDomain')->willReturn('solrtesta.local');
        $siteB = $this->getDumbMock(Site::class);
        $siteB->expects(self::any())->method('getDomain')->willReturn('solrtestb.local');
        $allSites = [$siteA, $siteB];

        /** @var $siteHashServiceMock SiteHashService */
        $siteHashServiceMock = $this->getMockBuilder(SiteHashService::class)->setMethods(['getAvailableSites', 'getSiteByPageId'])->getMock();
        $siteHashServiceMock->expects(self::any())->method('getAvailableSites')->willReturn($allSites);
        $siteHashServiceMock->expects(self::any())->method('getSiteByPageId')->willReturn($siteA);

        $allowedSites = $siteHashServiceMock->getAllowedSitesForPageIdAndAllowedSitesConfiguration(1, $allowedSitesConfiguration);
        self::assertSame($expectedAllowedSites, $allowedSites, 'resolveSiteHashAllowedSites did not return expected allowed sites');
    }

    /**
     * @test
     */
    public function getSiteHashForDomain()
    {
        $oldKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'testKey';

        $service = new SiteHashService();
        $hash1 = $service->getSiteHashForDomain('www.example.com');
        $hash2 = $service->getSiteHashForDomain('www.example.com');

        self::assertEquals('3f91984c5c353933cc82d3659dbb08e392b7d541', $hash1);
        self::assertEquals('3f91984c5c353933cc82d3659dbb08e392b7d541', $hash2);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $oldKey;
    }
}
