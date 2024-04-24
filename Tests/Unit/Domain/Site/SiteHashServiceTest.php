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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Site;

use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use Psr\Http\Message\UriInterface;
use Traversable;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Testcase to check if the SiteHashService class works as expected.
 *
 * The unit test is used to make sure that the SiteHashService works as expected when the calls to Site:: are mocked
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SiteHashServiceTest extends SetUpUnitTestCase
{
    public static function canResolveSiteHashAllowedSitesDataProvider(): Traversable
    {
        yield 'siteHashDisabled' => ['*', '*'];
        yield 'allSitesInSystem' => ['__all', 'solrtesta.local,solrtestb.local'];
        yield 'currentSiteOnly' => ['__current_site', 'solrtesta.local'];
        yield 'emptyIsFallingBackToCurrentSiteOnly' => ['', 'solrtesta.local'];
        yield 'nullIsFallingBackToCurrentSiteOnly' => [null, 'solrtesta.local'];
    }

    /**
     * @dataProvider canResolveSiteHashAllowedSitesDataProvider
     * @test
     */
    public function canResolveSiteHashAllowedSites($allowedSitesConfiguration, $expectedAllowedSites)
    {
        $siteLanguageMock = $this->createMock(SiteLanguage::class);
        $siteLanguageMock->method('getLanguageId')->willReturn(0);

        $siteConfiguration = ['solr_enabled_read' => 1, 'solr_core_read' => 'core_en'];

        $baseAMock = $this->createMock(UriInterface::class);
        $baseAMock->method('getHost')->willReturn('solrtesta.local');
        $siteA = $this->createMock(Site::class);
        $siteA->method('getBase')->willReturn($baseAMock);
        $siteA->method('getLanguages')->willReturn([$siteLanguageMock]);
        $siteA->method('getConfiguration')->willReturn($siteConfiguration);

        $baseBMock = $this->createMock(UriInterface::class);
        $baseBMock->method('getHost')->willReturn('solrtestb.local');
        $siteB = $this->createMock(Site::class);
        $siteB->method('getBase')->willReturn($baseBMock);
        $siteB->method('getLanguages')->willReturn([$siteLanguageMock]);
        $siteB->method('getConfiguration')->willReturn($siteConfiguration);

        $allSites = [$siteA, $siteB];

        $siteFinderMock = $this->createMock(SiteFinder::class);
        $siteFinderMock->method('getAllSites')->willReturn($allSites);
        $siteFinderMock->method('getSiteByPageId')->willReturn($siteA);

        $siteHashService = new SiteHashService($siteFinderMock);

        $allowedSites = $siteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration(1, $allowedSitesConfiguration);
        self::assertSame($expectedAllowedSites, $allowedSites, 'resolveSiteHashAllowedSites did not return expected allowed sites');
    }

    /**
     * @test
     */
    public function getSiteHashForDomain()
    {
        $oldKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'testKey';

        $service = new SiteHashService($this->createMock(SiteFinder::class));
        $hash1 = $service->getSiteHashForDomain('www.example.com');
        $hash2 = $service->getSiteHashForDomain('www.example.com');

        self::assertEquals('3f91984c5c353933cc82d3659dbb08e392b7d541', $hash1);
        self::assertEquals('3f91984c5c353933cc82d3659dbb08e392b7d541', $hash2);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $oldKey;
    }
}
