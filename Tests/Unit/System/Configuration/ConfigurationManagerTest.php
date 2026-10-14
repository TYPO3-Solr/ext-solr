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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Configuration;

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationManagerTest extends SetUpUnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function getTypoScriptConfigurationFallsBackToTypo3RequestWhenPageIsDeleted(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $this->createMock(ServerRequestInterface::class);
        $expectedConfiguration = new TypoScriptConfiguration([]);

        $manager = $this->createPartialMock(
            ConfigurationManager::class,
            ['fetchPageRecord', 'getTypoScriptFromRequest'],
        );

        // Simulate a page that was deleted in a workspace swap
        $manager->method('fetchPageRecord')
            ->with(999)
            ->willReturn(null);

        // The fallback must be called with TYPO3_REQUEST, not a page-derived request
        $manager->expects(self::once())
            ->method('getTypoScriptFromRequest')
            ->with($GLOBALS['TYPO3_REQUEST'])
            ->willReturn($expectedConfiguration);

        $result = $manager->getTypoScriptConfiguration(999);

        self::assertSame($expectedConfiguration, $result);
    }

    #[Test]
    public function getTypoScriptFromRequestRebuildsConfigurationWhenCacheEntryVanishedAfterHasCheck(): void
    {
        $setup = ['plugin.' => ['tx_solr.' => ['enabled' => '1']]];

        // has() still sees the entry, but it expires or is flushed before get() reads it
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('has')->with('42_0')->willReturn(true);
        $cache->method('get')->with('42_0')->willReturn(false);
        $cache->expects(self::once())->method('set')->with('42_0', $setup);
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCache')->with('hash')->willReturn($cache);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManager);

        $frontendTypoScript = $this->createMock(FrontendTypoScript::class);
        $frontendTypoScript->method('getSetupArray')->willReturn($setup);
        $manager = $this->createPartialMock(ConfigurationManager::class, ['getCoreTypoScriptFrontendByRequest']);
        $manager->expects(self::once())
            ->method('getCoreTypoScriptFrontendByRequest')
            ->willReturn($frontendTypoScript);

        $request = (new ServerRequest('https://example.com/', 'GET'))->withQueryParams(['id' => 42]);
        $result = $manager->getTypoScriptFromRequest($request);

        self::assertSame('1', $result->getValueByPath('plugin.tx_solr.enabled'));
    }

    #[Test]
    public function getTypoScriptFromRequestUsesCachedConfiguration(): void
    {
        $setup = ['plugin.' => ['tx_solr.' => ['enabled' => '1']]];

        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('has')->with('42_0')->willReturn(true);
        $cache->method('get')->with('42_0')->willReturn($setup);
        $cache->expects(self::never())->method('set');
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCache')->with('hash')->willReturn($cache);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManager);

        $manager = $this->createPartialMock(ConfigurationManager::class, ['getCoreTypoScriptFrontendByRequest']);
        $manager->expects(self::never())->method('getCoreTypoScriptFrontendByRequest');

        $request = (new ServerRequest('https://example.com/', 'GET'))->withQueryParams(['id' => 42]);
        $result = $manager->getTypoScriptFromRequest($request);

        self::assertSame('1', $result->getValueByPath('plugin.tx_solr.enabled'));
    }
}
