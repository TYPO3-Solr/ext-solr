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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler\Events;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\ContentElementDeletedEvent;

/**
 * Testcase for the ContentElementDeletedEvent
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class ContentElementDeletedEventTest extends AbstractDataUpdateEventTest
{
    protected const EVENT_CLASS = ContentElementDeletedEvent::class;
    protected const EVENT_TEST_TABLE = 'tt_content';

    /**
     * @test
     */
    public function canInitAndReturnFields(): void
    {
        $event = new ContentElementDeletedEvent(123, static::EVENT_TEST_TABLE, ['hidden' => 1]);
        self::assertEmpty($event->getFields());
    }

    /**
     * @test
     */
    public function canForceTable(): void
    {
        $event = new ContentElementDeletedEvent(123, 'tx_foo_bar');
        self::assertEquals('tt_content', $event->getTable());
    }

    /**
     * @test
     */
    public function canIndicatePageUpdate(): void
    {
        $event = new ContentElementDeletedEvent(123);
        self::assertFalse($event->isPageUpdate());
    }

    /**
     * @test
     */
    public function canIndicateContentElementUpdate(): void
    {
        $event = new ContentElementDeletedEvent(123);
        self::assertTrue($event->isContentElementUpdate());
    }
}
