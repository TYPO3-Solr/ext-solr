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
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic\QueueStatisticsRepository;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;

/**
 * Testcase for IndexQueueModuleController
 *
 * @property IndexQueueModuleController|MockObject $controller
 */
class IndexQueueModuleControllerTest extends SetUpSolrModuleControllerTestCase
{
    protected Queue|MockObject $indexQueueMock;
    protected EventDispatcherInterface|MockObject $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUpConcreteModuleController(
            IndexQueueModuleController::class,
            ['addIndexQueueFlashMessage'],
        );
        $this->eventDispatcher = new NoopEventDispatcher();
        $this->indexQueueMock = $this->getMockBuilder(Queue::class)
            ->onlyMethods(['updateOrAddItemForAllRelatedRootPages'])
            ->setConstructorArgs([
                $this->createMock(RootPageResolver::class),
                $this->createMock(ConfigurationAwareRecordService::class),
                $this->createMock(QueueItemRepository::class),
                $this->createMock(QueueStatisticsRepository::class),
                $this->createMock(FrontendEnvironment::class),
                $this->eventDispatcher,
            ])
            ->getMock();
        $this->controller->setIndexQueue($this->indexQueueMock);
        parent::setUp();
    }

    protected function assertQueueUpdateIsTriggeredFor(string $type, int $uid): void
    {
        $this->indexQueueMock->expects(self::once())->method('updateOrAddItemForAllRelatedRootPages')->with($type, $uid)->willReturn(1);
    }

    #[Test]
    public function requeueDocumentActionIsTriggeringReIndexOnIndexQueue(): void
    {
        $this->assertQueueUpdateIsTriggeredFor('pages', 4711);
        $this->controller->requeueDocumentAction('pages', 4711);
    }

    #[Test]
    public function hookIsTriggeredWhenRegistered(): void
    {
        $this->indexQueueMock->expects(self::once())->method('updateOrAddItemForAllRelatedRootPages')->willReturn(0);

        $this->assertQueueUpdateIsTriggeredFor('tx_solr_file', 88);
        $this->controller->requeueDocumentAction('tx_solr_file', 88);
    }
}
