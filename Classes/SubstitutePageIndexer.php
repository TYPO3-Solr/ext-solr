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
 * Substitute page indexer interface, describes the method an indexer must
 * implement to provide a substitute page document
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface SubstitutePageIndexer
{

    /**
     * returns a substitute document for the currently being indexed page
     *
     * @param Document $originalPageDocument The original page document.
     * @return Document returns an \ApacheSolrForTypo3\Solr\System\Solr\Document\Document object that replace the default page document
     */
    public function getPageDocument(Document $originalPageDocument);
}
