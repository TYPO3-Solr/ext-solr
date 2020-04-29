<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\ContentObject;

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

use ApacheSolrForTypo3\Solr\ContentObject\Classification;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Tests for the SOLR_CLASSIFICATION cObj.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ClassificationTest extends UnitTest
{
    /**
     * @var ContentObjectRenderer
     */
    protected $contentObject;

    /**
     * @test
     */
    public function canClassifyContent()
    {
        $GLOBALS['TSFE']->cObjectDepthCounter = 2;
        $content = 'i like TYPO3 more then joomla';
        $this->contentObject->start(['content' => $content]);

        $configuration = [
            'field' => 'content',
            'classes.' => [
                [
                    'patterns' => 'TYPO3, joomla, core media',
                    'class' => 'cms'
                ],
                [
                    'patterns' => 'php, java, go, groovy',
                    'class' => 'programming_language'
                ]
            ]
        ];

        $actual = $this->contentObject->cObjGetSingle(Classification::CONTENT_OBJECT_NAME, $configuration);
        $expected = serialize(['cms']);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public function excludePatternDataProvider()
    {
        return [
            'excludePatternShouldLeadToUnassignedClass' => [
                'input' => 'from the beach i can see the waves',
                'expectedOutput' => []
            ],
            'noMatchingExlucePatternLeadsToExpectedClass' => [
                'input' => 'i saw a shark between the waves',
                'expectedOutput' => ['ocean']
            ]
        ];
    }

    /**
     * @dataProvider excludePatternDataProvider
     * @test
     */
    public function canExcludePatterns($input, $expectedOutput)
    {
        $GLOBALS['TSFE']->cObjectDepthCounter = 2;
        $this->contentObject->start(['content' => $input]);

        $configuration = [
            'field' => 'content',
            'classes.' => [
                [
                    'matchPatterns' => 'waves',
                    'unmatchPatterns' => 'beach',
                    'class' => 'ocean'
                ]
            ]
        ];

        $actual = $this->contentObject->cObjGetSingle(Classification::CONTENT_OBJECT_NAME, $configuration);
        $expected = serialize($expectedOutput);
        $this->assertEquals($expected, $actual);
    }

    protected function setUp()
    {
        // fake a registered hook
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][Classification::CONTENT_OBJECT_NAME] = Classification::class;

        $GLOBALS['TSFE'] = $this->getDumbMock(TypoScriptFrontendController::class);

        $this->contentObject = $this->getMockBuilder(ContentObjectRenderer::class)
            ->setMethods(['getResourceFactory', 'getEnvironmentVariable'])
            ->setConstructorArgs([$GLOBALS['TSFE']])->getMock();
    }

    protected function tearDown()
    {
        unset($GLOBALS['TSFE']);
    }
}
