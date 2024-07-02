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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

class SearchRequestTest extends SetUpUnitTestCase
{
    protected SearchRequest $searchRequest;

    protected function setUp(): void
    {
        $this->searchRequest = new SearchRequest();
        parent::setUp();
    }

    #[Test]
    public function testGetPageIsNullWhenNothingWasPassed(): void
    {
        self::assertNull($this->searchRequest->getPage(), 'Page was expected to be null');
    }

    #[Test]
    public function testCanMerge(): void
    {
        $this->searchRequest = new SearchRequest(['tx_solr' => ['page' => 2]]);
        self::assertSame(2, $this->searchRequest->getPage(), 'Retrieved unexpected page');

        $this->searchRequest->mergeArguments(['tx_solr' => ['page' => 8]]);
        self::assertSame(8, $this->searchRequest->getPage(), 'Page was not properly merged');
    }

    #[Test]
    public function canGetActiveFilterNames(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages';
        $request = $this->getSearchRequestFromQueryString($query);
        self::assertEquals(['type'], $request->getActiveFacetNames());
    }

    #[Test]
    public function canGetRawQueryString(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages';
        $request = $this->getSearchRequestFromQueryString($query);
        self::assertEquals('typo3', $request->getRawUserQuery());
    }

    #[Test]
    public function canSetQueryString(): void
    {
        $request = $this->getSearchRequestFromQueryString('');
        $data  = $request->setRawQueryString('foobar')->getAsArray();
        self::assertEquals(['tx_solr' => ['q' => 'foobar']], $data, 'The argument container did not contain the expected argument');
    }

    #[Test]
    public function canAddOneFacet(): void
    {
        $request = $this->getSearchRequestFromQueryString('');
        $arguments  = $request->addFacetValue('foo', 'bar')->getAsArray();
        $expectedArguments = [];
        $expectedArguments['tx_solr']['filter'][0] = 'foo:bar';
        self::assertSame($arguments, $expectedArguments, 'Adding a facet did not product the expected structure');
    }

    #[Test]
    public function canAddManyFacets(): void
    {
        $request = $this->getSearchRequestFromQueryString('');
        $arguments  = $request->addFacetValue('type', 'pages')->addFacetValue('type', 'tt_content')->getAsArray();
        $expectedArguments = [];
        $expectedArguments['tx_solr']['filter'][0] = 'type:pages';
        $expectedArguments['tx_solr']['filter'][1] = 'type:tt_content';

        self::assertSame($arguments, $expectedArguments, 'Adding a facet did not product the expected structure');
    }

    #[Test]
    public function canAddFacetsAndQuery(): void
    {
        $request = $this->getSearchRequestFromQueryString('');
        $arguments  = $request->setRawQueryString('mysearch')->addFacetValue('type', 'tt_content')->getAsArray();

        $expectedArguments = [];
        $expectedArguments['tx_solr']['q'] = 'mysearch';
        $expectedArguments['tx_solr']['filter'][0] = 'type:tt_content';

        self::assertSame($arguments, $expectedArguments, 'Could not set a query and add a facet at the same time');
    }

    #[Test]
    public function canReset(): void
    {
        $request = $this->getSearchRequestFromQueryString('');
        $arguments  = $request->setRawQueryString('mysearch')->addFacetValue('type', 'tt_content')->reset()->getAsArray();
        $expectedArguments = [];
        self::assertSame($arguments, $expectedArguments, 'Could not reset arguments');
    }

    #[Test]
    public function canGetCopyForSubRequest(): void
    {
        $request = $this->getSearchRequestFromQueryString('');

        // we persist before we reset the arguments therefore the arguments should be kept
        $arguments  = $request->setRawQueryString('mysearch')
                            ->addFacetValue('type', 'tt_content')
                            ->getCopyForSubRequest()
                            ->getAsArray();

        $expectedArguments = [];
        $expectedArguments['tx_solr']['q'] = 'mysearch';
        $expectedArguments['tx_solr']['filter'][0] = 'type:tt_content';

        self::assertSame($arguments, $expectedArguments, 'Could not reset arguments');
    }

