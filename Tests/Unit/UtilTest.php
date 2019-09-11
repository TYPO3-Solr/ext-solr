<?php

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

class UtilTest extends UnitTest
{
    public function setUp()
    {
        /** @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend|\Prophecy\Prophecy\ObjectProphecy $frontendCache */
        $frontendCache = $this->prophesize(\TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class);
        /** @var \TYPO3\CMS\Core\Cache\CacheManager|\Prophecy\Prophecy\ObjectProphecy $cacheManager */
        $cacheManager = $this->prophesize(\TYPO3\CMS\Core\Cache\CacheManager::class);
        $cacheManager
            ->getCache('cache_pages')
            ->willReturn($frontendCache->reveal());
        $cacheManager
            ->getCache('cache_runtime')
            ->willReturn($frontendCache->reveal());
        $cacheManager
            ->getCache('tx_solr_configuration')
            ->willReturn($frontendCache->reveal());
        GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Cache\CacheManager::class, $cacheManager->reveal());
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
    }

    public function tearDown()
    {
        GeneralUtility::resetSingletonInstances([]);
        unset($GLOBALS['TSFE']);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getConfigurationFromPageIdReturnsEmptyConfigurationForPageIdZero()
    {
        $configuration = Util::getConfigurationFromPageId(0, 'plugin.tx_solr', false, 0, false);
        $this->assertInstanceOf('ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration', $configuration);
    }

    /**
     * @test
     */
    public function getConfigurationFromPageIdReturnsCachedConfiguration()
    {
        error_reporting(0); // needed to disable exception reporting of deprecate methods with trigger_error
        $pageId = 12;
        $path = '';
        $language = 0;
        $initializeTsfe = false;
        $cacheId = md5($pageId . '|' . $path . '|' . $language . '|' . ($initializeTsfe ? '1' : '0'));

        // prepare first call

        /** @var TwoLevelCache|\Prophecy\Prophecy\ObjectProphecy $twoLevelCache */
        $twoLevelCache = $this->prophesize(TwoLevelCache::class);
        $twoLevelCache
            ->get($cacheId)
            ->shouldBeCalled()
            ->willReturn([]);
        $twoLevelCache
            ->set($cacheId, [])
            ->shouldBeCalledOnce();
        GeneralUtility::addInstance(TwoLevelCache::class, $twoLevelCache->reveal());


        $rootLineUtility = $this->prophesize(\TYPO3\CMS\Core\Utility\RootlineUtility::class);
        $rootLineUtility->get()->shouldBeCalledOnce()->willReturn([]);
        GeneralUtility::addInstance(\TYPO3\CMS\Core\Utility\RootlineUtility::class, $rootLineUtility->reveal());

        /** @var ExtendedTemplateService|\Prophecy\Prophecy\ObjectProphecy $extendedTemplateService */
        $extendedTemplateService = $this->prophesize(ExtendedTemplateService::class);
        GeneralUtility::addInstance(ExtendedTemplateService::class, $extendedTemplateService->reveal());

        $newConfiguration = Util::getConfigurationFromPageId(
            $pageId,
            $path,
            $initializeTsfe,
            $language,
            true
        );

        $this->assertInstanceOf('ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration', $newConfiguration);

        // prepare second/cached call
        // pageRepository->getRootLine should be called only once
        // cache->set should be called only once

        $cachedConfiguration = Util::getConfigurationFromPageId(
            $pageId,
            $path,
            $initializeTsfe,
            $language,
            true
        );

        $this->assertInstanceOf('ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration', $cachedConfiguration);

        $this->assertSame(
            $newConfiguration,
            $cachedConfiguration
        );
    }

    /**
     * @test
     */
    public function getConfigurationFromPageIdInitializesTsfe()
    {
        error_reporting(0); // needed to disable exception reporting of deprecate methods with trigger_error
        $pageId = 24;
        $path = '';
        $language = 0;
        $initializeTsfe = true;
        $cacheId = md5($pageId . '|' . $path . '|' . $language . '|' . ($initializeTsfe ? '1' : '0'));

        /** @var TwoLevelCache|\Prophecy\Prophecy\ObjectProphecy $twoLevelCache */
        $twoLevelCache = $this->prophesize(TwoLevelCache::class);
        $twoLevelCache
            ->get($cacheId)
            ->shouldBeCalled()
            ->willReturn([]);
        $twoLevelCache
            ->set($cacheId, [])
            ->shouldBeCalledOnce();
        GeneralUtility::addInstance(TwoLevelCache::class, $twoLevelCache->reveal());

        $this->buildTestCaseForTsfe($pageId, 13);

        $newConfiguration = Util::getConfigurationFromPageId(
            $pageId,
            $path,
            $initializeTsfe,
            $language,
            true
        );

        $this->assertInstanceOf('ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration', $newConfiguration);

        $this->assertSame(
            24,
            $GLOBALS['TSFE']->id
        );
    }

    /**
     * @test
     */
    public function getConfigurationFromPageIdInitializesTsfeOnCacheCall()
    {
        error_reporting(0); // needed to disable exception reporting of deprecate methods with trigger_error
        $path = '';
        $language = 0;
        $initializeTsfe = true;

        // prepare first call

        /** @var TwoLevelCache|\Prophecy\Prophecy\ObjectProphecy $twoLevelCache */
        $twoLevelCache = $this->prophesize(TwoLevelCache::class);
        $twoLevelCache
            ->get(\Prophecy\Argument::cetera())
            ->willReturn([]);
        $twoLevelCache
            ->set(\Prophecy\Argument::cetera())
            ->shouldBeCalled();
        GeneralUtility::addInstance(TwoLevelCache::class, $twoLevelCache->reveal());

        // Change TSFE->id to 12 ($pageId) and create new cache
        $this->buildTestCaseForTsfe(34, 1);
        Util::getConfigurationFromPageId(
            34,
            $path,
            $initializeTsfe,
            $language,
            true
        );
        $this->assertSame(
            34,
            $GLOBALS['TSFE']->id
        );

        // Change TSFE->id to 23 and create new cache
        $this->buildTestCaseForTsfe(56, 8);
        Util::getConfigurationFromPageId(
            56,
            $path,
            $initializeTsfe,
            $language,
            true
        );
        $this->assertSame(
            56,
            $GLOBALS['TSFE']->id
        );

        // prepare second/cached call
        // TSFE->id has to be changed back to 12 $pageId

        Util::getConfigurationFromPageId(
            34,
            $path,
            $initializeTsfe,
            $language,
            true
        );

        $this->assertSame(
            34,
            $GLOBALS['TSFE']->id
        );
    }

    protected function buildTestCaseForTsfe(int $pageId, int $rootPageId)
    {
        /** @var PageRepository|\Prophecy\Prophecy\ObjectProphecy $pageRepository */
        $pageRepository = $this->prophesize(PageRepository::class);
        GeneralUtility::addInstance(PageRepository::class, $pageRepository->reveal());

        /** @var ExtendedTemplateService|\Prophecy\Prophecy\ObjectProphecy $extendedTemplateService */
        $extendedTemplateService = $this->prophesize(ExtendedTemplateService::class);
        GeneralUtility::addInstance(ExtendedTemplateService::class, $extendedTemplateService->reveal());

        /** @var \ApacheSolrForTypo3\Solr\Domain\Site\Site|\Prophecy\Prophecy\ObjectProphecy $site */
        $site = $this->prophesize(\ApacheSolrForTypo3\Solr\Domain\Site\Site::class);
        $site
            ->getRootPageId()
            ->shouldBeCalled()
            ->willReturn($rootPageId);

        /** @var \ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository|\Prophecy\Prophecy\ObjectProphecy $siteRepository */
        $siteRepository = $this->prophesize(\ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository::class);
        $siteRepository
            ->getSiteByPageId($pageId)
            ->shouldBeCalled()
            ->willReturn($site->reveal());
        GeneralUtility::addInstance(\ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository::class, $siteRepository->reveal());

        /** @var \ApacheSolrForTypo3\Solr\System\Mvc\Frontend\Controller\OverriddenTypoScriptFrontendController|\Prophecy\Prophecy\ObjectProphecy $tsfeProphecy */
        $tsfeProphecy = $this->prophesize(\ApacheSolrForTypo3\Solr\System\Mvc\Frontend\Controller\OverriddenTypoScriptFrontendController::class);
        $tsfeProphecy->willBeConstructedWith([null, $pageId, 0]);
        $tsfe = $tsfeProphecy->reveal();
        $tsfe->tmpl = new \TYPO3\CMS\Core\TypoScript\TemplateService();
        GeneralUtility::addInstance(\ApacheSolrForTypo3\Solr\System\Mvc\Frontend\Controller\OverriddenTypoScriptFrontendController::class, $tsfe);
    }
}
