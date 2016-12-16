<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Hund
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexServiceTest extends UnitTest
{
    /**
     * @var Site
     */
    protected $siteMock;

    /**
     * @var Queue
     */
    protected $queueMock;

    /**
     * @var Dispatcher
     */
    protected $dispatcherMock;

    /**
     * @var IndexService
     */
    protected $indexService;

    /**
     * @return void
     */
    public function setUp() {
        $this->siteMock = $this->getDumbMock(Site::class);
        $this->queueMock = $this->getDumbMock(Queue::class);
        $this->dispatcherMock = $this->getDumbMock(Dispatcher::class);

        // we create an IndexeService where indexItem is mocked to avoid real indexing in the unit test
        $this->indexService = $this->getMockBuilder(IndexService::class)
            ->setConstructorArgs([$this->siteMock, $this->queueMock, $this->dispatcherMock])
            ->setMethods(['indexItem'])
            ->getMock();
    }

    /**
     * @test
     */
    public function signalsAreTriggered()
    {
        // we fake an index queue with two items
        $item1 = $this->getDumbMock(Item::class);
        $item2 = $this->getDumbMock(Item::class);
        $fakeItems = [$item1, $item2];
        $this->fakeQueueItemContent($fakeItems);

            // we assert that 6 signals will be dispatched 1 at the beginning 1 before and after each items and 1 at the end.
        $this->assertSignalsWillBeDispatched(6);
        $this->indexService->indexItems(2);
    }

    /**
     * @param $fakeItems
     */
    protected function fakeQueueItemContent($fakeItems)
    {
        $this->queueMock->expects($this->once())->method('getItemsToIndex')->will($this->returnValue($fakeItems));
    }

    /**
     * @param int $amount
     */
    protected function assertSignalsWillBeDispatched($amount = 0)
    {
        $this->dispatcherMock->expects($this->exactly($amount))->method('dispatch');
    }
}