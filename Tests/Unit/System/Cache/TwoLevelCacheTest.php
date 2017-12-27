<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Cache;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Unit testcase to check if the two level cache is working as expected.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class TwoLevelCacheTest extends UnitTest
{

    /**
     * @var TwoLevelCache
     */
    protected $twoLevelCache;

    /**
     * @var  \TYPO3\CMS\Core\Cache\Frontend\FrontendInterface
     */
    protected $secondLevelCacheMock;

    /**
     * @return false
     */
    public function setUp()
    {
        $this->secondLevelCacheMock = $this->getDumbMock(FrontendInterface::class);
        $this->twoLevelCache = new TwoLevelCache('test', $this->secondLevelCacheMock);
    }

    /**
     * @test
     */
    public function getOnSecondaryCacheIsNeverCalledWhenValueIsPresentInFirstLevelCache()
    {
        $this->secondLevelCacheMock->expects($this->never())->method('get');

        // when we add a value with the identifier to the two level cache, the second level
        // cache should not be asked because the value should allready be found in the first
        // level cache
        $this->twoLevelCache->set('foo', 'bar');

        $value = $this->twoLevelCache->get('foo');
        $this->assertSame($value, 'bar', 'Did not get expected value from two level cache');
    }
}
