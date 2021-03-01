<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
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

        if (Util::getIsTYPO3VersionBelow10()) {

            /* @var CacheManager|ObjectProphecy $cacheManager */
            $cacheManager = $this->prophesize(CacheManager::class);
            /* @var VariableFrontend|ObjectProphecy $frontendCache */
            $frontendCache = $this->prophesize(VariableFrontend::class);
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
            $cacheManager
                ->getCache('tx_solr_configuration')
                ->willReturn($frontendCache->reveal());
            GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManager->reveal());
        }
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
}
