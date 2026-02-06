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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Query;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Elevation;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\FieldCollapsing;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Highlighting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Operator;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\PhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\ReturnFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Sorting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Spellchecking;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\TrigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\RequestBuilder;
use Symfony\Component\DependencyInjection\Container;
use Traversable;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

use function str_starts_with;

class QueryBuilderTest extends SetUpUnitTestCase
{
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected SolrLogManager|MockObject $loggerMock;
    protected SiteHashService|MockObject $siteHashServiceMock;
    protected QueryBuilder|MockObject $builder;

    protected function setUp(): void
    {
        parent::setUp();

        // Register ContentObjectService mock for tests that create TypoScriptConfiguration instances
        // Multiple instances needed as many tests create new TypoScriptConfiguration objects
        $this->setUpTypoScriptConfigurationMocks(contentObjectServiceCount: 50);

        // Register ContentObjectRenderer mocks for QueryBuilder methods that use stdWrap
        // (QueryBuilder::getQueryFieldsFromConfiguration and QueryBuilder::applyPageSectionFilters)
        $this->setUpContentObjectRenderer(instanceCount: 10);

        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->loggerMock = $this->createMock(SolrLogManager::class);
        $this->siteHashServiceMock = $this->createMock(SiteHashService::class);
        $this->builder = new QueryBuilder($this->configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $container = new Container();
        $container->set(EventDispatcherInterface::class, new NoopEventDispatcher());
        GeneralUtility::setContainer($container);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    protected function getAllQueryParameters(Query $searchQuery): array
    {
        $requestBuilder = new RequestBuilder();
        $request = $requestBuilder->build($searchQuery);
        return $request->getParams();
    }

    protected function getInitializedTestSearchQuery(
        string $queryString = '',
        ?TypoScriptConfiguration $fakeConfiguration = null,
    ): Query {
        $builder = new QueryBuilder(
            $fakeConfiguration ?? $this->createMock(TypoScriptConfiguration::class),
            $this->loggerMock,
            $this->siteHashServiceMock,
        );
        return $builder->buildSearchQuery($queryString);
    }

    #[Test]
    public function buildSearchQueryPassesQueryString(): void
    {
        $query = $this->builder->buildSearchQuery('one');
        self::assertSame('one', (string)$query->getQuery(), 'Query has unexpected value, when casted to string');
        self::assertTrue($query instanceof SearchQuery);
        self::assertSame('one', $query->getRawSearchTerm());
    }

    #[Test]
    public function buildSearchQueryPassesDefaultPerPage(): void
    {
        $query = $this->builder->buildSearchQuery('one');
        self::assertSame(10, $query->getRows(), 'Query was not created with default perPage value');
    }

    #[Test]
    public function buildSearchQueryPassesCustomPerPage(): void
    {
        $query = $this->builder->buildSearchQuery('one', 22);
        self::assertSame(22, $query->getRows(), 'Query was not created with default perPage value');
    }

    #[Test]
    public function buildSearchQueryInitializesQueryFieldsFromConfiguration(): void
    {
        $this->configurationMock->expects(self::once())->method('getSearchQueryQueryFields')->willReturn('title^10, content^123');
        $query = $this->builder->buildSearchQuery('foo');
        self::assertSame('title^10.0 content^123.0', $this->getAllQueryParameters($query)['qf'], 'The queryFields have not been initialized as expected');
    }

    #[Test]
    public function buildSearchQueryInitializesTrigramPhraseFields(): void
    {
        $this->configurationMock->expects(self::once())->method('getTrigramPhraseSearchIsEnabled')->willReturn(true);

        $this->configurationMock->expects(self::once())->method('getSearchQueryTrigramPhraseFields')->willReturn('content^10.0, title^10.0');
        $query = $this->builder->buildSearchQuery('trigram');
        self::assertSame('content^10.0 title^10.0', $this->getAllQueryParameters($query)['pf3'], 'The trigramPhraseFields have not been initialized as expected');
    }

    #[Test]
    public function buildSearchIsSettingWildCardQueryOnInitializeWithEmptyQuery(): void
    {
        $this->configurationMock->expects(self::once())->method('getSearchInitializeWithEmptyQuery')->willReturn(true);
        $query = $this->builder->buildSearchQuery('initializeWithEmpty');
        self::assertSame('*:*', $this->getAllQueryParameters($query)['q.alt'], 'The alterativeQuery has not been initialized as expected');
    }

    #[Test]
    public function buildSearchIsSettingWildCardQueryOnInitializeWithAllowEmptyQuery(): void
    {
        $this->configurationMock->expects(self::once())->method('getSearchQueryAllowEmptyQuery')->willReturn(true);
        $query = $this->builder->buildSearchQuery('initializeWithEmpty');
        self::assertSame('*:*', $this->getAllQueryParameters($query)['q.alt'], 'The alterativeQuery has not been initialized as expected');
    }

    #[Test]
    public function buildSearchIsSettingQuerystringForConfiguredInitialQuery(): void
    {
        $this->configurationMock->expects(self::exactly(2))->method('getSearchInitializeWithQuery')->willReturn('myinitialsearch');
        $query = $this->builder->buildSearchQuery('initializeWithEmpty');
        self::assertSame('myinitialsearch', $this->getAllQueryParameters($query)['q.alt'], 'The alterativeQuery has not been initialized from a configured initial query');
    }

    #[Test]
    public function buildSearchIsSettingConfiguredAdditionalFilters(): void
    {
        $this->configurationMock->expects(self::any())->method('getSearchQueryFilterConfiguration')->willReturn(['noPage' => '-type:pages']);
        $query = $this->builder->buildSearchQuery('applies configured filters');
        $filterValue = $this->getAllQueryParameters($query)['fq'];
        $filterArray = explode(' ', $filterValue);

        self::assertCount(1, $filterArray, 'Unpexcted amount of filters for query');
        self::assertSame('-type:pages', $filterValue, 'First filter has unexpected value');
    }

    #[Test]
    public function buildSearchIsSettingNoAlternativeQueryByDefault(): void
    {
        $query = $this->builder->buildSearchQuery('initializeWithEmpty');
        self::assertArrayNotHasKey('q.alt', $this->getAllQueryParameters($query), 'The alterativeQuery is not null when nothing was set');
    }

    #[DataProvider('buildSearchIsRespectingPageSectionFiltersDataProvider')]
    #[Test]
    public function buildSearchIsRespectingPageSectionFilters(
        array $rootLines,
        array $filterConfiguration,
        string $expectedResult,
    ): void {
        $rootLinesCount = count($rootLines);
        $rootlineUtilityMock = $this->createMock(RootlineUtility::class);
        $matcher = self::exactly($rootLinesCount);
        $rootlineUtilityMock->method('get')->willReturnCallback(static function () use ($matcher, $rootLines): array {
            if (isset($rootLines[$matcher->numberOfInvocations()])) {
                return $rootLines[$matcher->numberOfInvocations()];
            }
            self::fail('unexpected invocation count');
        });

        for ($i = 0; $i < $rootLinesCount; $i++) {
            GeneralUtility::addInstance(RootlineUtility::class, $rootlineUtilityMock);
        }

        $this->configurationMock->method('getSearchQueryFilterConfiguration')->willReturn($filterConfiguration);
        $this->builder->newSearchQuery('*:*')->useFiltersFromTypoScript();
        $query = $this->builder->getQuery();

        $filter = $query->getFilterQuery('pageSections');
        self::assertNotNull($filter);
        self::assertEquals($expectedResult, $filter->getQuery());
    }

    public static function buildSearchIsRespectingPageSectionFiltersDataProvider(): Traversable
    {
        yield 'static page section filter' => [
            [
                [
                    1 => [
                        'uid' => 123,
                        'pid' => 1,
                    ],
                    0 => [
                        'uid' => 1,
                        'pid' => 0,
                    ],
                ],
            ],
            [
                '__pageSections' => 123,
            ],
            'rootline:"2-0/1/123/"',
        ];

        yield 'static page section filter, with wrapping' => [
            [
                [
                    1 => [
                        'uid' => 123,
                        'pid' => 1,
                    ],
                    0 => [
                        'uid' => 1,
                        'pid' => 0,
                    ],
                ],
                [
                    1 => [
                        'uid' => 321,
                        'pid' => 1,
                    ],
                    0 => [
                        'uid' => 1,
                        'pid' => 0,
                    ],
                ],
            ],
            [
                '__pageSections' => 123,
                '__pageSections.' => [
                    'wrap' => '|,321',
                ],
            ],
            'rootline:"2-0/1/123/" OR rootline:"2-0/1/321/"',
        ];
    }

    #[Test]
    public function canBuildSearchQueryForVectorSearch(): void
    {
        $this->configurationMock->method('isPureVectorSearchEnabled')->willReturn(true);
        $this->configurationMock->method('getMinimumVectorSimilarity')->willReturn(85.0);
        $this->configurationMock->method('getTopKClosestVectorLimit')->willReturn(10);
        $this->configurationMock->method('getSearchQueryReturnFieldsAsArray')->willReturn(['content']);
        $query = $this->builder->buildSearchQuery('vector search term', 22, ['type' => 'pages']);

        self::assertSame('*:*', $query->getQuery());
        self::assertTrue($query instanceof SearchQuery);
        self::assertSame('vector search term', $query->getRawSearchTerm());
        self::assertSame(['$q_vector' => 'DESC'], $query->getSorts());
        self::assertSame(22, $query->getRows());

        $vectorRangeFilter = $query->getFilterQuery('vectorRange');
        self::assertTrue($vectorRangeFilter instanceof FilterQuery);
        self::assertSame(
            [
                'key' => 'vectorRange',
                'query' => '{!frange l=85}$q_vector',
            ],
            $vectorRangeFilter->getOptions(),
        );
        self::assertCount(2, $query->getFilterQueries());
        self::assertSame(
            '{!knn_text_to_vector model=llm f=vector topK=10}vector search term',
            $query->getParams()['q_vector'],
        );
        self::assertSame(['content', '$q_vector'], $query->getFields());
    }

    #[Test]
    public function canEnableHighlighting(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $highlighting = new Highlighting();
        $highlighting->setIsEnabled(true);

        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        self::assertSame(200, $queryParameters['hl.fragsize'], 'hl.fragsize was not set to the default value of 200');
    }

    #[Test]
    public function canDisableHighlighting(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $highlighting = new Highlighting();
        $highlighting->setIsEnabled(true);

        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');

        $highlighting->setIsEnabled(false);
        $query = $this->builder->startFrom($query)->useHighlighting($highlighting)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('hl', $queryParameters, 'Could not disable highlighting');
    }

    #[Test]
    public function canSetHighlightingFieldList(): void
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

        self::assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        self::assertSame('title', $queryParameters['hl.fl'], 'Can set highlighting field list');
    }

    #[Test]
    public function canPassCustomWrapForHighlighting(): void
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
        self::assertSame('[A]', $queryParameters['hl.tag.pre'], 'Can set highlighting hl.tag.pre');
        self::assertSame('[B]', $queryParameters['hl.tag.post'], 'Can set highlighting hl.tag.post');
        self::assertSame('[A]', $queryParameters['hl.simple.pre'], 'Can set highlighting hl.tag.pre');
        self::assertSame('[B]', $queryParameters['hl.simple.post'], 'Can set highlighting hl.tag.post');
    }