    #[Test]
    public function nonPersistentArgumentsGetLostForSubRequest(): void
    {
        $request = $this->getSearchRequestFromQueryString('');

        // we persist before we reset the arguments therefore the arguments should be kept
        $arguments  = $request->setRawQueryString('mysearch')
                                ->addFacetValue('type', 'tt_content')
                                ->setPage(2)
                                ->getCopyForSubRequest()->getAsArray();

        $expectedArguments = [];
        $expectedArguments['tx_solr']['q'] = 'mysearch';
        $expectedArguments['tx_solr']['filter'][0] = 'type:tt_content';

        self::assertSame($arguments, $expectedArguments);
    }

    #[Test]
    public function canGetContextSystemLanguageUidPassedOnCreation(): void
    {
        $request = new SearchRequest([], 111, 4711);
        self::assertSame($request->getContextSystemLanguageUid(), 4711, 'Can get initial passed sys_language_uid');
    }

    #[Test]
    public function canGetContextPageUidPassedOnCreation(): void
    {
        $request = new SearchRequest([], 111, 4711);
        self::assertSame($request->getContextPageUid(), 111, 'Can get initial passed page_uid');
    }

    #[Test]
    public function canRemoveFacetValue(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages';
        $request = $this->getSearchRequestFromQueryString($query);

        self::assertTrue($request->getHasFacetValue('type', 'pages'), 'Facet was not present');
        $request->removeFacetValue('type', 'pages');
        self::assertFalse($request->getHasFacetValue('type', 'pages'), 'Could not remove facet value');
    }

    #[Test]
    public function canGetFacetValues(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages&tx_solr%5Bfilter%5D%5B1%5D=type%253Anews';
        $request = $this->getSearchRequestFromQueryString($query);
        self::assertEquals(['pages', 'news'], $request->getActiveFacetValuesByName('type'));
    }

    #[Test]
    public function canRemoveAllFacets(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages&tx_solr%5Bfilter%5D%5B1%5D=type%253Aevents';
        $request = $this->getSearchRequestFromQueryString($query);
        self::assertSame(2, $request->getActiveFacetCount(), 'Expected to have two active facets');
        $request->removeAllFacets();
        self::assertSame(0, $request->getActiveFacetCount(), 'Expected to have no active facets');
    }

    #[Test]
    public function canRemoveFacetsByName(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages&tx_solr%5Bfilter%5D%5B1%5D=type%253Aevents&tx_solr%5Bfilter%5D%5B2%5D=created%253A1-4';
        $request = $this->getSearchRequestFromQueryString($query);
        self::assertSame(3, $request->getActiveFacetCount(), 'Expected to have two active facets');
        $request->removeAllFacetValuesByName('type');
        self::assertSame(1, $request->getActiveFacetCount(), 'Only 1 facet should remain active');
    }

    #[Test]
    public function canGetSortingField(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bsort%5D=title asc';
        $request = $this->getSearchRequestFromQueryString($query);
        self::assertTrue($request->getHasSorting(), 'Passed query has no sorting');
        self::assertSame('title', $request->getSortingName(), 'Expected sorting name was title');
        self::assertSame('asc', $request->getSortingDirection(), 'Expected sorting direction was asc');
    }

    #[Test]
    public function canRemoveSorting(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bsort%5D=title asc';
        $request = $this->getSearchRequestFromQueryString($query);
        self::assertTrue($request->getHasSorting(), 'Passed query has no sorting');
        self::assertSame('title', $request->getSortingName(), 'Expected sorting name was title');
        $requestAsArray = $request->getAsArray();
        self::assertTrue(isset($requestAsArray['tx_solr']['sort']), 'Sorting was not set but was expected to be set');

        $request->removeSorting();
        self::assertFalse($request->getHasSorting(), 'Expected that sorting was removed, but is still present');

        $requestAsArray = $request->getAsArray();
        self::assertFalse(isset($requestAsArray['tx_solr']['sort']), 'Sorting was set but was not expected to be set');
    }

