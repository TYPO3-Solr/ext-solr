<?php
declare(strict_types=1);

namespace ApacheSolrForTypo3\Solr\EventListener;

use ApacheSolrForTypo3\Solr\IndexQueue\DomainObjectObserver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Event\Persistence\EntityUpdatedInPersistenceEvent;
use TYPO3\CMS\Extbase\Event\Persistence\EntityRemovedFromPersistenceEvent;

/**
 *
 */
class EntityListener
{
    public function entityRemovedFromPersistence(EntityRemovedFromPersistenceEvent $event): void
    {
        $object = $event->getObject();
        $domainObjectObserver = GeneralUtility::makeInstance(DomainObjectObserver::class);
        $domainObjectObserver->afterRemoveObject($object);
    }

    public function entityUpdatedInPersistence(EntityUpdatedInPersistenceEvent $event): void
    {
        $object = $event->getObject();
        $domainObjectObserver = GeneralUtility::makeInstance(DomainObjectObserver::class);
        $domainObjectObserver->afterUpdateObject($object);
    }
}
