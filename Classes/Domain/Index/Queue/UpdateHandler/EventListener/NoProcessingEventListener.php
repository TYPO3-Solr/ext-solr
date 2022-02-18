<?php

declare(strict_types = 1);

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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;

/**
 * Event listener stopping the propagation of the data update
 * events, if deactivated in extension configuration
 */
class NoProcessingEventListener extends AbstractBaseEventListener
{
    public const MONITORING_TYPE = 2;

    /**
     * Stops the event propagation if processing is configured
     * See EM_CONF -> monitoringType
     *
     * @param DataUpdateEventInterface $event
     */
    public function __invoke(DataUpdateEventInterface $event): void
    {
        if (!$this->isProcessingEnabled()) {
            return;
        }

        $event->setStopProcessing(true);
    }
}
