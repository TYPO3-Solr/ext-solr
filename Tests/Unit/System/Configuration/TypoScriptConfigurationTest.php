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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase to check if the configuration object can be used as expected
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class TypoScriptConfigurationTest extends UnitTest
{

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    protected function setUp(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content'] = 'SOLR_CONTENT';
        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content.']['field'] = 'bodytext';
        $this->configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        parent::setUp();
    }

    /**
     * @test
     */
    public function canGetValueByPath()
    {
        $testPath = 'plugin.tx_solr.index.queue.tt_news.fields.content';
        self::assertSame('SOLR_CONTENT', $this->configuration->getValueByPath($testPath), 'Could not get configuration value by path');
    }

    /**
     * @test
     */
    public function canGetObjectByPath()
    {
        $testPath = 'plugin.tx_solr.index.queue.tt_news.fields.content';
        $expectedResult = [
            'content' => 'SOLR_CONTENT',
            'content.' => ['field' => 'bodytext'],
        ];

        self::assertSame($expectedResult, $this->configuration->getObjectByPath($testPath), 'Could not get configuration object by path');
    }

    /**
     * @test
     */
    public function canShowEvenIfEmptyFallBackToGlobalSetting()
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

    /**
     * @test
     */
    public function canGetIndexQueueTableOrFallbackToConfigurationName()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages.' => [
                    ],
                    'custom.' => [
                        'table' => 'tx_model_custom',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $customTableExpected = $configuration->getIndexQueueTableNameOrFallbackToConfigurationName('pages');
        self::assertSame($customTableExpected, 'pages', 'Can not fallback to configurationName');

        $customTableExpected = $configuration->getIndexQueueTableNameOrFallbackToConfigurationName('custom');
        self::assertSame($customTableExpected, 'tx_model_custom', 'Usage of custom table tx_model_custom was expected');
    }

    /**
     * @test
     */
    public function canGetIndexQueueConfigurationNames()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages' => 1,
                    'pages.' => [
                    ],
                    'custom.' => [
                        'table' => 'tx_model_custom',
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

    /**
     * @test
     */
    public function canGetIndexQueueConfigurationRecursiveUpdateFields()
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

    /**
     * @test
     */
    public function canGetInitialPagesAdditionalWhereClause()
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

    /**
     * @test
     */
    public function canGetAdditionalWhereClause()
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

    /**
     * @test
     */
    public function canGetIndexQueueConfigurationNamesByTableName()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'tx_model_news' => 1,
                    'tx_model_news.' => [
                    ],
                    'custom_one' => 1,
                    'custom_one.' => [
                        'table' => 'tx_model_bar',
                    ],

                    'custom_two' => 1,
                    'custom_two.' => [
                        'table' => 'tx_model_news',
                    ],
                ],
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertEquals(['tx_model_news', 'custom_two'], $configuration->getIndexQueueConfigurationNamesByTableName('tx_model_news'));
    }

    /**
     * @test
     */
    public function canGetIndexQueueMonitoredTables()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'tx_model_news' => 1,
                    'tx_model_news.' => [
                    ],
                    'custom_one' => 1,
                    'custom_one.' => [
                        'table' => 'tx_model_bar',
                    ],

                    'custom_two' => 1,
                    'custom_two.' => [
                        'table' => 'tx_model_news',
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

    /**
     * @test
     */
    public function canGetIndexQueueIsMonitoredTable()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'tx_model_news' => 1,
                    'tx_model_news.' => [
                    ],
                    'custom_one' => 1,
                    'custom_one.' => [
                        'table' => 'tx_model_bar',
                    ],

                    'custom_two' => 1,
                    'custom_two.' => [
                        'table' => 'tx_model_news',
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

    /**
     * @test
     */
    public function canGetLoggingEnableStateForIndexQueueByConfigurationName()
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
            'Wrong logging state for pages index queue'
        );
        self::assertFalse(
            $configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('tt_content'),
            'Wrong logging state for tt_content index queue'
        );
    }

    /**
     * @test
     */
    public function canGetLoggingEnableStateForIndexQueueByConfigurationNameByFallingBack()
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
            'Wrong logging state for pages index queue'
        );
        self::assertTrue(
            $configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('tt_content'),
            'Wrong logging state for tt_content index queue'
        );
    }

    /**
     * @test
     */
    public function canGetIndexFieldsConfigurationByConfigurationName()
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

    /**
     * @test
     */
    public function canGetIndexQueueMappedFieldNamesByConfigurationName()
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

    /**
     * @test
     */
    public function canGetIndexAdditionalFieldsConfiguration()
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

    /**
     * @test
     */
    public function canGetIndexMappedAdditionalFieldNames()
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

    /**
     * @test
     */
    public function canGetIndexQueueIndexerByConfigurationName()
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

    /**
     * @test
     */
    public function canGetIndexQueueIndexerConfigurationByConfigurationName()
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

    /**
     * @test
     */
    public function canGetIndexQueuePagesExcludeContentByClassArray()
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

    /**
     * @test
     */
    public function canSetSearchQueryFilterConfiguration()
    {
        $configuration = new TypoScriptConfiguration([]);
        self::assertEquals([], $configuration->getSearchQueryFilterConfiguration());
        $configuration->setSearchQueryFilterConfiguration(['foo']);
        self::assertEquals(['foo'], $configuration->getSearchQueryFilterConfiguration());
    }

    /**
     * @test
     */
    public function canRemovePageSectionFilter()
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

    /**
     * @test
     */
    public function removePageSectionFilterIsKeepingOtherFilters()
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

    /**
     * @test
     */
    public function canGetSearchQueryReturnFieldsAsArrayNoConfig()
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

    /**
     * @test
     */
    public function canGetSearchQueryReturnFieldsAsArrayWithConfig()
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

    /**
     * @test
     */
    public function canGetSearchSortingDefaultOrderBySortOptionNameIsFallingBackToDefaultSortOrder()
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

    /**
     * @test
     */
    public function canGetSearchSortingDefaultOrderBySortOptionName()
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

    /**
     * @test
     */
    public function canGetSearchSortingDefaultOrderBySortOptionNameInLowerCase()
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

    /**
     * @test
     */
    public function canGetSearchGroupingHighestGroupResultsLimit()
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

    /**
     * @test
     */
    public function canGetSearchGroupingHighestGroupResultsLimitAsGlobalFallback()
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

    /**
     * @test
     */
    public function canGetSearchGroupingWhenDisabled()
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

    /**
     * @test
     */
    public function getSearchAdditionalPersistentArgumentNamesReturnsEmptyArrayWhenNothingIsConfigured()
    {
        $configuration = new TypoScriptConfiguration([]);
        self::assertSame([], $configuration->getSearchAdditionalPersistentArgumentNames(), 'Expected to get an empty array, when nothing configured');
    }

    /**
     * @test
     */
    public function canGetAdditionalPersistentArgumentNames()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'additionalPersistentArgumentNames' => 'customA, customB',
            ],
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        self::assertSame(['customA', 'customB'], $configuration->getSearchAdditionalPersistentArgumentNames(), 'Can not get configured custom parameters');
    }
}
