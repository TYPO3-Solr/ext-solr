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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

use ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use UnexpectedValueException;

class AbstractIndexerTest extends SetUpUnitTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'] = [];
        parent::setUp();
    }

    #[Test]
    public function isSerializedValueCanHandleCustomContentElements(): void
    {
        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION',
            'categories_stringM' => 'SOLR_RELATION',
            'categories_stringM.' => [
                'multiValue' => true,
            ],
            'csv_stringM' => 'SOLR_MULTIVALUE',
            'category_stringM' => 'SOLR_RELATION',
        ];

        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM'), 'Response of SOLR_CLASSIFICATION is expected to be serialized');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'csv_stringM'), 'Response of SOLR_MULTIVALUE is expected to be serialized');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'categories_stringM'), 'Response of SOLR_MULTIVALUE is expected to be serialized');

        self::assertFalse(AbstractIndexer::isSerializedValue($indexingConfiguration, 'category_stringM'), 'Non configured fields should allways be unserialized');
        self::assertFalse(AbstractIndexer::isSerializedValue($indexingConfiguration, 'notConfigured_stringM'), 'Non configured fields should allways be unserialized');
    }

    #[Test]
    public function isSerializedValueCanHandleCustomInvalidSerializedValueDetector(): void
    {
        // register invalid detector
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'][] = InvalidSerializedValueDetector::class;
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/.*InvalidSerializedValueDetector must implement interface.*/');

        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION',
        ];

        // when an invalid detector is registered we expect that an exception is thrown
        AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM');
    }

    #[Test]
    public function isSerializedValueCanHandleCustomValidSerializedValueDetector(): void
    {
        // register invalid detector
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'][] = ValidSerializedValueDetector::class;

        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION',
            'categories_stringM' => 'SOLR_RELATION',
            'categories_stringM.' => [
                'multiValue' => true,
            ],
            'csv_stringM' => 'SOLR_MULTIVALUE',
            'category_stringM' => 'SOLR_RELATION',
        ];
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'csv_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'categories_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'category_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'notConfigured_stringM'), 'Every value should be treated as serialized by custom detector');
    }

    /**
     * Test that field values can be resolved
     */
    #[DataProvider('indexingDataProvider')]
    #[Test]
    public function resolveFieldValue(array $indexingConfiguration, string $solrFieldName, array $data, $expectedValue): void
    {
        $subject = new class () extends AbstractIndexer {};
        $tsfe = $this->createMock(TypoScriptFrontendController::class);
        self::assertEquals(
            $this->callInaccessibleMethod(
                $subject,
                'resolveFieldValue',
                $indexingConfiguration,
                $solrFieldName,
                $data,
                $tsfe,
                0,
            ),
            $expectedValue
        );
    }

    public static function indexingDataProvider(): Generator
    {
        yield 'solr field defined as string' => [
            ['solrFieldName_stringS' => 'solrFieldName'],
            'solrFieldName_stringS',
            ['solrFieldName' => 'test'],
            'test',
        ];
        yield 'solr field defined as int' => [
            ['solrFieldName_intS' => 'solrFieldName'],
            'solrFieldName_intS',
            ['solrFieldName' => 123],
            123,
        ];
        yield 'solr field not defined' => [
            ['solrFieldName_stringS' => 'solrFieldName'],
            'solrFieldName_stringS',
            [],
            null,
        ];
    }

    #[Test]
    #[DataProvider('vectorSearchDataProvider')]
    public function canEnrichVectorContent(
        bool $vectorSearchEnabled,
        ?string $existingVectorContent = null,
    ): void {
        $subject = $this->getAccessibleMock(AbstractIndexer::class, ['getTypoScriptConfiguration']);

        $configuration = new TypoScriptConfiguration([
            'plugin.' => [
                'tx_solr.' => [
                    'search.' => [
                        'query.' => [
                            'type' => $vectorSearchEnabled ? 1 : 0,
                        ],
                    ],
                ],
            ],
        ]);
        $subject->expects(self::once())->method('getTypoScriptConfiguration')->willReturn($configuration);

        $tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $tsfeMock->id = 123;

        $data = [];
        $indexingConfiguration = [];
        if ($existingVectorContent !== null) {
            $data['vectorFromField'] = $existingVectorContent;
            $indexingConfiguration = ['vectorContent' => 'vectorFromField'];
        }
        $document = new Document(['content' => 'dummy content']);
        $subject->_call(
            'addDocumentFieldsFromTyposcript',
            $document,
            $indexingConfiguration,
            $data,
            $tsfeMock,
            0
        );

        if ($vectorSearchEnabled) {
            self::assertEquals(
                $document['vectorContent'] ?? '',
                $existingVectorContent === null ? 'dummy content' : $existingVectorContent,
            );
        } else {
            self::assertArrayNotHasKey('vectorContent', $document->getFields());
        }
    }

    public static function vectorSearchDataProvider(): Traversable
    {
        yield 'vector search disabled' => [ false ];
        yield 'vector search enabled' => [ true ];
        yield 'vector search enabled, with content' => [ true, 'vector content from field'];
    }
}
