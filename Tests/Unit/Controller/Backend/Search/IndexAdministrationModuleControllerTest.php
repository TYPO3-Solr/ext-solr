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
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexAdministrationModuleController;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Solarium\Core\Client\Endpoint;

/**
 * Testcase for IndexQueueModuleController
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexAdministrationModuleControllerTest extends AbstractModuleControllerTest
{
    /**
     * @var ConnectionManager|MockObject
     */
    protected $connectionManagerMock;

    protected function setUp(): void
    {
        parent::setUpConcreteModuleController(IndexAdministrationModuleController::class);
        parent::setUp();
    }

    /**
     * @test
     */
    public function testReloadIndexConfigurationAction()
    {
        $responseMock = $this->getDumbMock(ResponseAdapter::class);
        $responseMock->expects(self::once())->method('getHttpStatus')->willReturn(200);

        $writeEndpointMock = $this->getDumbMock(Endpoint::class);
        $adminServiceMock = $this->getDumbMock(SolrAdminService::class);
        $adminServiceMock->expects(self::once())->method('reloadCore')->willReturn($responseMock);
        $adminServiceMock->expects(self::once())->method('getPrimaryEndpoint')->willReturn($writeEndpointMock);

        $solrConnection = $this->getDumbMock(SolrConnection::class);
        $solrConnection->expects(self::once())->method('getAdminService')->willReturn($adminServiceMock);

        $fakeConnections = [$solrConnection];
        $this->connectionManagerMock->expects(self::once())
            ->method('getConnectionsBySite')
            ->with($this->selectedSiteMock)
            ->willReturn($fakeConnections);
        $this->controller->reloadIndexConfigurationAction();
    }
}
