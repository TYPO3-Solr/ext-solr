<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\Util;
use Prophecy\Argument;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Core\Context\Context;

class UtilTest extends IntegrationTest
{
    public function setUp()
    {
        parent::setUp();
        /** @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend|\Prophecy\Prophecy\ObjectProphecy $frontendCache */
        $frontendCache = $this->prophesize(\TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class);
        /** @var \TYPO3\CMS\Core\Cache\CacheManager|\Prophecy\Prophecy\ObjectProphecy $cacheManager */
        $cacheManager = $this->prophesize(\TYPO3\CMS\Core\Cache\CacheManager::class);

        if (Util::getIsTYPO3VersionBelow10()) {

            $cacheManager
                ->getCache('cache_pages')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('cache_runtime')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('cache_hash')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('cache_core')
                ->willReturn($frontendCache->reveal());


            $cacheManager
                ->getCache('cache_rootline')
                ->willReturn($frontendCache->reveal());
        } else {
            $cacheManager
                ->getCache('pages')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('runtime')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('hash')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('core')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('hash')
                ->willReturn($frontendCache->reveal());
        }
        $cacheManager
            ->getCache('tx_solr_configuration')
            ->willReturn($frontendCache->reveal());
        GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Cache\CacheManager::class, $cacheManager->reveal());
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = [];
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
        $pageId = 12;
        $path = '';
        $language = 0;
        $initializeTsfe = false;
        $cacheId = md5($pageId . '|' . $path . '|' . $language);

        if (!Util::getIsTYPO3VersionBelow10()) {
            $rootLineUtility = $this->prophesize(\TYPO3\CMS\Core\Utility\RootlineUtility::class);
            $rootLineUtility->get()->shouldBeCalledOnce()->willReturn([]);
            GeneralUtility::addInstance(\TYPO3\CMS\Core\Utility\RootlineUtility::class, $rootLineUtility->reveal());
        }

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
        $pageId = 24;
        $path = '';
        $language = 0;
        $initializeTsfe = true;
        $cacheId = md5($pageId . '|' . $path . '|' . $language);

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

        $siteLanguage = $this->prophesize(SiteLanguage::class);


        $site = $this->prophesize(Site::class);
        $site->getLanguageById(0)
            ->shouldBeCalled()
            ->willReturn($siteLanguage->reveal());
        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId($pageId)
            ->shouldBeCalled()
            ->willReturn($site->reveal());

        GeneralUtility::addInstance(SiteFinder::class, $siteFinder->reveal());

        $tsfeProphecy = $this->prophesize(TypoScriptFrontendController::class);
        if (Util::getIsTYPO3VersionBelow10()) {
            $tsfeProphecy->willBeConstructedWith([null, $pageId, 0]);
        } else {
            $siteLanguage->getTypo3Language()->shouldBeCalled()->willReturn(0);

            $rootLineUtility = $this->prophesize(\TYPO3\CMS\Core\Utility\RootlineUtility::class);
            $rootLineUtility->get()->shouldBeCalledOnce()->willReturn([]);
            GeneralUtility::addInstance(\TYPO3\CMS\Core\Utility\RootlineUtility::class, $rootLineUtility->reveal());

            $frontendUserAspect = $this->prophesize(UserAspect::class);

            $context = $this->prophesize(Context::class);
            $context->hasAspect('frontend.preview')->shouldBeCalled()->willReturn(false);
            $context->setAspect('frontend.preview', Argument::any())->shouldBeCalled();
            $context->hasAspect('frontend.user')->shouldBeCalled()->willReturn(false);
            $context->hasAspect('language')->shouldBeCalled()->willReturn(true);
            $context->getPropertyFromAspect('language', 'id')->shouldBeCalled()->willReturn(0);
            $context->getPropertyFromAspect('language', 'id', 0)->shouldBeCalled()->willReturn(0);
            $context->getAspect('frontend.user')->shouldBeCalled()->willReturn($frontendUserAspect->reveal());
            $context->getPropertyFromAspect('visibility', 'includeHiddenContent', false)->shouldBeCalled();
            $context->getPropertyFromAspect('backend.user', 'isLoggedIn', false)->shouldBeCalled();
            $context->setAspect('frontend.user', Argument::any())->shouldBeCalled();
            $context->getPropertyFromAspect('workspace', 'id')->shouldBeCalled()->willReturn(0);
            $context->getPropertyFromAspect('visibility', 'includeHiddenPages')->shouldBeCalled()->willReturn(false);
            $context->setAspect('typoscript', Argument::any())->shouldBeCalled();
            GeneralUtility::setSingletonInstance(Context::class, $context->reveal());
            $GLOBALS['TYPO3_REQUEST'] = GeneralUtility::makeInstance(ServerRequest::class);
            $tsfeProphecy->willBeConstructedWith([$context->reveal(), $site->reveal(), $siteLanguage->reveal()]);
        }

        $tsfe = $tsfeProphecy->reveal();
        $tsfe->tmpl = new \TYPO3\CMS\Core\TypoScript\TemplateService();
        GeneralUtility::addInstance(TypoScriptFrontendController::class, $tsfe);
    }
}
