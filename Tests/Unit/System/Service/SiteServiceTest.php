<?php
namespace ApacheSolrForTypo3\Solr\Test\System\Service;

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

use ApacheSolrForTypo3\Solr\System\Service\SiteService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Unit test for the SiteService
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SiteServiceTest extends UnitTest
{

    /**
     * @test
     */
    public function canGetFirstDomainByPage()
    {
        $sites = [
            32 => [
                'domains' => ['mysite.com']
            ],
            78 => [
                'domains' => ['mymicrosite.com']
            ]
        ];

        $siteService = new SiteService($sites);
        $this->assertSame('mymicrosite.com', $siteService->getFirstDomainForRootPage(78));
        $this->assertSame('mymicrosite.com', $siteService->getFirstDomainForRootPage(78));
        $this->assertSame('mysite.com', $siteService->getFirstDomainForRootPage(32));
    }

    /**
     * @test
     */
    public function canGetEmptyDomainWhenNothingIsConfigured()
    {
        $siteService = new SiteService();
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['sites']);
        $this->assertSame('', $siteService->getFirstDomainForRootPage(32));
    }
}
