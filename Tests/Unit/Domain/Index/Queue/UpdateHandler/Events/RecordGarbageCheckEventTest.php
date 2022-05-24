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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\AbstractDataUpdateEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordGarbageCheckEvent;

/**
 * Testcase for the RecordGarbageCheckEvent
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class RecordGarbageCheckEventTest extends AbstractDataUpdateEventTest
{
    protected const EVENT_CLASS = RecordGarbageCheckEvent::class;
    protected const EVENT_TEST_TABLE = 'tx_foo_bar';

    /**
     * @test
     *
     * @return AbstractDataUpdateEvent
     */
    public function canInitAndReturnBasicProperties(): AbstractDataUpdateEvent
    {
        /** @var RecordGarbageCheckEvent $event */
        $event = parent::canInitAndReturnBasicProperties();

        // initial values
        self::assertFalse($event->frontendGroupsRemoved());

        return $event;
    }

    /**
     * @test
     */
    public function canInitAndReturnFrontendGroupsRemovedFlag(): void
    {
        $event = new RecordGarbageCheckEvent(123, 'tx_foo_bar', [], true);
        self::assertTrue($event->frontendGroupsRemoved());
    }
}
