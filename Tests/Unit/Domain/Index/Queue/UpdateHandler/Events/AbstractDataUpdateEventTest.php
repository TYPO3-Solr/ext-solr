<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler\Events;

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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\DataUpdateHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\AbstractDataUpdateEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Abstract testcase for the data update events
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
abstract class AbstractDataUpdateEventTest extends UnitTest
{
    /**
     * @return AbstractDataUpdateEventTest
     *
     * @test
     */
    public function canInitAndReturnBasicProperties(): AbstractDataUpdateEvent
    {
        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, static::EVENT_TEST_TABLE);

        self::assertEquals(123, $event->getUid());
        self::assertEquals(static::EVENT_TEST_TABLE, $event->getTable());

        // initial values
        self::assertEmpty($event->getFields());
        self::assertFalse($event->isPropagationStopped());
        self::assertFalse($event->isImmediateProcessingForced());

        return $event;
    }

    /**
     * @test
     */
    public function canInitAndReturnFields(): void
    {
        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, static::EVENT_TEST_TABLE, $fields = ['hidden' => 1]);

        self::assertEquals($fields, $event->getFields());
    }

    /**
     * @test
     */
    public function canIndicatePageUpdate(): void
    {
        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'tx_foo_bar');
        self::assertFalse($event->isPageUpdate());

        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'pages');
        self::assertTrue($event->isPageUpdate());
    }

    /**
     * @test
     */
    public function canIndicateContentElementUpdate(): void
    {
        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'tx_foo_bar');
        self::assertFalse($event->isContentElementUpdate());

        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'tt_content');
        self::assertTrue($event->isContentElementUpdate());
    }

    /**
     * @test
     */
    public function canMarkAndIndicateStoppedProcessing(): void
    {
        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'tx_foo_bar');

        self::assertFalse($event->isPropagationStopped());
        $event->setStopProcessing(true);
        self::assertTrue($event->isPropagationStopped());
        $event->setStopProcessing(false);
        self::assertFalse($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function canMarkAndIndicateForcedProcessing(): void
    {
        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'tx_foo_bar');

        self::assertFalse($event->isImmediateProcessingForced());
        $event->setForceImmediateProcessing(true);
        self::assertTrue($event->isImmediateProcessingForced());
        $event->setForceImmediateProcessing(false);
        self::assertFalse($event->isImmediateProcessingForced());
    }

    /**
     * @test
     */
    public function canCleanEventOnSerialization(): void
    {
        $fields = [
            'uid' => 123,
            'pid' => 10,
            'title' => 'dummy title',
            'l10n_diffsource' => 'dummy l10n_diffsource',
        ];

        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'pages', $fields);

        $properties = $event->__sleep();
        self::assertNotEmpty($properties);

        /** @var AbstractDataUpdateEvent $processedEvent */
        $processedEvent = unserialize(serialize($event));
        self::assertIsObject($processedEvent);
        self::assertArrayNotHasKey('l10n_diffsource', $processedEvent->getFields());

        if ($event->getFields() !== []) {
            $requiredUpdateFields = array_unique(array_merge(
                DataUpdateHandler::getRequiredUpdatedFields(),
                GarbageHandler::getRequiredUpdatedFields()
            ));

            foreach ($requiredUpdateFields as $requiredUpdateField) {
                self::assertArrayHasKey($requiredUpdateField, $processedEvent->getFields());
            }

            if ($event->getTable() === 'pages') {
                $processedFields = $fields;
                unset($processedFields['l10n_diffsource']);
                self::assertEquals($processedFields, $processedEvent->getFields());
            }

            self::assertEquals(10, $processedEvent->getFields()['pid']);
        }
    }
}
