<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\OptionBased\Options;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * Unit test for the OptionsFacet
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class OptionCollectionTest extends UnitTest
{
    /**
     * @test
     */
    public function canGetManualSortedCopy()
    {
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $facet = new OptionsFacet($searchResultSetMock, 'colors', 'colors_s');

        $red = new Option($facet, 'Rubin Red', 'red', 9);
        $blue = new Option($facet, 'Polar Blue', 'blue', 12);
        $yellow = new Option($facet, 'Lemon Yellow', 'yellow', 3);

        $facet->addOption($red);
        $facet->addOption($blue);
        $facet->addOption($yellow);

        // @extensionScannerIgnoreLine
        $sortedOptions = $facet->getOptions()->getManualSortedCopy(['yellow', 'blue']);

        $this->assertSame($yellow, $sortedOptions->getByPosition(0), 'First sorted item was not yellow');
        $this->assertSame($blue, $sortedOptions->getByPosition(1), 'First sorted item was not blue');
        $this->assertSame($red, $sortedOptions->getByPosition(2), 'First sorted item was not blue');
    }

    /**
     * @test
     */
    public function canGetLabelPrefixes()
    {
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $facet = new OptionsFacet($searchResultSetMock, 'colors', 'colors_s');

        $roseRed = new Option($facet, 'Rose Red', 'rose_red', 14);
        $blue = new Option($facet, 'Polar Blue', 'polar_blue', 12);
        $yellow = new Option($facet, 'Lemon Yellow', 'lemon_yellow', 3);
        $red = new Option($facet, 'Rubin Red', 'rubin_red', 9);
        $royalGreen = new Option($facet, 'Royal Green', 'royal_green', 14);

        $facet->addOption($red);
        $facet->addOption($blue);
        $facet->addOption($yellow);
        $facet->addOption($roseRed);
        $facet->addOption($royalGreen);

        // @extensionScannerIgnoreLine
        $labelPrefixes = $facet->getOptions()->getLowercaseLabelPrefixes(1);
        $this->assertSame(['r','p','l'], $labelPrefixes, 'Can not get expected label prefixes');
    }

    /**
     * @test
     */
    public function canGetByLowercaseLabelPrefix()
    {
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $facet = new OptionsFacet($searchResultSetMock, 'colors', 'colors_s');

        $roseRed = new Option($facet, 'Rose Red', 'rose_red', 14);
        $blue = new Option($facet, 'Polar Blue', 'polar_blue', 12);
        $yellow = new Option($facet, 'Lemon Yellow', 'lemon_yellow', 3);
        $red = new Option($facet, 'Rubin Red', 'rubin_red', 9);
        $royalGreen = new Option($facet, 'Royal Green', 'royal_green', 14);

        $facet->addOption($red);
        $facet->addOption($blue);
        $facet->addOption($yellow);
        $facet->addOption($roseRed);
        $facet->addOption($royalGreen);

        // @extensionScannerIgnoreLine
        $optionsStartingWithL = $facet->getOptions()->getByLowercaseLabelPrefix('l');
        $this->assertCount(1, $optionsStartingWithL, 'Unexpected amount of options starting with l');

        // @extensionScannerIgnoreLine
        $optionsStartingWithR = $facet->getOptions()->getByLowercaseLabelPrefix('r');
        $this->assertCount(3, $optionsStartingWithR, 'Unexpected amount of options starting with r');
    }

    /**
     * @test
     */
    public function canGetByLowercaseLabelPrefixWithMultiByteCharacter()
    {
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $facet = new OptionsFacet($searchResultSetMock, 'authors', 'authors_s');

        $ben = new Option($facet, 'Ben', 'ben', 14);
        $ole = new Option($facet, 'Øle', 'ole', 12);

        $facet->addOption($ben);
        $facet->addOption($ole);

        // @extensionScannerIgnoreLine
        $optionsStartingWithO = $facet->getOptions()->getByLowercaseLabelPrefix('ø');
        $this->assertCount(1, $optionsStartingWithO, 'Unexpected amount of options starting with ø');
    }

    /**
     * @test
     */
    public function canGetByValueAfterManualSorting()
    {
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $facet = new OptionsFacet($searchResultSetMock, 'colors', 'colors_s');

        $red = new Option($facet, 'Rubin Red', 'red', 9);
        $blue = new Option($facet, 'Polar Blue', 'blue', 12);
        $yellow = new Option($facet, 'Lemon Yellow', 'yellow', 3);

        $facet->addOption($red);
        $facet->addOption($blue);
        $facet->addOption($yellow);

        // @extensionScannerIgnoreLine
        $this->assertSame($yellow, $facet->getOptions()->getByValue('yellow'), 'Can not get option by value');
        // @extensionScannerIgnoreLine
        $this->assertSame($blue, $facet->getOptions()->getByValue('blue'), 'Can not get option by value');
        // @extensionScannerIgnoreLine
        $this->assertSame($red, $facet->getOptions()->getByValue('red'), 'Can not get option by value');

        // @extensionScannerIgnoreLine
        $sortedOptions = $facet->getOptions()->getManualSortedCopy(['yellow', 'blue']);

        $this->assertSame($yellow, $sortedOptions->getByValue('yellow'), 'Can not get option by value');
        $this->assertSame($blue, $sortedOptions->getByValue('blue'), 'Can not get option by value');
        $this->assertSame($red, $sortedOptions->getByValue('red'), 'Can not get option by value');

        $this->assertSame($yellow, $sortedOptions->getByPosition(0), 'First sorted item was not yellow');
        $this->assertSame($blue, $sortedOptions->getByPosition(1), 'First sorted item was not blue');
        $this->assertSame($red, $sortedOptions->getByPosition(2), 'First sorted item was not blue');
    }
}

