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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Testcase to check if we can index page documents using the PageIndexer
 */
class PageIndexerTest extends IntegrationTestBase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/solr',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();

        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            page.10.renderObj.5 = TEXT
            page.10.renderObj.5.field = header
            page.10.stdWrap.dataWrap = <!--TYPO3SEARCH_begin-->|<!--TYPO3SEARCH_end-->
            '
        );
    }

    /**
     * Executed after each test. Emptys solr and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    #[DataProvider('canIndexPageWithAccessProtectedContentIntoSolrDataProvider')]
    #[Test]
    public function canIndexPageWithAccessProtectedContentIntoSolr(
        string $fixture,
        int $expectedNumFound,
        array $expectedAccessFieldValues,
        array $expectedContents,
        int $expectedNumFoundAnonymousUser,
        string $userGroupToCheckAccessFilter,
        int $expectedNumFoundLoggedInUser,
        string $core = 'core_en'
    ): void {
        self::markTestSkipped('@todo: Fix it. See: https://github.com/TYPO3-Solr/ext-solr/issues/4161');

        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture . '.csv');

        $createPageIndexerMock = function(): PageIndexerRequest {
            $requestMock = $this->getMockBuilder(PageIndexerRequest::class)
                ->onlyMethods(['send'])
                ->getMock();
            $sendCallback = function($indexRequestUrl) use ($requestMock): PageIndexerResponse {
                return $this->sendPageIndexerRequest($indexRequestUrl, $requestMock);
            };
            $requestMock->method('send')->willReturnCallback($sendCallback);

            return $requestMock;
        };

        $pageIndexer = $this->getMockBuilder(PageIndexer::class)
            ->onlyMethods(['getPageIndexerRequest'])
            ->getMock();
        $pageIndexer->method('getPageIndexerRequest')->willReturnCallback($createPageIndexerMock);

        $item = $this->getIndexQueueItem(4711);
        $pageIndexer->index($item);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr($core);

        $solrContent = json_decode(
            file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/' . $core . '/select?q=*:*&sort=access%20asc'),
            true
        );

        self::assertEquals($expectedNumFound, $solrContent['response']['numFound'] ?? 0, 'Could not index documents into Solr');
        foreach ($expectedAccessFieldValues as $index => $expectedAccessFieldValue) {
            self::assertEquals(
                $expectedAccessFieldValue,
                $solrContent['response']['docs'][$index]['access'][0] ?? '',
                'Wrong access settings document ' . ($index + 1)
            );
        }
        foreach ($expectedContents as $index => $expectedContent) {
            self::assertEquals(
                $expectedContent,
                $solrContent['response']['docs'][$index]['content'] ?? '',
                'Wrong content in document ' . ($index + 1)
            );
        }

        $solrContent = json_decode(
            file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/' . $core . '/select?q=*:*&fl=uid&sort=access%20asc&fq={!typo3access}0'),
            true
        );
        self::assertEquals($expectedNumFoundAnonymousUser, $solrContent['response']['numFound'], 'Protected contents not filtered correctly');

        $solrContent = json_decode(
            file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/' . $core . '/select?q=*:*&fl=uid&sort=access%20asc&fq={!typo3access}' . $userGroupToCheckAccessFilter),
            true
        );
        self::assertEquals($expectedNumFoundLoggedInUser, $solrContent['response']['numFound'], 'Protected contents not returned correctly');
    }

    /**
     * Data provider for canIndexPageWithAccessProtectedContentIntoSolr
     */
    public static function canIndexPageWithAccessProtectedContentIntoSolrDataProvider(): Traversable
    {
        yield 'protected page' => [
            'fixture' => 'can_index_access_protected_page',
            'expectedNumFound' => 1,
            'expectedAccessFieldValues' => [
                '2:1/c:0',
            ],
            'expectedContents' => [
                'public content of protected page',
            ],
            'expectedNumFoundAnonymousUser' => 0,
            'userGroupToCheckAccessFilter' => '0,1',
            'expectedNumFoundLoggedInUser' => 1,
        ];

        yield 'page for any login(-2)' => [
            'fixture' => 'can_index_access_protected_page_show_at_any_login',
            'expectedNumFound' => 1,
            'expectedAccessFieldValues' => [
                '2:-2/c:0',
            ],
            'expectedContents' => [
                'access restricted content for any login',
            ],
            'expectedNumFoundAnonymousUser' => 0,
            'userGroupToCheckAccessFilter' => '-2,0,33',
            'expectedNumFoundLoggedInUser' => 1,
        ];

        yield 'protected page with protected content' => [
            'fixture' => 'can_index_access_protected_page_with_protected_contents',
            'expectedNumFound' => 2,
            'expectedAccessFieldValues' => [
                '2:1/c:0',
                '2:1/c:2',
            ],
            'expectedContents' => [
                'public content of protected page',
                'public content of protected pageprotected content of protected page',
            ],
            'expectedNumFoundAnonymousUser' => 0,
            'userGroupToCheckAccessFilter' => '0,1,2',
            'expectedNumFoundLoggedInUser' => 2,
        ];

        yield 'translation of protected page with protected content' => [
            'fixture' => 'can_index_access_protected_page_with_protected_contents',
            'expectedNumFound' => 2,
            'expectedAccessFieldValues' => [
                '2:1/c:0',
                '2:1/c:2',
            ],
            'expectedContents' => [
                'public content of protected page de',
                'public content of protected page deprotected content of protected page de',
            ],
            'expectedNumFoundAnonymousUser' => 0,
            'userGroupToCheckAccessFilter' => '0,1,2',
            'expectedNumFoundLoggedInUser' => 2,
            'core_de',
        ];

        yield 'public page with protected content and global content' => [
            'fixture' => 'can_index_page_with_protected_content',
            'expectedNumFound' => 2,
            'expectedAccessFieldValues' => [
                'c:0',
                'c:1',
            ],
            'expectedContents' => [
                'public ce',
                'protected cepublic ce',
            ],
            'expectedNumFoundAnonymousUser' => 1,
            'userGroupToCheckAccessFilter' => '0,1',
            'expectedNumFoundLoggedInUser' => 2,
        ];

        yield 'public page with protected and hide at login content' => [
            'fixture' => 'can_index_page_with_protected_and_hideatlogin_content',
            'expectedNumFound' => 2,
            'expectedAccessFieldValues' => [
                'c:0',
                'c:1',
            ],
            'expectedContents' => [
                'hide at login content',
                'protected ce',
            ],
            'expectedNumFoundAnonymousUser' => 1,
            'userGroupToCheckAccessFilter' => '0,1',
            'expectedNumFoundLoggedInUser' => 2,
        ];

        yield 'page protected by extend to subpages' => [
            'fixture' => 'can_index_sub_page_of_protected_page_with_extend_to_subpage',
            'expectedNumFound' => 1,
            'expectedAccessFieldValues' => [
                '2:1/c:0',
            ],
            'expectedContents' => [
                'public content of protected page',
            ],
            'expectedNumFoundAnonymousUser' => 0,
            'userGroupToCheckAccessFilter' => '0,1',
            'expectedNumFoundLoggedInUser' => 1,
        ];
    }

    /**
     * Sends a page indexer request
     *
     * In test environment we have to use an InternalRequest, this method
     * is intended to replace PageIndexerRequest->send()
     */
    protected function sendPageIndexerRequest(string $url, PageIndexerRequest $request): PageIndexerResponse
    {
        $internalRequest = new InternalRequest($url);

        foreach ($request->getHeaders() as $header) {
            [$headerName, $headerValue] = GeneralUtility::trimExplode(':', $header, true, 2);
            $internalRequest = $internalRequest->withAddedHeader($headerName, $headerValue);
        }

        $rawResponse = $this->executeFrontendSubRequest($internalRequest);
        $rawResponse->getBody()->rewind();

        $indexerResponse = GeneralUtility::makeInstance(PageIndexerResponse::class);
        $decodedResponse = $indexerResponse->getResultsFromJson($rawResponse->getBody()->getContents());
        $rawResponse->getBody()->rewind();

        self::assertNotNull($decodedResponse, 'Failed to execute Page Indexer Request during integration test');

        $requestId = $decodedResponse['requestId'] ?? null;
        self::assertNotNull($requestId, 'Request id not set as expected');
        $indexerResponse->setRequestId($requestId);
        foreach (($decodedResponse['actionResults'] ?? []) as $action => $actionResult) {
            $indexerResponse->addActionResult($action, $actionResult);
        }

        return $indexerResponse;
    }
}
