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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ContentObject;

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
                    'class' => 'cms',
                ],
                [
                    'patterns' => 'php, java, go, groovy',
                    'class' => 'programming_language',
                ],
            ],
        ];

        $actual = $this->contentObject->cObjGetSingle(Classification::CONTENT_OBJECT_NAME, $configuration);
        $expected = serialize(['cms']);
        self::assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public function excludePatternDataProvider()
    {
        return [
            'excludePatternShouldLeadToUnassignedClass' => [
                'input' => 'from the beach i can see the waves',
                'expectedOutput' => [],
            ],
            'noMatchingExlucePatternLeadsToExpectedClass' => [
                'input' => 'i saw a shark between the waves',
                'expectedOutput' => ['ocean'],
            ],
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
                    'class' => 'ocean',
                ],
            ],
        ];

        $actual = $this->contentObject->cObjGetSingle(Classification::CONTENT_OBJECT_NAME, $configuration);
        $expected = serialize($expectedOutput);
        self::assertEquals($expected, $actual);
    }

    protected function setUp(): void
    {
        // fake a registered hook
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][Classification::CONTENT_OBJECT_NAME] = Classification::class;

        $GLOBALS['TSFE'] = $this->getDumbMock(TypoScriptFrontendController::class);

        $this->contentObject = $this->getMockBuilder(ContentObjectRenderer::class)
            ->onlyMethods(['getResourceFactory', 'getEnvironmentVariable', 'getRequest'])
            ->setConstructorArgs([$GLOBALS['TSFE']])->getMock();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TSFE']);
        parent::tearDown();
    }
}
