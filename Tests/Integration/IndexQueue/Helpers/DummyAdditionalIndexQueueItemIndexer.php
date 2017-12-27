<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\Helpers;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017- Thomas Hohn <tho@systime.dk>
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

use ApacheSolrForTypo3\Solr\IndexQueue\AdditionalIndexQueueItemIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;

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
     * @param \Apache_Solr_Document $itemDocument The original item document.
     *
     * @return array An array of additional Apache_Solr_Document objects
     */
    public function getAdditionalItemDocuments(
        Item $item,
        $language,
        \Apache_Solr_Document $itemDocument
    ) {
        return [];
    }
}
