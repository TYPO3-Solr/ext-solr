<?php

declare(strict_types=1);
namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener;

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
