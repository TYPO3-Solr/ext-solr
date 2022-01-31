<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller\Backend\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexAdministrationModuleController;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class IndexAdministrationModuleControllerTest
 * @package ApacheSolrForTypo3\Solr\Tests\Integration\Controller\Search
 */
class IndexAdministrationModuleControllerTest extends IntegrationTest
{
    /**
     * @var IndexAdministrationModuleController
     */
    protected $controller;

    public function setUp() {
        parent::setUp();
        $GLOBALS['LANG'] = $this->getMockBuilder(LanguageService::class)->disableOriginalConstructor()->getMock();

        $this->writeDefaultSolrTestSiteConfiguration();
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

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
