<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\TestPackage;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItemCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\NodeCollection;

class TestFacet extends AbstractFacet
{
    /**
     * The implementation of this method should return a "flatten" collection of all items.
     */
    public function getAllFacetItems(): AbstractFacetItemCollection
    {
        return new NodeCollection();
    }
}
