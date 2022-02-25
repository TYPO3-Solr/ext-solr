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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;

/**
 * Basic data update event processing finished event
 */
abstract class AbstractProcessingFinishedEvent implements ProcessingFinishedEventInterface
{
    /**
     * The processed data update event
     */
    private DataUpdateEventInterface $dataUpdateEvent;

    /**
     * @param DataUpdateEventInterface $dataUpdateEvent
     */
    public function __construct(DataUpdateEventInterface $dataUpdateEvent)
    {
        $this->dataUpdateEvent = $dataUpdateEvent;
    }

    /**
     * Returns the processed data update event
     *
     * @return DataUpdateEventInterface
     */
    final public function getDataUpdateEvent(): DataUpdateEventInterface
    {
        return $this->dataUpdateEvent;
    }
}
