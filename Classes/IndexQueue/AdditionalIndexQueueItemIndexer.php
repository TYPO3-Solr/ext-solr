<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
     * @param \Apache_Solr_Document $itemDocument The original item document.
     * @return array An array of additional Apache_Solr_Document objects
     */
    public function getAdditionalItemDocuments(
        Item $item,
        $language,
        \Apache_Solr_Document $itemDocument
    );
}
