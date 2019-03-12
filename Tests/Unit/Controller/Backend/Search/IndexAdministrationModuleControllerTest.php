<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Controller\Backend\Search;

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
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Solarium\Core\Client\Endpoint;


/**
 * Testcase for IndexQueueModuleController
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexAdministrationModuleControllerTest extends UnitTest
{

    /**
     * @var IndexAdministrationModuleController
     */
    protected $controller;

    /**
     * @var ConnectionManager
     */
    protected $connectionManagerMock;

    /**
     * @var Site
     */
    protected $selectedSiteMock;

    /**
     *
     */
    public function setUp()
    {
        parent::setUp();

        $this->connectionManagerMock = $this->getDumbMock(ConnectionManager::class);
        $this->selectedSiteMock = $this->getDumbMock(Site::class);

        $this->controller = $this->getMockBuilder(IndexAdministrationModuleController::class)->setMethods(['addFlashMessage', 'redirect'])->getMock();
        $this->controller->setSolrConnectionManager($this->connectionManagerMock);
        $this->controller->setSelectedSite($this->selectedSiteMock);
    }

    /**
     * @test
     */
    public function testReloadIndexConfigurationAction()
    {
        $responseMock = $this->getDumbMock(ResponseAdapter::class);
        $responseMock->expects($this->once())->method('getHttpStatus')->willReturn(200);

        $writeEndpointMock = $this->getDumbMock(Endpoint::class);
        $adminServiceMock = $this->getDumbMock(SolrAdminService::class);
        $adminServiceMock->expects($this->once())->method('reloadCore')->willReturn($responseMock);
        $adminServiceMock->expects($this->once())->method('getPrimaryEndpoint')->willReturn($writeEndpointMock);

        $solrConnection = $this->getDumbMock(SolrConnection::class);
        $solrConnection->expects($this->once())->method('getAdminService')->willReturn($adminServiceMock);

        $fakeConnections = [$solrConnection];
        $this->connectionManagerMock->expects($this->once())
            ->method('getConnectionsBySite')
            ->with($this->selectedSiteMock)
            ->willReturn($fakeConnections);
        $this->controller->reloadIndexConfigurationAction();
    }
}
