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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\UpdateHandler\Events;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\DataUpdateHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\AbstractDataUpdateEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Abstract testcase for the data update events
 */
abstract class SetUpDataUpdateEvent extends SetUpUnitTestCase
{
    protected const EVENT_CLASS = AbstractDataUpdateEvent::class;
    protected const EVENT_TEST_TABLE = 'tx_foo_bar';

    #[Test]
    public function canInitAndReturnBasicProperties(): AbstractDataUpdateEvent
    {
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

    #[Test]
    public function canInitAndReturnFields(): void
    {
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, static::EVENT_TEST_TABLE, $fields = ['hidden' => 1]);

        self::assertEquals($fields, $event->getFields());
    }

    #[Test]
    public function canIndicatePageUpdate(): void
    {
        $eventClass = static::EVENT_CLASS;
        /** @var AbstractDataUpdateEvent $event */
        $event = new $eventClass(123, 'tx_foo_bar');
        self::assertFalse($event->isPageUpdate());

        /** @var AbstractDataUpdateEvent $event */
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'pages');
        self::assertTrue($event->isPageUpdate());
    }

    #[Test]
    public function canIndicateContentElementUpdate(): void
    {
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'tx_foo_bar');
        self::assertFalse($event->isContentElementUpdate());

        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'tt_content');
        self::assertTrue($event->isContentElementUpdate());
    }

    #[Test]
    public function canMarkAndIndicateStoppedProcessing(): void
    {
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'tx_foo_bar');

        self::assertFalse($event->isPropagationStopped());
        $event->setStopProcessing(true);
        self::assertTrue($event->isPropagationStopped());
        $event->setStopProcessing(false);
        self::assertFalse($event->isPropagationStopped());
    }

    #[Test]
    public function canMarkAndIndicateForcedProcessing(): void
    {
        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass(123, 'tx_foo_bar');

        self::assertFalse($event->isImmediateProcessingForced());
        $event->setForceImmediateProcessing(true);
        self::assertTrue($event->isImmediateProcessingForced());
        $event->setForceImmediateProcessing(false);
        self::assertFalse($event->isImmediateProcessingForced());
    }

    #[Test]
    public function canCleanEventOnSerialization(): void
    {
        $fields = [
            'uid' => 123,
            'pid' => 10,
            'title' => 'dummy title',
            'l10n_diffsource' => 'dummy l10n_diffsource',
        ];

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
                GarbageHandler::getRequiredUpdatedFields(),
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
