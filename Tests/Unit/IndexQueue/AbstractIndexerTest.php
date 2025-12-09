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
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Container;
use Traversable;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectFactory;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class AbstractIndexerTest extends SetUpUnitTestCase
{
    protected ContentObjectFactory|MockObject $contentObjectFactoryMock;
    protected ContentObjectRenderer $contentObjectRenderer;
    protected MockObject|AbstractContentObject $contentObjectMock;

    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'] = [];
        parent::setUp();

        $container = new Container();
        $tsfe = $this->getMockBuilder(TypoScriptFrontendController::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new ServerRequest();
        $this->contentObjectRenderer = new ContentObjectRenderer($tsfe);
        $this->contentObjectRenderer->setRequest($request);
        $this->contentObjectFactoryMock = $this->getMockBuilder(ContentObjectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->contentObjectMock = $this->createMock(AbstractContentObject::class);

        $this->contentObjectFactoryMock
            ->expects(self::any())
            ->method('getContentObject')
            ->willReturn($this->contentObjectMock);

        $container->set(ContentObjectFactory::class, $this->contentObjectFactoryMock);
        $container->set(EventDispatcherInterface::class, new NoopEventDispatcher());
        GeneralUtility::setContainer($container);
    }

    /**
     * Test that field values can be resolved
     */
    #[DataProvider('indexingDataProvider')]
    #[Test]
    public function resolveFieldValue(
        array $indexingConfiguration,
        string $solrFieldName,
        array $data,
        array $mockSettings,
        mixed $expectedValue,
    ): void {
        $subject = $this->getAccessibleMock(
            AbstractIndexer::class,
            array_merge(
                [
                    'getTypoScriptConfiguration',
                    'getRequest',
                ],
                $mockSettings[AbstractIndexer::class]['methods'] ?? [],
            ),
        );
        $subject->expects(self::any())
            ->method('getTypoScriptConfiguration')
            ->willReturn(new TypoScriptConfiguration($indexingConfiguration));
        $subject->expects(self::any())
            ->method('getRequest')
            ->willReturn($this->createMock(ServerRequest::class));
        $tsfe = $this->createMock(TypoScriptFrontendController::class);
        $tsfe->id = 0;
        if (is_callable($mockSettings['modsCallable'] ?? null)) {
            $mockSettings['modsCallable']();
        }
        self::assertSame(
            $expectedValue,
            $this->callInaccessibleMethod(
                $subject,
                'resolveFieldValue',
                $indexingConfiguration,
                $solrFieldName,
                $data,
                $tsfe,
                0,
            ),
        );
    }

    public static function indexingDataProvider(): Generator
    {
        yield 'solr field mapped to a string TCA/record column' => [
            'indexingConfiguration' => [
                'solrFieldName_stringS' => 'record_column_string',
            ],
            'solrFieldName' => 'solrFieldName_stringS',
            'data' => [
                'record_column_string' => 'test',
            ],
            'mockSettings' => [],
            'expectedValue' => 'test',
        ];
        yield 'solr field mapped to a int TCA/record column' => [
            'indexingConfiguration' => [
                'solrFieldName_intS' => 'record_column_int',
            ],
            'solrFieldName' => 'solrFieldName_intS',
            'data' => [
                'record_column_int' => 123,
            ],
            'mockSettings' => [],
            'expectedValue' => 123,
        ];
        yield 'solr field mapped to not defined TCA/record column' => [
            'indexingConfiguration' => [
                'solrFieldName_stringS' => 'undefined_record_column',
            ],
            'solrFieldName' => 'solrFieldName_stringS',
            'data' => [],
            'mockSettings' => [],
            'expectedValue' => null,
        ];
        yield 'empty SOLR_RELATION/multiValue value must be resolved to empty string' => [
            'indexingConfiguration' => [
                'solrFieldName_stringM' => 'SOLR_RELATION',
                'solrFieldName_stringM.' => [
                    'localField' => 'fake_field',
                    'multiValue' => 1,
                ],
            ],
            'solrFieldName' => 'solrFieldName_stringM',
            'data' => [],
            'mockSettings' => [],
            'expectedValue' => '',
        ];
        yield 'multiValued field within nested TypoScript with empty value must be resolved to empty string' => [
            'indexingConfiguration' => [
                'nestedTypoScriptDefField_stringM' => 'CASE',
                'nestedTypoScriptDefField_stringM.' => [
                    'key.field' => 'fake_case_variant',
                    '1' => 'SOLR_RELATION',
                    '1.' => [
                        'localField' => 'fake_field',
                        'multiValue' => 1,
                    ],
                ],
            ],
            'solrFieldName' => 'nestedTypoScriptDefField_stringM',
            'data' => [
                'fake_case_variant' => 1,
            ],
            'mockSettings' => [
                'modsCallable' => (static function (): void {}),
            ],
            'expectedValue' => '',
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
