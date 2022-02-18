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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Page;

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
    public function getRootPageIdReturnUidOfRootPage()
    {
        $testRootLineArray = [
            ['uid' => 100, 'pid' => 10, 'title' => 'level 2'],
            ['uid' => 10, 'pid' => 1, 'title' => 'level 1'],
            ['uid' => 1, 'pid' => 0, 'title' => 'rootpage', 'is_siteroot' => 1],
        ];

        $rootline = new Rootline($testRootLineArray);
        self::assertSame(1, $rootline->getRootPageId(), 'GetRootPageId does not return expected root page id');
    }

    /**
     * @test
     */
    public function getRootPageIdReturnsZeroWhenNoSiteRootIsPresent()
    {
        $rootline = new Rootline([]);
        self::assertSame(0, $rootline->getRootPageId(), 'Expecting null when no rootline given');
    }

    /**
     * @test
     */
    public function getHasRootPageReturnsFalseOnEmptyRootLine()
    {
        $rootline = new Rootline([]);
        self::assertFalse($rootline->getHasRootPage(), 'Expecting false when no rootline given');
    }

    /**
     * @test
     */
    public function getHasRootPageRturnsTrueWithGivenRootLine()
    {
        $testRootLineArray = [
            ['uid' => 100, 'pid' => 10, 'title' => 'level 2'],
            ['uid' => 10, 'pid' => 1, 'title' => 'level 1'],
            ['uid' => 1, 'pid' => 0, 'title' => 'rootpage', 'is_siteroot' => 1],
        ];

        $rootline = new Rootline($testRootLineArray);
        self::assertTrue($rootline->getHasRootPage(), 'Expecting true when rootline with rootpage given');
    }

    /**
     * @test
     */
    public function canGetParentPageIds()
    {
        $testRootLineArray = [
            ['uid' => 100, 'pid' => 10, 'title' => 'level 2'],
            ['uid' => 10, 'pid' => 1, 'title' => 'level 1'],
            ['uid' => 1, 'pid' => 0, 'title' => 'rootpage', 'is_siteroot' => 1],
        ];

        $rootline = new Rootline($testRootLineArray);
        self::assertEquals([100, 10, 1], $rootline->getParentPageIds(), 'Expecting true when rootline with rootpage given');
    }
}
