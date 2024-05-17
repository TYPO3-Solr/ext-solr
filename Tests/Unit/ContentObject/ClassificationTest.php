<?php

declare(strict_types=1);

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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

/**
 * Tests for the SOLR_CLASSIFICATION cObj.
 */
class ClassificationTest extends SetUpContentObject
{
    protected function getTestableContentObjectClassName(): string
    {
        return Classification::class;
    }

    #[Test]
    public function canClassifyContent(): void
    {
        $content = 'i like TYPO3 more then joomla';
        $this->contentObjectRenderer->start(['content' => $content]);

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

        $actual = $this->contentObjectRenderer->cObjGetSingle(Classification::CONTENT_OBJECT_NAME, $configuration);
        $expected = serialize(['cms']);
        self::assertEquals($expected, $actual);
    }

    public static function excludePatternDataProvider(): Traversable
    {
        yield 'excludePatternShouldLeadToUnassignedClass' => [
            'input' => 'from the beach i can see the waves',
            'expectedOutput' => [],
        ];
        yield 'noMatchingExlucePatternLeadsToExpectedClass' => [
            'input' => 'i saw a shark between the waves',
            'expectedOutput' => ['ocean'],
        ];
    }

    #[DataProvider('excludePatternDataProvider')]
    #[Test]
    public function canExcludePatterns($input, $expectedOutput): void
    {
        $this->contentObjectRenderer->start(['content' => $input]);

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

        $actual = $this->contentObjectRenderer->cObjGetSingle(Classification::CONTENT_OBJECT_NAME, $configuration);
        $expected = serialize($expectedOutput);
        self::assertEquals($expected, $actual);
    }
}
