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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Controller\Backend\Search;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\AbstractModuleController;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Mvc\Backend\Service\ModuleDataStorageService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

abstract class AbstractModuleControllerTest extends UnitTest
{

    /**
     * @var AbstractModuleController|MockObject
     */
    protected $controller;

    /**
     * @var Site|MockObject
     */
    protected $selectedSiteMock;

    /**
     * @var ConnectionManager|MockObject
     */
    protected $connectionManagerMock;

    /**
     * Initializes the concrete backend module controller
     *
     * @param string $concreteModuleControllerClass
     * @param array $mockMethods
     */
    protected function setUpConcreteModuleController(
        string $concreteModuleControllerClass,
        array $mockMethods = ['addFlashMessage']
    ): void {
        $this->selectedSiteMock = $this->getDumbMock(Site::class);
        $this->controller = $this->getMockBuilder($concreteModuleControllerClass)
            ->setConstructorArgs(
                [
                    'moduleTemplateFactory' => $this->getDumbMock(ModuleTemplateFactory::class),
                    'moduleDataStorageService' => $this->getDumbMock(ModuleDataStorageService::class),
                    'siteRepository' => $this->getDumbMock(SiteRepository::class),
                    'siteFinder' => $this->getDumbMock(SiteFinder::class),
                    'solrConnectionManager' => $this->connectionManagerMock = $this->getDumbMock(ConnectionManager::class),
                    'indexQueue' => $this->getDumbMock(Queue::class),
                ]
            )
            ->onlyMethods($mockMethods)
            ->getMock();
        $uriBuilderMock = $this->getMockBuilder(UriBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['uriFor'])->getMock();
        $uriBuilderMock->expects(self::any())
            ->method('uriFor')
            ->willReturn('index');
        $this->controller->injectUriBuilder($uriBuilderMock);
        $this->controller->setSelectedSite($this->selectedSiteMock);
    }
}
