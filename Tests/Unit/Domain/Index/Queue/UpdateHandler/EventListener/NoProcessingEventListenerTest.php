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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\UpdateHandler\EventListener;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\NoProcessingEventListener;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use PHPUnit\Framework\Attributes\Test;

/**
 * Testcase for the NoProcessingEventListener
 *
 * @property NoProcessingEventListener $listener;
 */
class NoProcessingEventListenerTest extends SetUpEventListener
{
    #[Test]
    public function canHandleEvents(): void
    {
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getMonitoringType')
            ->willReturn(2);

        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');
        $this->listener->__invoke($event);
        self::assertTrue($event->isPropagationStopped());
    }

    #[Test]
    public function canSkipEventHandlingIfDisabled(): void
    {
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getMonitoringType')
            ->willReturn(0);

        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');
        $this->listener->__invoke($event);
        self::assertFalse($event->isPropagationStopped());
    }

    protected function initListener(): NoProcessingEventListener
    {
        return new NoProcessingEventListener($this->extensionConfigurationMock, $this->eventDispatcherMock);
    }

    /**
     * Returns the current monitoring type
     */
    protected static function getMonitoringType(): int
    {
        return NoProcessingEventListener::MONITORING_TYPE;
    }
}
