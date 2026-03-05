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
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\UriInterface;
use Traversable;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the SiteHashService class works as expected.
 *
 * The unit test is used to make sure that the SiteHashService works as expected when the calls to Site:: are mocked
 */
class SiteHashServiceTest extends SetUpUnitTestCase
{
    protected EventDispatcherInterface|MockObject $eventDispatcherMock;

    protected function setUp(): void
    {
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        parent::setUp();
    }

    public static function canResolveSiteHashAllowedSitesDataProvider(): Traversable
    {
        yield 'siteHashDisabled' => ['*', '*'];
        yield 'allSitesInSystem' => ['__all', 'siteA,siteB'];
        yield 'currentSiteOnly' => ['__current_site', 'siteA'];
        yield 'emptyIsFallingBackToCurrentSiteOnly' => ['', 'siteA'];
        yield 'nullIsFallingBackToCurrentSiteOnly' => [null, 'siteA'];
    }

    #[DataProvider('canResolveSiteHashAllowedSitesDataProvider')]
    #[Test]
    public function canResolveSiteHashAllowedSites($allowedSitesConfiguration, $expectedAllowedSites): void
    {
        $siteLanguageMock = $this->createMock(SiteLanguage::class);
        $siteLanguageMock->method('getLanguageId')->willReturn(0);

        $siteConfiguration = [
            'solr_enabled_read' => 1,
            'solr_core_read' => 'core_en',
        ];

        $baseAMock = $this->createMock(UriInterface::class);
        $siteA = $this->createMock(Site::class);
        $siteA->method('getIdentifier')->willReturn('siteA');
        $siteA->method('getBase')->willReturn($baseAMock);
        $siteA->method('getLanguages')->willReturn([$siteLanguageMock]);
        $siteA->method('getConfiguration')->willReturn($siteConfiguration);

        $baseBMock = $this->createMock(UriInterface::class);
        $baseBMock->method('getHost')->willReturn('solrtestb.local');
        $siteB = $this->createMock(Site::class);
        $siteB->method('getIdentifier')->willReturn('siteB');
        $siteB->method('getBase')->willReturn($baseBMock);
        $siteB->method('getLanguages')->willReturn([$siteLanguageMock]);
        $siteB->method('getConfiguration')->willReturn($siteConfiguration);

        $allSites = [$siteA, $siteB];

        $siteFinderMock = $this->createMock(SiteFinder::class);
        $siteFinderMock->method('getAllSites')->willReturn($allSites);
        $siteFinderMock->method('getSiteByPageId')->willReturn($siteA);

        $siteHashService = new SiteHashService(
            $siteFinderMock,
            GeneralUtility::makeInstance(ExtensionConfiguration::class),
            $this->eventDispatcherMock,
        );

        $allowedSites = $siteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration(1, $allowedSitesConfiguration);
        self::assertSame($expectedAllowedSites, $allowedSites, 'resolveSiteHashAllowedSites did not return expected allowed sites');
    }

    #[Test]
    public function getSiteHashForSiteIdentifierCanHashTheGivenString(): void
    {
        $oldKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'testKey';

        $service = new SiteHashService(
            $this->createMock(SiteFinder::class),
            GeneralUtility::makeInstance(ExtensionConfiguration::class),
            $this->eventDispatcherMock,
        );
        /**
         * @todo: The method {@link SiteHashService::getSiteHashForSiteIdentifier()} uses static method variable `$siteHashes`,
         *        which leads to collisions between the tests, because the variable is never reset between the tests.
         *        Find the solution how to reset the $siteHashes in tearDown() or use proper caching implementation instead.
         *        Maybe we want a static analysis rule to disallow the static method vars.
         *
         * Current solution: Use always different parameter-values on each call of {@link SiteHashService::getSiteHashForSiteIdentifier()}...
         */
        $hash1 = $service->getSiteHashForSiteIdentifier('test-site-01');
        $hash2 = $service->getSiteHashForSiteIdentifier('www.example.com');

        self::assertEquals('f0a405dab4884e0a141f7bc203e63ed79dd150b2', $hash1);
        self::assertEquals('a8af88b144e020caf72a511c78e78fcdd378b2c9', $hash2);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $oldKey;
    }
}
