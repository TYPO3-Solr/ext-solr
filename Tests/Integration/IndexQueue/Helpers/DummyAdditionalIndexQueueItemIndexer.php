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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\Helpers;

use ApacheSolrForTypo3\Solr\IndexQueue\AdditionalIndexQueueItemIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

/**
 * Class DummyAdditionalIndexQueueItemIndexer for testing purpose
 * @author Thomas Hohn <tho@systime.dk>
 */
class DummyAdditionalIndexQueueItemIndexer implements AdditionalIndexQueueItemIndexer
{
    /**
     * Provides additional documents that should be indexed together with an Index Queue item.
     *
     * @param Item $item The item currently being indexed.
     * @param int $language The language uid of the documents
     * @param Document $itemDocument The original item document.
     *
     * @return Document[] An array of additional Apache Solr Document objects
     */
    public function getAdditionalItemDocuments(Item $item, $language, Document $itemDocument)
    {
        return [];
    }
}
