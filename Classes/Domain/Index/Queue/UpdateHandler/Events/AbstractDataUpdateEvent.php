<?php

declare(strict_types=1);
namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2021 Markus Friedrich <markus.friedrich@dkd.de>
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
     *
     * @var int
     */
    protected $uid;

    /**
     * Record table
     *
     * @var string
     */
    protected $table;

    /**
     * Updated record fields
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Flag indicating that propagation is stopped
     *
     * @var bool
     */
    protected $stopProcessing = false;

    /**
     * Flag indicating that immediate processing is forced
     *
     * @var bool
     */
    protected $forceImmediateProcessing = false;

    /**
     * Flag indicating that frontend groups were removed
     *
     * @var bool
     */
    protected $frontendGroupsRemoved = false;

    /**
     * Constructor
     *
     * @param int $uid
     * @param string $uid
     * @param array $fields
     * @param bool $frontendGroupsRemoved
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
     *
     * @return array
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
     *
     * @return int
     */
    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * Returns the table of the updated record
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Returns the updated fields
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Indicates if the event propagation is stopped
     * If stopped, it prevents other listeners from being called
     *
     * {@inheritDoc}
     * @see \Psr\EventDispatcher\StoppableEventInterface::isPropagationStopped()
     */
    final public function isPropagationStopped(): bool
    {
        return $this->stopProcessing;
    }

    /**
     * Sets the stop rendering flag
     *
     * If set, event propagation is stopped
     *
     * @param bool $stopRendering
     */
    final public function setStopProcessing(bool $stopProcessing): void
    {
        $this->stopProcessing = $stopProcessing;
    }

    /**
     * Indicates if event is a page update
     *
     * @return bool
     */
    public function isPageUpdate(): bool
    {
        return $this->table === 'pages';
    }

    /**
     * Indicates if event is a content element update
     *
     * @return bool
     */
    public function isContentElementUpdate(): bool
    {
        return $this->table === 'tt_content';
    }

    /**
     * Indicates if immediate processing is forced
     *
     * @return bool
     */
    final public function isImmediateProcessingForced(): bool
    {
        return $this->forceImmediateProcessing;
    }

    /**
     * Sets the flag indicating if immediate processing is forced
     *
     * @param bool $forceImmediateProcessing
     */
    final public function setForceImmediateProcessing(bool $forceImmediateProcessing): void
    {
        $this->forceImmediateProcessing = $forceImmediateProcessing;
    }

    /**
     * Indicates the removal of frontend groups
     *
     * @return bool
     */
    final public function frontendGroupsRemoved(): bool
    {
        return $this->frontendGroupsRemoved;
    }
}
