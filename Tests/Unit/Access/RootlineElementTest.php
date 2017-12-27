<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Access;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Access\RootlineElement;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase to verify the functionality of the RootlineElement
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RootlineElementTest extends UnitTest
{
    /**
     * @return array
     */
    public function validRootLineRePresentations()
    {
        return [
            'empty' => [
                'stringRepresentation' => '',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [0],
                'expectedPageId' => null,
                'expectedToString' => 'c:0'
            ],
            'no_prefix' => [
                'stringRepresentation' => '0',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [0],
                'expectedPageId' => null,
                'expectedToString' => 'c:0'
            ],
            'no_prefix_restricted' => [
                'stringRepresentation' => '1',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [1],
                'expectedPageId' => null,
                'expectedToString' => 'c:1'
            ],
            'no_prefix_multiple' => [
                'stringRepresentation' => '0,1,2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [0,1,2],
                'expectedPageId' => null,
                'expectedToString' => 'c:0,1,2'
            ],
            'simpleContent' => [
                'stringRepresentation' => 'c:0',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [0],
                'expectedPageId' => null,
                'expectedToString' => 'c:0'
            ],
            'contentWithPermissionContent' => [
                'stringRepresentation' => 'c:1,2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [1,2],
                'expectedPageId' => null,
                'expectedToString' => 'c:1,2'
            ],
            'record' => [
                'stringRepresentation' => 'r:1,2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_RECORD,
                'expectedGroups' => [1,2],
                'expectedPageId' => null,
                'expectedToString' => 'r:1,2'
            ],
            'page' => [
                'stringRepresentation' => '4711:0',
                'expectedType' => RootlineElement::ELEMENT_TYPE_PAGE,
                'expectedGroups' => [0],
                'expectedPageId' => 4711,
                'expectedToString' => '4711:0'
            ],
            'pageList' => [
                'stringRepresentation' => '4711:1,2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_PAGE,
                'expectedGroups' => [1,2],
                'expectedPageId' => 4711,
                'expectedToString' => '4711:1,2'
            ],
            'minusTwo' => [
                'stringRepresentation' => 'c:-2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [-2],
                'expectedPageId' => null,
                'expectedToString' => 'c:-2'
            ]
        ];
    }

    /**
     * @param string $stringRepresentation
     * @param int $expectedType
     * @param array $expectedGroups
     * @param int|null $expectedPageId
     * @param string $expectedToString
     * @dataProvider validRootLineRePresentations
     *
     * @test
     */
    public function canParse($stringRepresentation, $expectedType, $expectedGroups, $expectedPageId, $expectedToString)
    {
        $rootLine = new RootlineElement($stringRepresentation);

        $this->assertSame($expectedType, $rootLine->getType(), 'Unexpected type after parsing the RootlineElement');
        $this->assertSame($expectedGroups, $rootLine->getGroups(), 'Unexpected groups after parsing the RootlineElement');
        $this->assertSame($expectedPageId, $rootLine->getPageId(), 'Unexpected pageId after parsing the RootlineElement field');

        // most of the times the to string value is the same
        $this->assertSame($expectedToString, (string) $rootLine, 'Conversion to string is not returning expected result');
    }
}
