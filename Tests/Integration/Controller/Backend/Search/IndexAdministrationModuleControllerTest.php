<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller\Backend\Search;

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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexAdministrationModuleController;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\UnifiedConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class IndexAdministrationModuleControllerTest
 * @copyright (c) 2019 Timo Hund <timo.hund@dkd.de>
 */
class IndexAdministrationModuleControllerTest extends IntegrationTest
{
    /**
     * @var IndexAdministrationModuleController
     */
    protected $controller;

    public function setUp() {
        parent::setUp();

        $languageClass = Util::getIsTYPO3VersionBelow10() ? \TYPO3\CMS\Lang\LanguageService::class : \TYPO3\CMS\Core\Localization\LanguageService::class;
        $GLOBALS['LANG'] = $this->getMockBuilder($languageClass)->disableOriginalConstructor()->getMock($languageClass);

        $this->writeDefaultSolrTestSiteConfiguration();
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $connectionManager->injectUnifiedConfiguration(new UnifiedConfiguration(1));

        $this->controller = $this->getMockBuilder(IndexAdministrationModuleController::class)->setMethods(['addFlashMessage', 'redirect'])->getMock();
        $this->controller->setSolrConnectionManager($connectionManager);
    }

    /**
     * @test
     */
    public function testReloadIndexConfigurationAction()
    {
        $this->importDataSetFromFixture('can_reload_index_configuration.xml');

        /** @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $selectedSite = $siteRepository->getFirstAvailableSite();
        $this->controller->setSelectedSite($selectedSite);
        $this->controller->expects($this->exactly(1))
            ->method('addFlashMessage')
            ->with('Core configuration reloaded (core_en, core_de, core_da).', '', FlashMessage::OK);
        $this->controller->reloadIndexConfigurationAction();
    }

    /**
     * @test
     */
    public function testEmptyIndexAction()
    {
        $this->importDataSetFromFixture('can_reload_index_configuration.xml');

        /** @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $selectedSite = $siteRepository->getFirstAvailableSite();
        $this->controller->setSelectedSite($selectedSite);
        $this->controller->expects($this->once())
            ->method('addFlashMessage')
            ->with('Index emptied for Site ", Root Page ID: 1" (core_en, core_de, core_da).', '', FlashMessage::OK);

        $this->controller->emptyIndexAction();
    }
}
