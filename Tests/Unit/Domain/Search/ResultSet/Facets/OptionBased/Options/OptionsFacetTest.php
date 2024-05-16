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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Unit test for the OptionsFacet
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Frans Saris <frans@beech.it>
 */
class OptionsFacetTest extends SetUpUnitTestCase
{
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function canGetTitleFromOptionsFacet(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $optionsFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');
        self::assertSame('myTitle', $optionsFacet->getLabel(), 'Could not get title from options facet');
    }

    #[Test]
    public function canAddOptionsToFacet(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
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

    #[Test]
    public function getDefaultPartialName(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $queryGroupFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');

        self::assertEquals('Options', $queryGroupFacet->getPartialName());
    }

    #[Test]
    public function getCustomPartialName(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $queryGroupFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle', ['partialName' => 'MyPartial']);

        self::assertEquals('MyPartial', $queryGroupFacet->getPartialName());
    }

    #[Test]
    public function getType(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $myFacet = new OptionsFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle', ['partialName' => 'MyPartial']);

        self::assertEquals('options', $myFacet->getType());
    }

    public static function getIncludeInAvailableFacetsDataProvider(): Traversable
    {
        yield 'default' => [null, true];
        yield 'zero' => [0, false];
        yield 'one' => [1, true];
        yield '1' => ['1', true];
        yield '0' => ['0', false];
    }

    #[DataProvider('getIncludeInAvailableFacetsDataProvider')]
    #[Test]
    public function getIncludeInAvailableFacetsCastsSettingsToBoolProperly(
        null|int|string $includeInAvailableFacetsConfiguration,
        bool $expectedResult,
    ): void {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $myFacet = new OptionsFacet(
            $resultSetMock,
            'myFacet',
            'myFacetFieldName',
            'myTitle',
            [
                'includeInAvailableFacets' => $includeInAvailableFacetsConfiguration,
            ],
        );

        self::assertSame($myFacet->getIncludeInAvailableFacets(), $expectedResult, 'Method getIncludeInAvailableFacets returns unexpected result');
    }
}
