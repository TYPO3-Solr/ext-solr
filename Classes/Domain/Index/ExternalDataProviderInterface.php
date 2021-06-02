<?php
declare(strict_types=1);
namespace ApacheSolrForTypo3\Solr\Domain\Index;

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

/**
 * This interface defines all methods that are required to by the QueueItemRepository
 *
 * Implement this interface and configure it within your TypoScript queue configuration
 *   index.externalDataProvider.table_name = \My\Vendor\Domain\Solr\ExternalDataProvider
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
interface ExternalDataProviderInterface
{
    /**
     * This methods retrieve all data from the external data source.
     * It should return an array of data. The index of the array is the uid of your record.
     *
     * @see \ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository::getAllQueueItemRecordsByUidsGroupedByTable
     * @param array $uids
     * @return array
     */
    public function fetchRecordsQueueItemUids(array $uids = []): array;
}
