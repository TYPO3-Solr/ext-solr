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

namespace ApacheSolrForTypo3\SolrFakeExtension2\EventListeners;

use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

final class AddAdditionalTestDocumentsToIndexer
{
    /**
     * Gated on the record's title rather than on a flag, so it can be triggered by a fixture:
     * the record reaching this event during indexing is the one that is stored.
     */
    public function __invoke(BeforeDocumentIsProcessedForIndexingEvent $event): void
    {
        if (($event->getIndexQueueItem()->getRecord()['title'] ?? '') !== 'activate-event-listener') {
            return;
        }

        // Derived from the document being indexed, because this one reaches Solr now and needs
        // the fields the schema requires, with an id of its own.
        $fields = $event->getDocument()->getFields();
        $additionalDocument = new Document($fields);
        $additionalDocument->setField('id', $fields['id'] . '/additional');
        $additionalDocument->setField('alternativeRecord_stringS', 'additional-test-document');

        $event->addDocuments([$additionalDocument]);
    }
}
