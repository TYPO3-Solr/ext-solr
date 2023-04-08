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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\DataUpdateHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Abstract data update event
 */
abstract class AbstractDataUpdateEvent implements DataUpdateEventInterface, StoppableEventInterface
{
    /**
     * Record uid
     */
    protected int $uid;

    /**
     * Record table
     */
    protected string $table;

    /**
     * Updated record fields
     */
    protected array $fields = [];

    /**
     * Flag indicating that propagation is stopped
     */
    protected bool $stopProcessing = false;

    /**
     * Flag indicating that immediate processing is forced
     */
    protected bool $forceImmediateProcessing = false;

    /**
     * Flag indicating that frontend groups were removed
     */
    protected bool $frontendGroupsRemoved = false;

    /**
     * Constructor
     */
    public function __construct(int $uid, string $table, array $fields = [], bool $frontendGroupsRemoved = false)
    {
        $this->uid = $uid;
        $this->table = $table;
        $this->fields = $fields;
        $this->frontendGroupsRemoved = $frontendGroupsRemoved;
    }

    /**
     * Cleans the event before serialisation
     * e.g. only required fields should be kept
     * in fields array
     */
    public function __sleep(): array
    {
        // always remove l10n_diffsource
        unset($this->fields['l10n_diffsource']);

        $properties = array_keys(get_object_vars($this));
        if ($this->table == 'pages') {
            // skip cleanup for pages as there might be additional
            // required update fields in TypoScript which
            // we don't want to load here. (see: "recursiveUpdateFields")
            return $properties;
        }

        $requiredUpdateFields = array_unique(array_merge(
            DataUpdateHandler::getRequiredUpdatedFields(),
            GarbageHandler::getRequiredUpdatedFields()
        ));
        $this->fields = array_intersect_key($this->fields, array_flip($requiredUpdateFields));

        return $properties;
    }

    /**
     * Returns the uid of the updated record
     */
    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * Returns the table of the updated record
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Returns the updated fields
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Indicates if the event propagation is stopped
     * If stopped, it prevents other listeners from being called
     *
     * @see StoppableEventInterface::isPropagationStopped
     */
    final public function isPropagationStopped(): bool
    {
        return $this->stopProcessing;
    }

    /**
     * Sets the stop rendering flag
     *
     * If set, event propagation is stopped
     */
    final public function setStopProcessing(bool $stopProcessing): void
    {
        $this->stopProcessing = $stopProcessing;
    }

    /**
     * Indicates if event is a page update
     */
    public function isPageUpdate(): bool
    {
        return $this->table === 'pages';
    }

    /**
     * Indicates if event is a content element update
     */
    public function isContentElementUpdate(): bool
    {
        return $this->table === 'tt_content';
    }

    /**
     * Indicates if immediate processing is forced
     */
    final public function isImmediateProcessingForced(): bool
    {
        return $this->forceImmediateProcessing;
    }

    /**
     * Sets the flag indicating if immediate processing is forced
     */
    final public function setForceImmediateProcessing(bool $forceImmediateProcessing): void
    {
        $this->forceImmediateProcessing = $forceImmediateProcessing;
    }

    /**
     * Indicates the removal of frontend groups
     */
    final public function frontendGroupsRemoved(): bool
    {
        return $this->frontendGroupsRemoved;
    }
}
