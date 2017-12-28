<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Page;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Page\Rootline;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for the ArrayAccessor helper class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RootlineTest extends UnitTest
{

    /**
     * @test
     */
    public function getRootPageIdReturnUidOfRootPage() {
        $testRootLineArray = [
            ['uid' => 100,'pid' => 10, 'title' => 'level 2'],
            ['uid' => 10,'pid' => 1, 'title' => 'level 1'],
            ['uid' => 1,'pid' => 0, 'title' => 'rootpage', 'is_siteroot' => 1]
        ];

        $rootline = new Rootline($testRootLineArray);
        $this->assertSame(1, $rootline->getRootPageId(), 'GetRootPageId does not return expected root page id');
    }

    /**
     * @test
     */
    public function getRootPageIdReturnsZeroWhenNoSiteRootIsPresent() {
        $rootline = new Rootline([]);
        $this->assertSame(0, $rootline->getRootPageId(), 'Expecting null when no rootline given');
    }

    /**
     * @test
     */
    public function getHasRootPageReturnsFalseOnEmptyRootLine() {
        $rootline = new Rootline([]);
        $this->assertFalse($rootline->getHasRootPage(), 'Expecting false when no rootline given');
    }

    /**
     * @test
     */
    public function getHasRootPageRturnsTrueWithGivenRootLine() {
        $testRootLineArray = [
            ['uid' => 100,'pid' => 10, 'title' => 'level 2'],
            ['uid' => 10,'pid' => 1, 'title' => 'level 1'],
            ['uid' => 1,'pid' => 0, 'title' => 'rootpage', 'is_siteroot' => 1]
        ];

        $rootline = new Rootline($testRootLineArray);
        $this->assertTrue($rootline->getHasRootPage(), 'Expecting true when rootline with rootpage given');
    }

    /**
     * @test
     */
    public function canGetParentPageIds() {
        $testRootLineArray = [
            ['uid' => 100,'pid' => 10, 'title' => 'level 2'],
            ['uid' => 10,'pid' => 1, 'title' => 'level 1'],
            ['uid' => 1,'pid' => 0, 'title' => 'rootpage', 'is_siteroot' => 1]
        ];

        $rootline = new Rootline($testRootLineArray);
        $this->assertEquals([100,10,1], $rootline->getParentPageIds(), 'Expecting true when rootline with rootpage given');
    }
}