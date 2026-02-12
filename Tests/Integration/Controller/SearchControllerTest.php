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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

use ApacheSolrForTypo3\Solr\Controller\SearchController;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;

/**
 * Integration testcase to test for the SearchController
 */
class SearchControllerTest extends IntegrationTestBase
{
    /**
     * @var SearchController
     */
    protected $searchController;

    /**
     * @var Response
     */
    protected $searchResponse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->bootstrapSearchResultsPluginOnPage();
    }

    /**
     * Executed after each test. Empties solr and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    protected function bootstrapSearchResultsPluginOnPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/default_search_results_plugin.csv');
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
            ',
        );
    }

    #[Group('frontend')]
    #[Test]
    public function canShowSearchFormViaPlugin(): void
    {
        $response = $this->executeFrontendSubRequest($this->getPreparedRequest(2022));
        $content = (string)$response->getBody();
        self::assertStringContainsString('id="tx-solr-search-form-pi-results"', $content, 'Response did not contain search css selector');
    }

    #[Group('frontend')]
    #[Test]
    public function canShowSearchForm(): void
    {
        $response = $this->executeFrontendSubRequest($this->getPreparedRequest());
        $content = (string)$response->getBody();
        self::assertStringContainsString('id="tx-solr-search-form-pi-results"', $content, 'Response did not contain search css selector');
    }

    #[Group('frontend')]
    #[Test]
    public function canSearchForPrices(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->indexPages([2, 3]);

        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()->withQueryParameter('q', 'prices'),
        )->getBody();

        self::assertMatchesRegularExpression('/Found [0-9]+ results in [0-9]+ milliseconds/i', $result);
        self::assertStringContainsString('pages/3/0/0/0', $result, 'Could not find page 3 in result set');
        self::assertStringContainsString('pages/2/0/0/0', $result, 'Could not find page 2 in result set');
    }

    #[Group('frontend')]
    #[Test]
    public function canDoAPaginatedSearch(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.results.resultsPerPageSwitchOptions = 5, 10, 25, 50
            plugin.tx_solr.search.results.resultsPerPage = 5',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        $this->assertPaginationVisible($resultPage1);
        self::assertStringContainsString('Displaying results 1 to 5 of 8', $resultPage1, 'Wrong result count indicated in template of pagination page 1.');

        $this->assertCanOpenSecondPageOfPaginatedSearch();
        $this->assertCanChangeResultsPerPage();
    }

    protected function assertCanOpenSecondPageOfPaginatedSearch(): void
    {
        $resultPage2 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[page]', 2),
        )->getBody();

        self::assertStringContainsString('pages/8/0/0/0', $resultPage2, 'Could not find page(PID) 8 in result set.');
        self::assertStringContainsString('Displaying results 6 to 8 of 8', $resultPage2, 'Wrong result count indicated in template of pagination page 2.');
    }

    protected function assertCanChangeResultsPerPage(): void
    {
        $resultPage = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[resultsPerPage]', 10),
        )->getBody();

        self::assertStringContainsString('Displaying results 1 to 8 of 8', $resultPage, '');
        $this->assertContainerByIdContains('<option selected="selected" value="10">10</option>', $resultPage, 'results-per-page');
    }

    #[Group('frontend')]
    #[Test]
    public function canGetADidYouMeanProposalForATypo(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.spellchecking = 1
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'shoo'),
        )->getBody();

        self::assertStringContainsString('Did you mean', $resultPage1, 'Could not find did you mean in response');
        self::assertStringContainsString('shoes', $resultPage1, 'Could not find shoes in response');
    }

    #[Group('frontend')]
    #[Test]
    public function canAutoCorrectATypo(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.spellchecking = 1
            plugin.tx_solr.search.spellchecking {
                searchUsingSpellCheckerSuggestion = 1
                numberOfSuggestionsToTry = 1
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'shoo'),
        )->getBody();

        self::assertStringContainsString('Nothing found for &quot;shoo&quot;', $resultPage1, 'Could not find nothing found message');
        self::assertStringContainsString('Showing results for &quot;shoes&quot;', $resultPage1, 'Could not find correction message');
    }

    #[Group('frontend')]
    #[Test]
    public function canRenderAFacetWithFluid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');

        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets.type {
                label = Content Type
                field = type
            }
            ',
        );

        $this->indexPages([1, 2]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        self::assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="type".*?">.*?<li.*?data-facet-item-value="pages".*?>.*?<\/ul>/s', $resultPage1),
            'Could not find facet option for pages',
        );
    }

    #[DataProvider('canRenderSpeakingFacetUrlsDataProvider')]
    #[Group('frontend')]
    #[Test]
    public function canRenderSpeakingFacetUrls(int $enableRouteEnhancer, int $expectedMatchesDefaultUrl, int $expectedMatchesSpeakingUrl): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');

        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $extensionConfiguration->set('solr', ['enableRouteEnhancer' => $enableRouteEnhancer]);

        $this->mergeSiteConfiguration(
            'integration_tree_one',
            [
                'routeEnhancers' => [
                    'solrContentType' => [
                        'type' => 'SolrFacetMaskAndCombineEnhancer',
                        'extensionKey' => 'tx_solr',
                        'routePath' => '/contentType/{type}',
                        '_arguments' => [
                            'type' => 'filter-type',
                        ],
                        'requirements' => [
                            'type' => '.*',
                        ],
                    ],
                ],
            ],
        );

        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets.type {
                label = Content Type
                field = type
            }
            ',
        );

        $this->indexPages([1, 2]);

        $request = $this->getPreparedRequest()->withQueryParameter('tx_solr[q]', '*');
        $response = $this->executeFrontendSubRequest($request);
        $resultPage1 = (string)$response->getBody();

        self::assertEquals(
            $expectedMatchesDefaultUrl,
            preg_match('/<ul.*?data-facet-name="type".*?">.*?<li.*?data-facet-item-value="pages".*?>.*?href="\/en\/search\?tx_solr.*?<\/ul>/s', $resultPage1),
            'Could not find speaking facet url pages',
        );
        self::assertEquals(
            $expectedMatchesSpeakingUrl,
            preg_match('/<ul.*?data-facet-name="type".*?">.*?<li.*?data-facet-item-value="pages".*?>.*?href="\/en\/search\/contentType\/pages\?tx_solr.*?<\/ul>/s', $resultPage1),
            'Could not find speaking facet url pages',
        );
    }

    /**
     * Data provider for canRenderSpeakingFacetUrls
     */
    public static function canRenderSpeakingFacetUrlsDataProvider(): Traversable
    {
        yield 'route enhancer inactive' => [
            0,
            1,
            0,
        ];

        yield 'route enhancer active' => [
            0,
            1,
            0,
        ];
    }

    #[Group('frontend')]
    #[Test]
    public function canDoAnInitialEmptySearchWithoutResults(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                initializeWithEmptyQuery = 1
                showResultsOfInitialEmptyQuery = 0
                faceting = 1
                faceting.facets.type {
                    label = Content Type
                    field = type
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest($this->getPreparedRequest())->getBody();

        self::assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        self::assertStringNotContainsString('results-entry', $resultPage1, 'No results should be visible since showResultsOfInitialEmptyQuery was set to false');
    }

    #[Group('frontend')]
    #[Test]
    public function canDoAnInitialEmptySearchWithResults(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                initializeWithEmptyQuery = 1
                showResultsOfInitialEmptyQuery = 1
                faceting = 1
                faceting.facets.type {
                    label = Content Type
                    field = type
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest($this->getPreparedRequest())->getBody();

        self::assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        self::assertStringContainsString('results-entry', $resultPage1, 'Results should be visible since showResultsOfInitialEmptyQuery was set to true');
    }

    #[Group('frontend')]
    #[Test]
    public function canDoAnInitialSearchWithoutResults(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                initializeWithQuery = product
                showResultsOfInitialEmptyQuery = 0
                faceting = 1
                faceting.facets.type {
                    label = Content Type
                    field = type
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest($this->getPreparedRequest())->getBody();

        self::assertStringContainsString('fluidfacet', $resultPage1, 'fluidfacet should be generated since initializeWithQuery was configured with a query that should produce results');
        self::assertStringNotContainsString('results-entry', $resultPage1, 'No results should be visible since showResultsOfInitialQuery was set to false');
    }

    #[Group('frontend')]
    #[Test]
    public function canDoAnInitialSearchWithResults(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                initializeWithQuery = product
                showResultsOfInitialEmptyQuery = 1
                faceting = 1
                faceting.facets.type {
                    label = Content Type
                    field = type
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest($this->getPreparedRequest())->getBody();

        self::assertStringContainsString('fluidfacet', $resultPage1, 'fluidfacet should be generated since initializeWithQuery was configured with a query that should produce results');
        self::assertStringContainsString('results-entry', $resultPage1, 'Results should be visible since showResultsOfInitialQuery was set to true');
    }

    #[Group('frontend')]
    #[Test]
    public function removeOptionLinkWillBeShownWhenFacetWasSelected(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.type {
                    label = Content Type
                    field = type
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[filter][0]', 'type:pages'),
        )->getBody();

        self::assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        self::assertStringContainsString('remove-facet-option', $resultPage1, 'No link to remove facet option found');
    }

    #[Group('frontend')]
    #[Test]
    public function removeOptionLinkWillBeShownWhenAFacetOptionLeadsToAZeroResults(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.type {
                    label = Content Type
                    field = type
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[filter][0]', 'type:my_jobs'),
        )->getBody();

        self::assertStringContainsString('remove-facet-option', $resultPage1, 'No link to remove facet option found');
    }

    #[Group('frontend')]
    #[Test]
    public function canFilterOnPageSections(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.query.filter.__pageSections = 2,3
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        // we should only find 2 results since a __pageSections filter should be applied
        self::assertStringContainsString('Found 2 results', $resultPage1, 'No link to remove facet option found');
    }

    #[Group('frontend')]
    #[Test]
    public function exceptionWillBeThrownWhenAWrongTemplateIsConfiguredForTheFacet(): void
    {
        // we expected that an exception will be thrown when a facet is rendered
        // where an unknown partialName is referenced
        $this->expectException(InvalidTemplateResourceException::class);
        $this->expectExceptionMessageMatches('#(.*The partial files?.*NotFound.*|.*The Fluid template files? .*NotFound.*)#');

        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.type {
                    partialName = NotFound
                    label = Content Type
                    field = type
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        );
    }

    #[Group('frontend')]
    #[Test]
    public function canRenderAScoreAnalysisWhenBackendUserIsLoggedIn(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');

        $this->indexPages([1, 2]);

        // fake that a backend user is logged in
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sites_setup_and_data_set/be_users.csv');
        $GLOBALS['BE_USER'] = $this->setUpBackendUser(1);
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
            (new InternalRequestContext())->withBackendUserId(1),
        )->getBody();

        self::assertStringContainsString('document-score-analysis', $resultPage1, 'No score analysis in response');
    }

    #[Group('frontend')]
    #[Test]
    public function canSortFacetsByLex(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.subtitle {
                    label = Subtitle
                    field = subTitle
                    keepAllOptionsOnSelection = 1
                    // when we sort by lex "men" should appear before "woman" even when only one option is available
                    sortBy = lex
                }
            }
            ',
        );

        $womanPages = [4, 5, 8];
        $menPages = [2];
        $this->indexPages($womanPages);
        $this->indexPages($menPages);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="subtitle".*?">.*?<li.*?data-facet-item-value="men".*?>.*?<span class="facet-result-count badge bg-info">1<\/span>.*?<\/li>/s', $resultPage1),
            'Could not find count for "men"',
        );
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="subtitle".*?">.*?<li.*?data-facet-item-value="woman".*?>.*?<span class="facet-result-count badge bg-info">3<\/span>.*?<\/li>/s', $resultPage1),
            'Could not find count for "woman"',
        );
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="subtitle".*?">.*?data-facet-item-value="men".*?data-facet-item-value="woman".*?<\/ul>/s', $resultPage1),
            'Could not find facet options in the right order',
        );
        self::assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="type".*?">.*?<li.*?data-facet-item-value="pages".*?>.*?<\/ul>/s', $resultPage1),
            'Could not find facet option for pages',
        );
    }

    #[Group('frontend')]
    #[Test]
    public function canSortFacetsByOptionCountWhenNothingIsConfigured(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.subtitle {
                    label = Subtitle
                    field = subTitle
                    keepAllOptionsOnSelection = 1
                }
            }
            ',
        );

        $womanPages = [4, 5, 8];
        $menPages = [2];
        $this->indexPages($womanPages);
        $this->indexPages($menPages);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="subtitle".*?">.*?<li.*?data-facet-item-value="men".*?>.*?<span class="facet-result-count badge bg-info">1<\/span>.*?<\/li>/s', $resultPage1),
            'Could not find count for "men"',
        );
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="subtitle".*?">.*?<li.*?data-facet-item-value="woman".*?>.*?<span class="facet-result-count badge bg-info">3<\/span>.*?<\/li>/s', $resultPage1),
            'Could not find count for "woman"',
        );
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="subtitle".*?">.*?data-facet-item-value="woman".*?data-facet-item-value="men".*?<\/ul>/s', $resultPage1),
            'Could not find facet options in the right order',
        );

        self::assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="type".*?">.*?<li.*?data-facet-item-value="pages".*?>.*?<\/ul>/s', $resultPage1),
            'Could not find facet option for pages',
        );
    }

    #[Group('frontend')]
    #[Test]
    public function canRenderQueryGroupFacet(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.pid {
                    label = Uid Range
                    field = uid

                    type = queryGroup
                    queryGroup {
                        small {
                            query = [* TO 2]
                        }
                        medium {
                            query = [2 TO 5]
                        }

                        large {
                            query = [5 TO *]
                        }
                    }

                    renderingInstruction = CASE
                    renderingInstruction {
                        key.field = optionValue

                        default = TEXT
                        default.field = optionValue

                        small = TEXT
                        small.value = Small (1 & 2)

                        medium = TEXT
                        medium.value = Medium (2 to 5)

                        large = TEXT
                        large.value = Large (5 to *)
                    }
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        self::assertStringContainsString('Small (1 &amp; 2)', $resultPage1, 'Response did not contain expected small option of query facet');
        self::assertStringContainsString('Medium (2 to 5)', $resultPage1, 'Response did not contain expected medium option of query facet');
        self::assertStringContainsString('Large (5 to *)', $resultPage1, 'Response did not contain expected large option of query facet');
    }

    protected function addPageHierarchyFacetConfiguration(): void
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.pageHierarchy {
                    field = rootline
                    label = Rootline

                    type = hierarchy

                    hierarchy = HMENU
                    hierarchy {
                        1 = TMENU
                        1 {
                            NO = 1
                            NO {
                                wrapItemAndSub = <li class="rootlinefacet-item">|</li>
                            }
                        }

                        2 < .1
                        2.wrap = <ul>|</ul>

                        3 < .2
                    }
                }
            }
            ',
        );
    }

    #[Group('frontend')]
    #[Test]
    public function canRenderHierarchicalFacet(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addPageHierarchyFacetConfiguration();
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        self::assertStringContainsString('Found 8 results', $resultPage1, 'Assert to find 8 results without faceting');
        self::assertStringContainsString('facet-type-hierarchy', $resultPage1, 'Did not render hierarchy facet in the response');
        self::assertStringContainsString('data-facet-item-value="/1/2/"', $resultPage1, 'Hierarchy facet item did not contain expected data item');

        self::assertStringContainsString('tx_solr%5Bfilter%5D%5B0%5D=pageHierarchy%3A%2F1%2F2%2F&amp;tx_solr%5Bq%5D=%2A', $resultPage1, 'Result page did not contain hierarchical facet link');
    }

    #[Group('frontend')]
    #[Test]
    public function canFacetOnHierarchicalFacetItem(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addPageHierarchyFacetConfiguration();
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[filter][0]', 'pageHierarchy:/1/2/'),
        )->getBody();

        self::assertStringContainsString('Found 1 result', $resultPage1, 'Assert to only find one result after faceting');
        self::assertStringContainsString('facet-type-hierarchy', $resultPage1, 'Did not render hierarchy facet in the response');
        self::assertStringContainsString('data-facet-item-value="/1/2/"', $resultPage1, 'Hierarchy facet item did not contain expected data item');
        self::assertStringContainsString('tx_solr%5Bfilter%5D%5B0%5D=pageHierarchy%3A%2F1%2F2%2F&amp;tx_solr%5Bq%5D=%2A', $resultPage1, 'Result page did not contain hierarchical facet link');
    }

    #[Group('frontend')]
    #[Test]
    public function canFacetOnHierarchicalTextCategory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_render_path_facet_with_search_controller.csv');

        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr {
                index {
                    fieldProcessingInstructions.categoryPaths_stringM = pathToHierarchy
                    queue.pages.fields {
                        categoryPaths_stringM = SOLR_MULTIVALUE
                        categoryPaths_stringM {
                            stdWrap.cObject = USER
                            stdWrap.cObject.userFunc = ApacheSolrForTypo3\Solr\Tests\Integration\Controller\CategoryPathProvider->getPaths
                            separator = ,
                        }
                    }
                }
                search {
                    faceting = 1
                    faceting.facets.categoryPaths {
                      field = categoryPaths_stringM
                      label = Path
                      type = hierarchy
                   }
                }
            }
            ',
        );

        $this->indexPages([2, 3, 4]);
        // we should have 3 documents in solr
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":3', $solrContent, 'Could not index document into solr');

        // but when we facet on the categoryPaths:/Men/Shoes \/ Socks/ we should only have one result since the others
        // do not have the category assigned
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[filter][0]', 'categoryPaths:/Men/Shoes \/ Socks/'),
        )->getBody();

        self::assertStringContainsString('Found 1 result', $resultPage1, 'Assert to only find one result after faceting');
    }

    #[Group('frontend')]
    #[Test]
    public function canDefineAManualSortOrder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets.subtitle {
                label = Subtitle
                field = subTitle
                keepAllOptionsOnSelection = 1
                manualSortOrder = men, woman
            }
            ',
        );

        $womanPages = [4, 5, 8];
        $menPages = [2];
        $this->indexPages($womanPages);
        $this->indexPages($menPages);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="subtitle".*?">.*?<li.*?data-facet-item-value="men".*?>.*?<span class="facet-result-count badge bg-info">1<\/span>.*?<\/li>/s', $resultPage1),
            'Could not find count for "men"',
        );
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="subtitle".*?">.*?<li.*?data-facet-item-value="woman".*?>.*?<span class="facet-result-count badge bg-info">3<\/span>.*?<\/li>/s', $resultPage1),
            'Could not find count for "woman"',
        );
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="subtitle".*?">.*?data-facet-item-value="men".*?data-facet-item-value="woman".*?<\/ul>/s', $resultPage1),
            'Could not find facet options in the right order',
        );

        self::assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        self::assertEquals(
            1,
            preg_match('/<ul.*?data-facet-name="type".*?">.*?<li.*?data-facet-item-value="pages".*?>.*?<\/ul>/s', $resultPage1),
            'Could not find facet option for pages',
        );
    }

    #[Group('frontend')]
    #[Test]
    public function canSeeTheParsedQueryWhenABackendUserIsLoggedIn(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.enableDebugMode = 1
            ',
        );
        $this->indexPages([1, 2]);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sites_setup_and_data_set/be_users.csv');
        $GLOBALS['BE_USER'] = $this->setUpBackendUser(1);
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
            (new InternalRequestContext())->withBackendUserId(1),
        )->getBody();

        self::assertStringContainsString('Parsed Query:', $resultPage1, 'No parsed query in response');
    }

    public static function frontendWillRenderErrorMessageIfSolrNotAvailableDataProvider(): Traversable
    {
        yield ['action' => 'results', 'getArguments' => ['q' => '*']];
        yield ['action' => 'detail', 'getArguments' => ['id' => 1]];
    }

    /**
     * @param string $action
     * @param array $getArguments
     *
     * @todo: See: https://github.com/TYPO3/testing-framework/issues/324
     * Notes:
     * Fits removed frontendWillRenderErrorMessageForSolrNotAvailableAction() test case as well.
     * Removed code: https://github.com/TYPO3-Solr/ext-solr/blob/03080d4d55eeb9d50b15348f445d23e57e34e461/Tests/Integration/Controller/SearchControllerTest.php#L729-L747
     */
    #[DataProvider('frontendWillRenderErrorMessageIfSolrNotAvailableDataProvider')]
    #[Group('frontend')]
    #[Test]
    public function frontendWillRenderErrorMessageIfSolrNotAvailable(string $action, array $getArguments): void
    {
        $this->mergeSiteConfiguration(
            'integration_tree_one',
            [
                'solr_scheme_read' => 'http',
                'solr_host_read' => 'localhost',
                'solr_port_read' => 4711,
            ],
        );

        $response = $this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[action]', $action)
                ->withQueryParameter('tx_solr[' . key($getArguments) . ']', current($getArguments)),
        );

        self::assertStringContainsString('Search is currently not available.', (string)$response->getBody(), 'Response did not contain solr unavailable error message');
        self::markTestIncomplete('The status code can not be checked currently. See: https://github.com/TYPO3/testing-framework/issues/324');
        //self::assertEquals(503, $response->getStatusCode());
    }

    /**
     * @todo: https://github.com/TYPO3-Solr/ext-solr/issues/3160
     *       The session must be shared between both requests.
     */
    #[Group('frontend')]
    #[Test]
    public function canShowLastSearchesFromSessionInResponse(): void
    {
        self::markTestIncomplete(
            'Last searches component seems to be fine, but the test does not fit that case currently.
            The last-searches component is not rendered. See: https://github.com/TYPO3-Solr/ext-solr/issues/3160',
        );
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.lastSearches = 1
            plugin.tx_solr.search.lastSearches {
                limit = 10
                mode = user
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()->withQueryParameter('tx_solr[q]', 'shoe'),
        )->getBody();

        $resultSearch2 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        $this->assertContainerByIdContains('>shoe</a>', $resultSearch2, 'tx-solr-lastsearches');
    }

    #[Group('frontend')]
    #[Test]
    public function canShowLastSearchesFromDatabaseInResponse(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.lastSearches = 1
            plugin.tx_solr.search.lastSearches {
                limit = 10
                mode = global
            }
            ',
        );
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()->withQueryParameter('tx_solr[q]', 'shoe'),
        )->getBody();

        $resultSearch2 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        $this->assertContainerByIdContains('>shoe</a>', $resultSearch2, 'tx-solr-lastsearches');
    }

    #[Group('frontend')]
    #[Test]
    public function canNotStoreQueyStringInLastSearchesWhenQueryDoesNotReturnAResult(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.lastSearches = 1
            plugin.tx_solr.search.lastSearches {
                limit = 10
                mode = global
            }
            ',
        );
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'nothingwillbefound'),
        )->getBody();

        $resultSearch2 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'nothingwillbefound'),
        )->getBody();

        $this->assertContainerByIdNotContains('>nothingwillbefound</a>', $resultSearch2, 'tx-solr-lastsearches');
    }

    #[Group('frontend')]
    #[Test]
    public function canOverwriteAFilterWithTheFlexformSettings(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update(
            'tt_content',
            ['pi_flexform' => file_get_contents(__DIR__ . '/Fixtures/fakedFlexFormData.xml')],
            ['uid' => 2022],
        );

        $resultSearch = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        self::assertStringContainsString('Displaying results 1 to 4 of 4', $resultSearch);
    }

    #[Group('frontend')]
    #[Test]
    public function canRenderDateRangeFacet(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets.myCreatedFacet {
                label = Created Between
                field = created
                type = dateRange
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        self::assertStringContainsString('facet-type-dateRange', $resultSearch);
    }

    #[Group('frontend')]
    #[Test]
    public function canRenderASecondFacetOnTheTypeField(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets {
                type {
                    label = Content Type
                    field = type
                }
                myType {
                    label = My Type
                    field = type
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();

        self::assertStringContainsString('id="facetmyType"', $resultSearch);
        self::assertStringContainsString('id="facettype"', $resultSearch);
    }

    #[Group('frontend')]
    #[Test]
    public function canSortByMetric(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_sort_by_metric.csv');

        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets {
                pid {
                    label = PID
                    field = pid
                    metrics {
                        newest = max(created)
                    }
                    sortBy = metrics_newest desc
                }
            }
            ',
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);

        $pid1Option = urlencode('tx_solr[filter][0]') . '=' . urlencode('pid:1');
        $pid2Option = urlencode('tx_solr[filter][0]') . '=' . urlencode('pid:2');

        $content = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
        )->getBody();
        $pid1OptionPosition = strpos($content, $pid1Option);
        $pid2OptionPosition = strpos($content, $pid2Option);

        self::assertGreaterThan(0, $pid1OptionPosition, 'Pid 1 option does not appear in the content');
        self::assertGreaterThan(0, $pid2OptionPosition, 'Pid 2 option does not appear in the content');
        $isPid2OptionBefore1Option = $pid2OptionPosition < $pid1OptionPosition;
        self::assertTrue($isPid2OptionBefore1Option);
    }

    #[Group('frontend')]
    #[Test]
    public function formActionIsRenderingTheForm(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update(
            'tt_content',
            ['CType' => 'solr_pi_search'],
            ['uid' => 2022],
        );

        $formContent = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest(),
        )->getBody();
        self::assertStringContainsString('<div class="tx-solr-search-form">', $formContent);
        self::assertStringNotContainsString('id="tx-solr-search"', $formContent);
        self::assertStringNotContainsString('id="tx-solr-search-functions"', $formContent);
    }

    /**
     * @todo : https://github.com/TYPO3-Solr/ext-solr/issues/3166
     */
    #[Group('frontend')]
    #[Test]
    public function searchingAndRenderingFrequentSearchesIsShowingTheTermAsFrequentSearch(): void
    {
        self::markTestIncomplete('See: https://github.com/TYPO3-Solr/ext-solr/issues/3166');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->indexPages([2]);

        $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->update(
                'tt_content',
                ['CType' => 'solr_pi_frequentlysearched'],
                ['uid' => 2022],
            );

        $resultPage = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'shoes'),
        )->getBody();

        $this->assertContainerByIdContains('>shoes</a>', $resultPage, 'tx-solr-frequent-searches');
    }

    #[Group('frontend')]
    #[Test]
    public function canRenderDetailAction(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->indexPages([2]);

        $resultPage = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[action]', 'detail')
                ->withQueryParameter('tx_solr[documentId]', '002de2729efa650191f82900ea02a0a3189dfabb/pages/2/0/0/0'),
        )->getBody();

        self::assertStringContainsString('<h1>Socks</h1>', $resultPage);
        self::assertStringContainsString('<p>Our awesome new sock products prices starting at 10 euro</p>', $resultPage);
        self::assertStringContainsString('open</a>', $resultPage);
    }

    /**
     * The template root path is configured in the typoscript template to point to another folder-
     */
    #[Group('frontend')]
    #[Test]
    public function canOverrideTemplatesAndPartialsViaTypoScriptSetup(): void
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.view {
                templateRootPaths.20 = EXT:solr/Tests/Integration/Controller/Fixtures/customTemplates/
                partialRootPaths.20 = EXT:solr/Tests/Integration/Controller/Fixtures/customPartials/
            }
            ',
        );

        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest(),
        )->getBody();

        self::assertStringContainsString('<h1>From custom "Results" template</h1>', $result, 'Can not overwrite template path');
        self::assertStringContainsString('<h1>From custom "RelevanceBar" partial</h1>', $result, 'Can not overwrite partial path');
    }

    /**
     * The template root path is configured in the typoscript template to point to another folder
     */
    #[Group('frontend')]
    #[Test]
    public function canOverrideTemplatesAndPartialsViaTypoScriptConstants(): void
    {
        $this->addTypoScriptConstantsToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.view {
                templateRootPath = EXT:solr/Tests/Integration/Controller/Fixtures/customTemplates/
                partialRootPath = EXT:solr/Tests/Integration/Controller/Fixtures/customPartials/
            }
            ',
        );

        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest(),
        )->getBody();

        self::assertStringContainsString('<h1>From custom "Results" template</h1>', $result, 'Can not overwrite template path');
        self::assertStringContainsString('<h1>From custom "RelevanceBar" partial</h1>', $result, 'Can not overwrite partial path');
    }

    #[Group('frontend')]
    #[Test]
    public function canPassCustomSettingsToView(): void
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr {
                settings.foo.bar = mytestsetting
                view {
                    templateRootPaths.20 = EXT:solr/Tests/Integration/Controller/Fixtures/customTemplates/
                    partialRootPaths.20 = EXT:solr/Tests/Integration/Controller/Fixtures/customPartials/
                }
            }
            ',
        );

        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest(),
        )->getBody();

        self::assertStringContainsString('mytestsetting', $result, 'Can not output passed test setting');
    }

    /**
     * Only the entry template points to a different file.
     */
    #[Group('frontend')]
    #[Test]
    public function canRenderAsUserObjectWithCustomEntryTemplateInTypoScript(): void
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.view.templateFiles {
                results = EXT:solr/Tests/Integration/Controller/Fixtures/customTemplates/Search/MyResults.html
            }
            ',
        );
        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest(),
        )->getBody();

        self::assertStringContainsString('<h1>From custom entry template</h1>', $result, 'Can not set entry template file name in typoscript');
    }

    /**
     * @param string $content
     * @param string $id
     * @return string
     */
    protected function getIdContent($content, $id): string
    {
        if (!str_contains($content, $id)) {
            return '';
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML($content);
        libxml_use_internal_errors(false);

        return $dom->saveXML($dom->getElementById($id));
    }

    /**
     * Assert that a docContainer with a specific id contains an expected content snipped.
     *
     * @param string $expectedToContain
     * @param string $content
     * @param $id
     */
    protected function assertContainerByIdContains($expectedToContain, $content, $id): void
    {
        $containerContent = $this->getIdContent($content, $id);
        self::assertStringContainsString($expectedToContain, $containerContent, 'Failed asserting that container with id ' . $id . ' contains ' . $expectedToContain);
    }

    /**
     * Assert that a docContainer with a specific id contains an expected content snipped.
     *
     * @param string $expectedToContain
     * @param string $content
     * @param $id
     */
    protected function assertContainerByIdNotContains($expectedToContain, $content, $id): void
    {
        $containerContent = $this->getIdContent($content, $id);
        self::assertStringNotContainsString($expectedToContain, $containerContent, 'Failed asserting that container with id ' . $id . ' not contains ' . $expectedToContain);
    }

    /**
     * Assertion to check if the pagination markup is present in the response.
     *
     * @param string $content
     */
    protected function assertPaginationVisible($content): void
    {
        self::assertStringContainsString('class="solr-pagination"', $content, 'No pagination container visible');
        self::assertStringContainsString('ul class="pagination"', $content, 'Could not see pagination list');
    }

    protected function getPreparedRequest(int $pageId = 2022): InternalRequest
    {
        return (new InternalRequest('http://testone.site/'))->withPageId($pageId);
    }
}
