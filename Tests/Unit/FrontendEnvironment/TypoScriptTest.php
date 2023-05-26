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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\FrontendEnvironment\TypoScript;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TypoScriptTest extends SetUpUnitTestCase
{
    protected TypoScript|MockObject $typoScriptMock;
    protected TypoScriptConfiguration|MockObject $typoScriptConfigurationDumpMock;

    protected function setUp(): void
    {
        $this->typoScriptMock = $this->getMockBuilder(TypoScript::class)
            ->disableOriginalConstructor()
            ->onlyMethods(
                [
                    'buildConfigurationArray',
                    'buildTypoScriptConfigurationFromArray',
                ]
            )->getMock();

        $this->typoScriptConfigurationDumpMock = $this->createMock(TypoScriptConfiguration::class);
        parent::setUp();
    }

    /**
     * @test
     */
    public function getConfigurationFromPageIdReturnsCachedConfiguration(): void
    {
        $pageId = 12;
        $path = '';
        $language = 0;
        $cacheId = md5($pageId . '|' . $path);

        // prepare first call
        $twoLevelCache = $this->createMock(TwoLevelCache::class);
        $twoLevelCache
            ->expects(self::once())
            ->method('get')->with($cacheId)->willReturn([]);
        $twoLevelCache
            ->expects(self::once())
            ->method('set')->with($cacheId, []);
        GeneralUtility::addInstance(TwoLevelCache::class, $twoLevelCache);

        $this->typoScriptMock
            ->method('buildConfigurationArray')
            ->willReturn([]);

        $this->typoScriptMock
            ->method('buildTypoScriptConfigurationFromArray')
            ->willReturn($this->typoScriptConfigurationDumpMock);

        $newConfiguration = $this->typoScriptMock->getConfigurationFromPageId(
            $pageId,
            $path
        );

        self::assertInstanceOf(TypoScriptConfiguration::class, $newConfiguration);

        // prepare second/cached call
        // pageRepository->getRootLine should be called only once
        // cache->set should be called only once
        $cachedConfiguration = $this->typoScriptMock->getConfigurationFromPageId(
            $pageId,
            $path,
            $language
        );

        self::assertInstanceOf(TypoScriptConfiguration::class, $cachedConfiguration);

        self::assertSame($newConfiguration, $cachedConfiguration);
    }
}
