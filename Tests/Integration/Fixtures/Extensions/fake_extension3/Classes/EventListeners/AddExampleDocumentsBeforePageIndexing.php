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
use ApacheSolrForTypo3\Solr\Util;

final class AddExampleDocumentsBeforePageIndexing
{
    /**
     * Provides additional documents that should be indexed together with a page.
     *
     * Enabled per test through TypoScript, the way a real listener would be configured, because
     * the production pipeline builds the indexing sub-request itself and carries no query
     * parameters a test could switch on.
     */
    public function __invoke(BeforePageDocumentIsProcessedForIndexingEvent $event): void
    {
        $isEnabled = Util::getSolrConfiguration()
            ->getValueByPathOrDefaultValue('plugin.tx_solr.index.queue.pages.addExampleDocuments', 0);
        if (!$isEnabled) {
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
