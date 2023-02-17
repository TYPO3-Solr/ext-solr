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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\OptionBased\Options;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Unit test for the OptionsFacet
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class OptionCollectionTest extends UnitTest
{
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

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

        self::assertSame($yellow, $sortedOptions->getByPosition(0), 'First sorted item was not yellow');
        self::assertSame($blue, $sortedOptions->getByPosition(1), 'First sorted item was not blue');
        self::assertSame($red, $sortedOptions->getByPosition(2), 'First sorted item was not blue');
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
        self::assertSame(['r', 'p', 'l'], $labelPrefixes, 'Can not get expected label prefixes');
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
        self::assertCount(1, $optionsStartingWithL, 'Unexpected amount of options starting with l');

        // @extensionScannerIgnoreLine
        $optionsStartingWithR = $facet->getOptions()->getByLowercaseLabelPrefix('r');
        self::assertCount(3, $optionsStartingWithR, 'Unexpected amount of options starting with r');
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
        self::assertCount(1, $optionsStartingWithO, 'Unexpected amount of options starting with ø');
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
        self::assertSame($yellow, $facet->getOptions()->getByValue('yellow'), 'Can not get option by value');
        // @extensionScannerIgnoreLine
        self::assertSame($blue, $facet->getOptions()->getByValue('blue'), 'Can not get option by value');
        // @extensionScannerIgnoreLine
        self::assertSame($red, $facet->getOptions()->getByValue('red'), 'Can not get option by value');

        // @extensionScannerIgnoreLine
        $sortedOptions = $facet->getOptions()->getManualSortedCopy(['yellow', 'blue']);

        self::assertSame($yellow, $sortedOptions->getByValue('yellow'), 'Can not get option by value');
        self::assertSame($blue, $sortedOptions->getByValue('blue'), 'Can not get option by value');
        self::assertSame($red, $sortedOptions->getByValue('red'), 'Can not get option by value');

        self::assertSame($yellow, $sortedOptions->getByPosition(0), 'First sorted item was not yellow');
        self::assertSame($blue, $sortedOptions->getByPosition(1), 'First sorted item was not blue');
        self::assertSame($red, $sortedOptions->getByPosition(2), 'First sorted item was not blue');
    }
}
