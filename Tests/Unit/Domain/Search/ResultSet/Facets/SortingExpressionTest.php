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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets;

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
    public function canBuildSortExpressionDataProvider(): array
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

            'byMetricJsonSortAsc' => ['sorting' => 'metrics_newest', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'metrics_newest asc'],
            'byMetricJsonSortDesc' => ['sorting' => 'metrics_newest', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'metrics_newest desc'],
        ];
    }

    /**
     * @param string|int|bool $sorting
     * @param string $direction
     * @param bool $isJson
     * @param string $expectedResult
     * @dataProvider canBuildSortExpressionDataProvider
     * @test
     */
    public function canBuildSortExpression($sorting, string $direction, bool $isJson, string $expectedResult)
    {
        $expression = new SortingExpression();
        if ($isJson) {
            $result = $expression->getForJsonFacet((string)$sorting, $direction);
        } else {
            $result = $expression->getForFacet($sorting);
        }
        self::assertSame($expectedResult, $result, 'Unexpected expression for solr server');
    }
}
