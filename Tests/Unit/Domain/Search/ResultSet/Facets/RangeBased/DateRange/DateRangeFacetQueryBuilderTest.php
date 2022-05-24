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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangeFacetQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for the dateRange queryBuilder
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DateRangeFacetQueryBuilderTest extends UnitTest
{

    /**
     * @test
     */
    public function canBuild()
    {
        $fakeFacetConfiguration = [
            'field' => 'created',
            'keepAllOptionsOnSelection' => 1,
            'dateRange.' => ['start' => 'NOW/DAY-2YEAR', 'end' => 'NOW/DAY+2YEAR', 'gap' => '+1DAY'],
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('testFacet')->willReturn(
            $fakeFacetConfiguration
        );

        $builder = new DateRangeFacetQueryBuilder();
        $facetParameters = $builder->build('testFacet', $configurationMock);

        self::assertSame($facetParameters['facet.range'][0], '{!ex=created}created', 'Could not apply keepAllOptionsOnSelection');
        self::assertSame($facetParameters['f.created.facet.range.start'], 'NOW/DAY-2YEAR', 'Could not build range.start as expected');
        self::assertSame($facetParameters['f.created.facet.range.end'], 'NOW/DAY+2YEAR', 'Could not build range.end as expected');
        self::assertSame($facetParameters['f.created.facet.range.gap'], '+1DAY', 'Could not build range.gap as epxected');
    }
}
