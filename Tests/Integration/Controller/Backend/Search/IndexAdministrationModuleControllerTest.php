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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller\Backend\Search;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexAdministrationModuleController;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Mvc\Backend\Service\ModuleDataStorageService;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

/**
 * Class IndexAdministrationModuleControllerTest
 */
final class IndexAdministrationModuleControllerTest extends IntegrationTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');

        $this->writeDefaultSolrTestSiteConfiguration();
    }

    protected function getControllerMockObject(): IndexAdministrationModuleController|MockObject
    {
        $controller = $this->getMockBuilder(IndexAdministrationModuleController::class)
            ->setConstructorArgs(
                [
                    'moduleTemplateFactory' => $this->get(ModuleTemplateFactory::class),
                    'iconFactory' => $this->get(IconFactory::class),
                    'moduleDataStorageService' => $this->get(ModuleDataStorageService::class),
                    'siteRepository' => $this->get(SiteRepository::class),
                    'siteFinder' => $this->get(SiteFinder::class),
                    'solrConnectionManager' => $this->get(ConnectionManager::class),
                    'indexQueue' => $this->get(Queue::class),
                    'componentFactory' => $this->get(ComponentFactory::class),
                ],
            )
            ->onlyMethods(['addFlashMessage'])
            ->getMock();
        $uriBuilderMock = $this->getMockBuilder(UriBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['uriFor'])->getMock();
        $uriBuilderMock->expects(self::any())->method('uriFor')->willReturn('index');
        $controller->injectUriBuilder($uriBuilderMock);

        return $controller;
    }

    #[Test]
    public function testReloadIndexConfigurationAction(): void
    {
        /** @var SiteRepository $siteRepository */
        $siteRepository = $this->get(SiteRepository::class);
        $selectedSite = $siteRepository->getFirstAvailableSite();
        $controller = $this->getControllerMockObject();
        $controller->setSelectedSite($selectedSite);
        $controller->expects(self::exactly(1))
            ->method('addFlashMessage')
            ->with(
                'Core configuration reloaded (' .
                $this->resolveCoreName('core_en') . ', ' .
                $this->resolveCoreName('core_de') . ', ' .
                $this->resolveCoreName('core_da') .
                ').',
                '',
                ContextualFeedbackSeverity::OK,
            );
        $controller->reloadIndexConfigurationAction();
    }

    #[Test]
    public function testEmptyIndexAction(): void
    {
        /** @var SiteRepository $siteRepository */
        $siteRepository = $this->get(SiteRepository::class);
        $selectedSite = $siteRepository->getFirstAvailableSite();
        $controller = $this->getControllerMockObject();
        $controller->setSelectedSite($selectedSite);
        $controller->expects(self::atLeastOnce())
            ->method('addFlashMessage')
            ->with(
                'Index emptied for Site "Root of Testpage testone.site aka integration_tree_one, Root Page ID: 1" (' .
                $this->resolveCoreName('core_en') . ', ' .
                $this->resolveCoreName('core_de') . ', ' .
                $this->resolveCoreName('core_da') .
                ').',
            );

        $controller->emptyIndexAction();
    }
}
