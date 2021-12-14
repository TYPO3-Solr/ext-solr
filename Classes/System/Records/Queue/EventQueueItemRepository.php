<?php
namespace ApacheSolrForTypo3\Solr\System\Records\Queue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2021 Markus Friedrich <markus.friedrich@dkd.de>
 *
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

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use TYPO3\CMS\Core\SingletonInterface;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;
use ApacheSolrForTypo3\Solr\Util;

/**
 * EventQueueItemRepository to encapsulate the database access for the event queue items
 */
class EventQueueItemRepository extends AbstractRepository implements SingletonInterface
{
    /**
     * @var string
     */
    protected $table = 'tx_solr_eventqueue_item';

    /**
     * Add event to event queue
     *
     * @param DataUpdateEventInterface $event
     */
    public function addEventToQueue(DataUpdateEventInterface $event): void
    {
        $serializedEvent = serialize($event);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->insert($this->table)
            ->values([
                'tstamp' => Util::getExectionTime(),
                'event' => $serializedEvent

            ])
            ->execute();
    }

    /**
     * Returns event queue items
     *
     * @param int $limit
     * @param bool $excludeErroneousItems
     * @return array
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

        return $queryBuilder->execute()->fetchAll();
    }

    /**
     * Updates a event queue item
     *
     * @param int $uid
     * @param array $data
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

        foreach($data as $column => $value) {
            $queryBuilder->set($column, $value);
        }

        $queryBuilder->execute();
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
            ->execute();
    }

    /**
     * Returns current count of last searches
     *
     * @param bool $excludeErroneousItems
     * @return int
     */
    public function count($excludeErroneousItems = true): int
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->count('*')
            ->from($this->table);

        if ($excludeErroneousItems) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('error', 0));
        }

        return (int)$queryBuilder->execute()->fetchColumn();
    }
}
