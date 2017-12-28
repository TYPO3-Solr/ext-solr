<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
 *  (c) 2016 Markus Friedrich <markus.friedrich@dkd.de>
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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
            'dateRange.' => ['start' => 'NOW/DAY-2YEAR', 'end' => 'NOW/DAY+2YEAR', 'gap' => '+1DAY']
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetByName')->with('testFacet')->will(
            $this->returnValue($fakeFacetConfiguration)
        );

        $builder = new DateRangeFacetQueryBuilder();
        $facetParameters = $builder->build('testFacet', $configurationMock);

        $this->assertSame($facetParameters['facet.range'][0], '{!ex=created}created', 'Could not apply keepAllOptionsOnSelection');
        $this->assertSame($facetParameters['f.created.facet.range.start'], 'NOW/DAY-2YEAR', 'Could not build range.start as expected');
        $this->assertSame($facetParameters['f.created.facet.range.end'], 'NOW/DAY+2YEAR', 'Could not build range.end as expected');
        $this->assertSame($facetParameters['f.created.facet.range.gap'], '+1DAY', 'Could not build range.gap as epxected');
    }
}