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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Security;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Tests\Integration\Controller\AbstractFrontendControllerTest;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use JsonException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Regression-guard test for CVE-2026-56093 (detailAction access-control bypass).
 *
 * Asserts the SECURE behavior: {@link SearchController::detailAction()} MUST NOT disclose access-restricted documents to anonymous visitors.
 * The same siteHash and frontend-user-group access filters that the AccessComponent applies on the normal search path
 * must also constrain the direct documentId lookup.
 *
 * On 11.6.x {@link SearchResultSetService::getDocumentById()} builds a direct Solr lookup query without running any
 * search component, so AccessComponent never applies and restricted documents leak through.
 * The primary test method therefore FAILS before the fix — that failure IS the deterministic vulnerability reproduction.
 *
 * @group frontend
 * @group security
 */
class DetailActionAccessBypassTest extends AbstractFrontendControllerTest
{
    protected function setUp(): void
    {
        parent::setUp();
        // Plugin "pi_results" (search results + detail) on page 2022, reused from controller fixtures.
        $this->importDataSet(__DIR__ . '/../Controller/Fixtures/default_search_results_plugin.xml');
        // Mirrors SearchControllerTest's plugin bootstrap: enable indexing and
        // render the pi_results plugin on page 2022.
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            config.index_enable = 1
            [page["uid"] == 2022]
            page.10 = RECORDS
            page.10 {
              source = 2022
              dontCheckPid = 1
              tables = tt_content
              wrap >
            }
            [end]
            '
        );
    }

    /**
     * PRIMARY regression guard.
     *
     * An anonymous visitor calls the cacheable detail action with the documentId of a fe_group-restricted page.
     * The detailAction MUST refuse to render the protected document; {@link SearchResultSetService::getDocumentById()}
     * must apply the same access and siteHash filters that AccessComponent applies on the normal search path.
     *
     * @test
     */
    public function detailActionMustNotDiscloseAccessRestrictedDocumentToAnonymousVisitor(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/detailaction_access_bypass.csv');

        // Index public (uid 2) + restricted (uid 3) pages as fe_user 1, which belongs to fe_group 1;
        // the restricted page therefore reaches Solr with an "access" field that mandates
        // membership in group 1.
        $this->indexPagesAsFrontendUser([2, 3], 1);
        $this->assertSolrContainsDocumentCount(2);

        $restrictedDocumentId = $this->fetchSolrDocumentIdByTitle('RestrictedSecretAreaLoggedInOnly');
        self::assertNotEmpty(
            $restrictedDocumentId,
            'Restricted page was not indexed into Solr; precondition for the test is broken.'
        );

        $response = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[action]', 'detail')
                ->withQueryParameter('tx_solr[documentId]', $restrictedDocumentId)
        )->getBody();

        self::assertStringNotContainsString(
            'RestrictedSecretAreaLoggedInOnly',
            $response,
            'CVE-2026-56093: detailAction disclosed the title of an access-restricted document to an anonymous visitor.'
        );
        self::assertStringNotContainsString(
            'CLASSIFIED_BANK_DETAILS_MARKER_ABC123',
            $response,
            'CVE-2026-56093: detailAction disclosed the body of an access-restricted document to an anonymous visitor.'
        );
    }

    /**
     * COMPARISON BASELINE.
     *
     * The same restricted document, queried through the normal (non-cacheable) results action, is NOT disclosed
     * because the {@link AccessComponent} applies the frontend user access filter.
     * This test confirms that the bypass observed above is specific to the detailAction code path and not a general indexing/access-field bug.
     *
     * @test
     */
    public function normalResultsActionDoesNotDiscloseAccessRestrictedDocumentToAnonymousVisitor(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/detailaction_access_bypass.csv');
        $this->indexPagesAsFrontendUser([2, 3], 1);
        $this->assertSolrContainsDocumentCount(2);

        // Search for a public-content keyword so the search-term echo in the response template
        // cannot accidentally satisfy a substring assertion. The restricted document's title
        // and the surrounding restricted-content phrase remain the indicators of disclosure.
        $response = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        self::assertStringNotContainsString(
            'RestrictedSecretAreaLoggedInOnly',
            $response,
            'Normal resultsAction unexpectedly disclosed the title of the restricted document — the comparison baseline is invalid.'
        );
        self::assertStringNotContainsString(
            'CLASSIFIED_BANK_DETAILS_MARKER_ABC123',
            $response,
            'Normal resultsAction unexpectedly disclosed a marker from the restricted document body — the comparison baseline is invalid.'
        );
    }

    /**
     * Indexes pages with a logged-in frontend user, so an fe_group-protected page actually renders
     * and reaches Solr with its own access rootline.
     *
     * 11.6 has no helper that can do this — {@link AbstractFrontendControllerTest::indexPages()} renders
     * the sub-request anonymously and never sets the access rootline, while
     * {@link IntegrationTest::indexPageIds()} runs `determineId()` before simulating the user groups, so both
     * silently fall back to the site root for a protected page. This is the 11.6 equivalent of 12.1's
     * `indexPages($ids, $frontendUserId)`.
     */
    protected function indexPagesAsFrontendUser(array $importPageIds, int $frontendUserId): void
    {
        /** @var Tsfe $tsfeFactory */
        $tsfeFactory = GeneralUtility::makeInstance(Tsfe::class);
        $requestContext = (new InternalRequestContext())->withFrontendUserId($frontendUserId);

        foreach ($importPageIds as $importPageId) {
            $fakeTSFE = $tsfeFactory->getTsfeByPageIdAndLanguageId($importPageId);
            $GLOBALS['TSFE'] = $fakeTSFE;
            $fakeTSFE->newCObj();

            $response = $this->executeFrontendSubRequest(
                (new InternalRequest('http://testone.site/'))->withPageId($importPageId),
                $requestContext
            );
            $fakeTSFE->content = (string)$response->getBody();

            /** @var Typo3PageIndexer $pageIndexer */
            $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
            $indexQueueItemMock = $this->createMock(Item::class);
            $indexQueueItemMock->expects(self::any())
                ->method('getIndexingConfigurationName')
                ->willReturn('pages');
            $pageIndexer->setIndexQueueItem($indexQueueItemMock);
            $pageIndexer->setPageAccessRootline(Rootline::getAccessRootlineByPageId($importPageId));
            $pageIndexer->indexPage();
        }

        $this->waitToBeVisibleInSolr();
        unset($GLOBALS['TSFE']);
    }

    /**
     * Resolves the Solr documentId of an indexed page by issuing a REST query against Solr directly.
     * The id format is constructed by {@link Util::getDocumentId()} as `<siteHash>/<table>/<uid>/<typeNum>/<language>/<accessGroups>`,
     * but the exact accessGroups segment depends on the runtime indexing context — querying Solr avoids brittle assumptions.
     *
     * @throws JsonException
     */
    protected function fetchSolrDocumentIdByTitle(string $title): string
    {
        $url = sprintf(
            '%s/solr/core_en/select?q=*:*&fl=id&wt=json&fq=%s',
            $this->getSolrConnectionUriAuthority(),
            urlencode('title:"' . $title . '"')
        );
        $body = file_get_contents($url);
        if ($body === false) {
            self::fail('Could not query Solr at ' . $url);
        }
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        return (string)($data['response']['docs'][0]['id'] ?? '');
    }
}
