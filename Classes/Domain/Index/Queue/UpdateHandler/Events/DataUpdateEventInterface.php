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
 * Defines a data update event
 */
interface DataUpdateEventInterface
{
    /**
     * Cleans the event before serialisation
     * e.g. only required fields should be kept
     * in fields array
     */
    public function __sleep(): array;

    /**
     * Returns the uid of the updated record
     */
    public function getUid(): int;

    /**
     * Returns the table of the updated record
     */
    public function getTable(): string;

    /**
     * Returns the updated fields
     */
    public function getFields(): array;

    /**
     * Indicates if record is a page
     */
    public function isPageUpdate(): bool;

    /**
     * Indicates if event is a content element update
     */
    public function isContentElementUpdate(): bool;

    /**
     * Sets the stop processing flag
     *
     * If set, event propagation is stopped
     */
    public function setStopProcessing(bool $stopProcessing): void;

    /**
     * Indicates if immediate processing is forced
     */
    public function isImmediateProcessingForced(): bool;

    /**
     * Sets the flag indicating if immediate processing is forced
     */
    public function setForceImmediateProcessing(bool $forceImmediateProcessing): void;
}
