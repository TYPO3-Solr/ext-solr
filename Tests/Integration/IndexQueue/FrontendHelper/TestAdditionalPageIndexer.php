<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\AdditionalPageIndexer;

class TestAdditionalPageIndexer implements AdditionalPageIndexer
{

    /**
     * Provides additional documents that should be indexed together with a page.
     *
     * @param \Apache_Solr_Document $pageDocument The original page document.
     * @param array $allDocuments An array containing all the documents collected until here, including the page document
     * @return array An array of additional Apache_Solr_Document objects
     */
    public function getAdditionalPageDocuments(\Apache_Solr_Document $pageDocument, array $allDocuments)
    {
        $secondDocument = clone $pageDocument;

        $id = $pageDocument->getField('id');
        $copyId = $id['value'] . '-copy';

        $secondDocument->setField('id', $copyId);
        $secondDocument->setField('custom_stringS', 'additional text');

        return [$secondDocument];
    }
}
