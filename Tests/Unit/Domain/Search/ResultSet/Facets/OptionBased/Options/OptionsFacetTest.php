<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\OptionBased\Options;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResult;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * Unit test for the OptionsFacet
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Frans Saris <frans@beech.it>
 */
class OptionsFacetTest extends UnitTest
{

    /**
     * @test
     */
    public function canGetTitleFromOptionsFacet()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $optionsFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');
        $this->assertSame('myTitle', $optionsFacet->getLabel(), 'Could not get title from options facet');
    }

    /**
     * @test
     */
    public function canAddOptionsToFacet()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $optionsFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');
        $option = new Option($optionsFacet);

            // before adding there should not be any facet present
        $this->assertEquals(0, $optionsFacet->getOptions()->getCount());
        $optionsFacet->addOption($option);

            // now we should have 1 option present
        $this->assertEquals(1, $optionsFacet->getOptions()->getCount());
    }

    /**
     * @test
     */
    public function getDefaultPartialName()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $queryGroupFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');

        $this->assertEquals('Options', $queryGroupFacet->getPartialName());
    }

    /**
     * @test
     */
    public function getCustomPartialName()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $queryGroupFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle', ['partialName' => 'MyPartial']);

        $this->assertEquals('MyPartial', $queryGroupFacet->getPartialName());
    }

    /**
     * @test
     */
    public function getType()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $myFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle', ['partialName' => 'MyPartial']);

        $this->assertEquals('options', $myFacet->getType());
    }
}
