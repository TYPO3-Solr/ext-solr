<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Query;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests the ApacheSolrForTypo3\Solr\Query class
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class QueryTest extends UnitTest
{

    /**
     * @return array
     */
    public function escapeQueryDataProvider()
    {
        return array(
            'empty' => array('input' => '', 'expectedOutput' => ''),
            'simple' => array('input' => 'foo', 'expectedOutput' => 'foo'),
            'single quoted word' => array('input' => '"world"', 'expectedOutput' => '"world"'),
            'simple quoted phrase' => array('input' => '"hello world"', 'expectedOutput' => '"hello world"'),
            'simple quoted phrase with ~' => array('input' => '"hello world~"', 'expectedOutput' => '"hello world~"'),
            'simple phrase with ~' => array('input' => 'hello world~', 'expectedOutput' => 'hello world\~'),
            'single quote' =>  array('input' => '20" monitor', 'expectedOutput' => '20\" monitor'),
            'rounded brackets many words' => array('input' => 'hello (world)', 'expectedOutput' => 'hello \(world\)'),
            'rounded brackets one word' => array('input' => '(world)', 'expectedOutput' => '\(world\)'),
            'plus character is kept' => array('input' => 'foo +bar -world', 'expectedOutput' => 'foo +bar -world'),
            '&& character is kept' => array('input' => 'hello && world', 'expectedOutput' => 'hello && world'),
            '! character is kept' => array('input' => 'hello !world', 'expectedOutput' => 'hello !world'),
            '* character is kept' => array('input' => 'hello *world', 'expectedOutput' => 'hello *world'),
            '? character is kept' => array('input' => 'hello ?world', 'expectedOutput' => 'hello ?world'),
            'ö character is kept' => array('input' => 'schöner tag', 'expectedOutput' => 'schöner tag'),
            'numeric is kept' => array('input' => 42, 'expectedOutput' => 42),
            'combined quoted phrase' => array('input' => '"hello world" or planet', 'expectedOutput' => '"hello world" or planet'),
            'two combined quoted phrases' => array('input' => '"hello world" or "hello planet"', 'expectedOutput' => '"hello world" or "hello planet"'),
            'combined quoted phrase mixed with escape character' => array('input' => '"hello world" or (planet)', 'expectedOutput' => '"hello world" or \(planet\)')
        );
    }

    /**
     * @dataProvider escapeQueryDataProvider
     * @test
     */
    public function canEscapeAsExpected($input, $expectedOutput)
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test');

        $output = $query->escape($input);
        $this->assertSame($expectedOutput, $output, 'Query was not escaped as expected');
    }
}
