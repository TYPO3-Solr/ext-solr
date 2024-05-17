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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class FacetCollectionTest
 */
class FacetCollectionTest extends SetUpUnitTestCase
{
    #[Test]
    public function canAddAndRetrieveFacetByKey(): void
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->createMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'left']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);

        self::assertEquals($colorFacet, $facetCollection['color']);
        self::assertEquals($brandFacet, $facetCollection['brand']);
    }

    #[Test]
    public function canAddAndRetrieveFacetByPosition(): void
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->createMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'left']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);

        self::assertEquals($colorFacet, $facetCollection->getByPosition(0));
        self::assertEquals($brandFacet, $facetCollection->getByPosition(1));
    }

    #[Test]
    public function canRetrieveFacetOfCollectionCopyByKey(): void
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->createMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'top']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);

        $leftFacetCollection = $facetCollection->getByGroupName('left');
        self::assertEquals(1, $leftFacetCollection->count());
        self::assertEquals($brandFacet, $leftFacetCollection['brand']);
    }

    #[Test]
    public function canRetrieveFacetOfCollectionCopyByPosition(): void
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->createMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'top']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);

        $leftFacetCollection = $facetCollection->getByGroupName('left');
        self::assertEquals(1, $leftFacetCollection->count());
        self::assertEquals($brandFacet, $leftFacetCollection->getByPosition(0));
    }
}
