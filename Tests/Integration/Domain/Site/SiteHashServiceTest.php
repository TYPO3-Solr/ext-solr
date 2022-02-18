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
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;

/**
 * Testcase to check if the SiteHashService class works as expected.
 *
 * The integration test is used to check if we get the expected results with a defined database state.
 *
 * @author Timo Hund <timo.hund.de>
 */
class SiteHashServiceTest extends IntegrationTest
{

    /**
     * @throws NoSuchCacheException
     * @throws TestingFrameworkCoreException
     * @throws DBALException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @return array
     */
    public function canResolveSiteHashAllowedSitesDataProvider(): array
    {
        return [
            'siteHashDisabled' => ['*', '*'],
            'allSitesInSystem' => ['__all', 'testone.site,testtwo.site'],
            'currentSiteOnly' => ['__current_site', 'testone.site'],
        ];
    }

    /**
     * @dataProvider canResolveSiteHashAllowedSitesDataProvider
     * @test
     */
    public function canResolveSiteHashAllowedSites($allowedSitesConfiguration, $expectedAllowedSites)
    {
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        $allowedSites = $siteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration(1, $allowedSitesConfiguration);
        self::assertSame($expectedAllowedSites, $allowedSites, 'resolveSiteHashAllowedSites did not return expected allowed sites');
    }
}
