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
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class EscapeHelperTest extends UnitTest
{

    /**
     * @return array
     */
    public function escapeQueryDataProvider()
    {
        return [
            'empty' => ['input' => '', 'expectedOutput' => ''],
            'simple' => ['input' => 'foo', 'expectedOutput' => 'foo'],
            'single quoted word' => ['input' => '"world"', 'expectedOutput' => '"world"'],
            'simple quoted phrase' => ['input' => '"hello world"', 'expectedOutput' => '"hello world"'],
            'simple quoted phrase with ~' => ['input' => '"hello world~"', 'expectedOutput' => '"hello world~"'],
            'simple phrase with ~' => ['input' => 'hello world~', 'expectedOutput' => 'hello world\~'],
            'single quote' =>  ['input' => '20" monitor', 'expectedOutput' => '20\" monitor'],
            'rounded brackets many words' => ['input' => 'hello (world)', 'expectedOutput' => 'hello \(world\)'],
            'rounded brackets one word' => ['input' => '(world)', 'expectedOutput' => '\(world\)'],
            'plus character is kept' => ['input' => 'foo +bar -world', 'expectedOutput' => 'foo +bar -world'],
            '&& character is kept' => ['input' => 'hello && world', 'expectedOutput' => 'hello && world'],
            '! character is kept' => ['input' => 'hello !world', 'expectedOutput' => 'hello !world'],
            '* character is kept' => ['input' => 'hello *world', 'expectedOutput' => 'hello *world'],
            '? character is kept' => ['input' => 'hello ?world', 'expectedOutput' => 'hello ?world'],
            'ö character is kept' => ['input' => 'schöner tag', 'expectedOutput' => 'schöner tag'],
            'numeric is kept' => ['input' => 42, 'expectedOutput' => 42],
            'combined quoted phrase' => ['input' => '"hello world" or planet', 'expectedOutput' => '"hello world" or planet'],
            'two combined quoted phrases' => ['input' => '"hello world" or "hello planet"', 'expectedOutput' => '"hello world" or "hello planet"'],
            'combined quoted phrase mixed with escape character' => ['input' => '"hello world" or (planet)', 'expectedOutput' => '"hello world" or \(planet\)'],
        ];
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
