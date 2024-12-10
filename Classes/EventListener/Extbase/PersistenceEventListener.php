<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\EventListener\Extbase;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordGarbageCheckEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\Traits\SkipMonitoringTrait;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Extbase\Event\Persistence\EntityPersistedEvent;
use TYPO3\CMS\Extbase\Event\Persistence\EntityRemovedFromPersistenceEvent;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;

/**
 * Event listener that handle record changes by \TYPO3\CMS\Extbase\Persistence\Generic\Backend
 */
class PersistenceEventListener
{
    use SkipMonitoringTrait;

    public function __construct(
        protected DataMapFactory $dataMapFactory,
        protected EventDispatcher $eventDispatcher,
    ) {}

    public function entityPersisted(EntityPersistedEvent $event): void
    {
        $object = $event->getObject();
        $tableName = $this->getTableName($object);
        if (!$this->skipMonitoringOfTable($tableName)) {
            // Entity might turn inaccessible
            $this->eventDispatcher->dispatch(new RecordGarbageCheckEvent($object->getUid(), $tableName));
            // Entity added/updated
            $this->eventDispatcher->dispatch(new RecordUpdatedEvent($object->getUid(), $tableName));
        }
    }

    public function entityRemoved(EntityRemovedFromPersistenceEvent $event): void
    {
        $object = $event->getObject();
        $tableName = $this->getTableName($object);
        if (!$this->skipMonitoringOfTable($tableName)) {
            $this->eventDispatcher->dispatch(new RecordDeletedEvent($object->getUid(), $tableName));
        }
    }

    protected function getTableName(object $object): string
    {
        $dataMap = $this->dataMapFactory->buildDataMap(get_class($object));
        return $dataMap->getTableName();
    }
}
