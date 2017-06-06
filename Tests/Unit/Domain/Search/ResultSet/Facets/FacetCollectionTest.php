<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets;

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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * Class FacetCollectionTest
 *
 * @author Frans Saris <frans@beech.it>
 */
class FacetCollectionTest extends UnitTest
{

    /**
     * @test
     */
    public function canAddAndRetrieveFacetByKey()
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'left']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);

        $this->assertEquals($colorFacet, $facetCollection['color']);
        $this->assertEquals($brandFacet, $facetCollection['brand']);
    }

    /**
     * @test
     */
    public function canAddAndRetrieveFacetByPosition()
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'left']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);

        $this->assertEquals($colorFacet, $facetCollection->getByPosition(0));
        $this->assertEquals($brandFacet, $facetCollection->getByPosition(1));
    }

    /**
     * @test
     */
    public function canRetrieveFacetOfCollectionCopyByKey()
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'top']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);

        $leftFacetCollection = $facetCollection->getByGroupName('left');
        $this->assertEquals(1, $leftFacetCollection->count());
        $this->assertEquals($brandFacet, $leftFacetCollection['brand']);
    }

    /**
     * @test
     */
    public function canRetrieveFacetOfCollectionCopyByPosition()
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'top']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);

        $leftFacetCollection = $facetCollection->getByGroupName('left');
        $this->assertEquals(1, $leftFacetCollection->count());
        $this->assertEquals($brandFacet, $leftFacetCollection->getByPosition(0));
    }
}
