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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\AdditionalPageIndexer;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

class TestAdditionalPageIndexer implements AdditionalPageIndexer
{

    /**
     * Provides additional documents that should be indexed together with a page.
     *
     * @param Document $pageDocument The original page document.
     * @param array $allDocuments An array containing all the documents collected until here, including the page document
     * @return Document[] array An array of additional Document objects
     */
    public function getAdditionalPageDocuments(Document $pageDocument, array $allDocuments): array
    {
        $secondDocument = clone $pageDocument;

        $id = $pageDocument['id'];
        $copyId = $id . '-copy';

        $secondDocument->setField('id', $copyId);
        $secondDocument->setField('custom_stringS', 'additional text');
        return [$secondDocument];
    }
}
