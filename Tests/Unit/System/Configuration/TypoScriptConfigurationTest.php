<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2016 Timo Schmidt <timo.schmidt@dkd.de>
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

    /**
     * @return void
     */
    public function setUp()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content'] = 'SOLR_CONTENT';
        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content.']['field'] = 'bodytext';
        $this->configuration = new TypoScriptConfiguration($fakeConfigurationArray);
    }

    /**
     * @test
     */
    public function canGetValueByPath()
    {
        $testPath = 'plugin.tx_solr.index.queue.tt_news.fields.content';
        $this->assertSame('SOLR_CONTENT', $this->configuration->getValueByPath($testPath), 'Could not get configuration value by path');
    }

    /**
     * @test
     */
    public function canGetObjectByPath()
    {
        $testPath = 'plugin.tx_solr.index.queue.tt_news.fields.content';
        $expectedResult = [
            'content' => 'SOLR_CONTENT',
            'content.' => ['field' => 'bodytext']
        ];

        $this->assertSame($expectedResult, $this->configuration->getObjectByPath($testPath), 'Could not get configuration object by path');
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
                            'showEvenWhenEmpty' => true
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $showEmptyType = $configuration->getSearchFacetingShowEmptyFacetsByName('type');
        $this->assertTrue($showEmptyType);

        $showEmptyColor = $configuration->getSearchFacetingShowEmptyFacetsByName('color');
        $this->assertTrue($showEmptyColor);

        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'faceting.' => [
                    'facets.' => [
                        'color.' => [],
                        'type.' => [
                            'showEvenWhenEmpty' => true
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $showEmptyType = $configuration->getSearchFacetingShowEmptyFacetsByName('type');
        $this->assertTrue($showEmptyType);

        $showEmptyColor = $configuration->getSearchFacetingShowEmptyFacetsByName('color');
        $this->assertFalse($showEmptyColor);
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
                        'table' => 'tx_model_custom'
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $customTableExpected = $configuration->getIndexQueueTableNameOrFallbackToConfigurationName('pages');
        $this->assertSame($customTableExpected, 'pages', 'Can not fallback to configurationName');

        $customTableExpected = $configuration->getIndexQueueTableNameOrFallbackToConfigurationName('custom');
        $this->assertSame($customTableExpected, 'tx_model_custom', 'Usage of custom table tx_model_custom was expected');
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
                        'table' => 'tx_model_custom'
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $enabledIndexQueueNames = $configuration->getEnabledIndexQueueConfigurationNames();

        $this->assertCount(1, $enabledIndexQueueNames, 'Retrieved unexpected amount of index queue configurations');
        $this->assertContains('pages', $enabledIndexQueueNames, 'Pages was no enabled index queue configuration');

        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['custom'] = 1;
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $enabledIndexQueueNames = $configuration->getEnabledIndexQueueConfigurationNames();

        $this->assertCount(2, $enabledIndexQueueNames, 'Retrieved unexpected amount of index queue configurations');
        $this->assertContains('custom', $enabledIndexQueueNames, 'Pages was no enabled index queue configuration');
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
                        'recursiveUpdateFields' => ''
                    ],
                    'custom.' => [
                        'recursiveUpdateFields' => ''
                    ]
                ]
            ]
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertEquals([], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('pages'));
        $this->assertEquals([], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('custom'));

        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages' => 1,
                    'pages.' => [
                    ],
                    'custom.' => [
                    ]
                ]
            ]
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertEquals([], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('pages'));
        $this->assertEquals([], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('custom'));

        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'queue.' => [
                    'pages' => 1,
                    'pages.' => [
                        'recursiveUpdateFields' => 'title, subtitle'
                    ],
                    'custom.' => [
                        'recursiveUpdateFields' => 'title, subtitle'
                    ]
                ]
            ]
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertEquals(['title' => 'title', 'subtitle' => 'subtitle'], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('pages'));
        $this->assertEquals(['title' => 'title', 'subtitle' => 'subtitle'], $configuration->getIndexQueueConfigurationRecursiveUpdateFields('custom'));
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
                        'initialPagesAdditionalWhereClause' => '1=1'
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $this->assertEquals('', $configuration->getInitialPagesAdditionalWhereClause('pages'));
        $this->assertEquals('1=1', $configuration->getInitialPagesAdditionalWhereClause('custom'));
        $this->assertEquals('', $configuration->getInitialPagesAdditionalWhereClause('notconfigured'));
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
                        'additionalWhereClause' => '1=1'
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $this->assertEquals('', $configuration->getIndexQueueAdditionalWhereClauseByConfigurationName('pages'));
        $this->assertEquals(' AND 1=1', $configuration->getIndexQueueAdditionalWhereClauseByConfigurationName('custom'));
        $this->assertEquals('', $configuration->getIndexQueueAdditionalWhereClauseByConfigurationName('notconfigured'));
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
                        'table' => 'tx_model_bar'
                    ],

                    'custom_two' => 1,
                    'custom_two.' => [
                        'table' => 'tx_model_news'
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertEquals(['tx_model_news', 'custom_two'], $configuration->getIndexQueueConfigurationNamesByTableName('tx_model_news'));
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
                        'table' => 'tx_model_bar'
                    ],

                    'custom_two' => 1,
                    'custom_two.' => [
                        'table' => 'tx_model_news'
                    ],
                    'pages' => 1,
                    'pages.' => []
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $monitoredTables =  $configuration->getIndexQueueMonitoredTables();
        $this->assertEquals(['tx_model_news', 'tx_model_bar', 'pages'], $monitoredTables);
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
                        'table' => 'tx_model_bar'
                    ],

                    'custom_two' => 1,
                    'custom_two.' => [
                        'table' => 'tx_model_news'
                    ],
                    'pages' => 1,
                    'pages.' => []
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $this->assertFalse($configuration->getIndexQueueIsMonitoredTable('tx_mycustom_table2'), 'tx_mycustom_table2 was not expected to be monitored');

        $this->assertTrue($configuration->getIndexQueueIsMonitoredTable('pages'), 'pages was expected to be monitored');
        $this->assertTrue($configuration->getIndexQueueIsMonitoredTable('tx_model_bar'), 'tx_model_bar was expected to be monitored');
        $this->assertTrue($configuration->getIndexQueueIsMonitoredTable('tx_model_news'), 'tx_model_news was expected to be monitored');
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
                        'pages' => 1
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertTrue($configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('pages'),
            'Wrong logging state for pages index queue');
        $this->assertFalse($configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('tt_content'),
            'Wrong logging state for tt_content index queue');
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
                        'pages' => 0
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertTrue($configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('pages'),
            'Wrong logging state for pages index queue');
        $this->assertTrue($configuration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack('tt_content'),
            'Wrong logging state for tt_content index queue');
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
                            'sortSubTitle_stringS' => 'subtitle'
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedConfiguration = $configuration->getIndexQueueFieldsConfigurationByConfigurationName('pages');
        $this->assertEquals(['sortSubTitle_stringS' => 'subtitle'], $retrievedConfiguration);
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
                            'fooShouldBeSkipped.' => []
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $mappedFieldNames = $configuration->getIndexQueueMappedFieldsByConfigurationName('pages');
        $this->assertEquals(['sortSubTitle_stringS', 'subTitle_stringM'], $mappedFieldNames);
    }

    /**
     * @test
     */
    public function canGetIndexAdditionalFieldsConfiguration()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'additionalFields.' => [
                    'additional_sortSubTitle_stringS' => 'subtitle'
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedConfiguration = $configuration->getIndexAdditionalFieldsConfiguration();
        $this->assertEquals(['additional_sortSubTitle_stringS' => 'subtitle'], $retrievedConfiguration);
    }

    /**
     * @test
     */
    public function canGetIndexMappedAdditionalFieldNames()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'index.' => [
                'additionalFields.' => [
                    'additional_sortSubTitle_stringS' => 'subtitle'
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedConfiguration = $configuration->getIndexMappedAdditionalFieldNames();
        $this->assertEquals(['additional_sortSubTitle_stringS'], $retrievedConfiguration);
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
                        'indexer' => 'Foobar'
                    ]
                ]
            ]
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $configuredIndexer = $configuration->getIndexQueueIndexerByConfigurationName('pages');
        $this->assertSame('Foobar', $configuredIndexer, 'Retrieved unexpected indexer from typoscript configuration');
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
                        'indexer.' => ['configuration' => 'test']
                    ]
                ]
            ]
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $configuredIndexer = $configuration->getIndexQueueIndexerConfigurationByConfigurationName('pages');
        $this->assertSame(['configuration' => 'test'], $configuredIndexer, 'Retrieved unexpected indexer configuration from typoscript configuration');
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
                        'excludeContentByClass' => 'excludeClass'
                    ]
                ]
            ]
        ];
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $excludeClasses = $configuration->getIndexQueuePagesExcludeContentByClassArray();
        $this->assertEquals(['excludeClass'], $excludeClasses, 'Can not get exclude patterns from configuration');
    }

    /**
     * @test
     */
    public function canSetSearchQueryFilterConfiguration()
    {
        $configuration = new TypoScriptConfiguration([]);
        $this->assertEquals([], $configuration->getSearchQueryFilterConfiguration());
        $configuration->setSearchQueryFilterConfiguration(['foo']);
        $this->assertEquals(['foo'], $configuration->getSearchQueryFilterConfiguration());
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
                        '__pageSections' => '1,2,3'
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertEquals(['__pageSections' => '1,2,3'], $configuration->getSearchQueryFilterConfiguration());

        $configuration->removeSearchQueryFilterForPageSections();
        $this->assertEquals([], $configuration->getSearchQueryFilterConfiguration());
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
                        'type:pages', '__pageSections' => '1,2,3'
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertEquals(['type:pages', '__pageSections' => '1,2,3'], $configuration->getSearchQueryFilterConfiguration());

        $configuration->removeSearchQueryFilterForPageSections();
        $this->assertEquals(['type:pages'], $configuration->getSearchQueryFilterConfiguration());
    }

    /**
     * @test
     */
    public function canGetSearchQueryReturnFieldsAsArrayNoConfig()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'query.' => [
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertEquals([], $configuration->GetSearchQueryReturnFieldsAsArray());
    }

    /**
     * @test
     */
    public function canGetSearchQueryReturnFieldsAsArrayWithConfig()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'query.' => [
                    'returnFields' => 'foo, bar'
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertEquals(['foo', 'bar'], $configuration->GetSearchQueryReturnFieldsAsArray());
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
                            'label' => 'Titel'
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedSorting = $configuration->getSearchSortingDefaultOrderBySortOptionName('title');
        $this->assertEquals('desc', $retrievedSorting);
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
                            'label' => 'Titel'
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedSorting = $configuration->getSearchSortingDefaultOrderBySortOptionName('title');
        $this->assertEquals('asc', $retrievedSorting);
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
                            'label' => 'Titel'
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedSorting = $configuration->getSearchSortingDefaultOrderBySortOptionName('title');
        $this->assertEquals('desc', $retrievedSorting);
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
                            'numberOfResultsPerGroup' => 5
                        ],
                        'priceGroup.' => [
                            'field' => 'price',
                            'numberOfResultsPerGroup' => 2
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $highestResultsPerGroup = $configuration->getSearchGroupingHighestGroupResultsLimit();
        $this->assertEquals(5, $highestResultsPerGroup, 'Can not get highest result per group value');
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
                            'numberOfResultsPerGroup' => 5
                        ],
                        'priceGroup.' => [
                            'field' => 'price',
                            'numberOfResultsPerGroup' => 2
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $highestResultsPerGroup = $configuration->getSearchGroupingHighestGroupResultsLimit();
        $this->assertEquals(8, $highestResultsPerGroup, 'Can not get highest result per group value');
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
                            'numberOfResultsPerGroup' => 5
                        ]
                    ]
                ]
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $this->assertFalse($configuration->getSearchGrouping(), 'Expected grouping to be disabled');
    }

    /**
     * @test
     */
    public function getSearchAdditionalPersistentArgumentNamesReturnsEmptyArrayWhenNothingIsConfigured()
    {
        $configuration = new TypoScriptConfiguration([]);
        $this->assertSame([], $configuration->getSearchAdditionalPersistentArgumentNames(), 'Expected to get an empty array, when nothing configured');
    }

    /**
     * @test
     */
    public function canGetAdditionalPersistentArgumentNames()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = [
            'search.' => [
                'additionalPersistentArgumentNames' => 'customA, customB',
            ]
        ];

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertSame(['customA','customB'], $configuration->getSearchAdditionalPersistentArgumentNames(), 'Can not get configured custom parameters');
    }
}
