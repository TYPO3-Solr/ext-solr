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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class SearchResultSetServiceTest extends IntegrationTest
{
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
        $this->importCSVDataSet(__DIR__ . '/../../../Controller/Fixtures/indexing_data.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->indexPages([1, 2, 3, 4, 5]);

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('002de2729efa650191f82900ea02a0a3189dfabb/pages/1/0/0/0', $solrContent);

        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(1);

        $typoScriptConfiguration = Util::getSolrConfiguration();

        $search = GeneralUtility::makeInstance(Search::class, $solrConnection);
        /* @var SearchResultSetService $searchResultsSetService */
        $searchResultsSetService = GeneralUtility::makeInstance(SearchResultSetService::class, $typoScriptConfiguration, $search);
        $document = $searchResultsSetService->getDocumentById('002de2729efa650191f82900ea02a0a3189dfabb/pages/1/0/0/0');

        self::assertSame($document->getTitle(), 'Root of Testpage testone.site aka integration_tree_one', 'Could not get document from solr by id');
    }

    /**
     * @test
     */
    public function canGetVariants()
    {
        $this->importCSVDataSet(__DIR__ . '/../../../Controller/Fixtures/indexing_data.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_get_searchResultSet.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

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

        $searchResults = $this->doSearchWithResultSetService($typoScriptConfiguration);
        self::assertSame(4, count($searchResults), 'There should be three results at all');

        // We assume that the first result (pid=0) has no variants.
        $firstResult = $searchResults[0];
        self::assertSame(0, count($firstResult->getVariants()));

        // We assume that the second result (pid=1) has 6 variants.
        $secondResult = $searchResults[1];
        self::assertSame(6, count($secondResult->getVariants()));

        // We assume that the third result (pid=3) has no variants.
        $thirdResult = $searchResults[2];
        self::assertSame(0, count($thirdResult->getVariants()));
        self::assertSame('Men Sweatshirts', $thirdResult->getTitle());

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
        $this->importCSVDataSet(__DIR__ . '/../../../Controller/Fixtures/indexing_data.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_get_searchResultSet.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);

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

        $searchResults = $this->doSearchWithResultSetService($typoScriptConfiguration);
        self::assertSame(3, count($searchResults), 'There should be three results at all');

        // We assume that the first result has 6 variants.
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
        $this->importCSVDataSet(__DIR__ . '/../../../Controller/Fixtures/indexing_data.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_get_searchResultSet.csv');

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

        $searchResults = $this->doSearchWithResultSetService($typoScriptConfiguration, 'nomatchfound');
        self::assertSame(0, count($searchResults), 'There should zero results when the index is empty');
    }

    /**
     * @test
     */
    public function cantGetHiddenElementWithoutPermissions()
    {
        $this->importFrontendRestrictedPageScenario();

        $typoScriptConfiguration = Util::getSolrConfiguration();

        // only the default group
        $this->simulateFrontedUserGroups([0]);
        $searchResults = $this->doSearchWithResultSetService($typoScriptConfiguration);

        self::assertSame(2, count($searchResults), 'We should only see two documents because the restricted element should be filtered out');
    }

    /**
     * @test
     */
    public function canGetHiddenElementWithPermissions()
    {
        $this->importFrontendRestrictedPageScenario();

        $typoScriptConfiguration = Util::getSolrConfiguration();

        // user group 0 and 1 should see all elements
        $this->simulateFrontedUserGroups([0, 1]);
        $searchResults = $this->doSearchWithResultSetService($typoScriptConfiguration);

        self::assertSame(3, count($searchResults), 'We should see all content, because nothing should be filtered');
    }

    /**
     * Imports a simple page with user restricted content
     */
    protected function importFrontendRestrictedPageScenario()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/fe_user_page.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->indexPages([1, 2, 3], 1);
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":3', $solrContent);
    }

    protected function doSearchWithResultSetService(TypoScriptConfiguration $typoScriptConfiguration, string $queryString = '*'): SearchResultCollection
    {
        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(1);
        $search = GeneralUtility::makeInstance(Search::class, $solrConnection);

        /* @var SearchResultSetService $searchResultsSetService */
        $searchResultSetService = GeneralUtility::makeInstance(
            SearchResultSetService::class,
            $typoScriptConfiguration,
            $search
        );

        /* @var SearchRequest $searchRequest */
        $searchRequest = GeneralUtility::makeInstance(SearchRequest::class, [], 0, 0, $typoScriptConfiguration);
        $searchRequest->setRawQueryString($queryString);
        $searchRequest->setResultsPerPage(10);
        $searchRequest->setPage(1);

        // Simulate something as we still have some $GLOBALS[TSFE] dependency
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->id = 1;
        $searchResultSet = $searchResultSetService->search($searchRequest);

        $searchResults = $searchResultSet->getSearchResults();
        return $searchResults;
    }

    protected function simulateFrontedUserGroups(array $feUserGroupArray): void
    {
        /** @var  $context Context::class */
        $context = GeneralUtility::makeInstance(Context::class);
        $userAuthentication = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        // Simulate any user
        $userAuthentication->user = [
            'uid' => 1,
            'usergroup' => implode(',', $feUserGroupArray),
        ];
        $userAspect = new UserAspect($userAuthentication, $feUserGroupArray);
        $context->setAspect('frontend.user', $userAspect);
    }
}
