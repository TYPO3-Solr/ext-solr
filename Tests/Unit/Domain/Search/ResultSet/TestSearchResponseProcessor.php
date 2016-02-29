<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet;

use \ApacheSolrForTypo3\Solr\Response\Processor\ResponseProcessor;

/**
 * Class TestSearchResponseProcessor
 */
class TestSearchResponseProcessor implements ResponseProcessor
{

    /**
     * Processes a query and its response after searching for that query.
     *
     * @param \ApacheSolrForTypo3\Solr\Query $query The query that has been searched for.
     * @param \Apache_Solr_Response $response The response for the last query.
     * @return void
     */
    public function processResponse(\ApacheSolrForTypo3\Solr\Query $query, \Apache_Solr_Response $response)
    {
        foreach ($response->response->docs as $document) {
            $document->type = strtoupper($document->type);
        }
    }
}
