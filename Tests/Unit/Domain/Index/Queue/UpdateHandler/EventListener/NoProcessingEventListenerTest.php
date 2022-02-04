<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler\EventListener;

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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\AbstractBaseEventListener;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\NoProcessingEventListener;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;

/**
 * Testcase for the NoProcessingEventListener
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class NoProcessingEventListenerTest extends AbstractEventListenerTest
{
    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * Init listener
     *
     * @return AbstractBaseEventListener
     */
    protected function initListener(): AbstractBaseEventListener
    {
        return new NoProcessingEventListener($this->extensionConfigurationMock, $this->eventDispatcherMock);
    }

    /**
     * Returns the current monitoring type
     *
     * @return int
     */
    protected function getMonitoringType(): int
    {
        return NoProcessingEventListener::MONITORING_TYPE;
    }
}
