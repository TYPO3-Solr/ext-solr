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
