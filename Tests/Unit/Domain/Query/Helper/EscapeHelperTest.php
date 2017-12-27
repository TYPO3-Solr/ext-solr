<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Query\Helper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Schmidt
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
            'combined quoted phrase mixed with escape character' => ['input' => '"hello world" or (planet)', 'expectedOutput' => '"hello world" or \(planet\)']
        ];
    }

    /**
     * @dataProvider escapeQueryDataProvider
     * @test
     */
    public function canEscapeAsExpected($input, $expectedOutput)
    {
        $escapeHelper = new EscapeService();
        $output = $escapeHelper->escape($input);
        $this->assertSame($expectedOutput, $output, 'Query was not escaped as expected');
    }

}