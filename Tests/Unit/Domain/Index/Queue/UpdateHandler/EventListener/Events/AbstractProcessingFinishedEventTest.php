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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler\EventListener\Events;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\ProcessingFinishedEventInterface;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Abstract testcase for the processing finished events
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
abstract class AbstractProcessingFinishedEventTest extends UnitTest
{
    /**
     * @test
     */
    public function canSetAndReturnProcessedEvent(): void
    {
        $processedEvent = new RecordUpdatedEvent(123, 'tx_foo_bar');

        /** @var ProcessingFinishedEventInterface $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass($processedEvent);

        self::assertEquals($processedEvent, $event->getDataUpdateEvent());
    }
}
