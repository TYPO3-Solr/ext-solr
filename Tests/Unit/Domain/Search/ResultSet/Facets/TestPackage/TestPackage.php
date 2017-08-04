<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\TestPackage;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetPackage;

class TestPackage extends AbstractFacetPackage
{

    /**
     * @return string
     */
    public function getParserClassName()
    {
        return TestParser::class;
    }
}
