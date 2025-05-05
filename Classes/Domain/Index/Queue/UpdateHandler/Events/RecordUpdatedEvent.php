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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events;

/**
 * Event fired if a record is created or updated
 */
class RecordUpdatedEvent extends AbstractDataUpdateEvent
{
    /**
     * @deprecated For compatibility reasons flag "$isNewRecord" is added to RecordUpdatedEvent,
     *             in v14 a separate RecordInsertedEvent will be used
     */
    protected bool $isNewRecord;

    public function __construct(
        int $uid,
        string $table,
        array $fields = [],
        bool $frontendGroupsRemoved = false,
        bool $isNewRecord = false,
    ) {
        parent::__construct($uid, $table, $fields, $frontendGroupsRemoved);
        $this->isNewRecord = $isNewRecord;
    }

    /**
     * @deprecated For compatibility reasons flag "$isNewRecord" is added to RecordUpdatedEvent,
     *             in v14 a separate RecordInsertedEvent will be used
     */
    final public function isNewRecord(): bool
    {
        return $this->isNewRecord;
    }
}