    #[Test]
    public function canSetSorting(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3';
        $request = $this->getSearchRequestFromQueryString($query);
        self::assertFalse($request->getHasSorting(), 'Passed query has no sorting');

        $request->setSorting('auther', 'desc');
        self::assertTrue($request->getHasSorting(), 'Passed query has no sorting');
    }

    #[Test]
    public function canGetHighestGroupItemPageWhenNoPageWasPassed(): void
    {
        $request = $this->getSearchRequestFromQueryString('');
        self::assertSame(1, $request->getHighestGroupPage(), 'Can not get highest group item page when no group page was passed');
    }

    #[Test]
    public function canGetInitialGroupItemPage(): void
    {
        $request = $this->getSearchRequestFromQueryString('');
        self::assertSame(1, $request->getGroupItemPage('typeGroup', 'pages'), 'Can not get initial group item page');
    }

    #[Test]
    public function canSetGroupItemPage(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3';
        $request = $this->getSearchRequestFromQueryString($query);
        $request->setGroupItemPage('typeGroup', 'pages', 2);

        self::assertSame(2, $request->getGroupItemPage('typeGroup', 'pages'), 'Can not set and get groupItemPage');
    }

    #[Test]
    public function canSetGroupItemPageForQuery(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3';
        $request = $this->getSearchRequestFromQueryString($query);
        $request->setGroupItemPage('pidGroup', 'pid:[0 to 5]', 3);

        self::assertSame(3, $request->getGroupItemPage('pidGroup', 'pid:[0 to 5]'), 'Can not set and get groupItemPage for a query');
    }

    #[Test]
    public function canResetAllGroupItemPages(): void
    {
        $query = 'tx_solr%5Bq%5D=typo3';
        $request = $this->getSearchRequestFromQueryString($query);
        $request->setGroupItemPage('typeGroup', 'pages', 2);
        $request->setGroupItemPage('colorGroup', 'colors', 4);

        $requestArguments = $request->getAsArray();
        self::assertCount(2, $requestArguments['tx_solr']['groupPage'], 'Expected to have two group pages registered');

        $request->removeAllGroupItemPages();
        $requestArguments = $request->getAsArray();
        self::assertArrayNotHasKey('groupPage', $requestArguments['tx_solr'], 'Expected to have two group pages registered');
    }

    #[Test]
    public function twoDifferentRequestsHaveADifferentId(): void
    {
        $newSearchRequest = new SearchRequest();
        self::assertNotEquals($newSearchRequest->getId(), $this->searchRequest->getId(), 'Two different requests seem to have the same id');
    }

    #[Test]
    public function setPerPageWillMarkedTheRequestAsChanged(): void
    {
        self::assertFalse($this->searchRequest->getStateChanged());
        $this->searchRequest->setResultsPerPage(10);
        self::assertTrue($this->searchRequest->getStateChanged());
    }

    /**
     * @param $query
     * @return SearchRequest
     */
    protected function getSearchRequestFromQueryString($query)
    {
        $FAKE_GET = [];
        parse_str(urldecode($query), $FAKE_GET);
        $request = new SearchRequest($FAKE_GET);
        return $request;
    }

    #[Test]
    public function canGetContextTypoScriptConfigurationPassedOnCreation(): void
    {
        $typoScriptConfiguration = $this->createMock(TypoScriptConfiguration::class);
        $request = new SearchRequest([], 111, 4711, $typoScriptConfiguration);

        self::assertSame($request->getContextTypoScriptConfiguration(), $typoScriptConfiguration, 'Can get initial passed TypoScriptConfiguration');
    }
}
