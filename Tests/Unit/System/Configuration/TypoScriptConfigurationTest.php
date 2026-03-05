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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Configuration;

use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Record;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

/**
 * Testcase to check if the configuration object can be used as expected
 */
class TypoScriptConfigurationTest extends SetUpUnitTestCase
{
    protected TypoScriptConfiguration $configuration;

    protected function setUp(): void
    {
        // Must call parent::setUp() first to register ContentObjectService mock
        parent::setUp();

        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content'] = 'SOLR_CONTENT';
        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content.']['field'] = 'bodytext';
        $this->configuration = new TypoScriptConfiguration($fakeConfigurationArray);
    }

    #[Test]
    public function canGetValueByPath(): void
    {
        $testPath = 'plugin.tx_solr.index.queue.tt_news.fields.content';
        self::assertSame('SOLR_CONTENT', $this->configuration->getValueByPath($testPath), 'Could not get configuration value by path');
    }

    #[Test]
    public function canGetObjectByPath(): void
    {
        $testPath = 'plugin.tx_solr.index.queue.tt_news.fields.content';
        $expectedResult = [
            'content' => 'SOLR_CONTENT',
            'content.' => ['field' => 'bodytext'],
        ];

        self::assertSame($expectedResult, $this->configuration->getObjectByPath($testPath), 'Could not get configuration object by path');
    }

    #[Test]
    public function canShowEvenIfEmptyFallBackToGlobalSetting(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'faceting.' => [
                    'showEmptyFacets' => true,
                    'facets.' => [
                        'color.' => [],
                        'type.' => [
                            'showEvenWhenEmpty' => true,
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $showEmptyType = $configuration->getSearchFacetingShowEmptyFacetsByName('type');
        self::assertTrue($showEmptyType);

        $showEmptyColor = $configuration->getSearchFacetingShowEmptyFacetsByName('color');
        self::assertTrue($showEmptyColor);

        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'faceting.' => [
                    'facets.' => [
                        'color.' => [],
                        'type.' => [
                            'showEvenWhenEmpty' => true,
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $showEmptyType = $configuration->getSearchFacetingShowEmptyFacetsByName('type');
        self::assertTrue($showEmptyType);

        $showEmptyColor = $configuration->getSearchFacetingShowEmptyFacetsByName('color');
        self::assertFalse($showEmptyColor);
    }

    #[Test]
    public function canGetIndexQueueTypeOrFallbackToConfigurationName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages.' => [
                    ],
                    'custom.' => [
                        'type' => 'tx_model_custom',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $customTableExpected = $configuration->getIndexQueueTypeOrFallbackToConfigurationName('pages');
        self::assertSame($customTableExpected, 'pages', 'Can not fallback to configurationName');

        $customTableExpected = $configuration->getIndexQueueTypeOrFallbackToConfigurationName('custom');
        self::assertSame($customTableExpected, 'tx_model_custom', 'Usage of custom table tx_model_custom was expected');
    }

    #[Test]
    public function canGetIndexQueueConfigurationNames(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages' => 1,
                    'pages.' => [
                    ],
                    'custom.' => [
                        'type' => 'tx_model_custom',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $enabledIndexQueueNames = $configuration->getEnabledIndexQueueConfigurationNames();

        self::assertCount(1, $enabledIndexQueueNames, 'Retrieved unexpected amount of index queue configurations');
        self::assertContains('pages', $enabledIndexQueueNames, 'Pages was no enabled index queue configuration');

        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['custom'] = 1;
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $enabledIndexQueueNames = $configuration->getEnabledIndexQueueConfigurationNames();

        self::assertCount(2, $enabledIndexQueueNames, 'Retrieved unexpected amount of index queue configurations');
        self::assertContains('custom', $enabledIndexQueueNames, 'Pages was no enabled index queue configuration');
    }

    #[Test]
    public function canGetIndexQueueConfigurationRecursiveUpdateFields(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages' => 1,
                    'pages.' => [
                        'recursiveUpdateFields' => '',
                    ],
                    'custom.' => [
                        'recursiveUpdateFields' => '',
                    ],
                ],
            ],
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals([], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('pages'));
        self::assertEquals([], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('custom'));

        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages' => 1,
                    'pages.' => [
                    ],
                    'custom.' => [
                    ],
                ],
            ],
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals([], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('pages'));
        self::assertEquals([], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('custom'));

        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages' => 1,
                    'pages.' => [
                        'recursiveUpdateFields' => 'title, subtitle',
                    ],
                    'custom.' => [
                        'recursiveUpdateFields' => 'title, subtitle',
                    ],
                ],
            ],
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals(['title' => 'title', 'subtitle' => 'subtitle'], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('pages'));
        self::assertEquals(['title' => 'title', 'subtitle' => 'subtitle'], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('custom'));
    }

