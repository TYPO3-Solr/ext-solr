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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Cache;

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Cache\Backend\BackendInterface;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

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
     * @var  FrontendInterface
     */
    protected $secondLevelCacheMock;

    /**
     * Prepare
     */
    protected function setUp(): void
    {
        $this->secondLevelCacheMock = $this->getDumbMock(FrontendInterface::class);
        $this->twoLevelCache = new TwoLevelCache('test', $this->secondLevelCacheMock);
        parent::setUp();
    }

    /**
     * Cleanup
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        $this->twoLevelCache->flush();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getOnSecondaryCacheIsNeverCalledWhenValueIsPresentInFirstLevelCache(): void
    {
        $this->secondLevelCacheMock->expects(self::never())->method('get');

        // when we add a value with the identifier to the two level cache, the second level
        // cache should not be asked because the value should allready be found in the first
        // level cache
        $this->twoLevelCache->set('foo', 'bar');

        $value = $this->twoLevelCache->get('foo');
        self::assertSame($value, 'bar', 'Did not get expected value from two level cache');
    }

    /**
     * @test
     * @throws NoSuchCacheException
     */
    public function canHandleInvalidCacheIdentifierOnSet(): void
    {
        $cacheBackendMock = $this->createMock(BackendInterface::class);
        $cacheBackendMock->expects(self::once())->method('set');
        $variableFrontend = new VariableFrontend('TwoLevelCacheTest', $cacheBackendMock);
        $this->twoLevelCache = new TwoLevelCache('test', $variableFrontend);

        $this->twoLevelCache->set('I.Am.An.Invalid.Identifier-#ß%&!', 'dummyValue');
    }

    /**
     * @test
     * @throws NoSuchCacheException
     */
    public function canHandleInvalidCacheIdentifierOnGet(): void
    {
        $cacheBackendMock = $this->createMock(BackendInterface::class);
        $cacheBackendMock->expects(self::once())->method('get')->willReturn(self::returnValue(''));
        $variableFrontend = new VariableFrontend('TwoLevelCacheTest', $cacheBackendMock);
        $this->twoLevelCache = new TwoLevelCache('test', $variableFrontend);

        self::assertFalse($this->twoLevelCache->get('I.Am.An.Invalid.Identifier-#ß%&!'));
    }
}
