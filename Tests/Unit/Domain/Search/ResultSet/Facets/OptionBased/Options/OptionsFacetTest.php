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
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Unit test for the OptionsFacet
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Frans Saris <frans@beech.it>
 */
class OptionsFacetTest extends UnitTest
{
    protected function setUp(): void
    {
        parent::setUp();
        GeneralUtility::setSingletonInstance(ObjectManager::class, $this->createMock(ObjectManager::class));
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canGetTitleFromOptionsFacet()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $optionsFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');
        self::assertSame('myTitle', $optionsFacet->getLabel(), 'Could not get title from options facet');
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
        // @extensionScannerIgnoreLine
        self::assertEquals(0, $optionsFacet->getOptions()->getCount());
        $optionsFacet->addOption($option);

        // now we should have 1 option present
        // @extensionScannerIgnoreLine
        self::assertEquals(1, $optionsFacet->getOptions()->getCount());
    }

    /**
     * @test
     */
    public function getDefaultPartialName()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $queryGroupFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');

        self::assertEquals('Options', $queryGroupFacet->getPartialName());
    }

    /**
     * @test
     */
    public function getCustomPartialName()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $queryGroupFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle', ['partialName' => 'MyPartial']);

        self::assertEquals('MyPartial', $queryGroupFacet->getPartialName());
    }

    /**
     * @test
     */
    public function getType()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $myFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle', ['partialName' => 'MyPartial']);

        self::assertEquals('options', $myFacet->getType());
    }

    /**
     * @return array
     */
    public function getIncludeInAvailableFacetsDataProvider()
    {
        return [
            'default' => [null, true],
            'zero' => [0, false],
            'one' => [1, true],
            '1' => ['1', true],
            '0' => ['0', false],
        ];
    }

    /**
     * @param mixed $includeInAvailableFacetsConfiguration
     * @param mixed $expectedResult
     * @dataProvider getIncludeInAvailableFacetsDataProvider
     * @test
     */
    public function getIncludeInAvailableFacets($includeInAvailableFacetsConfiguration, $expectedResult)
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $myFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle', ['includeInAvailableFacets' => $includeInAvailableFacetsConfiguration]);

        self::assertSame($myFacet->getIncludeInAvailableFacets(), $expectedResult, 'Method getIncludeInAvailableFacets returns unexpected result');
    }
}
