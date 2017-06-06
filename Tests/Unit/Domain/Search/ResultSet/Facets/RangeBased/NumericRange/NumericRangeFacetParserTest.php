<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRange;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRangeFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRangeFacetParser;
use ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\AbstractFacetParserTest;

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
     * @return string
     */
    protected function getPageIdFacetConfiguration($start = -100, $end = 100, $gap = 2)
    {
        return [
            'myPids.' => [
                'type' => 'numericRange',
                'label' => 'Pids',
                'field' => 'pid',
                'numericRange.' => [
                    'start' => (int) $start,
                    'end' => (int) $end,
                    'gap' => (int) $gap

                ]
            ]
        ];
    }

    /**
     * Returns the numeric range facet
     *
     * @param array $facetConfiguration
     * @param array $filters
     * @param string $facetName
     * @return NumericRangeFacet
     */
    protected function getNumericRangeFacet($facetConfiguration, $filters, $facetName)
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_numericRange_facet.json',
            $facetConfiguration,
            $filters,
            $facetName
        );

        /** @var $parser NumericRangeFacetParser */
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

        $this->assertInstanceOf(NumericRangeFacet::class, $facet);
        $this->assertSame($facet->getConfiguration(), $facetConfiguration['myPids.'], 'Configuration was not passed to new facets');
        $this->assertTrue($facet->getIsUsed());

        $this->assertEquals('10-98', $facet->getRange()->getLabel());
        $this->assertEquals(25, $facet->getRange()->getDocumentCount());
        $this->assertCount(4, $facet->getRange()->getRangeCounts(), 'We expected that there are four count items attached');

        $this->assertSame($facet->getRange()->getEndInResponse(), 100);
        $this->assertSame($facet->getRange()->getStartInResponse(), -100);
        $this->assertSame($facet->getRange()->getGap(), 2);
        $this->assertSame((int) $facet->getRange()->getStartRequested(), 10);
        $this->assertSame((int) $facet->getRange()->getEndRequested(), 98);
    }

    /**
     * Test the parsing of the active range values
     *
     * @dataProvider canParseActiveFacetValuesProvider
     * @param int $startRequested
     * @param int $endRequested
     * @test
     */
    public function canParseActiveFacetValues($startRequested, $endRequested)
    {
        $facetConfiguration = $this->getPageIdFacetConfiguration();
        $facet = $this->getNumericRangeFacet($facetConfiguration, ['myPids:' . $startRequested . '-' . $endRequested], 'myPids');

        $this->assertSame((int) $facet->getRange()->getStartRequested(), $startRequested);
        $this->assertSame((int) $facet->getRange()->getEndRequested(), $endRequested);
    }

    /**
     * Data provider for testing the parsing of the active range values
     *
     * @return array
     */
    public function canParseActiveFacetValuesProvider()
    {
        return [
            [
                'startRequested' => 10,
                'endRequested' => 98
            ],
            [
                'startRequested' => -10,
                'endRequested' => 98
            ],
            [
                'startRequested' => -50,
                'endRequested' => -1
            ]
        ];
    }
}
