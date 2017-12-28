<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Controller\Backend\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexQueueModuleController;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;


/**
 * Testcase for IndexQueueModuleController
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexQueueModuleControllerTest extends UnitTest
{

    /**
     * @var Queue
     */
    protected $indexQueueMock;

    /**
     * @var IndexQueueModuleController
     */
    protected $controller;

    /**
     *
     */
    public function setUp()
    {
        parent::setUp();

        $this->indexQueueMock = $this->getMockBuilder(Queue::class)
            ->setMethods(['getHookImplementation', 'updateOrAddItemForAllRelatedRootPages'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = $this->getMockBuilder(IndexQueueModuleController::class)->setMethods(['addIndexQueueFlashMessage', 'redirect'])->getMock();
        $this->controller->setIndexQueue($this->indexQueueMock);
    }

    /**
     * @param string $type
     * @param int $uid
     */
    protected function assertQueueUpdateIsTriggeredFor($type, $uid)
    {
        $this->indexQueueMock->expects($this->once())->method('updateOrAddItemForAllRelatedRootPages')->with($type, $uid)->will($this->returnValue(1));
    }

    /**
     * @test
     */
    public function requeueDocumentActionIsTriggeringReIndexOnIndexQueue()
    {
        $this->assertQueueUpdateIsTriggeredFor('pages', 4711);
        $this->controller->requeueDocumentAction('pages', 4711, 1, 0);
    }

    /**
     * @test
     */
    public function hookIsTriggeredWhenRegistered()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueUpdateItem'][] = IndexQueueTestUpdateHandler::class;

        $testHandlerMock = $this->getDumbMock(IndexQueueTestUpdateHandler::class);
        $testHandlerMock->expects($this->once())->method('postProcessIndexQueueUpdateItem');

        $this->indexQueueMock->expects($this->once())->method('updateOrAddItemForAllRelatedRootPages')->willReturn(0);
        $this->indexQueueMock->expects($this->once())->method('getHookImplementation')->with(IndexQueueTestUpdateHandler::class)->willReturn($testHandlerMock);

        $this->assertQueueUpdateIsTriggeredFor('tx_solr_file', 88);
        $this->controller->requeueDocumentAction('tx_solr_file', 88, 1, 0);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueUpdateItem'] = array();
    }
}