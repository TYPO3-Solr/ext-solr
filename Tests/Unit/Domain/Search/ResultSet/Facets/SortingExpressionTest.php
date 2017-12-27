<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\SortingExpression;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Unit test for the SortingExpression
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Jens Jacobsen <jens.jacobsen@ueberbit.de>
 */
class SortingExpressionTest extends UnitTest
{
    public function canBuildSortExpressionDataProvider()
    {
        return [
            'byAlpha' => ['sorting' => 'alpha', 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'],
            'byLex' => ['sorting' => 'lex', 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'],
            'byOne' => ['sorting' => 1, 'direction' => '', 'isJson' => false, 'expectedResult' => 'count'],
            'byZero' => ['sorting' => 0, 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'],
            'byTrue' => ['sorting' => true, 'direction' => '', 'isJson' => false, 'expectedResult' => 'count'],
            'byFalse' => ['sorting' => false, 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'],

            'byIndex' => ['sorting' => 'index', 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'],
            'byCount' => ['sorting' => 'count', 'direction' => '', 'isJson' => false, 'expectedResult' => 'count'],

            'byAlphaJson' => ['sorting' => 'alpha', 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'],
            'byAlphaJsonSortAsc' => ['sorting' => 'alpha', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'],
            'byAlphaJsonSortDesc' => ['sorting' => 'alpha', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'],
            'byLexJson' => ['sorting' => 'lex', 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'],
            'byLexJsonSortAsc' => ['sorting' => 'lex', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'],
            'byLexJsonSortDesc' => ['sorting' => 'lex', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'],

            'byOneJson' => ['sorting' => 1, 'direction' => '', 'isJson' => true, 'expectedResult' => 'count'],
            'byOneJsonSortAsc' => ['sorting' => 1, 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'count asc'],
            'byOneJsonSortDesc' => ['sorting' => 1, 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'count desc'],
            'byZeroJson' => ['sorting' => 0, 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'],
            'byZeroJsonSortAsc' => ['sorting' => 0, 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'],
            'byZeroJsonSortDesc' => ['sorting' => 0, 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'],

            'byTrueJson' => ['sorting' => true, 'direction' => '', 'isJson' => true, 'expectedResult' => 'count'],
            'byTrueJsonSortAsc' => ['sorting' => true, 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'count asc'],
            'byTrueJsonSortDesc' => ['sorting' => true, 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'count desc'],
            'byFalseJson' => ['sorting' => false, 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'],
            'byFalseJsonSortAsc' => ['sorting' => false, 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'],
            'byFalseJsonSortDesc' => ['sorting' => false, 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'],

            'byIndexJson' => ['sorting' => 'index', 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'],
            'byIndexJsonSortAsc' => ['sorting' => 'index', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'],
            'byIndexJsonSortDesc' => ['sorting' => 'index', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'],

            'byCountJson' => ['sorting' => 'count', 'direction' => '', 'isJson' => true, 'expectedResult' => 'count'],
            'byCountJsonSortAsc' => ['sorting' => 'count', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'count asc'],
            'byCountJsonSortDesc' => ['sorting' => 'count', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'count desc'],
        ];
    }

    /**
     * @param string $sorting
     * @param string $direction
     * @param boolean $isJson
     * @param string $expectedResult
     * @dataProvider canBuildSortExpressionDataProvider
     * @test
     */
    public function canBuildSortExpression($sorting, $direction, $isJson, $expectedResult)
    {
        $expression = new SortingExpression();
        if ($isJson) {
            $result = $expression->getForJsonFacet($sorting, $direction);
        } else {
            $result = $expression->getForFacet($sorting);
        }
        $this->assertSame($expectedResult, $result, 'Unexpected expression for solr server');
    }
}
