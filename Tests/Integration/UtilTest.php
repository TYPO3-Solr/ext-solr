<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

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

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Core\Context\Context;

class UtilTest extends IntegrationTest
{
    public function setUp()
    {
        parent::setUp();
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
        $this->assertInstanceOf(TypoScriptConfiguration::class, $configuration);
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

        // prepare first call

        /* @var TwoLevelCache|ObjectProphecy $twoLevelCache */
        $twoLevelCache = $this->prophesize(TwoLevelCache::class);
        $twoLevelCache
            ->get($cacheId)
            ->shouldBeCalled()
            ->willReturn([]);
        $twoLevelCache
            ->set($cacheId, [])
            ->shouldBeCalledOnce();
        GeneralUtility::addInstance(TwoLevelCache::class, $twoLevelCache->reveal());

        /* @var ExtendedTemplateService|ObjectProphecy $extendedTemplateService */
        $extendedTemplateService = $this->prophesize(ExtendedTemplateService::class);
        GeneralUtility::addInstance(ExtendedTemplateService::class, $extendedTemplateService->reveal());

        $newConfiguration = Util::getConfigurationFromPageId(
            $pageId,
            $path,
            $initializeTsfe,
            $language,
            true
        );

        $this->assertInstanceOf(TypoScriptConfiguration::class, $newConfiguration);

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

        $this->assertInstanceOf(TypoScriptConfiguration::class, $cachedConfiguration);

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

        /* @var TwoLevelCache|ObjectProphecy $twoLevelCache */
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

        $this->assertInstanceOf(TypoScriptConfiguration::class, $newConfiguration);

        $this->assertSame(
            $pageId,
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

        /* @var TwoLevelCache|ObjectProphecy $twoLevelCache */
        $twoLevelCache = $this->prophesize(TwoLevelCache::class);
        $twoLevelCache
            ->get(Argument::cetera())
            ->willReturn([]);
        $twoLevelCache
            ->set(Argument::cetera())
            ->shouldBeCalled();
        GeneralUtility::addInstance(TwoLevelCache::class, $twoLevelCache->reveal());

        // Change TSFE->id to 34 ($pageId) and create new cache
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

        // Change TSFE->id to 56 and create new cache
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
        // TSFE->id has to be changed back to 34 $pageId
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
    }

    /**
     * Initialize a test case for TypoScript frontend controller
     *
     * @param int $pageId
     * @param int $rootPageId
     * @throws \TYPO3\CMS\Core\Context\Exception\AspectNotFoundException
     * @throws \TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException
     * @throws \TYPO3\CMS\Core\Error\Http\ServiceUnavailableException
     * @throws \TYPO3\CMS\Core\Exception\SiteNotFoundException
     * @throws \TYPO3\CMS\Core\Http\ImmediateResponseException
     */
    protected function buildTestCaseForTsfe(int $pageId, int $rootPageId)
    {
        /* @var PageRepository|ObjectProphecy $pageRepository */
        $pageRepository = $this->prophesize(PageRepository::class);
        GeneralUtility::addInstance(PageRepository::class, $pageRepository->reveal());

        /* @var ExtendedTemplateService|ObjectProphecy $extendedTemplateService */
        $extendedTemplateService = $this->prophesize(ExtendedTemplateService::class);
        GeneralUtility::addInstance(ExtendedTemplateService::class, $extendedTemplateService->reveal());

        /* @var SiteLanguage|ObjectProphecy $siteLanguage */
        $siteLanguage = $this->prophesize(SiteLanguage::class);

        /* @var Site|ObjectProphecy $site */
        $site = $this->prophesize(Site::class);
        $site->getLanguageById(0)
            ->shouldBeCalled()
            ->willReturn($siteLanguage->reveal());
        /* @var SiteFinder|ObjectProphecy $siteFinder */
        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId($pageId)
            ->shouldBeCalled()
            ->willReturn($site->reveal());

        $site->getConfiguration()
            ->willReturn(['settings' => []]);

        GeneralUtility::addInstance(SiteFinder::class, $siteFinder->reveal());

        /* @var TypoScriptFrontendController|ObjectProphecy $tsfeProphecy */
        $tsfeProphecy = $this->prophesize(TypoScriptFrontendController::class);
        $siteLanguage->getTypo3Language()->shouldBeCalled()->willReturn(0);
        $siteLanguage->getLocale()->willReturn('en_US.UTF8');
        $siteLanguage->getTwoLetterIsoCode()->willReturn('en');
        $siteLanguage->getLanguageId()->willReturn(0);
        $siteLanguage->getBase()->willReturn('/en/');

        /* @var RootlineUtility|ObjectProphecy $rootLineUtility */
        $rootLineUtility = $this->prophesize(RootlineUtility::class);
        $rootLineUtility->get()->shouldBeCalledOnce()->willReturn([]);
        GeneralUtility::addInstance(RootlineUtility::class, $rootLineUtility->reveal());

        /* @var UserAspect|ObjectProphecy $frontendUserAspect */
        $frontendUserAspect = $this->prophesize(UserAspect::class);
        $frontendUserAspect->isLoggedIn()->willReturn(false);
        $frontendUserAspect->get('isLoggedIn')->willReturn(false);
        $frontendUserAspect->get('id')->shouldBeCalled()->willReturn('UtilTest_TSFEUser');
        // Removed requirement of since the access of internal method getGroupIds should not be testet
        $frontendUserAspect->getGroupIds()->willReturn([0, -1]);
        $frontendUserAspect->get('groupIds')->shouldBeCalled()->willReturn([0, -1]);
        /* @var UserAspect|ObjectProphecy $backendUserAspect */
        $backendUserAspect = $this->prophesize(UserAspect::class);
        /* @var WorkspaceAspect|ObjectProphecy $workspaceAspect */
        $workspaceAspect =  $this->prophesize(WorkspaceAspect::class);

        /* @var Context|ObjectProphecy $context */
        $context = $this->prophesize(Context::class);
        $context->hasAspect('frontend.preview')->shouldBeCalled()->willReturn(false);
        $context->setAspect('frontend.preview', Argument::any())->shouldBeCalled();
        $context->hasAspect('frontend.user')->shouldBeCalled()->willReturn(false);
        $context->hasAspect('language')->shouldBeCalled()->willReturn(true);
        $context->getPropertyFromAspect('language', 'id')->shouldBeCalled()->willReturn(0);
        $context->getPropertyFromAspect('language', 'id', 0)->shouldBeCalled()->willReturn(0);
        $context->getPropertyFromAspect('language', 'contentId')->shouldBeCalled()->willReturn(0);
        $context->getAspect('frontend.user')->shouldBeCalled()->willReturn($frontendUserAspect->reveal());
        $context->getAspect('backend.user')->shouldBeCalled()->willReturn($backendUserAspect->reveal());
        $context->getAspect('workspace')->shouldBeCalled()->willReturn($workspaceAspect->reveal());
        $context->getPropertyFromAspect('visibility', 'includeHiddenContent', false)->shouldBeCalled();
        $context->getPropertyFromAspect('backend.user', 'isLoggedIn', false)->shouldBeCalled();
        $context->setAspect('frontend.user', Argument::any())->shouldBeCalled();
        $context->getPropertyFromAspect('workspace', 'id')->shouldBeCalled()->willReturn(0);
        $context->getPropertyFromAspect('date', 'accessTime', 0)->willReturn(0);
        $context->getPropertyFromAspect('typoscript', 'forcedTemplateParsing')->willReturn(false);
        $context->getPropertyFromAspect('visibility', 'includeHiddenPages')->shouldBeCalled()->willReturn(false);
        $context->setAspect('typoscript', Argument::any())->shouldBeCalled();
        GeneralUtility::setSingletonInstance(Context::class, $context->reveal());
        $GLOBALS['TYPO3_REQUEST'] = GeneralUtility::makeInstance(ServerRequest::class);
        $tsfeProphecy->willBeConstructedWith([$context->reveal(), $site->reveal(), $siteLanguage->reveal()]);
        $tsfeProphecy->getSite()->shouldBeCalled()->willReturn($site);
        $tsfeProphecy->getPageAndRootlineWithDomain($pageId, Argument::type(ServerRequest::class))->shouldBeCalled();
        $tsfeProphecy->getConfigArray()->shouldBeCalled();
        $tsfeProphecy->settingLanguage()->shouldBeCalled();
        $tsfeProphecy->newCObj()->shouldBeCalled();
        $tsfeProphecy->calculateLinkVars([])->shouldBeCalled();

        $tsfe = $tsfeProphecy->reveal();

        $tsfe->tmpl = new \TYPO3\CMS\Core\TypoScript\TemplateService();
        GeneralUtility::addInstance(TypoScriptFrontendController::class, $tsfe);
    }
}
