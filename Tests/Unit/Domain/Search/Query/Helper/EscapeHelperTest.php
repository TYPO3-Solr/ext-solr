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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Query\Helper;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\EscapeService;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use Traversable;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class EscapeHelperTest extends SetUpUnitTestCase
{
    public static function escapeQueryDataProvider(): Traversable
    {
        yield 'empty' => ['input' => '', 'expectedOutput' => ''];
        yield 'simple' => ['input' => 'foo', 'expectedOutput' => 'foo'];
        yield 'single quoted word' => ['input' => '"world"', 'expectedOutput' => '"world"'];
        yield 'simple quoted phrase' => ['input' => '"hello world"', 'expectedOutput' => '"hello world"'];
        yield 'simple quoted phrase with ~' => ['input' => '"hello world~"', 'expectedOutput' => '"hello world~"'];
        yield 'simple phrase with ~' => ['input' => 'hello world~', 'expectedOutput' => 'hello world\~'];
        yield 'single quote' =>  ['input' => '20" monitor', 'expectedOutput' => '20\" monitor'];
        yield 'rounded brackets many words' => ['input' => 'hello (world)', 'expectedOutput' => 'hello \(world\)'];
        yield 'rounded brackets one word' => ['input' => '(world)', 'expectedOutput' => '\(world\)'];
        yield 'plus character is kept' => ['input' => 'foo +bar -world', 'expectedOutput' => 'foo +bar -world'];
        yield '&& character is kept' => ['input' => 'hello && world', 'expectedOutput' => 'hello && world'];
        yield '! character is kept' => ['input' => 'hello !world', 'expectedOutput' => 'hello !world'];
        yield '* character is kept' => ['input' => 'hello *world', 'expectedOutput' => 'hello *world'];
        yield '? character is kept' => ['input' => 'hello ?world', 'expectedOutput' => 'hello ?world'];
        yield 'ö character is kept' => ['input' => 'schöner tag', 'expectedOutput' => 'schöner tag'];
        yield 'numeric is kept' => ['input' => 42, 'expectedOutput' => 42];
        yield 'combined quoted phrase' => ['input' => '"hello world" or planet', 'expectedOutput' => '"hello world" or planet'];
        yield 'two combined quoted phrases' => ['input' => '"hello world" or "hello planet"', 'expectedOutput' => '"hello world" or "hello planet"'];
        yield 'combined quoted phrase mixed with escape character' => ['input' => '"hello world" or (planet)', 'expectedOutput' => '"hello world" or \(planet\)'];
    }

    /**
     * @dataProvider escapeQueryDataProvider
     * @test
     */
    public function canEscapeAsExpected($input, $expectedOutput)
    {
        $escapeHelper = new EscapeService();
        $output = $escapeHelper::escape($input);
        self::assertSame($expectedOutput, $output, 'Query was not escaped as expected');
    }
}
