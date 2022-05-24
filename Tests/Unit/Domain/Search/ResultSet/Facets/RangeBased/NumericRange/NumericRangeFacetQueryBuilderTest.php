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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRangeFacetQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for the numericRange queryBuilder
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class NumericRangeFacetQueryBuilderTest extends UnitTest
{

    /**
     * @test
     */
    public function canBuild()
    {
        $fakeFacetConfiguration = [
            'field' => 'price',
            'keepAllOptionsOnSelection' => 1,
            'numericRange.' => ['start' => 1, 'end' => 100, 'gap' => 5],
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('testFacet')->willReturn(
            $fakeFacetConfiguration
        );

        $builder = new NumericRangeFacetQueryBuilder();
        $facetParameters = $builder->build('testFacet', $configurationMock);

        self::assertSame($facetParameters['facet.range'][0], '{!ex=price}price', 'Could not apply keepAllOptionsOnSelection');
        self::assertSame($facetParameters['f.price.facet.range.start'], 1, 'Could not build range.start as expected');
        self::assertSame($facetParameters['f.price.facet.range.end'], 100, 'Could not build range.end as expected');
        self::assertSame($facetParameters['f.price.facet.range.gap'], 5, 'Could not build range.gap as epxected');
    }
}
