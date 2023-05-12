<?php

declare(strict_types=1);

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerDocumentsModifier;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

class TestPageIndexerDocumentsModifier implements PageIndexerDocumentsModifier
{
    /**
     * Allows Modification of the Documents before they go into index
     *
     * @param Document[] $documents
     */
    public function modifyDocuments(Item $item, int $language, array $documents): array
    {
        foreach ($documents as $document) {
            $document->addField('postProcessorField_stringS', 'postprocessed');
        }

        return $documents;
    }
}
