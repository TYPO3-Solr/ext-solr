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
            'lone asterisk match-all stays literal' => ['input' => '*', 'expectedOutput' => '*'],
            '*:* match-all gets colon escaped' => ['input' => '*:*', 'expectedOutput' => '*\:*'],
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
    public function escapesOnlySelectorAndRangeCharactersWhenOperatorSyntaxIsAllowed($input, $expectedOutput)
    {
        self::assertSame(
            $expectedOutput,
            EscapeService::escape($input, true),
            'Legacy-mode escape (allowOperatorSyntax=true) did not produce expected output'
        );
    }

    /**
     * @dataProvider escapeQueryDataProvider
     * @test
     */
    public function escapeDefaultMirrorsAllowedOperatorSyntaxMode($input, $expectedOutput)
    {
        self::assertSame(
            $expectedOutput,
            EscapeService::escape($input),
            'Default escape() call must behave as legacy mode (allowOperatorSyntax=true)'
        );
    }

    /**
     * @return array
     */
    public function escapeQueryWithoutOperatorSyntaxDataProvider()
    {
        return [
            'empty stays empty' => ['input' => '', 'expectedOutput' => ''],
            'plain word stays plain' => ['input' => 'foo', 'expectedOutput' => 'foo'],
            'numeric is kept' => ['input' => 42, 'expectedOutput' => 42],
            'whitespace is preserved literally' => ['input' => 'foo bar baz', 'expectedOutput' => 'foo bar baz'],
            'umlaut is preserved' => ['input' => 'schöner tag', 'expectedOutput' => 'schöner tag'],
            'plus operator passes through (required term)' => ['input' => '+foo', 'expectedOutput' => '+foo'],
            'minus operator passes through (prohibited term)' => ['input' => '-foo', 'expectedOutput' => '-foo'],
            'bang operator passes through (NOT)' => ['input' => '!foo', 'expectedOutput' => '!foo'],
            'asterisk wildcard passes through (prefix search)' => ['input' => 'foo*', 'expectedOutput' => 'foo*'],
            'lone asterisk match-all stays literal' => ['input' => '*', 'expectedOutput' => '*'],
            '*:* match-all has only colon escaped' => ['input' => '*:*', 'expectedOutput' => '*\:*'],
            'question mark wildcard passes through (single-char)' => ['input' => 'foo?', 'expectedOutput' => 'foo?'],
            'double ampersand is escaped char by char' => ['input' => 'a && b', 'expectedOutput' => 'a \&\& b'],
            'double pipe is escaped char by char' => ['input' => 'a || b', 'expectedOutput' => 'a \|\| b'],
            'single ampersand is escaped' => ['input' => 'a&b', 'expectedOutput' => 'a\&b'],
            'single pipe is escaped' => ['input' => 'a|b', 'expectedOutput' => 'a\|b'],
            'semicolon is escaped' => ['input' => 'a;b', 'expectedOutput' => 'a\;b'],
            'field selector wildcard has only colon escaped' => ['input' => 'siteHash:*', 'expectedOutput' => 'siteHash\:*'],
            'range query is fully escaped' => ['input' => 'title:[a TO z]', 'expectedOutput' => 'title\:\[a TO z\]'],
            'rounded brackets are escaped' => ['input' => 'hello (world)', 'expectedOutput' => 'hello \(world\)'],
            'tilde is escaped' => ['input' => 'foo~2', 'expectedOutput' => 'foo\~2'],
            'backslash is escaped' => ['input' => 'a\b', 'expectedOutput' => 'a\\\\b'],
            'slash is escaped' => ['input' => 'a/b', 'expectedOutput' => 'a\/b'],
            'quoted phrase keeps inner operator literal' => ['input' => '"foo *bar"', 'expectedOutput' => '"foo *bar"'],
        ];
    }

    /**
     * @dataProvider escapeQueryWithoutOperatorSyntaxDataProvider
     * @test
     */
    public function escapesEveryLuceneSpecialCharacterWhenOperatorSyntaxIsDisallowed($input, $expectedOutput)
    {
        $output = EscapeService::escape($input, false);
        self::assertSame($expectedOutput, $output, 'Strict-mode escape did not produce expected output');
    }
}
