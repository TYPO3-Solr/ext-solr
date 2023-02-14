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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Access;

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
                'expectedToString' => 'c:0',
            ],
            'no_prefix' => [
                'stringRepresentation' => '0',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [0],
                'expectedPageId' => null,
                'expectedToString' => 'c:0',
            ],
            'no_prefix_restricted' => [
                'stringRepresentation' => '1',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [1],
                'expectedPageId' => null,
                'expectedToString' => 'c:1',
            ],
            'no_prefix_multiple' => [
                'stringRepresentation' => '0,1,2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [0, 1, 2],
                'expectedPageId' => null,
                'expectedToString' => 'c:0,1,2',
            ],
            'simpleContent' => [
                'stringRepresentation' => 'c:0',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [0],
                'expectedPageId' => null,
                'expectedToString' => 'c:0',
            ],
            'contentWithPermissionContent' => [
                'stringRepresentation' => 'c:1,2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [1, 2],
                'expectedPageId' => null,
                'expectedToString' => 'c:1,2',
            ],
            'record' => [
                'stringRepresentation' => 'r:1,2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_RECORD,
                'expectedGroups' => [1, 2],
                'expectedPageId' => null,
                'expectedToString' => 'r:1,2',
            ],
            'page' => [
                'stringRepresentation' => '4711:0',
                'expectedType' => RootlineElement::ELEMENT_TYPE_PAGE,
                'expectedGroups' => [0],
                'expectedPageId' => 4711,
                'expectedToString' => '4711:0',
            ],
            'pageList' => [
                'stringRepresentation' => '4711:1,2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_PAGE,
                'expectedGroups' => [1, 2],
                'expectedPageId' => 4711,
                'expectedToString' => '4711:1,2',
            ],
            'minusTwo' => [
                'stringRepresentation' => 'c:-2',
                'expectedType' => RootlineElement::ELEMENT_TYPE_CONTENT,
                'expectedGroups' => [-2],
                'expectedPageId' => null,
                'expectedToString' => 'c:-2',
            ],
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

        self::assertSame($expectedType, $rootLine->getType(), 'Unexpected type after parsing the RootlineElement');
        self::assertSame($expectedGroups, $rootLine->getGroups(), 'Unexpected groups after parsing the RootlineElement');
        self::assertSame($expectedPageId, $rootLine->getPageId(), 'Unexpected pageId after parsing the RootlineElement field');

        // most of the times the to string value is the same
        self::assertSame($expectedToString, (string)$rootLine, 'Conversion to string is not returning expected result');
    }
}
