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
 * Abstract event base for events fired if a record or page is moved
 */
abstract class AbstractRecordMovedEvent extends AbstractDataUpdateEvent
{
    /**
     * pid of the record prior moving
     *
     * @var int|null
     */
    protected ?int $previousParentId = null;

    /**
     * Sets the record's pid prior moving
     *
     * @param int $pid
     */
    public function setPreviousParentId(int $pid): void
    {
        $this->previousParentId = $pid;
    }

    /**
     * Returns the record's pid prior moving
     *
     * @return int|null
     */
    public function getPreviousParentId(): ?int
    {
        return $this->previousParentId;
    }
}
