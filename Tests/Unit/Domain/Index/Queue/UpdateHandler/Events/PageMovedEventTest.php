<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022 Markus Friedrich <markus.friedrich@dkd.de>
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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\UpdateHandler\Events;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\PageMovedEvent;

/**
 * Testcase for the PageMovedEvent
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class PageMovedEventTest extends AbstractDataUpdateEventTest
{
    protected const EVENT_CLASS = PageMovedEvent::class;
    protected const EVENT_TEST_TABLE = 'pages';

    /**
     * @test
     */
    public function canInitAndReturnFields(): void
    {
        $event = new PageMovedEvent(123, static::EVENT_TEST_TABLE, ['hidden' => 1]);
        $this->assertEmpty($event->getFields());
    }

    /**
     * @test
     */
    public function canForceTable(): void
    {
        $event = new PageMovedEvent(123, 'tx_foo_bar');
        $this->assertEquals('pages', $event->getTable());
    }

    /**
     * @test
     */
    public function canIndicatePageUpdate(): void
    {
        $event = new PageMovedEvent(123);
        $this->assertTrue($event->isPageUpdate());
    }

    /**
     * @test
     */
    public function canIndicateContentElementUpdate(): void
    {
        $event = new PageMovedEvent(123);
        $this->assertFalse($event->isContentElementUpdate());
    }
}
