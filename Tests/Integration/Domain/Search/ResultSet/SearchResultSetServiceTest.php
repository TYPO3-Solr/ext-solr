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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Search\ResultSet;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SearchResultSetServiceTest extends IntegrationTest
{
    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * Executed after each test. Emptys solr and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canGetDocumentById()
    {
        // trigger a search
        $this->indexPageIdsFromFixture('can_get_searchResultSet.xml', [1, 2, 3, 4, 5]);

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('002de2729efa650191f82900ea02a0a3189dfabb/pages/1/0/0/0', $solrContent);

        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(1, 0, 0);

        $typoScriptConfiguration = Util::getSolrConfiguration();

        $search = GeneralUtility::makeInstance(Search::class, $solrConnection);
        /** @var $searchResultsSetService SearchResultSetService */
        $searchResultsSetService = GeneralUtility::makeInstance(SearchResultSetService::class, $typoScriptConfiguration, $search);
        $document = $searchResultsSetService->getDocumentById('002de2729efa650191f82900ea02a0a3189dfabb/pages/1/0/0/0');

        self::assertSame($document->getTitle(), 'Products', 'Could not get document from solr by id');
    }

    /**
     * @test
     */
    public function canGetVariants()
    {
        $this->indexPageIdsFromFixture('can_get_searchResultSet.xml', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        $this->waitToBeVisibleInSolr();
        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(1, 0, 0);

        $typoScriptConfiguration = Util::getSolrConfiguration();
        $typoScriptConfiguration->mergeSolrConfiguration([
           'search.' =>[
               'variants' => 1,
               'variants.' => [
                   'variantField' => 'pid',
                   'expand' => 1,
                   'limit' => 11,
               ],
           ],
        ]);

        self::assertTrue($typoScriptConfiguration->getSearchVariants(), 'Variants are not enabled');
        self::assertEquals('pid', $typoScriptConfiguration->getSearchVariantsField());
        self::assertEquals(11, $typoScriptConfiguration->getSearchVariantsLimit());

        $searchResults = $this->doSearchWithResultSetService($solrConnection, $typoScriptConfiguration);
        self::assertSame(4, count($searchResults), 'There should be three results at all');

        // We assume that the first result (pid=0) has no variants.
        $firstResult = $searchResults[0];
        self::assertSame(0, count($firstResult->getVariants()));

        // We assume that the second result (pid=1) has 1 variant.
        $secondResult = $searchResults[1];
        self::assertSame(1, count($secondResult->getVariants()));

        // We assume that the third result (pid=3) has 3 variants.
        $thirdResult = $searchResults[2];
        self::assertSame(3, count($thirdResult->getVariants()));
        self::assertSame('Men Socks', $thirdResult->getTitle());

        // And every variant is indicated to be a variant.
        foreach ($thirdResult->getVariants() as $variant) {
            self::assertTrue($variant->getIsVariant(), 'Document should be a variant');
        }
    }

    /**
     * @test
     */
    public function canGetCaseSensitiveVariants()
    {
        $this->indexPageIdsFromFixture('can_get_searchResultSet.xml', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);

        $this->waitToBeVisibleInSolr();
        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(1, 0, 0);

        $typoScriptConfiguration = Util::getSolrConfiguration();
        $typoScriptConfiguration->mergeSolrConfiguration([
            'search.' =>[
                'variants' => 1,
                'variants.' => [
                    'variantField' => 'author',
                    'expand' => 1,
                    'limit' => 11,
                ],
                'query.' => [
                    'filter.' => [
                        'skipRootPage' => '-pid:0',
                    ],
                ],
            ],
        ]);

        self::assertTrue($typoScriptConfiguration->getSearchVariants(), 'Variants are not enabled');
        self::assertEquals('author', $typoScriptConfiguration->getSearchVariantsField());
        self::assertEquals(11, $typoScriptConfiguration->getSearchVariantsLimit());

        $searchResults = $this->doSearchWithResultSetService($solrConnection, $typoScriptConfiguration);
        self::assertSame(3, count($searchResults), 'There should be three results at all');

        // We assume that the first result has 2 variants.
        /* @var SearchResult $firstResult */
        $firstResult = $searchResults[0];
        self::assertSame(2, count($firstResult->getVariants()));
        self::assertSame('Jane Doe', $firstResult->getAuthor());
        self::assertSame(2, $firstResult->getVariantsNumFound());
        self::assertSame('Jane Doe', $firstResult->getVariantFieldValue());

        // We assume that the second result has 5 variants.
        /* @var SearchResult $secondResult */
        $secondResult = $searchResults[1];
        self::assertSame(5, count($secondResult->getVariants()));
        self::assertSame('John Doe', $secondResult->getAuthor());
        self::assertSame(5, $secondResult->getVariantsNumFound());

        // We assume that the third result has no variants.
        /* @var SearchResult $secondResult */
        $thirdResult = $searchResults[2];
        self::assertSame(0, count($thirdResult->getVariants()));
        self::assertSame('Baby Doe', $thirdResult->getAuthor());
        self::assertSame(0, $thirdResult->getVariantsNumFound());
        self::assertSame('Baby Doe', $thirdResult->getVariantFieldValue());

        // And every variant is indicated to be a variant.
        foreach ($firstResult->getVariants() as $variant) {
            self::assertTrue($variant->getIsVariant(), 'Document should be a variant');
            self::assertSame(0, $variant->getVariantsNumFound(), 'Variant shouldn\'t have variants itself');
            self::assertSame($firstResult, $variant->getVariantParent(), 'Variant parent should be set');
        }
        foreach ($secondResult->getVariants() as $variant) {
            self::assertTrue($variant->getIsVariant(), 'Document should be a variant');
            self::assertSame(0, $variant->getVariantsNumFound(), 'Variant shouldn\'t have variants itself');
            self::assertSame($secondResult, $variant->getVariantParent(), 'Variant parent should be set');
        }
    }

    /**
     * @test
     */
    public function canGetZeroResultsWithVariantsOnEmptyIndex()
    {
        $this->importDataSetFromFixture('can_get_searchResultSet.xml');
        $this->fakeTsfe(1);

        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(1, 0, 0);

        $typoScriptConfiguration = Util::getSolrConfiguration();
        $typoScriptConfiguration->mergeSolrConfiguration([
            'search.' =>[
                'variants' => 1,
                'variants.' => [
                    'variantField' => 'pid',
                    'expand' => 1,
                    'limit' => 11,
                ],
            ],
        ]);

        $searchResults = $this->doSearchWithResultSetService($solrConnection, $typoScriptConfiguration, 'nomatchfound');
        self::assertSame(0, count($searchResults), 'There should zero results when the index is empty');
    }

    /**
     * @test
     */
    public function cantGetHiddenElementWithoutPermissions()
    {
        $this->applyUsingErrorControllerForCMS9andAbove();
        $this->importFrontendRestrictedPageScenario();

        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(1, 0, 0);
        $typoScriptConfiguration = Util::getSolrConfiguration();

        // only the default group
        $this->simulateFrontedUserGroups([0]);
        $searchResults = $this->doSearchWithResultSetService($solrConnection, $typoScriptConfiguration);

        self::assertSame(2, count($searchResults), 'We should only see two documents because the restricted element should be filtered out');
    }

    /**
     * @test
     */
    public function canGetHiddenElementWithPermissions()
    {
        $this->applyUsingErrorControllerForCMS9andAbove();
        $this->importFrontendRestrictedPageScenario();

        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(1, 0, 0);
        $typoScriptConfiguration = Util::getSolrConfiguration();

        // user group 0 and 1 should see all elements
        $this->simulateFrontedUserGroups([0, 1]);
        $searchResults = $this->doSearchWithResultSetService($solrConnection, $typoScriptConfiguration);

        self::assertSame(3, count($searchResults), 'We should see all content, because nothing should be filtered');
    }

    /**
     * Imports a simple page with user restricted content
     */
    protected function importFrontendRestrictedPageScenario()
    {
        $this->indexPageIdsFromFixture('fe_user_page.xml', [1, 2, 3], [1]);
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":3', $solrContent);
    }

    /**
     * @param $solrConnection
     * @param $typoScriptConfiguration
     * @param string $queryString
     * @return array
     */
    protected function doSearchWithResultSetService($solrConnection, $typoScriptConfiguration, $queryString = '*')
    {
        $search = GeneralUtility::makeInstance(Search::class, $solrConnection);
        /** @var $searchResultsSetService SearchResultSetService */
        $searchResultSetService = GeneralUtility::makeInstance(SearchResultSetService::class, $typoScriptConfiguration, $search);

        $fakeObjectManager = $this->getFakeObjectManager();

        $searchResultSetService->injectObjectManager($fakeObjectManager);

        /** @var $searchRequest SearchRequest */
        $searchRequest = GeneralUtility::makeInstance(SearchRequest::class, [], 0, 0, $typoScriptConfiguration);
        $searchRequest->setRawQueryString($queryString);
        $searchRequest->setResultsPerPage(10);

        $searchResultSet = $searchResultSetService->search($searchRequest);

        $searchResults = $searchResultSet->getSearchResults();
        return $searchResults;
    }
}
