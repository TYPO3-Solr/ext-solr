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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\Tests\Integration\Controller\AbstractFrontendControllerTest;
use ApacheSolrForTypo3\Solr\Tests\Integration\TSFETestBootstrapper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Regression guard for SST #2026050810000025:
 * the highlighter must not surface `field:*` queries as a Solr HTTP 500 field-existence oracle.
 *
 * @group frontend
 * @group security
 */
class QueryInjectionViaRawSolrSyntaxTest extends AbstractFrontendControllerTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importDataSet(__DIR__ . '/../Controller/Fixtures/default_search_results_plugin.xml');
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sst_2026050810000025_query_injection.csv');
        $this->indexPages([2, 3, 4]);
        $this->assertSolrContainsDocumentCount(3);
    }

    /**
     * @test
     */
    public function literalTokenSearchReturnsExpectedDocument(): void
    {
        $response = $this->executeSearch('markeralpha235567');
        self::assertStringContainsString(
            'markeralpha235567',
            $response,
            'Sanity check failed: literal-token search for the alpha marker did not return the alpha document.'
        );
    }

    /**
     * @test
     */
    public function existingFieldWildcardMustNotTriggerSolrCommunicationException(): void
    {
        GeneralUtility::makeInstance(TSFETestBootstrapper::class)->bootstrap(1);
        $configuration = new TypoScriptConfiguration([
            'plugin.' => ['tx_solr.' => ['search.' => [
                'query.' => ['queryFields' => 'content,title'],
                'results.' => [
                    'resultsHighlighting' => 1,
                    'resultsHighlighting.' => [
                        'fragmentSize' => 50,
                        'wrap' => '<mark>|</mark>',
                    ],
                ],
            ]]],
        ]);
        $query = (new QueryBuilder($configuration))->buildSearchQuery('siteHash:*');
        try {
            GeneralUtility::makeInstance(Search::class)->search($query);
        } catch (SolrCommunicationException $e) {
            self::fail('SST 235567: highlighter raised ' . get_class($e) . ' — ' . $e->getMessage());
        }
    }

    protected function executeSearch(string $rawQuery): string
    {
        return (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://testone.site/'))
                ->withPageId(2022)
                ->withQueryParameter('tx_solr[q]', $rawQuery)
        )->getBody();
    }
}
