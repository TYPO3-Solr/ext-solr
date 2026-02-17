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

use ApacheSolrForTypo3\Solr\Event\Indexing\BeforePageDocumentIsProcessedForIndexingEvent;

final class AddExampleDocumentsBeforePageIndexing
{
    /**
     * Provides additional documents that should be indexed together with a page.
     */
    public function __invoke(BeforePageDocumentIsProcessedForIndexingEvent $event): void
    {
        $request = $event->getRequest();
        $queryParams = $request->getQueryParams();
        if (!($queryParams['additionalTestPageIndexer'] ?? false)) {
            return;
        }
        $pageDocument = $event->getDocument();
        $secondDocument = clone $pageDocument;

        $id = $pageDocument['id'];
        $copyId = $id . '-copy';

        $secondDocument->setField('id', $copyId);
        $secondDocument->setField('custom_stringS', 'additional text');
        $event->addDocuments([$secondDocument]);
    }
}
