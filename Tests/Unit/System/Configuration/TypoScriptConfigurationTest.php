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
 *  the Free Software Foundation; either version 2 of the License, or
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the configuration object can be used as expected
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
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
        $fakeConfigurationArray = array();
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
        $expectedResult = array(
            'content' => 'SOLR_CONTENT',
            'content.' => array('field' => 'bodytext')
        );

        $this->assertSame($expectedResult, $this->configuration->getObjectByPath($testPath), 'Could not get configuration object by path');
    }

    /**
     * @test
     */
    public function canUseAsArrayForBackwardsCompatibility()
    {
        $value = $this->configuration['index.']['queue.']['tt_news.']['fields.']['content'];
        $this->assertSame($value, 'SOLR_CONTENT', 'Can not use the configuration object with array access as backwards compatible implementation');
    }

    /**
     * @test
     */
    public function canGetFacetLinkOptionsByFacetName()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'search.' => array(
                'faceting.' => array(
                    'facetLinkATagParams' => 'class="all-facets"',
                    'facets.' => array(
                        'color.' => array(),
                        'type.' => array(
                            'facetLinkATagParams' => 'class="type-facets"'
                        )
                    )
                )
            )
        );


        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $typeATagParams = $configuration->getSearchFacetingFacetLinkATagParamsByName('type');
        $this->assertSame('class="type-facets"', $typeATagParams, 'can not get concrete a tag param for type');

        $typeATagParams = $configuration->getSearchFacetingFacetLinkATagParamsByName('color');
        $this->assertSame('class="all-facets"', $typeATagParams, 'can not get concrete a tag param for color');
    }


    /**
     * @test
     */
    public function canShowEvenIfEmptyFallBackToGlobalSetting()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'search.' => array(
                'faceting.' => array(
                    'showEmptyFacets' => true,
                    'facets.' => array(
                        'color.' => array(),
                        'type.' => array(
                            'showEvenWhenEmpty' => true
                        )
                    )
                )
            )
        );


        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $showEmptyType = $configuration->getSearchFacetingShowEmptyFacetsByName('type');
        $this->assertTrue($showEmptyType);

        $showEmptyColor = $configuration->getSearchFacetingShowEmptyFacetsByName('color');
        $this->assertTrue($showEmptyColor);


        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'search.' => array(
                'faceting.' => array(
                    'facets.' => array(
                        'color.' => array(),
                        'type.' => array(
                            'showEvenWhenEmpty' => true
                        )
                    )
                )
            )
        );

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $showEmptyType = $configuration->getSearchFacetingShowEmptyFacetsByName('type');
        $this->assertTrue($showEmptyType);

        $showEmptyColor = $configuration->getSearchFacetingShowEmptyFacetsByName('color');
        $this->assertFalse($showEmptyColor);
    }

    /**
     * @test
     */
    public function canGetJavaScriptFileByName()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'javascriptFiles.' => array(
                'ui' => 'ui.js'
            )
        );

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertSame('ui.js', $configuration->getJavaScriptFileByFileKey('ui'), 'Could get configured javascript file');
    }

    /**
     * @test
     */
    public function canGetIndexQueueTableOrFallbackToConfigurationName()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'pages.' => array(
                    ),
                    'custom.' => array(
                        'table' => 'tx_model_custom'
                    )
                )
            )
        );

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
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'pages' => 1,
                    'pages.' => array(
                    ),
                    'custom.' => array(
                        'table' => 'tx_model_custom'
                    )
                )
            )
        );

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
    public function canGetAdditionalWhereClause()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'pages' => 1,
                    'pages.' => array(
                    ),
                    'custom.' => array(
                        'additionalWhereClause' => '1=1'
                    )
                )
            )
        );

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
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'tx_model_news' => 1,
                    'tx_model_news.' => array(
                    ),
                    'custom_one' => 1,
                    'custom_one.' => array(
                        'table' => 'tx_model_bar'
                    ),

                    'custom_two' => 1,
                    'custom_two.' => array(
                        'table' => 'tx_model_news'
                    )
                )
            )
        );

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $this->assertEquals(array('tx_model_news', 'custom_two'), $configuration->getIndexQueueConfigurationNamesByTableName('tx_model_news'));
    }

    /**
     * @test
     */
    public function canGetLoggingEnableStateForIndexQueueByConfigurationName()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'logging.' => array(
                'indexing.' => array(
                    'queue.' => array(
                        'pages' => 1
                    )
                )
            )
        );

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
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'logging.' => array(
                'indexing' => 1,
                'indexing.' => array(
                    'queue.' => array(
                        'pages' => 0
                    )
                )
            )
        );

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
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'pages.' => array(
                        'fields.' => array(
                            'sortSubTitle_stringS' => 'subtitle'
                        )
                    )
                )
            )
        );

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedConfiguration = $configuration->getIndexQueueFieldsConfigurationByConfigurationName('pages');
        $this->assertEquals(array('sortSubTitle_stringS' => 'subtitle'), $retrievedConfiguration);
    }

    /**
     * @test
     */
    public function canGetIndexQueueMappedFieldNamesByConfigurationName()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'pages.' => array(
                        'fields.' => array(
                            'sortSubTitle_stringS' => 'subtitle',
                            'subTitle_stringM' => 'subtitle',
                            'fooShouldBeSkipped.' => array()
                        )
                    )
                )
            )
        );

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $mappedFieldNames = $configuration->getIndexQueueMappedFieldsByConfigurationName('pages');
        $this->assertEquals(array('sortSubTitle_stringS', 'subTitle_stringM'), $mappedFieldNames);
    }

    /**
     * @test
     */
    public function canGetIndexAdditionalFieldsConfiguration()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'additionalFields.' => array(
                    'additional_sortSubTitle_stringS' => 'subtitle'
                )
            )
        );

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedConfiguration = $configuration->getIndexAdditionalFieldsConfiguration();
        $this->assertEquals(array('additional_sortSubTitle_stringS' => 'subtitle'), $retrievedConfiguration);
    }



    /**
     * @test
     */
    public function canGetIndexMappedAdditionalFieldNames()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'additionalFields.' => array(
                    'additional_sortSubTitle_stringS' => 'subtitle'
                )
            )
        );

        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);

        $retrievedConfiguration = $configuration->getIndexMappedAdditionalFieldNames();
        $this->assertEquals(array('additional_sortSubTitle_stringS'), $retrievedConfiguration);
    }

    /**
     * @test
     */
    public function canGetIndexQueueIndexerByConfigurationName()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'pages.' => array(
                        'indexer' => 'Foobar'
                    )
                )
            )
        );
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $configuredIndexer = $configuration->getIndexQueueIndexerByConfigurationName('pages');
        $this->assertSame('Foobar', $configuredIndexer, 'Retrieved unexpected indexer from typoscript configuration');
    }

    /**
     * @test
     */
    public function canGetIndexQueueIndexerConfigurationByConfigurationName()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'pages.' => array(
                        'indexer' => 'Foobar',
                        'indexer.' => array('configuration' => 'test')
                    )
                )
            )
        );
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $configuredIndexer = $configuration->getIndexQueueIndexerConfigurationByConfigurationName('pages');
        $this->assertSame(array('configuration' => 'test'), $configuredIndexer, 'Retrieved unexpected indexer configuration from typoscript configuration');
    }

    /**
     * @test
     */
    public function canGetIndexQueuePagesAllowedPageTypesArray()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'pages.' => array(
                        'allowedPageTypes' => '1,2, 7'
                    )
                )
            )
        );
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $allowedPageTypes = $configuration->getIndexQueuePagesAllowedPageTypesArray();
        $this->assertEquals(array(1, 2, 7), $allowedPageTypes, 'Can not get allowed pagestype from configuration');
    }


    /**
     * @test
     */
    public function canGetIndexQueuePagesExcludeContentByClassArray()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.'] = array(
            'index.' => array(
                'queue.' => array(
                    'pages.' => array(
                        'excludeContentByClass' => 'excludeClass'
                    )
                )
            )
        );
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $excludeClasses = $configuration->getIndexQueuePagesExcludeContentByClassArray();
        $this->assertEquals(array('excludeClass'), $excludeClasses, 'Can not get exclude patterns from configuration');
    }
}
