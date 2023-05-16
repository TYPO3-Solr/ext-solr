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
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Testcase for IndexQueueModuleController
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexQueueModuleControllerTest extends AbstractModuleController
{
    protected Queue|MockObject $indexQueueMock;

    /**
     * @var IndexQueueModuleController|MockObject
     */
    protected $controller;

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

    protected function assertQueueUpdateIsTriggeredFor(string $type, int $uid): void
    {
        $this->indexQueueMock->expects(self::once())->method('updateOrAddItemForAllRelatedRootPages')->with($type, $uid)->willReturn(1);
    }

    /**
     * @test
     */
    public function requeueDocumentActionIsTriggeringReIndexOnIndexQueue(): void
    {
        $this->assertQueueUpdateIsTriggeredFor('pages', 4711);
        $this->controller->requeueDocumentAction('pages', 4711);
    }

    /**
     * @test
     */
    public function hookIsTriggeredWhenRegistered(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueUpdateItem'][] = IndexQueueTestUpdateHandler::class;

        $testHandlerMock = $this->createMock(IndexQueueTestUpdateHandler::class);
        $testHandlerMock->expects(self::once())->method('postProcessIndexQueueUpdateItem');

        $this->indexQueueMock->expects(self::once())->method('updateOrAddItemForAllRelatedRootPages')->willReturn(0);
        $this->indexQueueMock->expects(self::once())->method('getHookImplementation')->with(IndexQueueTestUpdateHandler::class)->willReturn($testHandlerMock);

        $this->assertQueueUpdateIsTriggeredFor('tx_solr_file', 88);
        $this->controller->requeueDocumentAction('tx_solr_file', 88);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueUpdateItem'] = [];
    }
}
