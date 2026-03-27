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

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingService;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Tests\Integration\Fixtures\IndexingServiceForTesting;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration tests for indexing access-protected pages and content elements.
 *
 * Verifies that the unified sub-request pipeline (IndexingService + SolrIndexingMiddleware)
 * correctly handles fe_group restrictions via UserGroupDetector and FrontendGroupsModifier.
 */
class AccessProtectedContentTest extends IntegrationTestBase
{
    protected ?Queue $indexQueue = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);

        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            page.10.renderObj.5 = TEXT
            page.10.renderObj.5.field = header
            page.10.stdWrap.dataWrap = <!--TYPO3SEARCH_begin-->|<!--TYPO3SEARCH_end-->
            ',
        );

        /** @var \Symfony\Component\DependencyInjection\Container $container */
        $container = GeneralUtility::getContainer();
        $container->set(
            IndexingService::class,
            IndexingServiceForTesting::fromProductionService($container->get(IndexingService::class)),
        );
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
        string $core = 'core_en',
    ): void {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture . '.csv');

        // Ensure the errors field is an empty string (CSV imports numeric 0, but the
        // queue repository filters with errors = '' which is a text column)
        $connection = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
            ->getConnectionForTable('tx_solr_indexqueue_item');
        $connection->executeStatement("UPDATE tx_solr_indexqueue_item SET errors = ''");

        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $indexService = GeneralUtility::makeInstance(IndexService::class, $site);
        $indexService->indexItems(10);

        $this->waitToBeVisibleInSolr($core);

        $solrContent = json_decode(
            file_get_contents($this->getSolrCoreUrl($core) . '/select?q=*:*&sort=access%20asc'),
            true,
        );

        self::assertEquals($expectedNumFound, $solrContent['response']['numFound'] ?? 0, 'Could not index documents into Solr');
        foreach ($expectedAccessFieldValues as $index => $expectedAccessFieldValue) {
            self::assertEquals(
                $expectedAccessFieldValue,
                $solrContent['response']['docs'][$index]['access'][0] ?? '',
                'Wrong access settings document ' . ($index + 1),
            );
        }
        foreach ($expectedContents as $index => $expectedContent) {
            self::assertEquals(
                $expectedContent,
                $solrContent['response']['docs'][$index]['content'] ?? '',
                'Wrong content in document ' . ($index + 1),
            );
        }

        $solrContent = json_decode(
            file_get_contents($this->getSolrCoreUrl($core) . '/select?q=*:*&fl=uid&sort=access%20asc&fq={!typo3access}0'),
            true,
        );
        self::assertEquals($expectedNumFoundAnonymousUser, $solrContent['response']['numFound'], 'Protected contents not filtered correctly');

        $solrContent = json_decode(
            file_get_contents($this->getSolrCoreUrl($core) . '/select?q=*:*&fl=uid&sort=access%20asc&fq={!typo3access}' . $userGroupToCheckAccessFilter),
            true,
        );
        self::assertEquals($expectedNumFoundLoggedInUser, $solrContent['response']['numFound'], 'Protected contents not returned correctly');
    }

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
            'core' => 'core_de',
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
}