    #[Test]
    public function simplePreAndPostIsUsedWhenFastVectorHighlighterCouldNotBeUsed(): void
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

        self::assertSame('[A]', $queryParameters['hl.simple.pre'], 'Can set highlighting field list');
        self::assertSame('[B]', $queryParameters['hl.simple.post'], 'Can set highlighting field list');
        self::assertEmpty($queryParameters['hl.tag.pre'], 'When the highlighting fragment size is to small hl.tag.pre should not be used because FastVectoreHighlighter will not be used');
        self::assertEmpty($queryParameters['hl.tag.post'], 'When the highlighting fragment size is to small hl.tag.post should not be used because FastVectoreHighlighter will not be used');
    }

    #[Test]
    public function canUseFastVectorHighlighting(): void
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

        self::assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        self::assertSame('true', $queryParameters['hl.useFastVectorHighlighter'], 'Enable highlighting did not set the "hl.useFastVectorHighlighter" query parameter');
    }

    #[Test]
    public function fastVectorHighlighterIsDisabledWhenFragSizeIsLessThen18(): void
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

        self::assertSame('true', $queryParameters['hl'], 'Enable highlighting did not set the "hl" query parameter');
        self::assertSame('false', $queryParameters['hl.useFastVectorHighlighter'], 'FastVectorHighlighter was disabled but still requested');
    }

    #[Test]
    public function canSetQueryString(): void
    {
        $query = $this->getInitializedTestSearchQuery('i like solr');
        self::assertSame('i like solr', $query->getQuery(), 'Can not set and get query string');
    }

    #[Test]
    public function canSetPage(): void
    {
        $query = $this->getInitializedTestSearchQuery('i like solr');
        $query->setStart(10);

        self::assertSame(10, $query->getStart(), 'Can not set and get page');
    }

    #[Test]
    public function noFiltersAreSetAfterInitialization(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('fq', $queryParameters, 'Query already contains filters after intialization.');
    }

    #[Test]
    public function addsCorrectAccessFilterForAnonymousUser(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([-1, 0]);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('{!typo3access}-1,0', $queryParameters['fq'], 'Accessfilter was not applied');
    }

    #[Test]
    public function grantsAccessToGroupZeroIfNoGroupsProvided(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([]);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('{!typo3access}0', $queryParameters['fq'], 'Changed accessfilter was not applied');
    }

    #[Test]
    public function grantsAccessToGroupZeroIfZeroNotProvided(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([5]);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('{!typo3access}0,5', $queryParameters['fq'], 'Access filter was not applied as expected');
    }

    #[Test]
    public function filtersDuplicateAccessGroups(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([1, 1]);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('{!typo3access}0,1', $queryParameters['fq'], 'Access filter was not applied as expected');
    }

    #[Test]
    public function allowsOnlyOneAccessFilter(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $queryBuilder->startFrom($query)->useUserAccessGroups([1])->useUserAccessGroups([2]);
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertSame('{!typo3access}0,2', $queryParameters['fq'], 'Unexpected filter query');
    }

    // TODO if user is in group -2 (logged in), disallow access to group -1
    // grouping
    #[Test]
    public function groupingIsNotActiveAfterInitialization(): void
    {
        $query = $this->getInitializedTestSearchQuery();

        $queryParameters = $query->getParams();
        foreach (array_keys($queryParameters) as $queryParameter) {
            self::assertTrue(
                !str_starts_with($queryParameter, 'group'),
                'Query already contains grouping parameter "' . $queryParameter . '"',
            );
        }
    }

    #[Test]
    public function settingGroupingTrueActivatesGrouping()
    {
        $query = $this->getInitializedTestSearchQuery();

        $grouping = new Grouping(true);
        $query = $this->builder->startFrom($query)->useGrouping($grouping)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayHasKey('group', $queryParameters);
        self::assertEquals('true', $queryParameters['group']);

        self::assertArrayHasKey('group.format', $queryParameters);
        self::assertEquals('grouped', $queryParameters['group.format']);

        self::assertArrayHasKey('group.ngroups', $queryParameters);
        self::assertEquals('true', $queryParameters['group.ngroups']);

        return $query;
    }

    #[Depends('settingGroupingTrueActivatesGrouping')]
    #[Test]
    public function settingGroupingFalseDeactivatesGrouping(SearchQuery $query): void
    {
        $grouping = new Grouping(false);
        $query = $this->builder->startFrom($query)->useGrouping($grouping)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        foreach (array_keys($queryParameters) as $queryParameter) {
            self::assertTrue(
                !str_starts_with($queryParameter, 'group'),
                'Query contains grouping parameter "' . $queryParameter . '"',
            );
        }
    }

    #[Test]
    public function canSetNumberOfGroups(): void
    {
        $query = $this->getInitializedTestSearchQuery('test');
        $query->getGrouping()->setNumberOfGroups(true);
        self::assertTrue($query->getGrouping()->getNumberOfGroups(), 'Could not set and get number of groups');
    }

    #[Test]
    public function canAddGroupField(): void
    {
        $query = $this->getInitializedTestSearchQuery('test');
        self::assertSame([], $query->getGrouping()->getFields(), 'Unexpected default state of groupFields');
        $query->getGrouping()->addField('category_s');
        self::assertSame(['category_s'], $query->getGrouping()->getFields(), 'groupFields has unexpected state after adding a group field');
    }

    #[Test]
    public function canGetGroupSorting(): void
    {
        $query = $this->getInitializedTestSearchQuery('test');
        self::assertNull($query->getGrouping()->getSort(), 'By default getGroupSortings should return an empty array');
        $grouping = new Grouping(true);
        $grouping->addSorting('price_f');
        $grouping->addSorting('author_s');
        $query = $this->builder->startFrom($query)->useGrouping($grouping)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('price_f author_s', $queryParameters['group.sort'], 'Can not get groupSortings after adding');
    }

    #[Test]
    public function canSetNumberOfResultsByGroup(): void
    {
        $query = $this->getInitializedTestSearchQuery('group test');
        $grouping = new Grouping(true);
        $grouping->addSorting('price_f');
        $grouping->addSorting('author_s');
        self::assertSame(1, $grouping->getResultsPerGroup());
        $grouping->setResultsPerGroup(22);
        $query = $this->builder->startFrom($query)->useGrouping($grouping)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame(22, $queryParameters['group.limit'], 'Can not set number of results per group');
    }

    #[Test]
    public function canAddGroupQuery(): void
    {
        $query = $this->getInitializedTestSearchQuery('group test');
        $initialGroupQueries = $query->getGrouping()->getQueries();
        self::assertSame([], $initialGroupQueries, 'Group queries should be an empty array at the beginning');
        $query->getGrouping()->addQuery('price:[* TO 500]');
        self::assertSame(['price:[* TO 500]'], $query->getGrouping()->getQueries(), 'Could not retrieve group queries after adding one');
    }

    #[Test]
    public function canGetQueryFieldsAsStringWhenPassedFromConfiguration(): void
    {
        $input = 'content^10, title^5';
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['queryFields'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $expectedOutput = 'content^10.0 title^5.0';
        $output = $queryParameters['qf'];
        self::assertSame($output, $expectedOutput, 'Passed and retrieved query fields are not the same');
    }

    #[Test]
    public function canReturnEmptyStringAsQueryFieldStringWhenNothingWasPassed(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        $expectedOutput = '';
        $output = $queryParameters['qf'];
        self::assertSame($output, $expectedOutput, 'Unexpected output from getQueryFieldsAsString when no configuration was passed');
    }

    #[Test]
    public function canSetMinimumMatch(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('mm', $queryParameters);

        $query = $this->builder->startFrom($query)->useMinimumMatch('2<-35%')->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('2<-35%', $queryParameters['mm']);

        $query = $this->builder->startFrom($query)->removeMinimumMatch()->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertEmpty($queryParameters['mm']);
    }

    #[Test]
    public function canSetBoostFunction(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('bf', $queryParameters);

        $testBoostFunction = 'recip(ms(NOW,created),3.16e-11,1,1)';
        $query = $this->builder->startFrom($query)->useBoostFunction($testBoostFunction)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertSame($testBoostFunction, $queryParameters['bf'], 'bf queryParameter was not present after setting a boostFunction');

        $query = $this->builder->startFrom($query)->removeAllBoostFunctions()->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertEmpty($queryParameters['bf'], 'bf parameter should be null after reset');
    }

    #[Test]
    public function canSetBoostQuery(): void
    {
        $query = $this->getInitializedTestSearchQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('bq', $queryParameters);
        $testBoostQuery = '(type:tt_news)^10';
        $query = $this->builder->startFrom($query)->useBoostQueries($testBoostQuery)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame($testBoostQuery, $queryParameters['bq'], 'bq queryParameter was not present after setting a boostQuery');
        $query = $this->builder->startFrom($query)->removeAllBoostQueries()->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('bq', $queryParameters, 'bq parameter should be null after reset');
    }

    #[Test]
    public function canReturnFieldListWhenConfigurationWithReturnFieldsWasPassed(): void
    {
        $input = 'abstract, price';
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['returnFields'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('abstract,price', $queryParameters['fl'], 'Did not parse returnsFields as expected');
    }

    #[Test]
    public function canReturnDefaultFieldListWhenNoConfigurationWasPassed(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('*,score', $queryParameters['fl'], 'Did not parse returnsFields as expected');
    }

    #[Test]
    public function canAddReturnField(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $returnFields = ReturnFields::fromString('url');
        $returnFields->add('title');
        $this->builder->startFrom($query)->useReturnFields($returnFields);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('url,title', $queryParameters['fl'], 'Added return field was not in the list of valid fields');
    }

    #[Test]
    public function canRemoveReturnField(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $initialReturnFieldList = ['title', 'content', 'url'];
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $returnFields = ReturnFields::fromArray($initialReturnFieldList);
        $returnFields->remove('content');
        $this->builder->startFrom($query)->useReturnFields($returnFields);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('title,url', $queryParameters['fl'], 'content was not remove from the fieldList');
    }

    #[Test]
    public function canEnableFaceting(): void
    {
        /** @var SearchQuery $query */
        $query = $this->getInitializedTestSearchQuery();
        $faceting = new Faceting(true);
        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('true', $queryParameters['facet'], 'Enable faceting did not set the "facet" query parameter');
    }

    #[Test]
    public function canDisableFaceting(): void
    {
        $query = $this->getInitializedTestSearchQuery();

        $faceting = new Faceting(true);
        $faceting->addAdditionalParameter('f.title.facet.sort', 'lex');

        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertSame('true', $queryParameters['facet'], 'Enable faceting did not set the "facet" query parameter');
        self::assertSame('lex', $queryParameters['f.title.facet.sort'], 'Facet sorting parameter should be lex');

        $faceting = new Faceting(false);
        $faceting->addAdditionalParameter('f.title.facet.sort', 'lex');
        $this->builder->startFrom($query)->useFaceting($faceting);

        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('facet', $queryParameters, 'Facet argument should be null after reset');
        self::assertArrayNotHasKey('f.title.facet.sort', $queryParameters, 'Facet sorting parameter should also be removed after reset');
    }

    #[Test]
    public function canAddFacetField(): void
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);

        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $query->getParams();
        self::assertArrayNotHasKey('facet.field', $queryParameters, 'facet.field query parameter was expected to be null after init.');

        $faceting = Faceting::fromTypoScriptConfiguration($fakeConfiguration);
        // after adding a few facet fields we should be able to retrieve them
        $faceting->setIsEnabled(true);
        $faceting->addField('color_s');
        $faceting->addField('price_f');

        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame(['color_s', 'price_f'], $queryParameters['facet.field'], 'facet.field should not be empty after adding a few fields.');
    }

    #[Test]
    public function canSetFacetFields(): void
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        $fakeFields = ['lastname_s', 'role_s'];

        $faceting = Faceting::fromTypoScriptConfiguration($fakeConfiguration);
        $faceting->setIsEnabled(true);
        $faceting->setFields($fakeFields);

        $this->builder->startFrom($query)->useFaceting($faceting);
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertSame(['lastname_s', 'role_s'], $queryParameters['facet.field'], 'Could not use setFields to pass facet fields');
    }

    #[Test]
    public function canUseFacetMinCountFromConfiguration(): void
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

        self::assertSame(10, $queryParameters['facet.mincount'], 'Can not use facet.minimumCount from configuration');
    }

    #[Test]
    public function canUseFacetSortByFromConfiguration(): void
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

        self::assertSame('index', $queryParameters['facet.sort'], 'Can not use facet.sort from configuration');
    }

    #[Test]
    public function canSetSpellChecking(): void
    {
        /** @var SearchQuery $query */
        $query = $this->getInitializedTestSearchQuery();

        $spellchecking = Spellchecking::getEmpty();
        $spellchecking->setIsEnabled(true);
        $this->builder->startFrom($query)->useSpellchecking($spellchecking);
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertSame('true', $queryParameters['spellcheck'], 'Enable spellchecking did not set the "spellcheck" query parameter');

        // can we unset it again?
        $spellchecking->setIsEnabled(false);
        $this->builder->startFrom($query)->useSpellchecking($spellchecking);

        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('spellcheck', $queryParameters, 'Disable spellchecking did not unset the "spellcheck" query parameter');
        self::assertArrayNotHasKey('spellcheck.maxCollationTries', $queryParameters, 'spellcheck.maxCollationTries was not unsetted');
    }

    #[Test]
    public function noSiteHashFilterIsSetWhenWildcardIsPassed(): void
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getObjectByPathOrDefault')->willReturn(['allowedSites' => '*']);
        $this->siteHashServiceMock->expects(self::once())->method('getAllowedSitesForPageIdAndAllowedSitesConfiguration')->willReturn('*');

        $builder = new QueryBuilder($configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $query = $builder->buildSearchQuery('');

        $query = $builder->startFrom($query)->useSiteHashFromTypoScript(4711)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('fq', $queryParameters, 'The filters should be empty when a wildcard sitehash was passed');
    }

    #[Test]
    public function filterIsAddedWhenAllowedSiteIsPassed(): void
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getObjectByPathOrDefault')->willReturn(['allowedSites' => 'site1.local']);

        $this->siteHashServiceMock->expects(self::once())->method('getAllowedSitesForPageIdAndAllowedSitesConfiguration')->willReturn('site1.local');
        $this->siteHashServiceMock->expects(self::once())->method('getSiteHashForSiteIdentifier')->willReturn('dsada43242342342');

        $builder = new QueryBuilder($configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $query = $builder->buildSearchQuery('');

        $query = $builder->startFrom($query)->useSiteHashFromTypoScript(4711)->getQuery();
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertEquals('siteHash:"dsada43242342342"', $queryParameters['fq'], 'Unexpected siteHashFilter was added to the query');
    }

    #[Test]
    public function canTestNumberOfSuggestionsToTryFromConfiguration(): void
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
        self::assertSame($input, $queryParameters['spellcheck.maxCollationTries'], 'Could not set spellcheck.maxCollationTries as expected');
    }

    #[Test]
    public function canUseConfiguredVariantsFieldWhenVariantsAreActive(): void
    {
        $fakeConfigurationArray = ['plugin.' => ['tx_solr.' => ['search.' => ['variants' => 1]]]];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants.'] = [
            'variantField' => 'myField',
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('{!collapse field=myField}', $queryParameters['fq'], 'Collapse filter query was not created');
    }

    #[Test]
    public function canUseConfiguredVariantsExpandAndRowCount(): void
    {
        $fakeConfigurationArray = ['plugin.' => ['tx_solr.' => ['search.' => ['variants' => 1]]]];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants.'] = [
            'variantField' => 'variants',
            'expand' => true,
            'limit' => 10,
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('true', $queryParameters['expand'], 'Expand argument of query was not set to true with configured expand');
        self::assertSame(10, $queryParameters['expand.rows'], 'Expand.rows argument of query was not set to true with configured expand.rows');
    }

    #[Test]
    public function expandRowsIsNotSetWhenExpandIsInactive(): void
    {
        $fakeConfigurationArray = ['plugin.' => ['tx_solr.' => ['search.' => ['variants' => 1]]]];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants.'] = [
            'variantField' => 'variants',
            'expand' => false,
            'limit' => 10,
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('expand.rows', $queryParameters, 'Expand.rows should not be set when expand is set to false');
    }

    #[Test]
    public function variantsAreDisabledWhenNothingWasConfigured(): void
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('fq', $queryParameters, 'No filter query should be generated when field collapsing is disbled');
    }

    #[Test]
    public function canConvertQueryToString(): void
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        $query->__toString();
        $queryToString = (string)$query;
        self::assertSame('test', $queryToString, 'Could not convert query to string');
    }

    #[Test]
    public function canAddAndRemoveFilters(): void
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        // can we add a filter?

        $this->builder->startFrom($query)->useFilter('foo:bar');
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('foo:bar', $parameters['fq'], 'Could not get filters from query object');

        // can we remove the filter after adding?
        $this->builder->startFrom($query)->removeFilterByFieldName('foo');
        $parameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('fq', $parameters, 'Could not remove filters from query object');

        // can we add a new filter
        $this->builder->startFrom($query)->useFilter('title:test');
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('title:test', $parameters['fq'], 'Could not get filters from query object');
        $this->builder->startFrom($query)->removeFilterByFieldName('title');

        // can we remove the filter by name?
        $this->builder->startFrom($query)->useFilter('siteHash:xyz', 'siteHashFilter');
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('siteHash:xyz', $parameters['fq'], 'Could not get filters from query object');
        $this->builder->startFrom($query)->removeFilterByName('siteHashFilter');

        $parameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('fq', $parameters, 'Could not remove filters from query object by filter key');
    }

    #[Test]
    public function canRemoveFilterByValue(): void
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        // can we add a filter?
        $this->builder->startFrom($query)->useFilter('foo:bar');
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('foo:bar', $parameters['fq'], 'Could not get filters from query object');

        $this->builder->startFrom($query)->removeFilterByValue('foo:bar');
        $parameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('fq', $parameters, 'Filters are not empty after removing the last one');
    }

    #[Test]
    public function canUseFilterIgnoreSecondePassedFilterWithSameKey(): void
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $query = $this->getInitializedTestSearchQuery('test', $fakeConfiguration);

        // we add a filter with the same key twice and expect that only the first one is kept and not overwritten
        $this->builder->startFrom($query)->useFilter('foo:bar', 'test')->useFilter('foo:bla', 'test');
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('foo:bar', $parameters['fq'], 'Unexpected filter query was added');
    }

    #[Test]
    public function canSetAndUnSetQueryType(): void
    {
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('qt', $queryParameters, 'The qt parameter was expected to be null');

        $this->builder->startFrom($query)->useQueryType('dismax');
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('dismax', $queryParameters['qt'], 'The qt parameter was expected to be dismax');

        //passing false as parameter should reset the query type
        $this->builder->startFrom($query)->removeQueryType();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('qt', $queryParameters, 'The qt parameter was expected to be null after reset');
    }

    #[Test]
    public function canSetOperator(): void
    {
        $query = $this->getInitializedTestSearchQuery('test');

        $queryParameters = $query->getParams();
        self::assertArrayNotHasKey('q.op', $queryParameters, 'The queryParameter q.op should be null because no operator was passed');

        $this->builder->startFrom($query)->useOperator(Operator::getOr());
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertEquals(Operator::OPERATOR_OR, $queryParameters['q.op'], 'The queryParameter q.op should be OR');

        $this->builder->startFrom($query)->useOperator(Operator::getAnd());
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertEquals(Operator::OPERATOR_AND, $queryParameters['q.op'], 'The queryParameter q.op should be AND');

        $this->builder->startFrom($query)->removeOperator();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertEmpty($queryParameters['q.op'], 'The queryParameter q.op should be null because operator was resetted');
    }

    #[Test]
    public function canSetAlternativeQuery(): void
    {
        // check initial value
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('q.alt', $queryParameters, 'We expected that alternative query is initially null');

        // can we set it?
        $this->builder->startFrom($query)->useAlternativeQuery('alt query');
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertEquals('alt query', $queryParameters['q.alt'], 'Could not get passed alternative query');

        // can we reset it?
        $this->builder->startFrom($query)->removeAlternativeQuery();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('q.alt', $queryParameters, 'We expect alternative query is null after reset');
    }

    #[Test]
    public function canSetOmitHeaders(): void
    {
        // check initial value
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $query->getParams();
        self::assertArrayNotHasKey('omitHeader', $queryParameters, 'The queryParameter omitHeader should be null because it was not');

        $this->builder->startFrom($query)->useOmitHeader();

        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('true', $queryParameters['omitHeader'], 'The queryParameter omitHeader should be "true" because it was enabled');

        $this->builder->startFrom($query)->useOmitHeader(false);

        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('false', $queryParameters['omitHeader'], 'The queryParameter omitHeader should be null because it was resetted');
    }

    #[Test]
    public function canSetReturnFields(): void
    {
        // check initial value
        $query = $this->getInitializedTestSearchQuery(
            'test',
            new TypoScriptConfiguration([]),
        );
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('*,score', $queryParameters['fl'], 'FieldList initially contained unexpected values');

        // set from string
        $this->builder->startFrom($query)->useReturnFields(ReturnFields::fromString('content, title'));
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('content,title', $queryParameters['fl'], 'Can not set fieldList from string');

        // set from array
        $this->builder->startFrom($query)->useReturnFields(ReturnFields::fromArray(['content', 'title']));
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('content,title', $queryParameters['fl'], 'Can not set fieldList from array');
    }

    #[Test]
    public function canSetSorting(): void
    {
        // check initial value
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('sort', $queryParameters, 'Sorting should be null at the beginning');

        // can set a field and direction combination
        $this->builder->startFrom($query)->useSorting(Sorting::fromString('title desc'));
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('title desc', $queryParameters['sort'], 'Could not set sorting');

        // can reset
        $this->builder->startFrom($query)->removeAllSortings();
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('sort', $queryParameters, 'Sorting should be null after reset');

        // when relevance is getting passed it is the same as we have no
        // sorting because this is a "virtual" value
        $this->builder->startFrom($query)->useSorting(Sorting::fromString('relevance desc'));
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('sort', $queryParameters, 'Sorting should be null after reset');
    }

    #[Test]
    public function canSetQueryElevation(): void
    {
        $query = $this->getInitializedTestSearchQuery(
            'test',
            new TypoScriptConfiguration([]),
        );
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('enableElevation', $queryParameters);
        self::assertArrayNotHasKey('forceElevation', $queryParameters);
        self::assertStringNotContainsString('isElevated:[elevated]', $queryParameters['fl']);

        // do we get the expected default values, when calling setQueryElevantion with no arguments?

        $elevation = new Elevation(true);
        $this->builder->startFrom($query)->useElevation($elevation);
        $queryParameters = $this->getAllQueryParameters($query);
        self::assertSame('true', $queryParameters['enableElevation'], 'enabledElevation was not set after enabling elevation');
        self::assertSame('true', $queryParameters['forceElevation'], 'forceElevation was not set after enabling elevation');
        self::assertStringContainsString('isElevated:[elevated]', $queryParameters['fl'], 'isElevated should be in the list of return fields');

        // can we reset the elevantion?
        $elevation->setIsEnabled(false);
        $this->builder->startFrom($query)->useElevation($elevation);
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('enableElevation', $queryParameters);
        self::assertArrayNotHasKey('forceElevation', $queryParameters);
        self::assertSame('*,score', $queryParameters['fl']);
    }

    #[Test]
    public function forceElevationIsFalseWhenForcingToFalse(): void
    {
        $query = $this->getInitializedTestSearchQuery('test');
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('enableElevation', $queryParameters);
        self::assertArrayNotHasKey('forceElevation', $queryParameters);

        $elevation = new Elevation();
        $elevation->setIsEnabled(true);
        $elevation->setIsForced(false);

        $this->builder->startFrom($query)->useElevation($elevation);
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertSame('true', $queryParameters['enableElevation'], 'enabledElevation was not set after enabling elevation');
        self::assertSame('false', $queryParameters['forceElevation'], 'forceElevation was not false after forcing');

        $elevation->setIsEnabled(false);
        $this->builder->startFrom($query)->useElevation($elevation);
        $queryParameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('enableElevation', $queryParameters);
        self::assertArrayNotHasKey('forceElevation', $queryParameters);
    }

    #[Test]
    public function canBuildExpectedQueryUrlFromCombinedQuery(): void
    {
        $faceting = new Faceting(true);
        $faceting->addField('content');
        $faceting->addField('type');
        $faceting->addField('color');

        $returnFields = new ReturnFields();
        $returnFields->add('title');

        $fieldCollapsing = new FieldCollapsing(true);

        $queryBuilder = new QueryBuilder($this->configurationMock, $this->loggerMock, $this->siteHashServiceMock);
        $query = $queryBuilder->newSearchQuery('hello world')
            ->useFilter('color:red')
            ->useUserAccessGroups([1, 2, 3])
            ->useFaceting($faceting)
            ->useReturnFields($returnFields)
            ->useFieldCollapsing($fieldCollapsing)
            ->getQuery();

        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('title', $parameters['fl']);

        $filterQueries = $parameters['fq'];
        self::assertCount(3, $filterQueries, 'Unexpected amount of filter queries created');
        self::assertSame('color:red', $parameters['fq'][0]);
        self::assertSame('{!typo3access}0,1,2,3', $parameters['fq'][1]);
        self::assertSame('{!collapse field=variantId}', $parameters['fq'][2]);

        self::assertSame('content', $parameters['facet.field'][0]);
        self::assertSame('type', $parameters['facet.field'][1]);
        self::assertSame('color', $parameters['facet.field'][2]);

        self::assertArrayNotHasKey('qf', $parameters, 'No query fields have been set');
    }

    #[Test]
    public function canSetQueryFieldsFromString(): void
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');

        $this->builder->startFrom($query)->useQueryFields(QueryFields::fromString('content^100.0, title^10.0'));

        $parameters = $this->getAllQueryParameters($query);
        // the , delimiter is removed
        self::assertSame('content^100.0 title^10.0', $parameters['qf'], 'Can not set and get query fields');
    }

    #[Test]
    public function canSetQueryFields(): void
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');
        $parameters = $this->getAllQueryParameters($query);

        self::assertEmpty($parameters['qf'], 'QueryFields are not empty by default');

        $queryFields = new QueryFields([]);
        $queryFields->set('content', 10);
        $queryFields->set('title', 11);

        $this->builder->startFrom($query)->useQueryFields($queryFields);
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('content^10.0 title^11.0', $parameters['qf']);

        // overwrite the boost of title
        $queryFields->set('title', 9);
        $this->builder->startFrom($query)->useQueryFields($queryFields);
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('content^10.0 title^9.0', $parameters['qf'], 'qf parameter not set in QueryParameters');
    }

    #[Test]
    public function canSetPhraseFieldsFromString(): void
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');
        $this->builder->startFrom($query)->usePhraseFields(PhraseFields::fromString('content^100.0, title^10.0'));
        $parameters = $this->getAllQueryParameters($query);
        // the , delimiter is removed
        self::assertSame('content^100.0 title^10.0', $parameters['pf'], 'Can not set and get phrase fields');
    }

    #[Test]
    public function canSetPhraseFields(): void
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');
        $parameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('pf', $parameters, 'Phrase Fields must be empty by default');

        $phraseFields = new PhraseFields(true);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);

        $this->builder->startFrom($query)->usePhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('content^10.0 title^11.0', $parameters['pf']);

        // overwrite the boost of title
        $phraseFields->add('title', 9);
        $this->builder->startFrom($query)->usePhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);

        self::assertSame('content^10.0 title^9.0', $parameters['pf']);
    }

    #[Test]
    public function phraseFieldsAreNotSetInUrlQueryIfPhraseSearchIsDisabled(): void
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');

        $phraseFields = new PhraseFields(false);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);
        $this->builder->startFrom($query)->usePhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('pf', $parameters, 'pf parameter must be empty(not set) if phrase search is disabled');
    }

    #[Test]
    public function phraseFieldsAreSetInUrlQueryIfPhraseSearchIsEnabled(): void
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
        self::assertSame('content^10.0 title^11.0', $parameters['pf'], 'pf parameters must be set if phrase search is enabled');
    }

    #[Test]
    public function canAddPhraseFieldsFromConfiguration(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['phrase'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['phrase.']['fields'] = 'content^22.0, title^11.0';

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('foo bar', $fakeConfiguration);

        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('content^22.0 title^11.0', $parameters['pf'], 'pf parameters must be set if phrase search is enabled');
    }

    #[Test]
    public function bigramPhraseFieldsAreNotSetInUrlQueryIfBigramPhraseSearchIsDisabled(): void
    {
        $query = $this->getInitializedTestSearchQuery('foo bar baz');
        $phraseFields = new BigramPhraseFields(false);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);
        $this->builder->startFrom($query)->useBigramPhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('pf2', $parameters, 'pf2 parameter must be empty(not set) if phrase search is disabled');
    }

    #[Test]
    public function canAddBigramFieldsWhenBigramPhraseIsEnabled(): void
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
        self::assertSame('content^10.0 title^11.0', $parameters['pf2'], 'pf2 parameters must be set if bigram phrase search is enabled');
    }

    #[Test]
    public function canAddBigramFieldsFromConfiguration(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['bigramPhrase'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['bigramPhrase.']['fields'] = 'content^12.0, title^14.0';

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('foo bar', $fakeConfiguration);

        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('content^12.0 title^14.0', $parameters['pf2'], 'pf2 parameters must be set if bigram phrase search is enabled');
    }

    #[Test]
    public function trigramPhraseFieldsAreNotSetInUrlQueryIfTrigramPhraseSearchIsDisabled(): void
    {
        $query = $this->getInitializedTestSearchQuery('foo bar baz foobar barbaz');
        $phraseFields = new TrigramPhraseFields(false);
        $phraseFields->add('content', 10);
        $phraseFields->add('title', 11);
        $this->builder->startFrom($query)->useTrigramPhraseFields($phraseFields);
        $parameters = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('pf3', $parameters, 'pf3 parameter must be empty(not set) if phrase search is disabled');
    }

    #[Test]
    public function trigramPhraseFieldsAreSetInUrlQueryIfTrigramPhraseSearchIsEnabled(): void
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
        self::assertSame('content^10.0 title^11.0', $parameters['pf3'], 'pf3 parameters must be set if trigram phrase search is enabled');
    }

    #[Test]
    public function canAddTrigramFieldsFromConfiguration(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['trigramPhrase'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['trigramPhrase.']['fields'] = 'content^12.0, title^14.0';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $query = $this->getInitializedTestSearchQuery('foo bar', $fakeConfiguration);
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('content^12.0 title^14.0', $parameters['pf3'], 'pf3 parameters must be set if trigram phrase search is enabled');
    }

    #[Test]
    public function setDebugMode(): void
    {
        $query = $this->getInitializedTestSearchQuery();

        $parameter = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('debugQuery', $parameter, 'Debug query should be disabled by default');
        self::assertArrayNotHasKey('echoParams', $parameter, 'Debug query should be disabled by default');

        $this->builder->startFrom($query)->useDebug(true);
        $parameter = $this->getAllQueryParameters($query);

        self::assertSame('true', $parameter['debugQuery'], 'Debug query should be disabled by default');
        self::assertSame('all', $parameter['echoParams'], 'Debug query should be disabled by default');

        $this->builder->startFrom($query)->useDebug(false);
        $parameter = $this->getAllQueryParameters($query);
        self::assertArrayNotHasKey('debugQuery', $parameter, 'Can not unset debug mode');
        self::assertArrayNotHasKey('echoParams', $parameter, 'Can not unset debug mode');
    }

    #[Test]
    public function addingQueriesToGroupingAddsToRightGroupingParameter(): void
    {
        $query = $this->getInitializedTestSearchQuery('group test');

        $grouping = new Grouping(true);
        $grouping->addQuery('price:[* TO 500]');
        $grouping->addQuery('someField:someValue');
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame(['price:[* TO 500]', 'someField:someValue'], $parameters['group.query'], 'Could not add group queries properly');
    }

    #[Test]
    public function addingSortingsToGroupingAddsToRightGroupingParameter(): void
    {
        $query = $this->getInitializedTestSearchQuery('group test');
        $grouping = new Grouping(true);
        $grouping->addSorting('price_f');
        $grouping->addSorting('title desc');
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame('price_f title desc', $parameters['group.sort'], 'Could not add group sortings properly');
    }

    #[Test]
    public function addingFieldsToGroupingAddsToRightGroupingParameter(): void
    {
        $query = $this->getInitializedTestSearchQuery('group test');
        $grouping = new Grouping(true);
        $grouping->addField('price_f');
        $grouping->addField('category_s');
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);
        self::assertSame(['price_f', 'category_s'], $parameters['group.field'], 'Could not add group fields properly');
    }

    #[Test]
    public function canDisablingGroupingRemoveTheGroupSorting(): void
    {
        $query = $this->getInitializedTestSearchQuery('foo bar');

        $grouping = new Grouping(true);
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);

        self::assertSame($parameters['group'], 'true');
        self::assertSame($parameters['group.format'], 'grouped');
        self::assertSame($parameters['group.ngroups'], 'true');

        $grouping->addSorting('title desc');
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);

        self::assertSame($parameters['group.sort'], 'title desc', 'Group sorting was not added');
        self::assertArrayNotHasKey('group.field', $parameters, 'No field was passed, so it should not be set');
        self::assertArrayNotHasKey('group.query', $parameters, 'No query was passed, so it should not be set');

        $grouping->setIsEnabled(false);
        $this->builder->startFrom($query)->useGrouping($grouping);
        $parameters = $this->getAllQueryParameters($query);

        self::assertArrayNotHasKey('group.sort', $parameters, 'Grouping parameters should be removed');
        self::assertArrayNotHasKey('group', $parameters, 'Grouping parameters should be removed');
        self::assertArrayNotHasKey('group.format', $parameters, 'Grouping parameters should be removed');
        self::assertArrayNotHasKey('group.ngroups', $parameters, 'Grouping parameters should be removed');
    }

    #[Test]
    public function canBuildSuggestQuery(): void
    {
        $this->configurationMock
            ->expects(self::once())
            ->method('getObjectByPathOrDefault')
            ->willReturn([
                'suggestField' => 'spell',
                'numberOfSuggestions' => 10,
            ]);
        $this->builder = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$this->configurationMock, $this->loggerMock, $this->siteHashServiceMock])
            ->onlyMethods(['useSiteHashFromTypoScript'])
            ->getMock();

        $suggestQuery = $this->builder->buildSuggestQuery('foo', [], 3232, []);
        $queryParameters = $this->getAllQueryParameters($suggestQuery);
        self::assertSame('foo', $queryParameters['facet.prefix'], 'Passed query string is not used as facet.prefix argument');
    }

    #[Test]
    public function alternativeQueryIsWildCardQueryForSuggestQuery(): void
    {
        $this->configurationMock
            ->expects(self::once())
            ->method('getObjectByPathOrDefault')
            ->willReturn([
                'suggestField' => 'spell',
                'numberOfSuggestions' => 10,
            ]);
        $this->builder = $this->getMockBuilder(QueryBuilder::class)
                                ->setConstructorArgs([$this->configurationMock, $this->loggerMock, $this->siteHashServiceMock])
                                ->onlyMethods(['useSiteHashFromTypoScript'])
                                ->getMock();

        $suggestQuery = $this->builder->buildSuggestQuery('bar', [], 3232, []);
        $queryParameters = $this->getAllQueryParameters($suggestQuery);
        self::assertSame('*:*', $queryParameters['q.alt'], 'Alterntive query is not set to wildcard query by default');
    }
}
