<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRangeFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRangeFacetParser;
use ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\AbstractFacetParserTest;

/**
 * Class DateRangeFacetParserTest
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class NumericRangeFacetParserTest extends AbstractFacetParserTest
{
    /**
     * Returns a basic facet configuration
     *
     * @param int $start
     * @param int $end
     * @param int $gap
     * @return array[]
     */
    protected function getPageIdFacetConfiguration($start = -100.0, $end = 100.0, $gap = '2'): array
    {
        return [
            'myPids.' => [
                'type' => 'numericRange',
                'label' => 'Pids',
                'field' => 'pid',
                'numericRange.' => [
                    'start' => (int)$start,
                    'end' => (int)$end,
                    'gap' => (int)$gap,

                ],
            ],
        ];
    }

    /**
     * Returns the numeric range facet
     *
     * @param array $facetConfiguration
     * @param array $filters
     * @param string $facetName
     * @return AbstractFacet|NumericRangeFacet|null
     */
    protected function getNumericRangeFacet(
        array $facetConfiguration,
        array $filters,
        string $facetName
    ): ?AbstractFacet {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_numericRange_facet.json',
            $facetConfiguration,
            $filters
        );

        /* @var NumericRangeFacetParser $parser */
        $parser = $this->getInitializedParser(NumericRangeFacetParser::class);
        return $parser->parse($searchResultSet, $facetName, $facetConfiguration[$facetName . '.']);
    }

    /**
     * @test
     */
    public function facetIsCreated()
    {
        $facetConfiguration = $this->getPageIdFacetConfiguration();
        $facet = $this->getNumericRangeFacet($facetConfiguration, ['myPids:10-98'], 'myPids');

        self::assertInstanceOf(NumericRangeFacet::class, $facet);
        self::assertSame(
            $facet->getConfiguration(),
            $facetConfiguration['myPids.'],
            'Configuration was not passed to new facets'
        );
        self::assertTrue($facet->getIsUsed());

        self::assertEquals('10-98', $facet->getRange()->getLabel());
        self::assertEquals(25, $facet->getRange()->getDocumentCount());
        self::assertCount(
            4,
            $facet->getRange()->getRangeCounts(),
            'We expected that there are four count items attached'
        );

        self::assertSame($facet->getRange()->getEndInResponse(), 100.0);
        self::assertSame($facet->getRange()->getStartInResponse(), -100.0);
        self::assertSame($facet->getRange()->getGap(), '2');
        self::assertSame($facet->getRange()->getStartRequested(), 10.0);
        self::assertSame($facet->getRange()->getEndRequested(), 98.0);
    }

    /**
     * Test the parsing of the active range values
     *
     * @dataProvider canParseActiveFacetValuesProvider
     * @param int $startRequested
     * @param int $endRequested
     * @test
     */
    public function canParseActiveFacetValues(int $startRequested, int $endRequested): void
    {
        $facetConfiguration = $this->getPageIdFacetConfiguration();
        $facet = $this->getNumericRangeFacet(
            $facetConfiguration,
            ['myPids:' . $startRequested . '-' . $endRequested],
            'myPids'
        );

        self::assertSame((int)$facet->getRange()->getStartRequested(), $startRequested);
        self::assertSame((int)$facet->getRange()->getEndRequested(), $endRequested);
    }

    /**
     * Data provider for testing the parsing of the active range values
     *
     * @return array
     */
    public function canParseActiveFacetValuesProvider(): array
    {
        return [
            [
                'startRequested' => 10,
                'endRequested' => 98,
            ],
            [
                'startRequested' => -10,
                'endRequested' => 98,
            ],
            [
                'startRequested' => -50,
                'endRequested' => -1,
            ],
        ];
    }
}
