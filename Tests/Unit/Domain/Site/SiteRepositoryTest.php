<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Site;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 - Thomas Hohn <tho@systime.dk>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the SiteRepository class works as expected.
 *
 * The unit test is used to make sure that the SiteRepository works as expected
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class SiteRepositoryTest extends UnitTest
{
    /**
     * @test
     */
    public function canGetSiteByRootPageId()
    {
        // @todo

    }

    /**
     * @test
     */
    public function canGetSiteByPageId()
    {
        // @todo
    }

    /**
     * @test
     */
    public function canGetFirstAvailableSite()
    {
        // @todo
    }

    /**
     * @test
     */
    public function canGetAvailableSites()
    {
        // @todo
    }

    /**
     * @test
     */
    public function canGetAllLanguages()
    {
        // @todo
    }
}