    #[Test]
    public function canGetInitialPagesAdditionalWhereClause(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages' => 1,
                    'pages.' => [
                    ],
                    'custom.' => [
                        'initialPagesAdditionalWhereClause' => '1=1',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        self::assertEquals('', $configuration->getInitialPagesAdditionalWhereClause('pages'));
        self::assertEquals('1=1', $configuration->getInitialPagesAdditionalWhereClause('custom'));
        self::assertEquals('', $configuration->getInitialPagesAdditionalWhereClause('notconfigured'));
    }

    #[Test]
    public function canGetAdditionalWhereClause(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages' => 1,
                    'pages.' => [
                    ],
                    'custom.' => [
                        'additionalWhereClause' => '1=1',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        self::assertEquals('', $configuration->getIndexQueueAdditionalWhereClauseByConfigurationName('pages'));
        self::assertEquals(' AND 1=1', $configuration->getIndexQueueAdditionalWhereClauseByConfigurationName('custom'));
        self::assertEquals('', $configuration->getIndexQueueAdditionalWhereClauseByConfigurationName('notconfigured'));
    }

    #[Test]
    public function canGetIndexQueueConfigurationNamesByTableName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'tx_model_news' => 1,
                    'tx_model_news.' => [
                    ],
                    'custom_one' => 1,
                    'custom_one.' => [
                        'type' => 'tx_model_bar',
                    ],
                    'custom_two' => 1,
                    'custom_two.' => [
                        'type' => 'tx_model_news',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals(['tx_model_news', 'custom_two'], $configuration->getIndexQueueConfigurationNamesByTableName('tx_model_news'));
    }

    #[Test]
    public function canGetIndexQueueInitializerClassByConfigurationName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'tx_model_news' => 1,
                    'tx_model_news.' => [
                    ],
                    'custom_one' => 1,
                    'custom_one.' => [
                        'initialization' => 'CustomInitializer',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals(Record::class, $configuration->getIndexQueueInitializerClassByConfigurationName('tx_model_news'));
        self::assertEquals('CustomInitializer', $configuration->getIndexQueueInitializerClassByConfigurationName('custom_one'));
    }

    #[Test]
    public function canGetIndexQueueClassByConfigurationName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'tx_model_news' => 1,
                    'tx_model_news.' => [
                    ],
                    'custom_one' => 1,
                    'custom_one.' => [
                        'indexQueue' => 'CustomQueue',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals(Queue::class, $configuration->getIndexQueueClassByConfigurationName('tx_model_news'));
        self::assertEquals('CustomQueue', $configuration->getIndexQueueClassByConfigurationName('custom_one'));
    }

    #[Test]
    public function canGetIndexQueueMonitoredTables(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'tx_model_news' => 1,
                    'tx_model_news.' => [
                    ],
                    'custom_one' => 1,
                    'custom_one.' => [
                        'type' => 'tx_model_bar',
                    ],

                    'custom_two' => 1,
                    'custom_two.' => [
                        'type' => 'tx_model_news',
                    ],
                    'pages' => 1,
                    'pages.' => [],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $monitoredTables =  $configuration->getIndexQueueMonitoredTables();
        self::assertEquals(['tx_model_news', 'tx_model_bar', 'pages'], $monitoredTables);
    }

    #[Test]
    public function canGetIndexQueueIsMonitoredTable(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'tx_model_news' => 1,
                    'tx_model_news.' => [
                    ],
                    'custom_one' => 1,
                    'custom_one.' => [
                        'type' => 'tx_model_bar',
                    ],

                    'custom_two' => 1,
                    'custom_two.' => [
                        'type' => 'tx_model_news',
                    ],
                    'pages' => 1,
                    'pages.' => [],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        self::assertFalse($configuration->getIndexQueueIsMonitoredTable('tx_mycustom_table2'), 'tx_mycustom_table2 was not expected to be monitored');

        self::assertTrue($configuration->getIndexQueueIsMonitoredTable('pages'), 'pages was expected to be monitored');
        self::assertTrue($configuration->getIndexQueueIsMonitoredTable('tx_model_bar'), 'tx_model_bar was expected to be monitored');
        self::assertTrue($configuration->getIndexQueueIsMonitoredTable('tx_model_news'), 'tx_model_news was expected to be monitored');
    }

    #[Test]
    public function canGetLoggingEnableStateForIndexQueueByConfigurationName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'logging.' => [
                'indexing.' => [
                    'queue.' => [
                        'pages' => 1,
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertTrue(
            $configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('pages'),
            'Wrong logging state for pages index queue',
        );
        self::assertFalse(
            $configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('tt_content'),
            'Wrong logging state for tt_content index queue',
        );
    }

    #[Test]
    public function canGetLoggingEnableStateForIndexQueueByConfigurationNameByFallingBack(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'logging.' => [
                'indexing' => 1,
                'indexing.' => [
                    'queue.' => [
                        'pages' => 0,
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertTrue(
            $configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('pages'),
            'Wrong logging state for pages index queue',
        );
        self::assertTrue(
            $configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('tt_content'),
            'Wrong logging state for tt_content index queue',
        );
    }

    #[Test]
    public function canGetIndexFieldsConfigurationByConfigurationName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages.' => [
                        'fields.' => [
                            'sortSubTitle_stringS' => 'subtitle',
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedConfiguration = $configuration->getIndexQueueFieldsConfigurationByConfigurationName('pages');
        self::assertEquals(['sortSubTitle_stringS' => 'subtitle'], $retrievedConfiguration);
    }

    #[Test]
    public function canGetIndexQueueMappedFieldNamesByConfigurationName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages.' => [
                        'fields.' => [
                            'sortSubTitle_stringS' => 'subtitle',
                            'subTitle_stringM' => 'subtitle',
                            'fooShouldBeSkipped.' => [],
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $mappedFieldNames = $configuration->getIndexQueueMappedFieldsByConfigurationName('pages');
        self::assertEquals(['sortSubTitle_stringS', 'subTitle_stringM'], $mappedFieldNames);
    }

    #[Test]
    public function canGetIndexAdditionalFieldsConfiguration(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'additionalFields.' => [
                    'additional_sortSubTitle_stringS' => 'subtitle',
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedConfiguration = $configuration->getIndexAdditionalFieldsConfiguration();
        self::assertEquals(['additional_sortSubTitle_stringS' => 'subtitle'], $retrievedConfiguration);
    }

    #[Test]
    public function canGetIndexMappedAdditionalFieldNames(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'additionalFields.' => [
                    'additional_sortSubTitle_stringS' => 'subtitle',
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedConfiguration = $configuration->getIndexMappedAdditionalFieldNames();
        self::assertEquals(['additional_sortSubTitle_stringS'], $retrievedConfiguration);
    }

    #[Test]
    public function canGetIndexQueueIndexerByConfigurationName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages.' => [
                        'indexer' => 'Foobar',
                    ],
                ],
            ],
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $configuredIndexer = $configuration->getIndexQueueIndexerByConfigurationName('pages');
        self::assertSame('Foobar', $configuredIndexer, 'Retrieved unexpected indexer from typoscript configuration');
    }

    #[Test]
    public function canGetIndexQueueIndexerConfigurationByConfigurationName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages.' => [
                        'indexer' => 'Foobar',
                        'indexer.' => ['configuration' => 'test'],
                    ],
                ],
            ],
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $configuredIndexer = $configuration->getIndexQueueIndexerConfigurationByConfigurationName('pages');
        self::assertSame(['configuration' => 'test'], $configuredIndexer, 'Retrieved unexpected indexer configuration from typoscript configuration');
    }

    #[Test]
    public function canGetIndexQueuePagesExcludeContentByClassArray(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages.' => [
                        'excludeContentByClass' => 'excludeClass',
                    ],
                ],
            ],
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $excludeClasses = $configuration->getIndexQueuePagesExcludeContentByClassArray();
        self::assertEquals(['excludeClass'], $excludeClasses, 'Can not get exclude patterns from configuration');
    }

    #[Test]
    public function canSetSearchQueryFilterConfiguration(): void
    {
        $configuration = new TypoScriptConfiguration([]);
        self::assertEquals([], $configuration->getSearchQueryFilterConfiguration());
        $configuration->setSearchQueryFilterConfiguration(['foo']);
        self::assertEquals(['foo'], $configuration->getSearchQueryFilterConfiguration());
    }

    #[Test]
    public function canRemovePageSectionFilter(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'query.' => [
                    'filter.' => [
                        '__pageSections' => '1,2,3',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals(['__pageSections' => '1,2,3'], $configuration->getSearchQueryFilterConfiguration());

        $configuration->removeSearchQueryFilterForPageSections();
        self::assertEquals([], $configuration->getSearchQueryFilterConfiguration());
    }

    #[Test]
    public function removePageSectionFilterIsKeepingOtherFilters(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'query.' => [
                    'filter.' => [
                        'type:pages', '__pageSections' => '1,2,3',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals(['type:pages', '__pageSections' => '1,2,3'], $configuration->getSearchQueryFilterConfiguration());

        $configuration->removeSearchQueryFilterForPageSections();
        self::assertEquals(['type:pages'], $configuration->getSearchQueryFilterConfiguration());
    }

    #[Test]
    public function canGetSearchQueryReturnFieldsAsArrayNoConfig(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'query.' => [
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals([], $configuration->GetSearchQueryReturnFieldsAsArray());
    }

    #[Test]
    public function canGetSearchQueryReturnFieldsAsArrayWithConfig(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'query.' => [
                    'returnFields' => 'foo, bar',
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals(['foo', 'bar'], $configuration->GetSearchQueryReturnFieldsAsArray());
    }

    #[Test]
    public function canGetSearchSortingDefaultOrderBySortOptionNameIsFallingBackToDefaultSortOrder(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'sorting.' => [
                    'defaultOrder' => 'desc',
                    'options.' => [
                        'title.' => [
                            'field' => 'title',
                            'label' => 'Titel',
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedSorting = $configuration->getSearchSortingDefaultOrderBySortOptionName('title');
        self::assertEquals('desc', $retrievedSorting);
    }

    #[Test]
    public function canGetSearchSortingDefaultOrderBySortOptionName(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'sorting.' => [
                    'defaultOrder' => 'desc',
                    'options.' => [
                        'title.' => [
                            'defaultOrder' => 'asc',
                            'field' => 'title',
                            'label' => 'Titel',
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedSorting = $configuration->getSearchSortingDefaultOrderBySortOptionName('title');
        self::assertEquals('asc', $retrievedSorting);
    }

    #[Test]
    public function canGetSearchSortingDefaultOrderBySortOptionNameInLowerCase(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'sorting.' => [
                    'options.' => [
                        'title.' => [
                            'defaultOrder' => 'DESC',
                            'field' => 'title',
                            'label' => 'Titel',
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedSorting = $configuration->getSearchSortingDefaultOrderBySortOptionName('title');
        self::assertEquals('desc', $retrievedSorting);
    }

    #[Test]
    public function canGetSearchGroupingHighestGroupResultsLimit(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'grouping.' => [
                    'numberOfResultsPerGroup' => 3,
                    'groups.' => [
                        'typeGroup.' => [
                            'field' => 'type',
                            'numberOfResultsPerGroup' => 5,
                        ],
                        'priceGroup.' => [
                            'field' => 'price',
                            'numberOfResultsPerGroup' => 2,
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $highestResultsPerGroup = $configuration->getSearchGroupingHighestGroupResultsLimit();
        self::assertEquals(5, $highestResultsPerGroup, 'Can not get highest result per group value');
    }

    #[Test]
    public function canGetSearchGroupingHighestGroupResultsLimitAsGlobalFallback(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'grouping.' => [
                    'numberOfResultsPerGroup' => 8,
                    'groups.' => [
                        'typeGroup.' => [
                            'field' => 'type',
                            'numberOfResultsPerGroup' => 5,
                        ],
                        'priceGroup.' => [
                            'field' => 'price',
                            'numberOfResultsPerGroup' => 2,
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $highestResultsPerGroup = $configuration->getSearchGroupingHighestGroupResultsLimit();
        self::assertEquals(8, $highestResultsPerGroup, 'Can not get highest result per group value');
    }

    #[Test]
    public function canGetSearchGroupingWhenDisabled(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'grouping' => 0,
                'grouping.' => [
                    'numberOfResultsPerGroup' => 8,
                    'groups.' => [
                        'typeGroup.' => [
                            'field' => 'type',
                            'numberOfResultsPerGroup' => 5,
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        self::assertFalse($configuration->getIsSearchGroupingEnabled(), 'Expected grouping to be disabled');
    }

    #[Test]
    public function canGetGroupingAllowGetParameterSwitch(): void
    {
        $fakeConfigurationArray = [
            'plugin.' => [
                'tx_solr.' => [
                    'search.' => [
                        'grouping' => 1,
                        'grouping.' => [
                            'allowGetParameterSwitch' => 0,
                        ],
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertFalse($configuration->getIsGroupingGetParameterSwitchEnabled(), 'Expected allowGetParameterSwitch to be disabled');

        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['grouping.']['allowGetParameterSwitch'] = 1;
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertTrue($configuration->getIsGroupingGetParameterSwitchEnabled(), 'Expected allowGetParameterSwitch to be enabled');
    }

    #[Test]
    public function getSearchAdditionalPersistentArgumentNamesReturnsEmptyArrayWhenNothingIsConfigured(): void
    {
        $configuration = new TypoScriptConfiguration([]);
        self::assertSame([], $configuration->getSearchAdditionalPersistentArgumentNames(), 'Expected to get an empty array, when nothing configured');
    }

    #[Test]
    public function canGetAdditionalPersistentArgumentNames(): void
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'additionalPersistentArgumentNames' => 'customA, customB',
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertSame(['customA', 'customB'], $configuration->getSearchAdditionalPersistentArgumentNames(), 'Can not get configured custom parameters');
    }

    #[Test]
    #[DataProvider('queryTypeDataProvider')]
    public function canIndicateQueryType(
        array $fakeConfiguration,
        int $queryType,
        bool $vectorSearchEnabled,
    ): void {
        $configuration = new TypoScriptConfiguration($fakeConfiguration);
        self::assertEquals($queryType, $configuration->getSearchQueryType());
        self::assertEquals($vectorSearchEnabled, $configuration->isVectorSearchEnabled());
    }

    public static function queryTypeDataProvider(): Traversable
    {
        yield 'unconfigured' => [
            'fakeConfiguration' => [],
            'queryType' => 0,
            'vectorSearchEnabled' => false,
        ];

        yield 'default search' => [
            'fakeConfiguration' => [
                'plugin.' => [
                    'tx_solr.' => [
                        'search.' => [
                            'query.' => [
                                'type' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            'queryType' => 0,
            'vectorSearchEnabled' => false,
        ];

        yield 'vector search' => [
            'fakeConfiguration' => [
                'plugin.' => [
                    'tx_solr.' => [
                        'search.' => [
                            'query.' => [
                                'type' => 1,
                            ],
                        ],
                    ],
                ],
            ],
            'queryType' => 1,
            'vectorSearchEnabled' => true,
        ];
    }

    #[Test]
    public function canGetMinimumVectorSimilarity(): void
    {
        $configuration = new TypoScriptConfiguration(['plugin.' => ['tx_solr.' => []]]);
        self::assertEquals(0.75, $configuration->getMinimumVectorSimilarity());

        $configuration->mergeSolrConfiguration([
            'search.' => [
                'vectorSearch.' => [
                    'minimumSimilarity' => 0.80,
                ],
            ],
        ]);
        self::assertEquals(0.80, $configuration->getMinimumVectorSimilarity());
    }

    #[Test]
    public function canGetTopKClosestVectorLimit(): void
    {
        $configuration = new TypoScriptConfiguration(['plugin.' => ['tx_solr.' => []]]);
        self::assertEquals(1000, $configuration->getTopKClosestVectorLimit());

        $configuration->mergeSolrConfiguration([
            'search.' => [
                'vectorSearch.' => [
                    'topK' => 9999,
                ],
            ],
        ]);
        self::assertEquals(9999, $configuration->getTopKClosestVectorLimit());
    }
}
