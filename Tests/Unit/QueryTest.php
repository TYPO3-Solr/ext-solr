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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\EscapeService;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\PhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\ReturnFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\TrigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
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
    public function canSetQueryString()
    {
        $query = $this->getInitializedTestQuery('i like solr');
        $this->assertSame('i like solr', $query->getQueryString(), 'Can not set and get query string');
    }

    /**
     * @test
     */
    public function queryStringCanBeOverwrittenWhenUseQueryStringWasSet()
    {
        $query = $this->getInitializedTestQuery('i like solr');
        $query->useRawQueryString(true);
        $query->setQueryString('i like SOLR!');
        $this->assertSame('i like SOLR!', $query->getQueryString(), 'Can not set and get query string');
    }

    /**
     * @test
     */
    public function queryStringCanNotBeOverwrittenWhenUseQueryStringWasSetToFalse()
    {
        $query = $this->getInitializedTestQuery('i like solr');
        $query->useRawQueryString(false);
        $query->setQueryString('i like SOLR!');
        $this->assertSame('i like solr', $query->getQueryString(), 'Can not set and get query string');
    }

    /**
     * @test
     */
    public function canSetPage()
    {
        $query = $this->getInitializedTestQuery('i like solr');
        $query->setPage(10);

        $this->assertSame(10, $query->getPage(), 'Can not set and get page');
    }

    /**
     * @test
     */
    public function noFiltersAreSetAfterInitialization()
    {
        $query = $this->getInitializedTestQuery();
        $filters = $query->getFilters()->getValues();


        $this->assertCount(
            0,
            $filters,
            'Query already contains filters after intialization.'
        );
    }

    /**
     * @test
     */
    public function addsCorrectAccessFilterForAnonymousUser()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups([-1, 0]);
        $filters = $query->getFilters()->getValues();

        $this->assertContains(
            '{!typo3access}-1,0',
            $filters,
            'Access filter not found in [' . implode('], [', (array)$filters) . ']'
        );
    }

    /**
     * @test
     */
    public function grantsAccessToGroupZeroIfNoGroupsProvided()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups([]);
        $filters = $query->getFilters()->getValues();

        $this->assertContains(
            '{!typo3access}0',
            $filters,
            'Access filter not found in [' . implode('], [', (array)$filters) . ']'
        );
    }

    /**
     * @test
     */
    public function grantsAccessToGroupZeroIfZeroNotProvided()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups([5]);
        $filters = $query->getFilters()->getValues();

        $this->assertContains(
            '{!typo3access}0,5',
            $filters,
            'Access filter not found in [' . implode('], [', (array)$filters) . ']'
        );
    }

    /**
     * @test
     */
    public function filtersDuplicateAccessGroups()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups([1, 1]);
        $filters = $query->getFilters()->getValues();

        $this->assertContains(
            '{!typo3access}0,1',
            $filters,
            'Access filter not found in [' . implode('], [', (array)$filters) . ']'
        );
    }

    /**
     * @test
     */
    public function allowsOnlyOneAccessFilter()
    {
        $query = $this->getInitializedTestQuery();
        $query->setUserAccessGroups([1]);
        $query->setUserAccessGroups([2]);
        $filters = $query->getFilters()->getValues();

        $this->assertSame(
            count($filters),
            1,
            'Too many filters in [' . implode('], [', (array)$filters) . ']'
        );

        $parameter = $query->getQueryParameters();
        $this->assertSame('{!typo3access}0,2', $parameter['fq'][0], 'Unexpected filter query');
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
        $query->getGrouping()->setIsEnabled(true);

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
        $query->getGrouping()->setIsEnabled(false);

        $queryParameters = $query->getQueryParameters();

        foreach ($queryParameters as $queryParameter => $value) {
            $this->assertTrue(
                !GeneralUtility::isFirstPartOfStr($queryParameter, 'group'),
                'Query contains grouping parameter "' . $queryParameter . '"'
            );
        }
    }

    /**
     * @test
     */
    public function canSetNumberOfGroups()
    {
        $query = $this->getInitializedTestQuery('test');
        $query->getGrouping()->setNumberOfGroups(2);
        $this->assertSame(2, $query->getGrouping()->getNumberOfGroups(), 'Could not set and get number of groups');
    }

    /**
     * @test
     */
    public function canAddGroupField()
    {
        $query = $this->getInitializedTestQuery('test');
        $this->assertSame([], $query->getGrouping()->getFields(), 'Unexpected default state of groupFields');
        $query->getGrouping()->addField('category_s');
        $this->assertSame(['category_s'], $query->getGrouping()->getFields(), 'groupFields has unexpected state after adding a group field');
    }

    /**
     * @test
     */
    public function canGetGroupSorting()
    {
        $query = $this->getInitializedTestQuery('test');
        $this->assertSame([], $query->getGrouping()->getSortings(), 'By default getGroupSortings should return an empty array');

        $query->getGrouping()->addSorting('price_f');
        $query->getGrouping()->addSorting('author_s');

        $this->assertSame(['price_f', 'author_s'], $query->getGrouping()->getSortings(), 'Can not get groupSortings after adding');
    }

    /**
     * @test
     */
    public function canSetNumberOfResultsByGroup()
    {
        $query = $this->getInitializedTestQuery('group test');
        $initialValue = $query->getGrouping()->getResultsPerGroup();
        $this->assertSame(1, $initialValue);

        $query->getGrouping()->setResultsPerGroup(22);
        $this->assertSame(22, $query->getGrouping()->getResultsPerGroup(), 'Can not set number of results per group');
    }

    /**
     * @test
     */
    public function canAddGroupQuery()
    {
        $query = $this->getInitializedTestQuery('group test');
        $initialGroupQueries = $query->getGrouping()->getQueries();
        $this->assertSame([], $initialGroupQueries, 'Group queries should be an empty array at the beginning');
        $query->getGrouping()->addQuery('price:[* TO 500]');
        $this->assertSame(['price:[* TO 500]'], $query->getGrouping()->getQueries(), 'Could not retrieve group queries after adding one');
    }

    // highlighting
    /**
     * @test
     */
    public function canEnableHighlighting()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = $this->getInitializedTestQuery();
        $query->getHighlighting()->setIsEnabled(true);
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
        $query->getHighlighting()->setIsEnabled(true);

        $queryParameters = $query->getQueryParameters();
        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');

        $query->getHighlighting()->setIsEnabled(false);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['hl'], 'Could not disable highlighting');
    }

    /**
     * @test
     */
    public function canSetHighlightingFieldList()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting.']['highlightFields'] = 'title';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->getHighlighting()->setIsEnabled(true);
        $queryParameters = $query->getQueryParameters();
        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame('title', $queryParameters['hl.fl'], 'Can set highlighting field list');
    }

    /**
     * @test
     */
    public function canPassCustomWrapForHighlighting()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting.']['wrap'] = '[A]|[B]';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->getHighlighting()->setIsEnabled(true);

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
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting.']['wrap'] = '[A]|[B]';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        // fragSize 10 is to small for FastVectorHighlighter
        $query->getHighlighting()->setIsEnabled(true);
        $query->getHighlighting()->setFragmentSize(17);

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
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->getHighlighting()->setIsEnabled(true);
        $query->getHighlighting()->setFragmentSize(200);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame('true', $queryParameters['hl.useFastVectorHighlighter'], 'Enable highlighting did not set the "hl.useFastVectorHighlighter" query parameter');
    }

    /**
     * @test
     */
    public function fastVectorHighlighterIsDisabledWhenFragSizeIsLessThen18()
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->getHighlighting()->setIsEnabled(true);
        $query->getHighlighting()->setFragmentSize(0);
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
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['queryFields'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $output = $query->getQueryFields()->toString();
        $expectedOutput = 'content^10.0 title^5.0';

        $this->assertSame($output, $expectedOutput, 'Passed and retrieved query fields are not the same');
    }

    /**
     * @test
     */
    public function canReturnEmptyStringAsQueryFieldStringWhenNothingWasPassed()
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $output = $query->getQueryFields()->toString();
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
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['returnFields'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        $output = $query->getReturnFields()->getValues();
        $expectedOutput = ['abstract', 'price'];

        $this->assertSame($output, $expectedOutput, 'Did not parse returnsFields as expected');
    }

    /**
     * @test
     */
    public function canReturnDefaultFieldListWhenNoConfigurationWasPassed()
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $output = $query->getReturnFields()->getValues();
        $expectedOutput = ['*', 'score'];

        $this->assertSame($output, $expectedOutput, 'Did not parse returnsFields as expected');
    }

    /**
     * @test
     */
    public function canAddReturnField()
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        $expectedOutput = ['*', 'score'];
        $this->assertSame($query->getReturnFields()->getValues(), $expectedOutput, 'Did not parse returnsFields as expected');

        $query->getReturnFields()->add('title');
        $expectedOutput = ['score', 'title'];

        // why is the * removed from the fieldList
        $this->assertSame($expectedOutput, $query->getReturnFields()->getValues(), 'Added return field was not in the list of valid fields');
    }

    /**
     * @test
     */
    public function canRemoveReturnField()
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $initialReturnFieldList = ['title','content','url'];
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->setReturnFields(ReturnFields::fromArray($initialReturnFieldList));
        $query->getReturnFields()->remove('content');

        $expectedOutput = ['title', 'url'];
        $this->assertSame($expectedOutput, $query->getReturnFields()->getValues(), 'content was not remove from the fieldList');
    }

    /**
     * @test
     */
    public function canSetTargetPageFromConfiguration()
    {
        $input = 4711;
        $fakeConfigurationArray = [];
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
        $fakeConfigurationArray = [];
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
        $query->getFaceting()->setIsEnabled(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('true', $queryParameters['facet'], 'Enable faceting did not set the "facet" query parameter');
    }

    /**
     * @test
     */
    public function canDisableFaceting()
    {
        $query = $this->getInitializedTestQuery();

        $query->getFaceting()->setIsEnabled(true);
        $query->getFaceting()->addAdditionalParameter('f.title.facet.sort', 'lex');

        $queryParameters = $query->getQueryParameters();
        $this->assertSame('true', $queryParameters['facet'], 'Enable faceting did not set the "facet" query parameter');
        $this->assertSame('lex', $queryParameters['f.title.facet.sort'], 'Facet sorting parameter should be lex');
        $query->getFaceting()->setIsEnabled(false);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['facet'], 'Facet argument should be null after reset');
        $this->assertNull($queryParameters['f.title.facet.sort'], 'Facet sorting parameter should also be removed after reset');
    }

    /**
     * @test
     */
    public function canAddFacetField()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $facetFields = $query->getQueryParameter('facet.field');
        $this->assertNull($facetFields, 'facet.field query parameter was expected to be null after init.');

        // after adding a few facet fields we should be able to retrieve them
        $query->getFaceting()->setIsEnabled(true);
        $query->getFaceting()->addField('color_s');
        $query->getFaceting()->addField('price_f');

        $facetFields = $query->getQueryParameter('facet.field');
        $this->assertSame(['color_s', 'price_f'], $facetFields, 'facet.field should not be empty after adding a few fields.');
    }

    /**
     * @test
     */
    public function canSetFacetFields()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        $fakeFields = ['lastname_s', 'role_s'];

        $query->getFaceting()->setIsEnabled(true);
        $query->getFaceting()->setFields($fakeFields);
        $retrievedFields = $query->getQueryParameter('facet.field');

        $this->assertSame(['lastname_s', 'role_s'], $retrievedFields, 'Could not use setFacetFields to pass facet fields');
    }

    /**
     * @test
     */
    public function canUseFacetMinCountFromConfiguration()
    {
        $input = 10;
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['minimumCount'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->getFaceting()->setIsEnabled(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame(10, $queryParameters['facet.mincount'], 'Can not use facet.minimumCount from configuration');
    }

    /**
     * @test
     */
    public function canUseFacetSortByFromConfiguration()
    {
        $input = 'alpha';
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['sortBy'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $query->getFaceting()->setIsEnabled(true);
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
    public function noSiteHashFilterIsSetWhenWildcardIsPassed()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = $this->getInitializedTestQuery();
        $query->setSiteHashFilter('*');
        $filters = $query->getFilters()->getValues();
        $this->assertEmpty($filters, 'The filters should be empty when a wildcard sitehash was passed');
    }

    /**
     * @test
     */
    public function filterIsAddedWhenAllowedSiteIsPassed()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = $this->getInitializedTestQuery();
        $query->setSiteHashFilter('solrtest.local');
        $filters = $query->getFilters()->getValues();

        $this->assertCount(1, $filters, 'We expected that one filter was added');

        $firstFilter= $filters[0];
        $this->assertContains('siteHash:', $firstFilter, 'The filter was expected to start with siteHash*');
    }

    /**
     * @test
     */
    public function canTestNumberOfSuggestionsToTryFromConfiguration()
    {
        $input = 9;
        $fakeConfigurationArray = [];
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
    public function canSetCollapsing()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);
        $filters = $query->getFilters()->getValues();
        $this->assertNull($filters['collapsing'], 'No collapsing filter should be set without collpasing');

        // can we enable collapsing
        $query->setCollapsing(true);
        $filters = $query->getFilters()->getValues();
        $this->assertSame($filters['collapsing'], '{!collapse field=variantId}', 'No filter should be set without collpasing');

        // can we disable it again
        $query->setCollapsing(false);
        $filters = $query->getFilters()->getValues();
        $this->assertNull($filters['collapsing'], 'No collapsing filter should be set after disables collpasing');
    }

    /**
     * @test
     */
    public function canAddAndRemoveFilters()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        // can we add a filter?
        $query->getFilters()->add('foo:bar');
        $filters = $query->getFilters()->getValues();

        $this->assertSame(['foo:bar'], $filters, 'Could not get filters from query object');

        // can we remove the filter after adding?
        $query->getFilters()->removeByFieldName('foo');
        $filters = $query->getFilters()->getValues();
        $this->assertSame([], $filters, 'Could not remove filters from query object');

        // can we add a new filter
        $query->getFilters()->add('title:test');
        $filters = $query->getFilters()->getValues();
        $this->assertSame(['title:test'], array_values($filters), 'Could not get filters from query object');

        // can we remove the filter by name?
        $name = array_search('title:test', $filters);

        // @todo analyze this: why is the key different between php5.6 and php7
        $query->getFilters()->removeByName($name);
        $filters = $query->getFilters()->getValues();
        $this->assertSame([], $filters, 'Could not remove filters from query object by filter key');
    }

    /**
     * @test
     */
    public function canRemoveFilterByValue()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestQuery('test', $fakeConfiguration);

        // can we add a filter?
        $query->getFilters()->add('foo:bar');
        $filters = $query->getFilters()->getValues();
        $this->assertSame(['foo:bar'], $filters, 'Could not get filters from query object');

        $query->getFilters()->removeByValue('foo:bar');
        $filters = $query->getFilters()->getValues();
        $this->assertSame([], $filters, 'Filters are not empty after removing the last one');
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
    public function canSetReturnFields()
    {
        // check initial value
        $query = $this->getInitializedTestQuery('test');
        $this->assertSame(['*', 'score'], $query->getReturnFields()->getValues(), 'FieldList initially contained unexpected values');

        // set from string
        $query->setReturnFields(ReturnFields::fromString('content, title'));
        $this->assertSame(['content', 'title'], $query->getReturnFields()->getValues(), 'Can not set fieldList from string');

        // set from array
        $query->setReturnFields(ReturnFields::fromArray(['content', 'title']));
        $this->assertSame(['content', 'title'], $query->getReturnFields()->getValues(), 'Can not set fieldList from array');
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
    public function canSetQueryElevation()
    {
        $query = $this->getInitializedTestQuery('test');

        $this->assertNull($query->getQueryParameter('enableElevation'));
        $this->assertNull($query->getQueryParameter('forceElevation'));
        $this->assertNotContains('isElevated:[elevated]', $query->getReturnFields()->getValues());

        // do we get the expected default values, when calling setQueryElevantion with no arguments?
        $query->setQueryElevation();
        $this->assertSame('true', $query->getQueryParameter('enableElevation'), 'enabledElevation was not set after enabling elevation');
        $this->assertSame('true', $query->getQueryParameter('forceElevation'), 'forceElevation was not set after enabling elevation');
        $this->assertContains('isElevated:[elevated]', $query->getReturnFields()->getValues(), 'isElevated should be in the list of return fields');

        // can we reset the elevantion?
        $query->setQueryElevation(false);
        $this->assertSame('false', $query->getQueryParameter('enableElevation'));
        $this->assertNull($query->getQueryParameter('forceElevation'));
        $this->assertNotContains('isElevated:[elevated]', $query->getReturnFields()->getValues());
    }

    /**
     * @test
     */
    public function forceElevationIsFalseWhenForcingToFalse()
    {
        $query = $this->getInitializedTestQuery('test');
        $this->assertNull($query->getQueryParameter('enableElevation'));
        $this->assertNull($query->getQueryParameter('forceElevation'));

        $query->setQueryElevation(true, false);

        $this->assertSame('true', $query->getQueryParameter('enableElevation'), 'enabledElevation was not set after enabling elevation');
        $this->assertSame('false', $query->getQueryParameter('forceElevation'), 'forceElevation was not false after forcing');
    }

    /**
     * @test
     */
    public function canBuildExpectedQueryUrlFromCombinedQuery()
    {
        $query = $this->getInitializedTestQuery('hello world');

        $query->getFaceting()->setIsEnabled(true);
        $query->getFaceting()->addField('content');
        $query->getFaceting()->addField('type');
        $query->getFilters()->add('color:red');
        $query->getReturnFields()->add('title');
        $query->getFaceting()->addField('color');
        $query->setCollapsing(true);
        $query->setUserAccessGroups([1,2,3]);
        $query->setPage(3);

        $parameters = $query->getQueryParameters();

        $this->assertSame('score,title', $parameters['fl']);
        $this->assertSame('color:red', $parameters['fq'][0]);
        $this->assertSame('{!collapse field=variantId}', $parameters['fq'][1]);
        $this->assertSame('{!typo3access}0,1,2,3', $parameters['fq'][2]);
        $this->assertSame('content', $parameters['facet.field'][0]);
        $this->assertSame('type', $parameters['facet.field'][1]);
        $this->assertSame('color', $parameters['facet.field'][2]);

        $this->assertEmpty($parameters['qf'], 'No query fields have been set');
    }

    /**
     * @test
     */
    public function canSetQueryFieldsFromString()
    {
        $query = $this->getInitializedTestQuery('foo bar');
        $query->setQueryFields(QueryFields::fromString('content^100.0, title^10.0'));
        $queryFields = $query->getQueryFields()->toString();

        // the , delimiter is removed
        $this->assertSame('content^100.0 title^10.0', $queryFields, 'Can not set and get query fields');
    }

    /**
     * @test
     */
    public function canSetQueryFields()
    {
        $query = $this->getInitializedTestQuery('foo bar');
        $this->assertSame('', $query->getQueryFields()->toString(), 'QueryFields are not empty by default');

        $query->getQueryFields()->set('content', 10);
        $query->getQueryFields()->set('title', 11);

        $this->assertSame('content^10.0 title^11.0', $query->getQueryFields()->toString());

        // overwrite the boost of title
        $query->getQueryFields()->set('title', 9);

        $parameters = $query->getQueryParameters();
        $this->assertSame('content^10.0 title^9.0', $parameters['qf'], 'qf parameter not set in QueryParameters');
    }

    /**
     * @test
     */
    public function canSetPhraseFieldsFromString()
    {
        $query = $this->getInitializedTestQuery('foo bar');
        $query->setPhraseFields(PhraseFields::fromString('content^100.0, title^10.0'));
        $phraseFields = $query->getPhraseFields()->toString();

        // the , delimiter is removed
        $this->assertSame('content^100.0 title^10.0', $phraseFields, 'Can not set and get phrase fields');
    }

    /**
     * @test
     */
    public function canSetPhraseFields()
    {
        $query = $this->getInitializedTestQuery('foo bar');
        $this->assertSame('', $query->getPhraseFields()->toString(), 'Phrase Fields must be empty by default');

        $query->getPhraseFields()->add('content', 10);
        $query->getPhraseFields()->add('title', 11);

        $this->assertSame('content^10.0 title^11.0', $query->getPhraseFields()->toString());

        // overwrite the boost of title
        $query->getPhraseFields()->add('title', 9);
        $this->assertSame('content^10.0 title^9.0', $query->getPhraseFields()->toString());
    }

    /**
     * @test
     */
    public function phraseFieldsAreNotSetInUrlQueryIfPhraseSearchIsDisabled()
    {
        $query = $this->getInitializedTestQuery('foo bar');
        $query->getPhraseFields()->add('content', 10);
        $query->getPhraseFields()->add('title', 11);
        $parameters = $query->getQueryParameters();
        $this->assertNull($parameters['pf'], 'pf parameter must be empty(not set) if phrase search is disabled');
    }

    /**
     * @test
     */
    public function phraseFieldsAreSetInUrlQueryIfPhraseSearchIsEnabled()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['phrase'] = 1;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestQuery('foo bar', $fakeConfiguration);

        $query->getPhraseFields()->add('content', 10);
        $query->getPhraseFields()->add('title', 11);
        $parameters = $query->getQueryParameters();
        $this->assertSame('content^10.0 title^11.0', $parameters['pf'], 'pf parameters must be set if phrase search is enabled');
    }

    /**
     * @test
     */
    public function bigramPhraseFieldsAreNotSetInUrlQueryIfBigramPhraseSearchIsDisabled()
    {
        $query = $this->getInitializedTestQuery('foo bar baz');
        $query->getBigramPhraseFields()->add('content', 10);
        $query->getBigramPhraseFields()->add('title', 11);
        $parameters = $query->getQueryParameters();
        $this->assertNull($parameters['pf2'], 'pf2 parameter must be empty(not set) if phrase search is disabled');
    }

    /**
     * @test
     */
    public function bigramPhraseFieldsAreSetInUrlQueryIfBigramPhraseSearchIsEnabled()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['bigramPhrase'] = 1;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestQuery('foo bar', $fakeConfiguration);

        $query->getBigramPhraseFields()->add('content', 10);
        $query->getBigramPhraseFields()->add('title', 11);
        $parameters = $query->getQueryParameters();
        $this->assertSame('content^10.0 title^11.0', $parameters['pf2'], 'pf2 parameters must be set if bigram phrase search is enabled');
    }

    /**
     * @test
     */
    public function trigramPhraseFieldsAreNotSetInUrlQueryIfTrigramPhraseSearchIsDisabled()
    {
        $query = $this->getInitializedTestQuery('foo bar baz foobar barbaz');
        $query->getTrigramPhraseFields()->add('content', 10);
        $query->getTrigramPhraseFields()->add('title', 11);
        $parameters = $query->getQueryParameters();
        $this->assertNull($parameters['pf3'], 'pf3 parameter must be empty(not set) if phrase search is disabled');
    }

    /**
     * @test
     */
    public function trigramPhraseFieldsAreSetInUrlQueryIfTrigramPhraseSearchIsEnabled()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['trigramPhrase'] = 1;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestQuery('foo bar', $fakeConfiguration);

        $query->getTrigramPhraseFields()->add('content', 10);
        $query->getTrigramPhraseFields()->add('title', 11);
        $parameters = $query->getQueryParameters();
        $this->assertSame('content^10.0 title^11.0', $parameters['pf3'], 'pf3 parameters must be set if trigram phrase search is enabled');
    }

    /**
     * @test
     */
    public function setDebugMode()
    {
        $query = $this->getInitializedTestQuery();

        $parameter = $query->getQueryParameters();
        $this->assertEmpty($parameter['debugQuery'], 'Debug query should be disabled by default');
        $this->assertEmpty($parameter['echoParams'], 'Debug query should be disabled by default');

        $query->setDebugMode();

        $parameter = $query->getQueryParameters();
        $this->assertSame('true', $parameter['debugQuery'], 'Debug query should be disabled by default');
        $this->assertSame('all', $parameter['echoParams'], 'Debug query should be disabled by default');

        $query->setDebugMode(false);
        $parameter = $query->getQueryParameters();
        $this->assertEmpty($parameter['debugQuery'], 'Can not unset debug mode');
        $this->assertEmpty($parameter['echoParams'], 'Can not unset debug mode');
    }

    /**
     * @test
     */
    public function canGetResultsPerPage()
    {
        $query = $this->getInitializedTestQuery('foo bar');

        // is this wanted behaviour?
        $this->assertSame(null, $query->getResultsPerPage());

        $query->setResultsPerPage(10);
        $this->assertSame(10, $query->getResultsPerPage());

        $query->setResultsPerPage(-1);
        $this->assertSame(0, $query->getResultsPerPage());

        // setNumberOfGroups implicitly changes the results per page since the row argument is used for the number of groups
        $query->getGrouping()->setIsEnabled(true);
        $query->getGrouping()->setNumberOfGroups(5);
        $this->assertSame(5, $query->getResultsPerPage());
    }

    /**
     * @test
     */
    public function addingQueriesToGroupingAddsToRightGroupingParameter()
    {
        $query = $this->getInitializedTestQuery('group test');
        $query->getGrouping()->setIsEnabled(true);
        $query->getGrouping()->addQuery('price:[* TO 500]');
        $query->getGrouping()->addQuery('someField:someValue');

        $parameters = $query->getQueryParameters();
        $this->assertSame(['price:[* TO 500]', 'someField:someValue'], $parameters['group.query'], 'Could not add group queries properly');
    }

    /**
     * @test
     */
    public function addingSortingsToGroupingAddsToRightGroupingParameter()
    {
        $query = $this->getInitializedTestQuery('group test');
        $query->getGrouping()->setIsEnabled(true);
        $query->getGrouping()->addSorting('price_f');
        $query->getGrouping()->addSorting('title desc');

        $parameters = $query->getQueryParameters();
        $this->assertSame(['price_f', 'title desc'], $parameters['group.sort'], 'Could not add group sortings properly');
    }

    /**
     * @test
     */
    public function addingFieldsToGroupingAddsToRightGroupingParameter()
    {
        $query = $this->getInitializedTestQuery('group test');
        $query->getGrouping()->setIsEnabled(true);
        $query->getGrouping()->addField('price_f');
        $query->getGrouping()->addField('category_s');

        $parameters = $query->getQueryParameters();
        $this->assertSame(['price_f', 'category_s'], $parameters['group.field'], 'Could not add group fields properly');
    }

    /**
     * @test
     */
    public function canDisablingGroupingRemoveTheGroupSorting()
    {
        $query = $this->getInitializedTestQuery('foo bar');
        $query->getGrouping()->setIsEnabled(true);

        $parameters = $query->getQueryParameters();

        $this->assertSame($parameters['group'], 'true');
        $this->assertSame($parameters['group.format'], 'grouped');
        $this->assertSame($parameters['group.ngroups'], 'true');

        $query->getGrouping()->addSorting('title desc');
        $parameters = $query->getQueryParameters();
        $this->assertSame($parameters['group.sort'][0], 'title desc', 'Group sorting was not added');
        $this->assertEmpty($parameters['group.field'], 'No field was passed, so it should not be set');
        $this->assertEmpty($parameters['group.query'], 'No query was passed, so it should not be set');

        $query->getGrouping()->setIsEnabled(false);
        $parameters = $query->getQueryParameters();

        $this->assertEmpty($parameters['group.sort'], 'Grouping parameters should be removed');
        $this->assertEmpty($parameters['group'], 'Grouping parameters should be removed');
        $this->assertEmpty($parameters['group.format'], 'Grouping parameters should be removed');
        $this->assertEmpty($parameters['group.ngroups'], 'Grouping parameters should be removed');
    }

    /**
     * @param string $queryString
     * @param TypoScriptConfiguration $solrConfiguration
     * @return Query
     */
    protected function getInitializedTestQuery($queryString = 'test', TypoScriptConfiguration $solrConfiguration = null)
    {
        $siteHashServiceMock = $this->getDumbMock(SiteHashService::class);
        $solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);

        // since the escape service does not have any dependencies and is just doing some simple escape logic we pass a real instance
        $escapeService = new EscapeService();

        $query = new Query($queryString, $solrConfiguration, $siteHashServiceMock, $escapeService, $solrLogManagerMock);
        return $query;
    }
}
