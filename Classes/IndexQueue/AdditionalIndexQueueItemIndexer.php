<?php

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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

/**
 * Interface that defines the method an indexer must implement to provide
 * additional documents to index for an item being indexed by the Index Queue.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface AdditionalIndexQueueItemIndexer
{

    /**
     * Provides additional documents that should be indexed together with an Index Queue item.
     *
     * @param Item $item The item currently being indexed.
     * @param int $language The language uid of the documents
     * @param Document $itemDocument The original item document.
     * @return Document[] array An array of additional Document objects
     */
    public function getAdditionalItemDocuments(Item $item, $language, Document $itemDocument);
}
