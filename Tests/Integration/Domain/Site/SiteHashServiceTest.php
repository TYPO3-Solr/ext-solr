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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Site;

use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the SiteHashService class works as expected.
 *
 * The integration test is used to check if we get the expected results with a defined database state.
 *
 * @author Timo Hund <timo.hund.de>
 */
class SiteHashServiceTest extends IntegrationTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    public static function canResolveSiteHashAllowedSitesDataProvider(): Traversable
    {
        yield 'siteHashDisabled' => ['*', '*'];
        yield 'allSitesInSystem' => ['__all', 'testone.site,testtwo.site'];
        yield 'currentSiteOnly' => ['__current_site', 'testone.site'];
    }

    #[DataProvider('canResolveSiteHashAllowedSitesDataProvider')]
    #[Test]
    public function canResolveSiteHashAllowedSites($allowedSitesConfiguration, $expectedAllowedSites): void
    {
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        $allowedSites = $siteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration(1, $allowedSitesConfiguration);
        self::assertSame($expectedAllowedSites, $allowedSites, 'resolveSiteHashAllowedSites did not return expected allowed sites');
    }
}
