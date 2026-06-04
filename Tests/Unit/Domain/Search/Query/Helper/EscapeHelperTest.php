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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

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
        yield 'lone asterisk match-all stays literal' => ['input' => '*', 'expectedOutput' => '*'];
        yield '*:* match-all gets colon escaped' => ['input' => '*:*', 'expectedOutput' => '*\:*'];
        yield '? character is kept' => ['input' => 'hello ?world', 'expectedOutput' => 'hello ?world'];
        yield 'ö character is kept' => ['input' => 'schöner tag', 'expectedOutput' => 'schöner tag'];
        yield 'numeric is kept' => ['input' => 42, 'expectedOutput' => 42];
        yield 'combined quoted phrase' => ['input' => '"hello world" or planet', 'expectedOutput' => '"hello world" or planet'];
        yield 'two combined quoted phrases' => ['input' => '"hello world" or "hello planet"', 'expectedOutput' => '"hello world" or "hello planet"'];
        yield 'combined quoted phrase mixed with escape character' => ['input' => '"hello world" or (planet)', 'expectedOutput' => '"hello world" or \(planet\)'];
    }

    #[DataProvider('escapeQueryDataProvider')]
    #[Test]
    public function escapesOnlySelectorAndRangeCharactersWhenOperatorSyntaxIsAllowed($input, $expectedOutput): void
    {
        self::assertSame(
            $expectedOutput,
            EscapeService::escape($input, true),
            'Legacy-mode escape (allowOperatorSyntax=true) did not produce expected output',
        );
    }

    #[DataProvider('escapeQueryDataProvider')]
    #[Test]
    public function escapeDefaultMirrorsAllowedOperatorSyntaxMode($input, $expectedOutput): void
    {
        self::assertSame(
            $expectedOutput,
            EscapeService::escape($input),
            'Default escape() call must behave as legacy mode (allowOperatorSyntax=true)',
        );
    }

    public static function escapeQueryWithoutOperatorSyntaxDataProvider(): Traversable
    {
        yield 'empty stays empty' => ['input' => '', 'expectedOutput' => ''];
        yield 'plain word stays plain' => ['input' => 'foo', 'expectedOutput' => 'foo'];
        yield 'numeric is kept' => ['input' => 42, 'expectedOutput' => 42];
        yield 'whitespace is preserved literally' => ['input' => 'foo bar baz', 'expectedOutput' => 'foo bar baz'];
        yield 'umlaut is preserved' => ['input' => 'schöner tag', 'expectedOutput' => 'schöner tag'];
        yield 'plus operator passes through (required term)' => ['input' => '+foo', 'expectedOutput' => '+foo'];
        yield 'minus operator passes through (prohibited term)' => ['input' => '-foo', 'expectedOutput' => '-foo'];
        yield 'bang operator passes through (NOT)' => ['input' => '!foo', 'expectedOutput' => '!foo'];
        yield 'asterisk wildcard passes through (prefix search)' => ['input' => 'foo*', 'expectedOutput' => 'foo*'];
        yield 'lone asterisk match-all stays literal' => ['input' => '*', 'expectedOutput' => '*'];
        yield '*:* match-all has only colon escaped' => ['input' => '*:*', 'expectedOutput' => '*\:*'];
        yield 'question mark wildcard passes through (single-char)' => ['input' => 'foo?', 'expectedOutput' => 'foo?'];
        yield 'double ampersand is escaped char by char' => ['input' => 'a && b', 'expectedOutput' => 'a \&\& b'];
        yield 'double pipe is escaped char by char' => ['input' => 'a || b', 'expectedOutput' => 'a \|\| b'];
        yield 'single ampersand is escaped' => ['input' => 'a&b', 'expectedOutput' => 'a\&b'];
        yield 'single pipe is escaped' => ['input' => 'a|b', 'expectedOutput' => 'a\|b'];
        yield 'semicolon is escaped' => ['input' => 'a;b', 'expectedOutput' => 'a\;b'];
        yield 'field selector wildcard has only colon escaped' => ['input' => 'siteHash:*', 'expectedOutput' => 'siteHash\:*'];
        yield 'range query is fully escaped' => ['input' => 'title:[a TO z]', 'expectedOutput' => 'title\:\[a TO z\]'];
        yield 'rounded brackets are escaped' => ['input' => 'hello (world)', 'expectedOutput' => 'hello \(world\)'];
        yield 'tilde is escaped' => ['input' => 'foo~2', 'expectedOutput' => 'foo\~2'];
        yield 'backslash is escaped' => ['input' => 'a\b', 'expectedOutput' => 'a\\\\b'];
        yield 'slash is escaped' => ['input' => 'a/b', 'expectedOutput' => 'a\/b'];
        yield 'quoted phrase keeps inner operator literal' => ['input' => '"foo *bar"', 'expectedOutput' => '"foo *bar"'];
    }

    #[DataProvider('escapeQueryWithoutOperatorSyntaxDataProvider')]
    #[Test]
    public function escapesEveryLuceneSpecialCharacterWhenOperatorSyntaxIsDisallowed($input, $expectedOutput): void
    {
        $output = EscapeService::escape($input, false);
        self::assertSame($expectedOutput, $output, 'Strict-mode escape did not produce expected output');
    }
}
