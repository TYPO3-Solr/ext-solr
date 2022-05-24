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

use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexQueueModuleController;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;

/**
 * Testcase for IndexQueueModuleController
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexQueueModuleControllerTest extends AbstractModuleControllerTest
{

    /**
     * @var Queue
     */
    protected $indexQueueMock;

    protected function setUp(): void
    {
        parent::setUpConcreteModuleController(
            IndexQueueModuleController::class,
            ['addIndexQueueFlashMessage']
        );
        $this->indexQueueMock = $this->getMockBuilder(Queue::class)
            ->onlyMethods(['getHookImplementation', 'updateOrAddItemForAllRelatedRootPages'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller->setIndexQueue($this->indexQueueMock);
        parent::setUp();
    }

    /**
     * @param string $type
     * @param int $uid
     */
    protected function assertQueueUpdateIsTriggeredFor($type, $uid)
    {
        $this->indexQueueMock->expects(self::once())->method('updateOrAddItemForAllRelatedRootPages')->with($type, $uid)->willReturn(1);
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
        $testHandlerMock->expects(self::once())->method('postProcessIndexQueueUpdateItem');

        $this->indexQueueMock->expects(self::once())->method('updateOrAddItemForAllRelatedRootPages')->willReturn(0);
        $this->indexQueueMock->expects(self::once())->method('getHookImplementation')->with(IndexQueueTestUpdateHandler::class)->willReturn($testHandlerMock);

        $this->assertQueueUpdateIsTriggeredFor('tx_solr_file', 88);
        $this->controller->requeueDocumentAction('tx_solr_file', 88, 1, 0);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueUpdateItem'] = [];
    }
}
