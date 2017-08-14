<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetProcessor;

/**
 * Class TestSearchResponseProcessor
 */
class TestSearchResultSetProcessor implements SearchResultSetProcessor
{


    /**
     * @param SearchResultSet $resultSet
     * @return SearchResultSet
     */
    public function process(SearchResultSet $resultSet)
    {
        foreach ($resultSet->getSearchResults() as $result) {
            $result->type = strtoupper($result->type);
        }

        return $resultSet;
    }
}
