<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\AdditionalPageIndexer;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

class TestAdditionalPageIndexer implements AdditionalPageIndexer {


    /**
     * Provides additional documents that should be indexed together with a page.
     *
     * @param Document $pageDocument The original page document.
     * @param array $allDocuments An array containing all the documents collected until here, including the page document
     * @return Document[] array An array of additional Document objects
     */
    public function getAdditionalPageDocuments(Document $pageDocument, array $allDocuments)
    {
        $secondDocument = clone $pageDocument;

        $id = $pageDocument['id'];
        $copyId = $id['value'] . '-copy';

        $secondDocument->setField('id', $copyId);
        $secondDocument->setField('custom_stringS', 'additional text');
        return [$secondDocument];
    }
}
