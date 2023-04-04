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

/**
 * Defines a data update event
 */
interface DataUpdateEventInterface
{
    /**
     * Cleans the event before serialisation
     * e.g. only required fields should be kept
     * in fields array
     *
     * @return array
     */
    public function __sleep(): array;

    /**
     * Returns the uid of the updated record
     *
     * @return int
     */
    public function getUid(): int;

    /**
     * Returns the table of the updated record
     *
     * @return string
     */
    public function getTable(): string;

    /**
     * Returns the updated fields
     *
     * @return array
     */
    public function getFields(): array;

    /**
     * Indicates if record is a page
     *
     * @return bool
     */
    public function isPageUpdate(): bool;

    /**
     * Indicates if event is a content element update
     *
     * @return bool
     */
    public function isContentElementUpdate(): bool;

    /**
     * Sets the stop processing flag
     *
     * If set, event propagation is stopped
     *
     * @param bool $stopProcessing
     */
    public function setStopProcessing(bool $stopProcessing): void;

    /**
     * Indicates if immediate processing is forced
     *
     * @return bool
     */
    public function isImmediateProcessingForced(): bool;

    /**
     * Sets the flag indicating if immediate processing is forced
     *
     * @param bool $forceImmediateProcessing
     */
    public function setForceImmediateProcessing(bool $forceImmediateProcessing): void;
}
