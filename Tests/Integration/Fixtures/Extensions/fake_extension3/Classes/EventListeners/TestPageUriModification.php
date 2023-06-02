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

namespace ApacheSolrForTypo3\SolrFakeExtension3\EventListeners;

use ApacheSolrForTypo3\Solr\Event\Indexing\AfterFrontendPageUriForIndexingHasBeenGeneratedEvent;

class TestPageUriModification
{
    public function __invoke(AfterFrontendPageUriForIndexingHasBeenGeneratedEvent $event): void
    {
        if (!$event->getItem()->hasIndexingProperty('size')) {
            return;
        }
        if ($event->getItem()->getIndexingProperty('size') === 'enorme') {
            $event->setPageIndexUri(
                $event->getPageIndexUri()->withQuery('&larger=large')
            );
        }
    }
}
