<?php
declare(strict_types=1);

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

use ApacheSolrForTypo3\Solr\EventListener\UnifiedConfigurationExtensionConfiguration;
use ApacheSolrForTypo3\Solr\EventListener\UnifiedConfigurationGlobalConfiguration;
use ApacheSolrForTypo3\Solr\EventListener\UnifiedConfigurationTypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\SiteConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Testcase to test the unified configuration with the configuration manager
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class ConfigurationManagerTest extends UnitTest
{
    protected function setUp()
    {
        parent::setUp();
        $TSFE = $this->getDumbMock(TypoScriptFrontendController::class);
        $GLOBALS['TSFE'] = $TSFE;

        /* @var $GLOBALS ['TSFE']->tmpl  \TYPO3\CMS\Core\TypoScript\TemplateService */
        $GLOBALS['TSFE']->tmpl = $this->getDumbMock(TemplateService::class, ['linkData']);
        $GLOBALS['TSFE']->tmpl->getFileName_backPath = Environment::getPublicPath() . '/';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['enabled'] = 1;
    }

    /**
     * @test
     */
    public function testGetIsUseConfigurationFromClosestTemplateEnabled()
    {
        $listenderProvider = new class implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable
            {
                return [
                    new UnifiedConfigurationGlobalConfiguration(),
                    new UnifiedConfigurationExtensionConfiguration(),
                    new UnifiedConfigurationTypoScriptConfiguration(),
                ];
            }
        };

        /* @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = GeneralUtility::makeInstance(
            EventDispatcher::class,
            $listenderProvider
        );

        $configurationManager = new ConfigurationManager();
        $configurationManager->injectEventDispatcher($eventDispatcher);

        $unifiedConfiguration = $configurationManager->getUnifiedConfiguration(1);

        $this->assertTrue($unifiedConfiguration->isEnabled());
    }

    /**
     * @test
     */
    public function testIfSiteConfigurationOverruledTypoScript()
    {
        $listenderProvider = new class implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable
            {
                return [
                    new UnifiedConfigurationGlobalConfiguration(),
                    new UnifiedConfigurationExtensionConfiguration(),
                    new UnifiedConfigurationTypoScriptConfiguration(),
                ];
            }
        };

        /* @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = GeneralUtility::makeInstance(
            EventDispatcher::class,
            $listenderProvider
        );


        $site = new Site(
            'solr',
            1,
            [
                'rootPageId' => 1,
                'base' => 'https://localhost/',
                'baseVariants' => [],
                'solr_enabled_read' => false
            ]
        );

        $configurationManager = new ConfigurationManager();
        $configurationManager->injectEventDispatcher($eventDispatcher);

        $unifiedConfiguration = $configurationManager->getUnifiedConfiguration(1);
        $unifiedConfiguration->replaceConfigurationByObject(new TypoScriptConfiguration($GLOBALS['TSFE']->tmpl->setup));
        $unifiedConfiguration->mergeConfigurationByObject(SiteConfiguration::newWithSite($site));

        $this->assertFalse($unifiedConfiguration->isEnabled());
    }

    /**
     * @test
     */
    public function testIfLanguageConfigurationOverruledSiteConfiguration()
    {
        $listenderProvider = new class implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable
            {
                return [
                    new UnifiedConfigurationGlobalConfiguration(),
                    new UnifiedConfigurationExtensionConfiguration(),
                    new UnifiedConfigurationTypoScriptConfiguration(),
                ];
            }
        };

        /* @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = GeneralUtility::makeInstance(
            EventDispatcher::class,
            $listenderProvider
        );

        $site = new Site(
            'solr',
            1,
            [
                'rootPageId' => 1,
                'base' => 'https://localhost/',
                'baseVariants' => [],
                'solr_enabled_read' => true,
                'languages' => [
                    [
                        'languageId' => 0,
                        'title' => 'Default',
                        'navigationTitle' => '',
                        'typo3Language' => 'default',
                        'flag' => 'us',
                        'locale' => 'en_US.UTF-8',
                        'iso-639-1' => 'en',
                        'hreflang' => 'en-US',
                        'direction' => '',
                        'solr_enabled_read' => false,
                    ]
                ]
            ]
        );

        $configurationManager = new ConfigurationManager();
        $configurationManager->injectEventDispatcher($eventDispatcher);

        $unifiedConfiguration = $configurationManager->getUnifiedConfiguration(1);
        $unifiedConfiguration->replaceConfigurationByObject(new TypoScriptConfiguration($GLOBALS['TSFE']->tmpl->setup));
        $unifiedConfiguration->mergeConfigurationByObject(SiteConfiguration::newWithSite($site));

        $this->assertFalse($unifiedConfiguration->isEnabled());
    }
}
