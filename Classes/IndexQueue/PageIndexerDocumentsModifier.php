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

/**
 * IndexQueuePageIndexerDocumentsModifier interface, allows to modify documents
 * before adding them to the Solr index in the index queue page indexer.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface PageIndexerDocumentsModifier
{

    /**
     * Modifies the given documents
     *
     * @param Item $item The currently being indexed item.
     * @param int $language The language uid of the documents
     * @param array $documents An array of documents to be indexed
     * @return array An array of modified documents
     */
    public function modifyDocuments(Item $item, int $language, array $documents);
}
