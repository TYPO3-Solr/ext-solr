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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

/**
 * Interface that defines the method an indexer must implement to provide
 * additional documents to index for a page being indexed.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface AdditionalPageIndexer
{

    /**
     * Provides additional documents that should be indexed together with a page.
     *
     * @param Document $pageDocument The original page document.
     * @param array $allDocuments An array containing all the documents collected until here, including the page document
     * @return array An array of additional \ApacheSolrForTypo3\Solr\System\Solr\Document\Document objects
     */
    public function getAdditionalPageDocuments(Document $pageDocument, array $allDocuments);
}
