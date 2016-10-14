<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests the ApacheSolrForTypo3\Solr\Query class
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class QueryTest extends UnitTest
{

    /**
     * @test
     */
    public function noFiltersAreSetAfterInitialization()
    {
        $query = $this->getInitializedTestQuery();
        $filters = $query->getFilters();

        $this->assertTrue(
            empty($filters),
            'Query already contains filters after intialization.'
        );
    }

    /**
     * @test
     */
    public function addsCorrectAccessFilterForAnonymousUser()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups(array(-1, 0));
        $filters = $query->getFilters();

        $this->assertContains(
            '{!typo3access}-1,0',
            $filters,
            'Access filter not found in [' . implode('], [', $filters) . ']'
        );
    }

    /**
     * @test
     */
    public function grantsAccessToGroupZeroIfNoGroupsProvided()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups(array());
        $filters = $query->getFilters();

        $this->assertContains(
            '{!typo3access}0',
            $filters,
            'Access filter not found in [' . implode('], [', $filters) . ']'
        );
    }

    /**
     * @test
     */
    public function grantsAccessToGroupZeroIfZeroNotProvided()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups(array(5));
        $filters = $query->getFilters();

        $this->assertContains(
            '{!typo3access}0,5',
            $filters,
            'Access filter not found in [' . implode('], [', $filters) . ']'
        );
    }

    /**
     * @test
     */
    public function filtersDuplicateAccessGroups()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups(array(1, 1));
        $filters = $query->getFilters();

        $this->assertContains(
            '{!typo3access}0,1',
            $filters,
            'Access filter not found in [' . implode('], [', $filters) . ']'
        );
    }

    /**
     * @test
     */
    public function allowsOnlyOneAccessFilter()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups(array(1));
        $query->setUserAccessGroups(array(2));
        $filters = $query->getFilters();

        $this->assertSame(
            count($filters),
            1,
            'Too many filters in [' . implode('], [', $filters) . ']'
        );
    }

    // TODO if user is in group -2 (logged in), disallow access to group -1

    // grouping

    /**
     * @test
     */
    public function groupingIsNotActiveAfterInitialization()
    {
        $query = $this->getInitializedTestQuery();

        $queryParameters = $query->getQueryParameters();
        foreach ($queryParameters as $queryParameter => $value) {
            $this->assertTrue(
                !GeneralUtility::isFirstPartOfStr($queryParameter, 'group'),
                'Query already contains grouping parameter "' . $queryParameter . '"'
            );
        }
    }

    /**
     * @test
     */
    public function settingGroupingTrueActivatesGrouping()
    {
        $query = $this->getInitializedTestQuery();

        $query->setGrouping(true);

        $queryParameters = $query->getQueryParameters();
        $this->assertArrayHasKey('group', $queryParameters);
        $this->assertEquals('true', $queryParameters['group']);

        $this->assertArrayHasKey('group.format', $queryParameters);
        $this->assertEquals('grouped', $queryParameters['group.format']);

        $this->assertArrayHasKey('group.ngroups', $queryParameters);
        $this->assertEquals('true', $queryParameters['group.ngroups']);

        return $query;
    }

    /**
     * @test
     * @depends settingGroupingTrueActivatesGrouping
     */
    public function settingGroupingFalseDeactivatesGrouping(Query $query)
    {
        $query->setGrouping(false);

        $queryParameters = $query->getQueryParameters();

        foreach ($queryParameters as $queryParameter => $value) {
            $this->assertTrue(
                !GeneralUtility::isFirstPartOfStr($queryParameter, 'group'),
                'Query contains grouping parameter "' . $queryParameter . '"'
            );
        }
    }

    // highlighting
    /**
     * @test
     */
    public function canEnableHighlighting()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = $this->getInitializedTestQuery();
        $query->setHighlighting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame(200, $queryParameters['hl.fragsize'], 'hl.fragsize was not set to the default value of 200');
    }

    /**
     * @test
     */
    public function canDisableHighlighting()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = $this->getInitializedTestQuery();
        $query->setHighlighting(true);

        $queryParameters = $query->getQueryParameters();
        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');

        $query->setHighlighting(false);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['hl'], 'Could not disable highlighting');
    }

    /**
     * @test
     */
    public function canSetHighlightingFieldList()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting.']['highlightFields'] = 'title';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->setHighlighting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame('title', $queryParameters['hl.fl'], 'Can set highlighting field list');
    }

    /**
     * @test
     */
    public function canPassCustomWrapForHighlighting()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting.']['wrap'] = '[A]|[B]';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->setHighlighting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('[A]', $queryParameters['hl.tag.pre'], 'Can set highlighting hl.tag.pre');
        $this->assertSame('[B]', $queryParameters['hl.tag.post'], 'Can set highlighting hl.tag.post');
        $this->assertSame('[A]', $queryParameters['hl.simple.pre'], 'Can set highlighting hl.tag.pre');
        $this->assertSame('[B]', $queryParameters['hl.simple.post'], 'Can set highlighting hl.tag.post');
    }

    /**
     * @test
     */
    public function simplePreAndPostIsUsedWhenFastVectorHighlighterCouldNotBeUsed()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting.']['wrap'] = '[A]|[B]';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        // fragSize 10 is to small for FastVectorHighlighter
        $query->setHighlighting(true, 17);
        $queryParameters = $query->getQueryParameters();
        $this->assertSame('[A]', $queryParameters['hl.simple.pre'], 'Can set highlighting field list');
        $this->assertSame('[B]', $queryParameters['hl.simple.post'], 'Can set highlighting field list');
        $this->assertEmpty($queryParameters['hl.tag.pre'], 'When the highlighting fragment size is to small hl.tag.pre should not be used because FastVectoreHighlighter will not be used');
        $this->assertEmpty($queryParameters['hl.tag.post'], 'When the highlighting fragment size is to small hl.tag.post should not be used because FastVectoreHighlighter will not be used');
    }

    /**
     * @test
     */
    public function canUseFastVectorHighlighting()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->setHighlighting(true, 200);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame('true', $queryParameters['hl.useFastVectorHighlighter'], 'Enable highlighting did not set the "hl.useFastVectorHighlighter" query parameter');
    }

    /**
     * @test
     */
    public function fastVectorHighlighterIsDisabledWhenFragSizeIsLessThen18()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->setHighlighting(true, 0);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertNull($queryParameters['hl.useFastVectorHighlighter'], 'FastVectorHighlighter was disabled but still requested');
    }

    /**
     * @test
     */
    public function canGetQueryFieldsAsStringWhenPassedFromConfiguration()
    {
        $input = 'content^10, title^5';
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['queryFields'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $output = $query->getQueryFieldsAsString();
        $expectedOutput = 'content^10.0 title^5.0';

        $this->assertSame($output, $expectedOutput, 'Passed and retrieved query fields are not the same');
    }

    /**
     * @test
     */
    public function canReturnEmptyStringAsQueryFieldStringWhenNothingWasPassed()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $output = $query->getQueryFieldsAsString();
        $expectedOutput = '';

        $this->assertSame($output, $expectedOutput, 'Unexpected output from getQueryFieldsAsString when no configuration was passed');
    }

    /**
     * @test
     */
    public function canSetMinimumMatch()
    {
        $query = $this->getInitializedTestQuery();
        $this->assertNull($query->getQueryParameter('mm'));

        // can we set a value?
        $query->setMinimumMatch('2<-35%');
        $this->assertSame('2<-35%', $query->getQueryParameter('mm'));

        // can we unset the value?
        $query->setMinimumMatch(false);
        $this->assertNull($query->getQueryParameter('mm'));
    }

    /**
     * @test
     */
    public function canSetBoostFunction()
    {
        $query = $this->getInitializedTestQuery();
        $this->assertNull($query->getQueryParameter('bf'));

        $testBoostFunction = 'recip(ms(NOW,created),3.16e-11,1,1)';
        $query->setBoostFunction($testBoostFunction);
        $this->assertSame($testBoostFunction, $query->getQueryParameter('bf'), 'bf queryParameter was not present after setting a boostFunction');

        $query->setBoostFunction(false);
        $this->assertNull($query->getQueryParameter('bf'), 'bf parameter should be null after reset');
    }

    /**
     * @test
     */
    public function canSetBoostQuery()
    {
        $query = $this->getInitializedTestQuery();
        $this->assertNull($query->getQueryParameter('bq'));

        $testBoostQuery = '(type:tt_news)^10';
        $query->setBoostQuery($testBoostQuery);
        $this->assertSame($testBoostQuery, $query->getQueryParameter('bq'), 'bq queryParameter was not present after setting a boostQuery');

        $query->setBoostQuery(false);
        $this->assertNull($query->getQueryParameter('bq'), 'bq parameter should be null after reset');
    }

    /**
     * @test
     */
    public function canReturnFieldListWhenConfigurationWithReturnFieldsWasPassed()
    {
        $input = 'abstract, price';
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['returnFields'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $output = $query->getFieldList();
        $expectedOutput = array('abstract', 'price');
        $this->assertSame($output, $expectedOutput, 'Did not parse returnsFields as expected');
    }

    /**
     * @test
     */
    public function canReturnDefaultFieldListWhenNoConfigurationWasPassed()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $output = $query->getFieldList();
        $expectedOutput = array('*', 'score');
        $this->assertSame($output, $expectedOutput, 'Did not parse returnsFields as expected');
    }

    /**
     * @test
     */
    public function canAddReturnField()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        $expectedOutput = ['*', 'score'];
        $this->assertSame($query->getFieldList(), $expectedOutput, 'Did not parse returnsFields as expected');

        $query->addReturnField('title');
        $expectedOutput = ['score', 'title'];

        // why is the * removed from the fieldList
        $this->assertSame(array_values($query->getFieldList()), $expectedOutput, 'Added return field was not in the list of valid fields');
    }

    /**
     * @test
     */
    public function canRemoveReturnField()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $initialReturnFieldList = ['title','content','url'];
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->setFieldList($initialReturnFieldList);
        $query->removeReturnField('content');

        $expectedOutput = ['title', 'url'];
        $this->assertSame(array_values($query->getFieldList()), $expectedOutput, 'content was not remove from the fieldList');
    }

    /**
     * @test
     */
    public function canSetTargetPageFromConfiguration()
    {
        $input = 4711;
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['targetPage'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $this->assertEquals($input, $query->getLinkTargetPageId());
    }

    /**
     * @test
     */
    public function canFallbackToTSFEIdWhenNoTargetPageConfigured()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray, 8000);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $this->assertEquals(8000, $query->getLinkTargetPageId());
    }

    /**
     * @test
     */
    public function canEnableFaceting()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = $this->getInitializedTestQuery();
        $query->setFaceting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('true', $queryParameters['facet'], 'Enable faceting did not set the "facet" query parameter');
    }

    /**
     * @test
     */
    public function canDisableFaceting()
    {
        $query = $this->getInitializedTestQuery();

        $query->setFaceting(true);
        $query->addQueryParameter('f.title.facet.sort', 'lex');

        $queryParameters = $query->getQueryParameters();
        $this->assertSame('true', $queryParameters['facet'], 'Enable faceting did not set the "facet" query parameter');
        $this->assertSame('lex', $queryParameters['f.title.facet.sort'], 'Facet sorting parameter should be lex');


        $query->setFaceting(false);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['facet'], 'Facet argument should be null after reset');
        $this->assertNull($queryParameters['f.title.facet.sort'], 'Facet sorting parameter should also be removed after reset');
    }

    /**
     * @test
     */
    public function canUseFacetMinCountFromConfiguration()
    {
        $input = 10;
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['minimumCount'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->setFaceting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame(10, $queryParameters['facet.mincount'], 'Can not use facet.minimumCount from configuration');
    }

    /**
     * @test
     */
    public function canUseFacetSortByFromConfiguration()
    {
        $input = 'alpha';
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['sortBy'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->setFaceting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('index', $queryParameters['facet.sort'], 'Can not use facet.sort from configuration');
    }

    /**
     * @test
     */
    public function canSetSpellChecking()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = $this->getInitializedTestQuery();
        $query->setSpellchecking(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('true', $queryParameters['spellcheck'], 'Enable spellchecking did not set the "spellcheck" query parameter');

        // can we unset it again?
        $query->setSpellchecking(false);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['spellcheck'], 'Disable spellchecking did not unset the "spellcheck" query parameter');
        $this->assertNull($queryParameters['spellcheck.maxCollationTries'], 'spellcheck.maxCollationTries was not unsetted');
    }

    /**
     * @test
     */
    public function canTestNumberOfSuggestionsToTryFromConfiguration()
    {
        $input = 9;
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['spellchecking.']['numberOfSuggestionsToTry'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->setSpellchecking(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame($input, $queryParameters['spellcheck.maxCollationTries'], 'Could not set spellcheck.maxCollationTries as expected');
    }

    /**
     * @test
     */
    public function canWriteALogForAFilterWhenLoggingIsEnabled()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['logging.']['query.']['filters'] = true;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = $this->getMockBuilder(\ApacheSolrForTypo3\Solr\Query::class)
            ->setMethods(['writeDevLog'])
            ->setConstructorArgs(['test', $fakeConfiguration])
            ->getMock();
        $query->expects($this->once())->method('writeDevLog');
        $query->addFilter('foo');
    }

    /**
     * @return array
     */
    public function escapeQueryDataProvider()
    {
        return array(
            'empty' => array('input' => '', 'expectedOutput' => ''),
            'simple' => array('input' => 'foo', 'expectedOutput' => 'foo'),
            'single quoted word' => array('input' => '"world"', 'expectedOutput' => '"world"'),
            'simple quoted phrase' => array('input' => '"hello world"', 'expectedOutput' => '"hello world"'),
            'simple quoted phrase with ~' => array('input' => '"hello world~"', 'expectedOutput' => '"hello world~"'),
            'simple phrase with ~' => array('input' => 'hello world~', 'expectedOutput' => 'hello world\~'),
            'single quote' =>  array('input' => '20" monitor', 'expectedOutput' => '20\" monitor'),
            'rounded brackets many words' => array('input' => 'hello (world)', 'expectedOutput' => 'hello \(world\)'),
            'rounded brackets one word' => array('input' => '(world)', 'expectedOutput' => '\(world\)'),
            'plus character is kept' => array('input' => 'foo +bar -world', 'expectedOutput' => 'foo +bar -world'),
            '&& character is kept' => array('input' => 'hello && world', 'expectedOutput' => 'hello && world'),
            '! character is kept' => array('input' => 'hello !world', 'expectedOutput' => 'hello !world'),
            '* character is kept' => array('input' => 'hello *world', 'expectedOutput' => 'hello *world'),
            '? character is kept' => array('input' => 'hello ?world', 'expectedOutput' => 'hello ?world'),
            'ö character is kept' => array('input' => 'schöner tag', 'expectedOutput' => 'schöner tag'),
            'numeric is kept' => array('input' => 42, 'expectedOutput' => 42),
            'combined quoted phrase' => array('input' => '"hello world" or planet', 'expectedOutput' => '"hello world" or planet'),
            'two combined quoted phrases' => array('input' => '"hello world" or "hello planet"', 'expectedOutput' => '"hello world" or "hello planet"'),
            'combined quoted phrase mixed with escape character' => array('input' => '"hello world" or (planet)', 'expectedOutput' => '"hello world" or \(planet\)')
        );
    }

    /**
     * @dataProvider escapeQueryDataProvider
     * @test
     */
    public function canEscapeAsExpected($input, $expectedOutput)
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        $output = $query->escape($input);
        $this->assertSame($expectedOutput, $output, 'Query was not escaped as expected');
    }

    /**
     * @test
     */
    public function canUseConfiguredVariantsFieldWhenVariantsAreActive()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants.'] = [
            'variantField' => 'myField'
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        $configuredField = $query->getVariantField();
        $this->assertTrue($query->getIsCollapsing(), 'Collapsing was enabled but not indicated to be enabled');
        $this->assertSame('myField', $configuredField, 'Did not use the configured collapseField');
    }

    /**
     * @test
     */
    public function variantsAreDisabledWhenNothingWasConfigured()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $this->assertFalse($query->getIsCollapsing(), 'Collapsing was not disabled by default');
    }

    /**
     * @test
     */
    public function canConvertQueryToString()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        $queryToString = (string) $query;
        $this->assertSame('test', $queryToString, 'Could not convert query to string');
    }

    /**
     * @test
     */
    public function canAddAndRemoveFilters()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        // can we add a filter?
        $query->addFilter('foo:bar');
        $filters = $query->getFilters();
        $this->assertSame(['foo:bar'], $filters, 'Could not get filters from query object');

        // can we remove the filter after adding?
        $query->removeFilter('foo');
        $filters = $query->getFilters();
        $this->assertSame([], $filters, 'Could not remove filters from query object');

        // can we add a new filter
        $query->addFilter('title:test');
        $filters = $query->getFilters();
        $this->assertSame(['title:test'], array_values($filters), 'Could not get filters from query object');

        // can we remove the filter by key?
        $key = array_search('title:test', $filters);

        // @todo analyze this: why is the key different between php5.6 and php7
        $query->removeFilterByKey($key);
        $filters = $query->getFilters();
        $this->assertSame([], $filters, 'Could not remove filters from query object by filter key');
    }

    /**
     * @test
     */
    public function canSetAndUnSetQueryType()
    {
        $query = $this->getInitializedTestQuery('test');
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['qt'], 'The qt parameter was expected to be null');

        $query->setQueryType('dismax');
        $queryParameters = $query->getQueryParameters();
        $this->assertSame('dismax', $queryParameters['qt'], 'The qt parameter was expected to be dismax');
        $this->assertSame('dismax', $query->getQueryType(), 'getQueryType should return the qt queryParameter');

            //passing false as parameter should reset the query type
        $query->setQueryType(false);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['qt'], 'The qt parameter was expected to be null after reset');
    }

    /**
     * @test
     */
    public function canSetOperator()
    {
        $query = $this->getInitializedTestQuery('test');

        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['q.op'], 'The queryParameter q.op should be null because no operator was passed');

        $query->setOperator(Query::OPERATOR_OR);
        $queryParameters = $query->getQueryParameters();
        $this->assertEquals(Query::OPERATOR_OR, $queryParameters['q.op'], 'The queryParameter q.op should be OR');

        $query->setOperator(Query::OPERATOR_AND);
        $queryParameters = $query->getQueryParameters();
        $this->assertEquals(Query::OPERATOR_AND, $queryParameters['q.op'], 'The queryParameter q.op should be AND');

        $query->setOperator(false);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['q.op'], 'The queryParameter q.op should be null because operator was resetted');
    }

    /**
     * @test
     */
    public function canSetAlternativeQuery()
    {
        // check initial value
        $query = $this->getInitializedTestQuery('test');
        $this->assertNull($query->getAlternativeQuery(), 'We expected that alternative query is initially null');

        // can we set it?
        $query->setAlternativeQuery('alt query');
        $this->assertEquals('alt query', $query->getAlternativeQuery(), 'Could not get passed alternative query');

        // can we reset it?
        $query->setAlternativeQuery(false);
        $this->assertNull($query->getAlternativeQuery(), 'We expect alternative query is null after reset');
    }

    /**
     * @test
     */
    public function canSetOmitHeaders()
    {
        // check initial value
        $query = $this->getInitializedTestQuery('test');
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['omitHeader'], 'The queryParameter omitHeader should be null because it was not');

        $query->setOmitHeader();
        $queryParameters = $query->getQueryParameters();
        $this->assertSame('true', $queryParameters['omitHeader'], 'The queryParameter omitHeader should be "true" because it was enabled');

        $query->setOmitHeader(false);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['omitHeader'], 'The queryParameter omitHeader should be null because it was resetted');
    }

    /**
     * @test
     */
    public function canSetFieldList()
    {
        // check initial value
        $query = $this->getInitializedTestQuery('test');
        $this->assertSame(['*', 'score'], $query->getFieldList(), 'FieldList initially contained unexpected values');

        // set from string
        $query->setFieldList('content, title');
        $this->assertSame(['content', 'title'], $query->getFieldList(), 'Can not set fieldList from string');

        // set from array
        $query->setFieldList(['content', 'title']);
        $this->assertSame(['content', 'title'], $query->getFieldList(), 'Can not set fieldList from array');

        // other arguments throws exception
        $this->setExpectedException(\UnexpectedValueException::class);
        $query->setFieldList(12);

        // default value is *, score
        $query->setFieldList();
        $this->assertSame(['*', 'score'], $query->getFieldList(), 'FieldList should be setted to default when calling with no arguments');
    }

    /**
     * @test
     */
    public function canSetSorting()
    {
        // check initial value
        $query = $this->getInitializedTestQuery('test');
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['sort'], 'Sorting should be null at the beginning');

        // can set a field and direction combination
        $query->setSorting('title desc');
        $queryParameters = $query->getQueryParameters();
        $this->assertSame('title desc', $queryParameters['sort'], 'Could not set sorting');

        // can reset
        $query->setSorting(false);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['sort'], 'Sorting should be null after reset');

        // when relevance is getting passed it is the same as we have no
        // sorting because this is a "virtual" value
        $query->setSorting('relevance desc');
        $queryParameters = $query->getQueryParameters();
        $this->assertEquals('', $queryParameters['sort'], 'Sorting should be null after reset');
    }

    /**
     * @test
     */
    public function canAddSortField()
    {
        $query = $this->getInitializedTestQuery('test');

        // do we have the expected default value?
        $sortingFields = $query->getSortingFields();
        $this->assertSame([], $sortingFields, 'Unexpected initial sorting fields');

        // can we set an ascending sorting?
        $query->addSortField('title', Query::SORT_ASC);
        $sortingFields = $query->getSortingFields();
        $this->assertSame(['title' => Query::SORT_ASC], $sortingFields, 'Could not add ascending sort field');

        // can we in addition add an ascending sorting
        $query->addSortField('price', Query::SORT_DESC);
        $sortingFields = $query->getSortingFields();
        $this->assertSame(['title' => Query::SORT_ASC, 'price' => Query::SORT_DESC],
                            $sortingFields, 'Could not add descending sort field');

        // do we get an exception when an invalid sort direction is getting passed?
        $this->setExpectedException(\InvalidArgumentException::class);
        $query->addSortField('width', 'arsc');
    }

    /**
     * @test
     */
    public function canSetQueryElevation()
    {
        $query = $this->getInitializedTestQuery('test');

        $this->assertNull($query->getQueryParameter('enableElevation'));
        $this->assertNull($query->getQueryParameter('forceElevation'));
        $this->assertNotContains('isElevated:[elevated]', $query->getFieldList());

        // do we get the expected default values, when calling setQueryElevantion with no arguments?
        $query->setQueryElevation();
        $this->assertSame('true', $query->getQueryParameter('enableElevation'), 'enabledElevation was not set after enabling elevation');
        $this->assertSame('true', $query->getQueryParameter('forceElevation'), 'forceElevation was not set after enabling elevation');
        $this->assertContains('isElevated:[elevated]', $query->getFieldList(), 'isElevated should be in the list of return fields');

        // can we reset the elevantion?
        $query->setQueryElevation(false);
        $this->assertSame('false', $query->getQueryParameter('enableElevation'));
        $this->assertNull($query->getQueryParameter('forceElevation'));
        $this->assertNotContains('isElevated:[elevated]', $query->getFieldList());
    }

    /**
     * @param string $queryString
     * @param TypoScriptConfiguration $solrConfiguration
     * @return Query
     */
    protected function getInitializedTestQuery($queryString = 'test', TypoScriptConfiguration $solrConfiguration = null)
    {
        $query = GeneralUtility::makeInstance(Query::class, $queryString, $solrConfiguration);
        return $query;
    }
}
