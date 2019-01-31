<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Query;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Elevation;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\FieldCollapsing;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Filters;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Highlighting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Operator;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\PhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\ReturnFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Sorting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Spellchecking;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\TrigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Solarium\QueryType\Select\RequestBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class QueryBuilderTest extends UnitTest
{

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @var SolrLogManager
     */
    protected $loggerMock;

    /**
     * @var
     */
    protected $siteHashServiceMock;

    /**
     * @var QueryBuilder
     */
    protected $builder;

    public function setUp()
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->loggerMock = $this->getDumbMock(SolrLogManager::class);
        $this->siteHashServiceMock = $this->getDumbMock(SiteHashService::class);
        $this->builder = new QueryBuilder($this->configurationMock, $this->loggerMock, $this->siteHashServiceMock);
    }

    /**
     * @param Query $searchQuery
     * @return array
     */
    protected function getAllQueryParameters(Query $searchQuery)
    {
        $requestBuilder = new RequestBuilder();
        $request = $requestBuilder->build($searchQuery);
        return $request->getParams();
    }

    /**
     * @param string $queryString
     * @param TypoScriptConfiguration|null $fakeConfiguration
     * @return SearchQuery
     */
    protected function getInitializedTestSearchQuery(string $queryString = '', TypoScriptConfiguration $fakeConfiguration = null): SearchQuery
    {
        $builder = new QueryBuilder($fakeConfiguration, $this->loggerMock);
        return $builder->buildSearchQuery($queryString);
    }

    /**
     * @test
     */
    public function buildSearchQueryPassesQueryString()
    {
        $query = $this->builder->buildSearchQuery('one');
        $this->assertSame('one', (string)$query, 'Query has unexpected value, when casted to string');
    }

    /**
     * @test
     */
    public function buildSearchQueryPassesDefaultPerPage()
    {
        $query = $this->builder->buildSearchQuery('one');
        $this->assertSame(10, $query->getRows(), 'Query was not created with default perPage value');

    }

    /**
     * @test
     */
    public function buildSearchQueryPassesCustomPerPage()
    {
        $query = $this->builder->buildSearchQuery('one', 22);
        $this->assertSame(22, $query->getRows(), 'Query was not created with default perPage value');
    }

    /**
     * @test
     */
    public function buildSearchQueryInitializesQueryFieldsFromConfiguration()
    {
        $this->configurationMock->expects($this->once())->method('getSearchQueryQueryFields')->willReturn('title^10, content^123');
        $query = $this->builder->buildSearchQuery('foo');
        $this->assertSame('title^10.0 content^123.0', $this->getAllQueryParameters($query)['qf'], 'The queryFields have not been initialized as expected');
    }

    /**
     * @test
     */
    public function buildSearchQueryInitializesTrigramPhraseFields()
    {
        $this->configurationMock->expects($this->once())->method('getTrigramPhraseSearchIsEnabled')->willReturn(true);

        $this->configurationMock->expects($this->once())->method('getSearchQueryTrigramPhraseFields')->willReturn('content^10.0, title^10.0');
        $query = $this->builder->buildSearchQuery('trigram');
        $this->assertSame('content^10.0 title^10.0', $this->getAllQueryParameters($query)['pf3'], 'The trigramPhraseFields have not been initialized as expected');
    }

    /**
     * @test
     */
    public function buildSearchIsSettingWildCardQueryOnInitializeWithEmptyQuery()
    {
        $this->configurationMock->expects($this->once())->method('getSearchInitializeWithEmptyQuery')->willReturn(true);
        $query = $this->builder->buildSearchQuery('initializeWithEmpty');
        $this->assertSame('*:*', $this->getAllQueryParameters($query)['q.alt'], 'The alterativeQuery has not been initialized as expected');
    }

    /**
     * @test
     */
    public function buildSearchIsSettingWildCardQueryOnInitializeWithAllowEmptyQuery()
    {
        $this->configurationMock->expects($this->once())->method('getSearchQueryAllowEmptyQuery')->willReturn(true);
        $query = $this->builder->buildSearchQuery('initializeWithEmpty');
        $this->assertSame('*:*', $this->getAllQueryParameters($query)['q.alt'], 'The alterativeQuery has not been initialized as expected');
    }

    /**
     * @test
     */
    public function buildSearchIsSettingQuerystringForConfiguredInitialQuery()
    {
        $this->configurationMock->expects($this->exactly(2))->method('getSearchInitializeWithQuery')->willReturn('myinitialsearch');
        $query = $this->builder->buildSearchQuery('initializeWithEmpty');
        $this->assertSame('myinitialsearch', $this->getAllQueryParameters($query)['q.alt'], 'The alterativeQuery has not been initialized from a configured initial query');
    }

    /**
     * @test
     */
    public function buildSearchIsSettingConfiguredAdditionalFilters()
    {
        $this->configurationMock->expects($this->any())->method('getSearchQueryFilterConfiguration')->willReturn(['noPage' => '-type:pages']);
        $query = $this->builder->buildSearchQuery('applies configured filters');
        $filterValue = $this->getAllQueryParameters($query)['fq'];
        $filterArray = explode(" ", $filterValue);

        $this->assertCount(1, $filterArray, 'Unpexcted amount of filters for query');
        $this->assertSame('-type:pages', $filterValue, 'First filter has unexpected value');
    }

    /**
     * @test
     */
    public function buildSearchIsSettingNoAlternativeQueryByDefault()
    {
        $query = $this->builder->buildSearchQuery('initializeWithEmpty');
        $this->assertNull($this->getAllQueryParameters($query)['q.alt'], 'The alterativeQuery is not null when nothing was set');
    }

    /**
     * @test
     */
    public function canEnableHighlighting()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery */
        $query = $this->getInitializedTestSearchQuery();
        $highlighting = new Highlighting();
        $highlighting->setIsEnabled(true);

        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame(200, $queryParameters['hl.fragsize'], 'hl.fragsize was not set to the default value of 200');
    }

    /**
     * @test
     */
    public function canDisableHighlighting()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery */
        $query = $this->getInitializedTestSearchQuery();
        $highlighting = new Highlighting();
        $highlighting->setIsEnabled(true);

        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');

        $highlighting->setIsEnabled(false);
        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

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

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        $highlighting = Highlighting::fromTypoScriptConfiguration($fakeConfiguration);
        $highlighting->setIsEnabled(true);
        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

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
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        $highlighting = Highlighting::fromTypoScriptConfiguration($fakeConfiguration);
        $highlighting->setIsEnabled(true);
        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
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

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        $highlighting = Highlighting::fromTypoScriptConfiguration($fakeConfiguration);
        $highlighting->setIsEnabled(true);
        // fragSize 10 is to small for FastVectorHighlighter
        $highlighting->setFragmentSize(17);
        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

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

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $highlighting = Highlighting::fromTypoScriptConfiguration($fakeConfiguration);
        $highlighting->setIsEnabled(true);
        // fragSize 10 is to small for FastVectorHighlighter
        $highlighting->setFragmentSize(200);
        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

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

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $highlighting = Highlighting::fromTypoScriptConfiguration($fakeConfiguration);
        $highlighting->setIsEnabled(true);
        // fragSize 10 is to small for FastVectorHighlighter
        $highlighting->setFragmentSize(0);
        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame('false',$queryParameters['hl.useFastVectorHighlighter'], 'FastVectorHighlighter was disabled but still requested');
    }

    /**
     * @test
     */
    public function canSetQueryString()
    {
        $query = $this->getInitializedTestSearchQuery('i like solr');
        $this->assertSame('i like solr', $query->getQuery(), 'Can not set and get query string');
    }

    /**
     * @test
     */
    public function canSetPage()
    {
        $query = $this->getInitializedTestSearchQuery('i like solr');
        $query->setStart(10);

        $this->assertSame(10, $query->getStart(), 'Can not set and get page');
    }

    /**
     * @test
     */
    public function noFiltersAreSetAfterInitialization()
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertEmpty($queryParameters['fq'], 'Query already contains filters after intialization.');
    }

    /**
     * @test
     */
    public function addsCorrectAccessFilterForAnonymousUser()
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([-1, 0]);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('{!typo3access}-1,0', $queryParameters['fq'], 'Accessfilter was not applied');
    }

    /**
     * @test
     */
    public function grantsAccessToGroupZeroIfNoGroupsProvided()
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([]);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('{!typo3access}0', $queryParameters['fq'], 'Changed accessfilter was not applied');
    }

    /**
     * @test
     */
    public function grantsAccessToGroupZeroIfZeroNotProvided()
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([5]);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('{!typo3access}0,5', $queryParameters['fq'], 'Access filter was not applied as expected');
    }

    /**
     * @test
     */
    public function filtersDuplicateAccessGroups()
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([1, 1]);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('{!typo3access}0,1', $queryParameters['fq'], 'Access filter was not applied as expected');
    }

    /**
     * @test
     */
    public function allowsOnlyOneAccessFilter()
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([1])->useUserAccessGroups([2]);
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertSame('{!typo3access}0,2', $queryParameters['fq'], 'Unexpected filter query');
    }

    // TODO if user is in group -2 (logged in), disallow access to group -1

    // grouping

    /**
     * @test
     */
    public function groupingIsNotActiveAfterInitialization()
    {
        $query = $this->getInitializedTestSearchQuery();

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
        $query = $this->getInitializedTestSearchQuery();

        $grouping = new Grouping(true);
        $query = $this->builder->startFrom($query)->useGrouping($grouping)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

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
    public function settingGroupingFalseDeactivatesGrouping(SearchQuery $query)
    {
        $grouping = new Grouping(false);
        $query = $this->builder->startFrom($query)->useGrouping($grouping)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
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
        $query = $this->getInitializedTestSearchQuery('test');
        $query->getGrouping()->setNumberOfGroups(2);
        $this->assertSame(2, $query->getGrouping()->getNumberOfGroups(), 'Could not set and get number of groups');
    }

    /**
     * @test
     */
    public function canAddGroupField()
    {
        $query = $this->getInitializedTestSearchQuery('test');
        $this->assertSame([], $query->getGrouping()->getFields(), 'Unexpected default state of groupFields');
        $query->getGrouping()->addField('category_s');
        $this->assertSame(['category_s'], $query->getGrouping()->getFields(), 'groupFields has unexpected state after adding a group field');
    }

    /**
     * @test
     */
    public function canGetGroupSorting()
    {
        $query = $this->getInitializedTestSearchQuery('test');
        $this->assertNull($query->getGrouping()->getSort(), 'By default getGroupSortings should return an empty array');
        $grouping = new Grouping(true);
        $grouping->addSorting('price_f');
        $grouping->addSorting('author_s');
        $query = $this->builder->startFrom($query)->useGrouping($grouping)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('price_f author_s', $queryParameters['group.sort'], 'Can not get groupSortings after adding');
    }

    /**
     * @test
     */
    public function canSetNumberOfResultsByGroup()
    {
        $query = $this->getInitializedTestSearchQuery('group test');
        $grouping = new Grouping(true);
        $grouping->addSorting('price_f');
        $grouping->addSorting('author_s');
        $this->assertSame(1, $grouping->getResultsPerGroup());
        $grouping->setResultsPerGroup(22);
        $query = $this->builder->startFrom($query)->useGrouping($grouping)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame(22, $queryParameters['group.limit'], 'Can not set number of results per group');
    }

    /**
     * @test
     */
    public function canAddGroupQuery()
    {
        $query = $this->getInitializedTestSearchQuery('group test');
        $initialGroupQueries = $query->getGrouping()->getQueries();
        $this->assertSame([], $initialGroupQueries, 'Group queries should be an empty array at the beginning');
        $query->getGrouping()->addQuery('price:[* TO 500]');
        $this->assertSame(['price:[* TO 500]'], $query->getGrouping()->getQueries(), 'Could not retrieve group queries after adding one');
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
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $expectedOutput = 'content^10.0 title^5.0';
        $output = $queryParameters['qf'];
        $this->assertSame($output, $expectedOutput, 'Passed and retrieved query fields are not the same');
    }

    /**
     * @test
     */
    public function canReturnEmptyStringAsQueryFieldStringWhenNothingWasPassed()
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $expectedOutput = '';
        $output = $queryParameters['qf'];
        $this->assertSame($output, $expectedOutput, 'Unexpected output from getQueryFieldsAsString when no configuration was passed');
    }

    /**
     * @test
     */
    public function canSetMinimumMatch()
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertNull($queryParameters['mm']);

        $query = $this->builder->startFrom($query)->useMinimumMatch('2<-35%')->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('2<-35%', $queryParameters['mm']);

        $query = $this->builder->startFrom($query)->removeMinimumMatch()->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['mm']);
    }

    /**
     * @test
     */
    public function canSetBoostFunction()
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['bf']);

        $testBoostFunction = 'recip(ms(NOW,created),3.16e-11,1,1)';
        $query = $this->builder->startFrom($query)->useBoostFunction($testBoostFunction)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertSame($testBoostFunction, $queryParameters['bf'], 'bf queryParameter was not present after setting a boostFunction');

        $query = $this->builder->startFrom($query)->removeAllBoostFunctions()->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertNull($queryParameters['bf'], 'bf parameter should be null after reset');
    }

    /**
     * @test
     */
    public function canSetBoostQuery()
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['bq']);
        $testBoostQuery = '(type:tt_news)^10';
        $query = $this->builder->startFrom($query)->useBoostQueries($testBoostQuery)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame($testBoostQuery, $queryParameters['bq'], 'bq queryParameter was not present after setting a boostQuery');
        $query = $this->builder->startFrom($query)->removeAllBoostQueries()->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertEmpty($queryParameters['bq'], 'bq parameter should be null after reset');
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
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('abstract,price', $queryParameters['fl'], 'Did not parse returnsFields as expected');
    }

    /**
     * @test
     */
    public function canReturnDefaultFieldListWhenNoConfigurationWasPassed()
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('*,score', $queryParameters['fl'], 'Did not parse returnsFields as expected');
    }

    /**
     * @test
     */
    public function canAddReturnField()
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $returnFields = ReturnFields::fromString('url');
        $returnFields->add('title');
        $this->builder->startFrom($query)->useReturnFields($returnFields);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('url,title', $queryParameters['fl'], 'Added return field was not in the list of valid fields');
    }

    /**
     * @test
     */
    public function canRemoveReturnField()
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $initialReturnFieldList = ['title','content','url'];
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $returnFields = ReturnFields::fromArray($initialReturnFieldList);
        $returnFields->remove('content');
        $this->builder->startFrom($query)->useReturnFields($returnFields);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('title,url', $queryParameters['fl'], 'content was not remove from the fieldList');
    }

    /**
     * @test
     */
    public function canEnableFaceting()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery */
        $query = $this->getInitializedTestSearchQuery();
        $faceting = new Faceting(true);
        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('true', $queryParameters['facet'], 'Enable faceting did not set the "facet" query parameter');
    }

    /**
     * @test
     */
    public function canDisableFaceting()
    {
        $query = $this->getInitializedTestSearchQuery();

        $faceting = new Faceting(true);
        $faceting->addAdditionalParameter('f.title.facet.sort', 'lex');

        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertSame('true', $queryParameters['facet'], 'Enable faceting did not set the "facet" query parameter');
        $this->assertSame('lex', $queryParameters['f.title.facet.sort'], 'Facet sorting parameter should be lex');

        $faceting = new Faceting(false);
        $faceting->addAdditionalParameter('f.title.facet.sort', 'lex');
        $this->builder->startFrom($query)->useFaceting($faceting);

        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['facet'], 'Facet argument should be null after reset');
        $this->assertNull($queryParameters['f.title.facet.sort'], 'Facet sorting parameter should also be removed after reset');
    }

    /**
     * @test
     */
    public function canAddFacetField()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['facet.field'], 'facet.field query parameter was expected to be null after init.');

        $faceting = Faceting::fromTypoScriptConfiguration($fakeConfiguration);
        // after adding a few facet fields we should be able to retrieve them
        $faceting->setIsEnabled(true);
        $faceting->addField('color_s');
        $faceting->addField('price_f');

        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame(['color_s', 'price_f'], $queryParameters['facet.field'], 'facet.field should not be empty after adding a few fields.');
    }

    /**
     * @test
     */
    public function canSetFacetFields()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        $fakeFields = ['lastname_s', 'role_s'];

        $faceting = Faceting::fromTypoScriptConfiguration($fakeConfiguration);
        $faceting->setIsEnabled(true);
        $faceting->setFields($fakeFields);

        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertSame(['lastname_s', 'role_s'], $queryParameters['facet.field'], 'Could not use setFields to pass facet fields');
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

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $faceting = Faceting::fromTypoScriptConfiguration($fakeConfiguration);
        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);

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

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $faceting = Faceting::fromTypoScriptConfiguration($fakeConfiguration);
        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertSame('index', $queryParameters['facet.sort'], 'Can not use facet.sort from configuration');
    }

    /**
     * @test
     */
    public function canSetSpellChecking()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery */
        $query = $this->getInitializedTestSearchQuery();

        $spellchecking = Spellchecking::getEmpty();
        $spellchecking->setIsEnabled(true);
        $this->builder->startFrom($query)->useSpellchecking($spellchecking);
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertSame('true', $queryParameters['spellcheck'], 'Enable spellchecking did not set the "spellcheck" query parameter');

        // can we unset it again?
        $spellchecking->setIsEnabled(false);
        $this->builder->startFrom($query)->useSpellchecking($spellchecking);

        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['spellcheck'], 'Disable spellchecking did not unset the "spellcheck" query parameter');
        $this->assertNull($queryParameters['spellcheck.maxCollationTries'], 'spellcheck.maxCollationTries was not unsetted');
    }

    /**
     * @test
     */
    public function noSiteHashFilterIsSetWhenWildcardIsPassed()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery */
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getObjectByPathOrDefault')->willReturn(['allowedSites' => '*']);
        $this->siteHashServiceMock->expects($this->once())->method('getAllowedSitesForPageIdAndAllowedSitesConfiguration')->willReturn('*');

        $builder = new QueryBuilder($configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $query = $builder->buildSearchQuery('');

        $query = $builder->startFrom($query)->useSiteHashFromTypoScript(4711)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertEmpty($queryParameters['fq'], 'The filters should be empty when a wildcard sitehash was passed');
    }

    /**
     * @test
     */
    public function filterIsAddedWhenAllowedSiteIsPassed()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery */
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getObjectByPathOrDefault')->willReturn(['allowedSites' => 'site1.local']);

        $this->siteHashServiceMock->expects($this->once())->method('getAllowedSitesForPageIdAndAllowedSitesConfiguration')->willReturn('site1.local');
        $this->siteHashServiceMock->expects($this->once())->method('getSiteHashForDomain')->willReturn('dsada43242342342');

        $builder = new QueryBuilder($configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $query = $builder->buildSearchQuery('');

        $query = $builder->startFrom($query)->useSiteHashFromTypoScript(4711)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertEquals('siteHash:"dsada43242342342"', $queryParameters['fq'], 'Unexpected siteHashFilter was added to the query');
    }

    /**
     * @test
     */
    public function canTestNumberOfSuggestionsToTryFromConfiguration()
    {
        $input = 9;
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['spellchecking'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['spellchecking.']['numberOfSuggestionsToTry'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        $builder = new QueryBuilder($fakeConfiguration, $this->loggerMock, $this->siteHashServiceMock);
        $builder->startFrom($query)->useSpellcheckingFromTypoScript();

        $queryParameters = $this->getAllQueryParameters($query);
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
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('{!collapse field=myField}', $queryParameters['fq'], 'Collapse filter query was not created');
    }

    /**
     * @test
     */
    public function canUseConfiguredVariantsExpandAndRowCount()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants.'] = [
            'variantField' => 'variants',
            'expand' => true,
            'limit' => 10
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('true', $queryParameters['expand'], 'Expand argument of query was not set to true with configured expand');
        $this->assertSame(10, $queryParameters['expand.rows'], 'Expand.rows argument of query was not set to true with configured expand.rows');
    }

    /**
     * @test
     */
    public function expandRowsIsNotSetWhenExpandIsInactive()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants.'] = [
            'variantField' => 'variants',
            'expand' => false,
            'limit' => 10
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['expand.rows'], 'Expand.rows should not be set when expand is set to false');
    }

    /**
     * @test
     */
    public function variantsAreDisabledWhenNothingWasConfigured()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['fq'], 'No filter query should be generated when field collapsing is disbled');
    }

    /**
     * @test
     */
    public function canConvertQueryToString()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        $queryToString = (string) $query;
        $this->assertSame('test', $queryToString, 'Could not convert query to string');
    }

    /**
     * @test
     */
    public function canAddAndRemoveFilters()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        // can we add a filter?

        $this->builder->startFrom($query)->useFilter('foo:bar');
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('foo:bar', $parameters['fq'], 'Could not get filters from query object');

        // can we remove the filter after adding?
        $this->builder->startFrom($query)->removeFilterByFieldName('foo');
        $parameters = $this->getAllQueryParameters($query);
        $this->assertNull($parameters['fq'], 'Could not remove filters from query object');

        // can we add a new filter
        $this->builder->startFrom($query)->useFilter('title:test');
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('title:test', $parameters['fq'], 'Could not get filters from query object');
        $this->builder->startFrom($query)->removeFilterByFieldName('title');

        // can we remove the filter by name?
        $this->builder->startFrom($query)->useFilter('siteHash:xyz', 'siteHashFilter');
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('siteHash:xyz', $parameters['fq'], 'Could not get filters from query object');
        $this->builder->startFrom($query)->removeFilterByName('siteHashFilter');

        $parameters = $this->getAllQueryParameters($query);
        $this->assertNull($parameters['fq'], 'Could not remove filters from query object by filter key');
    }

    /**
     * @test
     */
    public function canRemoveFilterByValue()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        // can we add a filter?
        $this->builder->startFrom($query)->useFilter('foo:bar');
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('foo:bar', $parameters['fq'], 'Could not get filters from query object');

        $this->builder->startFrom($query)->removeFilterByValue('foo:bar');
        $parameters = $this->getAllQueryParameters($query);
        $this->assertNull($parameters['fq'], 'Filters are not empty after removing the last one');
    }

    /**
     * @test
     */
    public function canUseFilterIgnoreSecondePassedFilterWithSameKey()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        // we add a filter with the same key twice and expect that only the first one is kept and not overwritten
        $this->builder->startFrom($query)->useFilter('foo:bar', 'test')->useFilter('foo:bla', 'test');
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('foo:bar', $parameters['fq'], 'Unexpected filter query was added');
    }

    /**
     * @test
     */
    public function canSetAndUnSetQueryType()
    {
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertNull($queryParameters['qt'], 'The qt parameter was expected to be null');

        $this->builder->startFrom($query)->useQueryType('dismax');
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('dismax', $queryParameters['qt'], 'The qt parameter was expected to be dismax');

        //passing false as parameter should reset the query type
        $this->builder->startFrom($query)->removeQueryType();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['qt'], 'The qt parameter was expected to be null after reset');
    }

    /**
     * @test
     */
    public function canSetOperator()
    {
        $query = $this->getInitializedTestSearchQuery('test');

        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['q.op'], 'The queryParameter q.op should be null because no operator was passed');

        $this->builder->startFrom($query)->useOperator(Operator::getOr());
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertEquals(Operator::OPERATOR_OR, $queryParameters['q.op'], 'The queryParameter q.op should be OR');

        $this->builder->startFrom($query)->useOperator(Operator::getAnd());
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertEquals(Operator::OPERATOR_AND, $queryParameters['q.op'], 'The queryParameter q.op should be AND');

        $this->builder->startFrom($query)->removeOperator();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['q.op'], 'The queryParameter q.op should be null because operator was resetted');
    }

    /**
     * @test
     */
    public function canSetAlternativeQuery()
    {
        // check initial value
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertNull($queryParameters['q.alt'], 'We expected that alternative query is initially null');

        // can we set it?
        $this->builder->startFrom($query)->useAlternativeQuery('alt query');
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertEquals('alt query', $queryParameters['q.alt'], 'Could not get passed alternative query');


        // can we reset it?
        $this->builder->startFrom($query)->removeAlternativeQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['q.alt'], 'We expect alternative query is null after reset');
    }

    /**
     * @test
     */
    public function canSetOmitHeaders()
    {
        // check initial value
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $query->getQueryParameters();
        $this->assertNull($queryParameters['omitHeader'], 'The queryParameter omitHeader should be null because it was not');

        $this->builder->startFrom($query)->useOmitHeader();

        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('true', $queryParameters['omitHeader'], 'The queryParameter omitHeader should be "true" because it was enabled');

        $this->builder->startFrom($query)->useOmitHeader(false);

        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('false',$queryParameters['omitHeader'], 'The queryParameter omitHeader should be null because it was resetted');
    }

    /**
     * @test
     */
    public function canSetReturnFields()
    {
        // check initial value
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('*,score', $queryParameters['fl'], 'FieldList initially contained unexpected values');

        // set from string
        $this->builder->startFrom($query)->useReturnFields(ReturnFields::fromString('content, title'));
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('content,title', $queryParameters['fl'], 'Can not set fieldList from string');

        // set from array
        $this->builder->startFrom($query)->useReturnFields(ReturnFields::fromArray(['content', 'title']));
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('content,title', $queryParameters['fl'], 'Can not set fieldList from array');
    }

    /**
     * @test
     */
    public function canSetSorting()
    {
        // check initial value
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['sort'], 'Sorting should be null at the beginning');

        // can set a field and direction combination
        $this->builder->startFrom($query)->useSorting(Sorting::fromString('title desc'));
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('title desc', $queryParameters['sort'], 'Could not set sorting');

        // can reset
        $this->builder->startFrom($query)->removeAllSortings();
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertNull($queryParameters['sort'], 'Sorting should be null after reset');

        // when relevance is getting passed it is the same as we have no
        // sorting because this is a "virtual" value
        $this->builder->startFrom($query)->useSorting(Sorting::fromString('relevance desc'));
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertEquals('', $queryParameters['sort'], 'Sorting should be null after reset');
    }

    /**
     * @test
     */
    public function canSetQueryElevation()
    {
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertNull($queryParameters['enableElevation']);
        $this->assertNull($queryParameters['forceElevation']);
        $this->assertNotContains('isElevated:[elevated]', $queryParameters['fl']);

        // do we get the expected default values, when calling setQueryElevantion with no arguments?

        $elevation = new Elevation(true);
        $this->builder->startFrom($query)->useElevation($elevation);
        $queryParameters = $this->getAllQueryParameters($query);
        $this->assertSame('true', $queryParameters['enableElevation'], 'enabledElevation was not set after enabling elevation');
        $this->assertSame('true', $queryParameters['forceElevation'], 'forceElevation was not set after enabling elevation');
        $this->assertContains('isElevated:[elevated]', $queryParameters['fl'], 'isElevated should be in the list of return fields');

        // can we reset the elevantion?
        $elevation->setIsEnabled(false);
        $this->builder->startFrom($query)->useElevation($elevation);
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertNull($queryParameters['enableElevation']);
        $this->assertNull($queryParameters['forceElevation']);
        $this->assertSame('*,score',$queryParameters['fl']);
    }

    /**
     * @test
     */
    public function forceElevationIsFalseWhenForcingToFalse()
    {
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertNull($queryParameters['enableElevation']);
        $this->assertNull($queryParameters['forceElevation']);

        $elevation = new Elevation();
        $elevation->setIsEnabled(true);
        $elevation->setIsForced(false);

        $this->builder->startFrom($query)->useElevation($elevation);
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertSame('true', $queryParameters['enableElevation'], 'enabledElevation was not set after enabling elevation');
        $this->assertSame('false', $queryParameters['forceElevation'], 'forceElevation was not false after forcing');

        $elevation->setIsEnabled(false);
        $this->builder->startFrom($query)->useElevation($elevation);
        $queryParameters = $this->getAllQueryParameters($query);

        $this->assertNull($queryParameters['enableElevation']);
        $this->assertNull($queryParameters['forceElevation']);
    }

    /**
     * @test
     */
    public function canBuildExpectedQueryUrlFromCombinedQuery()
    {
        $faceting = new Faceting(true);
        $faceting->addField('content');
        $faceting->addField('type');
        $faceting->addField('color');

        $returnFields = new ReturnFields();
        $returnFields->add('title');

        $fieldCollapsing = new FieldCollapsing(true);

        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock);
        $query = $queryBuilder->newSearchQuery('hello world')
            ->useFilter('color:red')
            ->useUserAccessGroups([1,2,3])
            ->useFaceting($faceting)
            ->useReturnFields($returnFields)
            ->useFieldCollapsing($fieldCollapsing)
            ->getQuery();

        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('title', $parameters['fl']);

        $filterQueries = $parameters['fq'];
        $this->assertCount(3, $filterQueries, 'Unexpected amount of filter queries created');
        $this->assertSame('color:red', $parameters['fq'][0]);
        $this->assertSame('{!typo3access}0,1,2,3', $parameters['fq'][1]);
        $this->assertSame('{!collapse field=variantId}', $parameters['fq'][2]);

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
        $query = $this->getInitializedTestSearchQuery('foo bar');

        $this->builder->startFrom($query)->useQueryFields(QueryFields::fromString('content^100.0, title^10.0'));

        $parameters = $this->getAllQueryParameters($query);
        // the , delimiter is removed
        $this->assertSame('content^100.0 title^10.0', $parameters['qf'], 'Can not set and get query fields');
    }

    /**
     * @test
     */
    public function canSetQueryFields()
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');
        $parameters = $this->getAllQueryParameters($query);

        $this->assertEmpty($parameters['qf'], 'QueryFields are not empty by default');

        $queryFields = new QueryFields([]);
        $queryFields->set('content', 10);
        $queryFields->set('title', 11);

        $this->builder->startFrom($query)->useQueryFields($queryFields);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('content^10.0 title^11.0', $parameters['qf']);

        // overwrite the boost of title
        $queryFields->set('title', 9);
        $this->builder->startFrom($query)->useQueryFields($queryFields);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('content^10.0 title^9.0', $parameters['qf'], 'qf parameter not set in QueryParameters');
    }

    /**
     * @test
     */
    public function canSetPhraseFieldsFromString()
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');
        $this->builder->startFrom($query)->usePhraseFields(PhraseFields::fromString('content^100.0, title^10.0'));
        $parameters = $this->getAllQueryParameters($query);
        // the , delimiter is removed
        $this->assertSame('content^100.0 title^10.0', $parameters['pf'], 'Can not set and get phrase fields');
    }

    /**
     * @test
     */
    public function canSetPhraseFields()
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');
        $parameters = $this->getAllQueryParameters($query);

        $this->assertEmpty($parameters['pf'], 'Phrase Fields must be empty by default');

        $phraseFields = new PhraseFields(true);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);

        $this->builder->startFrom($query)->usePhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('content^10.0 title^11.0', $parameters['pf']);

        // overwrite the boost of title
        $phraseFields->add('title', 9);
        $this->builder->startFrom($query)->usePhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);

        $this->assertSame('content^10.0 title^9.0', $parameters['pf']);
    }

    /**
     * @test
     */
    public function phraseFieldsAreNotSetInUrlQueryIfPhraseSearchIsDisabled()
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');

        $phraseFields = new PhraseFields(false);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);
        $this->builder->startFrom($query)->usePhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
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
        $query = $this->getInitializedTestSearchQuery('foo bar', $fakeConfiguration);

        $phraseFields = new PhraseFields(true);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);
        $this->builder->startFrom($query)->usePhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('content^10.0 title^11.0', $parameters['pf'], 'pf parameters must be set if phrase search is enabled');
    }

    /**
     * @test
     */
    public function canAddPhraseFieldsFromConfiguration()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['phrase'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['phrase.']['fields'] = 'content^22.0, title^11.0';

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('foo bar', $fakeConfiguration);

        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('content^22.0 title^11.0', $parameters['pf'], 'pf parameters must be set if phrase search is enabled');
    }

    /**
     * @test
     */
    public function bigramPhraseFieldsAreNotSetInUrlQueryIfBigramPhraseSearchIsDisabled()
    {
        $query = $this->getInitializedTestSearchQuery('foo bar baz');
        $phraseFields = new BigramPhraseFields(false);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);
        $this->builder->startFrom($query)->useBigramPhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertNull($parameters['pf2'], 'pf2 parameter must be empty(not set) if phrase search is disabled');
    }

    /**
     * @test
     */
    public function canAddBigramFieldsWhenBigramPhraseIsEnabled()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['bigramPhrase'] = 1;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('foo bar', $fakeConfiguration);

        $phraseFields = BigramPhraseFields::fromTypoScriptConfiguration($fakeConfiguration);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);
        $this->builder->startFrom($query)->useBigramPhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('content^10.0 title^11.0', $parameters['pf2'], 'pf2 parameters must be set if bigram phrase search is enabled');
    }

    /**
     * @test
     */
    public function canAddBigramFieldsFromConfiguration()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['bigramPhrase'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['bigramPhrase.']['fields'] = 'content^12.0, title^14.0';

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('foo bar', $fakeConfiguration);

        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('content^12.0 title^14.0', $parameters['pf2'], 'pf2 parameters must be set if bigram phrase search is enabled');
    }

    /**
     * @test
     */
    public function trigramPhraseFieldsAreNotSetInUrlQueryIfTrigramPhraseSearchIsDisabled()
    {
        $query = $this->getInitializedTestSearchQuery('foo bar baz foobar barbaz');
        $phraseFields = new TrigramPhraseFields(false);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);
        $this->builder->startFrom($query)->useTrigramPhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
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
        $query = $this->getInitializedTestSearchQuery('foo bar', $fakeConfiguration);
        $trigram = TrigramPhraseFields::fromTypoScriptConfiguration($fakeConfiguration);
        $trigram->add('content', 10);
        $trigram->add('title', 11);
        $this->builder->startFrom($query)->useTrigramPhraseFields($trigram);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('content^10.0 title^11.0', $parameters['pf3'], 'pf3 parameters must be set if trigram phrase search is enabled');
    }

    /**
     * @test
     */
    public function canAddTrigramFieldsFromConfiguration()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['trigramPhrase'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['trigramPhrase.']['fields'] = 'content^12.0, title^14.0';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('foo bar', $fakeConfiguration);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('content^12.0 title^14.0', $parameters['pf3'], 'pf3 parameters must be set if trigram phrase search is enabled');
    }

    /**
     * @test
     */
    public function setDebugMode()
    {
        $query = $this->getInitializedTestSearchQuery();

        $parameter = $this->getAllQueryParameters($query);
        $this->assertEmpty($parameter['debugQuery'], 'Debug query should be disabled by default');
        $this->assertEmpty($parameter['echoParams'], 'Debug query should be disabled by default');

        $this->builder->startFrom($query)->useDebug(true);
        $parameter = $this->getAllQueryParameters($query);

        $this->assertSame('true', $parameter['debugQuery'], 'Debug query should be disabled by default');
        $this->assertSame('all', $parameter['echoParams'], 'Debug query should be disabled by default');

        $this->builder->startFrom($query)->useDebug(false);
        $parameter = $this->getAllQueryParameters($query);
        $this->assertEmpty($parameter['debugQuery'], 'Can not unset debug mode');
        $this->assertEmpty($parameter['echoParams'], 'Can not unset debug mode');
    }

    /**
     * @test
     */
    public function addingQueriesToGroupingAddsToRightGroupingParameter()
    {
        $query = $this->getInitializedTestSearchQuery('group test');

        $grouping = new Grouping(true);
        $grouping->addQuery('price:[* TO 500]');
        $grouping->addQuery('someField:someValue');
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame(['price:[* TO 500]', 'someField:someValue'], $parameters['group.query'], 'Could not add group queries properly');
    }

    /**
     * @test
     */
    public function addingSortingsToGroupingAddsToRightGroupingParameter()
    {
        $query = $this->getInitializedTestSearchQuery('group test');
        $grouping = new Grouping(true);
        $grouping->addSorting('price_f');
        $grouping->addSorting('title desc');
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame('price_f title desc', $parameters['group.sort'], 'Could not add group sortings properly');
    }

    /**
     * @test
     */
    public function addingFieldsToGroupingAddsToRightGroupingParameter()
    {
        $query = $this->getInitializedTestSearchQuery('group test');
        $grouping = new Grouping(true);
        $grouping->addField('price_f');
        $grouping->addField('category_s');
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);
        $this->assertSame(['price_f', 'category_s'], $parameters['group.field'], 'Could not add group fields properly');
    }

    /**
     * @test
     */
    public function canDisablingGroupingRemoveTheGroupSorting()
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');

        $grouping = new Grouping(true);
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);

        $this->assertSame($parameters['group'], 'true');
        $this->assertSame($parameters['group.format'], 'grouped');
        $this->assertSame($parameters['group.ngroups'], 'true');

        $grouping->addSorting('title desc');
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);

        $this->assertSame($parameters['group.sort'], 'title desc', 'Group sorting was not added');
        $this->assertEmpty($parameters['group.field'], 'No field was passed, so it should not be set');
        $this->assertEmpty($parameters['group.query'], 'No query was passed, so it should not be set');

        $grouping->setIsEnabled(false);
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);

        $this->assertEmpty($parameters['group.sort'], 'Grouping parameters should be removed');
        $this->assertEmpty($parameters['group'], 'Grouping parameters should be removed');
        $this->assertEmpty($parameters['group.format'], 'Grouping parameters should be removed');
        $this->assertEmpty($parameters['group.ngroups'], 'Grouping parameters should be removed');
    }

    /**
     * @test
     */
    public function canBuildSuggestQuery()
    {
        $this->builder = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$this->configurationMock, $this->loggerMock])
            ->setMethods(['useSiteHashFromTypoScript'])
            ->getMock();

        $suggestQuery = $this->builder->buildSuggestQuery('foo', [], 3232, '');
        $queryParameters = $this->getAllQueryParameters($suggestQuery);
        $this->assertSame('foo', $queryParameters['facet.prefix'], 'Passed query string is not used as facet.prefix argument');
    }

    /**
     * @test
     */
    public function alternativeQueryIsWildCardQueryForSuggestQuery()
    {
        $this->builder = $this->getMockBuilder(QueryBuilder::class)
                                ->setConstructorArgs([$this->configurationMock, $this->loggerMock])
                                ->setMethods(['useSiteHashFromTypoScript'])
                                ->getMock();

        $suggestQuery = $this->builder->buildSuggestQuery('bar', [], 3232, '');
        $queryParameters = $this->getAllQueryParameters($suggestQuery);
        $this->assertSame('*:*', $queryParameters['q.alt'], 'Alterntive query is not set to wildcard query by default');
    }
}