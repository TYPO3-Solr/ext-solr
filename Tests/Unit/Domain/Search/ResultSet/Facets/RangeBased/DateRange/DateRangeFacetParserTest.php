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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangeFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangeFacetParser;
use ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\AbstractFacetParserTest;

/**
 * Class DateRangeFacetParserTest
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DateRangeFacetParserTest extends AbstractFacetParserTest
{
    /**
     * @test
     */
    public function facetIsCreated()
    {
        $facetConfiguration = [
            'myCreated.' => [
                'type' => 'dateRange',
                'label' => 'Created',
                'field' => 'created',
            ],
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_dateRange_facet.json',
            $facetConfiguration,
            ['myCreated:201506020000-201706020000']
        );

        /** @var $parser DateRangeFacetParser */
        $parser = $this->getInitializedParser(DateRangeFacetParser::class);

        $facet = $parser->parse($searchResultSet, 'myCreated', $facetConfiguration['myCreated.']);
        self::assertInstanceOf(DateRangeFacet::class, $facet);
        self::assertSame($facet->getConfiguration(), $facetConfiguration['myCreated.'], 'Configuration was not passed to new facets');
        self::assertTrue($facet->getIsUsed());

        self::assertEquals('201506020000-201706020000', $facet->getRange()->getLabel());
        self::assertEquals(32, $facet->getRange()->getDocumentCount());
        self::assertCount(3, $facet->getRange()->getRangeCounts(), 'We expected that there are three count items attached');

        self::assertSame($facet->getRange()->getEndInResponse()->format('Ymd'), '20170602');
        self::assertSame($facet->getRange()->getStartInResponse()->format('Ymd'), '20150602');
    }
}
