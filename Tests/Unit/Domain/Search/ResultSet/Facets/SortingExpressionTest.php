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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

/**
 * Unit test for the SortingExpression
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Jens Jacobsen <jens.jacobsen@ueberbit.de>
 */
class SortingExpressionTest extends SetUpUnitTestCase
{
    public static function canBuildSortExpressionDataProvider(): Traversable
    {
        yield 'byAlpha' => ['sorting' => 'alpha', 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'];
        yield 'byLex' => ['sorting' => 'lex', 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'];
        yield 'byOne' => ['sorting' => 1, 'direction' => '', 'isJson' => false, 'expectedResult' => 'count'];
        yield 'byZero' => ['sorting' => 0, 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'];
        yield 'byTrue' => ['sorting' => true, 'direction' => '', 'isJson' => false, 'expectedResult' => 'count'];
        yield 'byFalse' => ['sorting' => false, 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'];

        yield 'byIndex' => ['sorting' => 'index', 'direction' => '', 'isJson' => false, 'expectedResult' => 'index'];
        yield 'byCount' => ['sorting' => 'count', 'direction' => '', 'isJson' => false, 'expectedResult' => 'count'];

        yield 'byAlphaJson' => ['sorting' => 'alpha', 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'];
        yield 'byAlphaJsonSortAsc' => ['sorting' => 'alpha', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'];
        yield 'byAlphaJsonSortDesc' => ['sorting' => 'alpha', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'];
        yield 'byLexJson' => ['sorting' => 'lex', 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'];
        yield 'byLexJsonSortAsc' => ['sorting' => 'lex', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'];
        yield 'byLexJsonSortDesc' => ['sorting' => 'lex', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'];

        yield 'byOneJson' => ['sorting' => 1, 'direction' => '', 'isJson' => true, 'expectedResult' => 'count'];
        yield 'byOneJsonSortAsc' => ['sorting' => 1, 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'count asc'];
        yield 'byOneJsonSortDesc' => ['sorting' => 1, 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'count desc'];
        yield 'byZeroJson' => ['sorting' => 0, 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'];
        yield 'byZeroJsonSortAsc' => ['sorting' => 0, 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'];
        yield 'byZeroJsonSortDesc' => ['sorting' => 0, 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'];

        yield 'byTrueJson' => ['sorting' => true, 'direction' => '', 'isJson' => true, 'expectedResult' => 'count'];
        yield 'byTrueJsonSortAsc' => ['sorting' => true, 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'count asc'];
        yield 'byTrueJsonSortDesc' => ['sorting' => true, 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'count desc'];
        yield 'byFalseJson' => ['sorting' => false, 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'];
        yield 'byFalseJsonSortAsc' => ['sorting' => false, 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'];
        yield 'byFalseJsonSortDesc' => ['sorting' => false, 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'];

        yield 'byIndexJson' => ['sorting' => 'index', 'direction' => '', 'isJson' => true, 'expectedResult' => 'index'];
        yield 'byIndexJsonSortAsc' => ['sorting' => 'index', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'index asc'];
        yield 'byIndexJsonSortDesc' => ['sorting' => 'index', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'index desc'];

        yield 'byCountJson' => ['sorting' => 'count', 'direction' => '', 'isJson' => true, 'expectedResult' => 'count'];
        yield 'byCountJsonSortAsc' => ['sorting' => 'count', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'count asc'];
        yield 'byCountJsonSortDesc' => ['sorting' => 'count', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'count desc'];

        yield 'byMetricJsonSortAsc' => ['sorting' => 'metrics_newest', 'direction' => 'asc', 'isJson' => true, 'expectedResult' => 'metrics_newest asc'];
        yield 'byMetricJsonSortDesc' => ['sorting' => 'metrics_newest', 'direction' => 'desc', 'isJson' => true, 'expectedResult' => 'metrics_newest desc'];
    }

    /**
     * @param string|int|bool $sorting
     * @param string $direction
     * @param bool $isJson
     * @param string $expectedResult
     */
    #[DataProvider('canBuildSortExpressionDataProvider')]
    #[Test]
    public function canBuildSortExpression($sorting, string $direction, bool $isJson, string $expectedResult): void
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
