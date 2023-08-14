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

namespace ApacheSolrForTypo3\Solr\System\Records\Queue;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;
use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use ApacheSolrForTypo3\Solr\Util;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * EventQueueItemRepository to encapsulate the database access for the event queue items
 */
class EventQueueItemRepository extends AbstractRepository implements SingletonInterface
{
    protected string $table = 'tx_solr_eventqueue_item';

    /**
     * Add event to event queue
     *
     * @throws AspectNotFoundException
     */
    public function addEventToQueue(DataUpdateEventInterface $event): void
    {
        $serializedEvent = serialize($event);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->insert($this->table)
            ->values([
                'tstamp' => Util::getExecutionTime(),
                'event' => $serializedEvent,
            ])
            ->executeStatement();
    }

    /**
     * Returns event queue items
     *
     * @throws DBALException
     */
    public function getEventQueueItems(int $limit = null, bool $excludeErroneousItems = true): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->table)
            ->addOrderBy('uid');

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }
        if ($excludeErroneousItems) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('error', 0));
        }

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Updates a event queue item
     */
    public function updateEventQueueItem(int $uid, array $data): void
    {
        if (!$uid > 0) {
            return;
        }

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->update($this->table)
            ->where(
                $queryBuilder->expr()->eq('uid', $uid)
            );

        foreach ($data as $column => $value) {
            $queryBuilder->set($column, $value);
        }

        $queryBuilder->executeStatement();
    }

    /**
     * Deletes event queue items
     *
     * @param int[] $uids
     */
    public function deleteEventQueueItems(array $uids): void
    {
        if (empty($uids)) {
            return;
        }

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->in('uid', array_map('intval', $uids))
            )
            ->executeStatement();
    }

    /**
     * Returns current count of last searches
     *
     * @throws DBALException
     */
    public function count(bool $excludeErroneousItems = true): int
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->count('*')
            ->from($this->table);

        if ($excludeErroneousItems) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('error', 0));
        }

        return (int)$queryBuilder
            ->executeQuery()
            ->fetchOne();
    }
}
