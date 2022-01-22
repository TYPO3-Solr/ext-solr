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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

use DOMDocument;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\Controller\SearchController;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager as ExtbaseConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Integration testcase to test for the SearchController
 *
 * (c) 2010-2015 Timo Hund <timo.hund@dkd.de>
 * @author Timo Hund
 */
class SearchControllerTest extends AbstractFrontendControllerTest
{
    /**
     * @var ObjectManagerInterface The object manager
     */
    protected $objectManager;

    /**
     * @var SearchController
     */
    protected $searchController;

    /**
     * @var Response
     */
    protected $searchResponse;

    public function setUp(): void
    {
        parent::setUp();
        $this->bootstrapSearchResultsPluginOnPage();
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][PageFieldMappingIndexer::class] = PageFieldMappingIndexer::class;
    }

    /**
     * @param int $pageId
     * @return void
     * @throws TestingFrameworkCoreException
     */
    protected function bootstrapSearchResultsPluginOnPage(): void
    {
        $this->importDataSetFromFixture('default_search_results_plugin.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
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
     * @test
     * @group frontend
     */
    public function canShowSearchFormViaPlugin()
    {
        $response = $this->executeFrontendSubRequest($this->getPreparedRequest(2022));
        $content = (string)$response->getBody();
        $this->assertStringContainsString('id="tx-solr-search-form-pi-results"', $content, 'Response did not contain search css selector');
    }

    /**
     * @test
     * @group frontend
     */
    public function canShowSearchForm()
    {
        $response = $this->executeFrontendSubRequest($this->getPreparedRequest());
        $content = (string)$response->getBody();
        $this->assertStringContainsString('id="tx-solr-search-form-pi-results"', $content, 'Response did not contain search css selector');
    }

    /**
     * @test
     * @group frontend
     */
    public function canSearchForPrices()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->indexPages([2, 3]);

        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()->withQueryParameter('q', 'prices')
        )->getBody();

        $this->assertMatchesRegularExpression('/Found [0-9]+ results in [0-9]+ milliseconds/i', $result);
        $this->assertStringContainsString('pages/3/0/0/0', $result, 'Could not find page 3 in result set');
        $this->assertStringContainsString('pages/2/0/0/0', $result, 'Could not find page 2 in result set');
    }

    /**
     * @test
     * @group frontend
     */
    public function canDoAPaginatedSearch()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.search.results.resultsPerPageSwitchOptions = 5, 10, 25, 50
            plugin.tx_solr.search.results.resultsPerPage = 5'
        );


        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $this->assertPaginationVisible($resultPage1);
        $this->assertStringContainsString('Displaying results 1 to 5 of 8', $resultPage1, 'Wrong result count indicated in template of pagination page 1.');

        $this->assertCanOpenSecondPageOfPaginatedSearch();
        $this->assertCanChangeResultsPerPage();
    }

    protected function assertCanOpenSecondPageOfPaginatedSearch(): void
    {
        $resultPage2 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[page]', 2)
        )->getBody();

        $this->assertStringContainsString('pages/8/0/0/0', $resultPage2, 'Could not find page(PID) 8 in result set.');
        $this->assertStringContainsString('Displaying results 6 to 8 of 8', $resultPage2, 'Wrong result count indicated in template of pagination page 2.');
    }

    protected function assertCanChangeResultsPerPage()
    {
        $resultPage = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[resultsPerPage]', 10)
        )->getBody();

        $this->assertStringContainsString("Displaying results 1 to 8 of 8", $resultPage, '');
        $this->assertContainerByIdContains('<option selected="selected" value="10">10</option>', $resultPage, 'results-per-page');
    }

    /**
     * @test
     * @group frontend
     */
    public function canGetADidYouMeanProposalForATypo()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.search.spellchecking = 1
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'shoo')
        )->getBody();

        $this->assertStringContainsString("Did you mean", $resultPage1, 'Could not find did you mean in response');
        $this->assertStringContainsString("shoes", $resultPage1, 'Could not find shoes in response');
    }

    /**
     * @test
     * @group frontend
     */
    public function canAutoCorrectATypo()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.search.spellchecking = 1
            plugin.tx_solr.search.spellchecking {
                searchUsingSpellCheckerSuggestion = 1
                numberOfSuggestionsToTry = 1
            }
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'shoo')
        )->getBody();

        $this->assertStringContainsString("Nothing found for shoo", $resultPage1, 'Could not find nothing found message');
        $this->assertStringContainsString("Showing results for shoes", $resultPage1, 'Could not find correction message');
    }

    /**
     * @test
     * @group frontend
     */
    public function canRenderAFacetWithFluid()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');

        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets.type {
                label = Content Type
                field = type
            }
            '
        );

        $this->indexPages([1, 2]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringContainsString('pages</a> <span class="facet-result-count badge">', $resultPage1, 'Could not find facet option for pages');
    }

    /**
     * @test
     * @group frontend
     * @todo: https://github.com/TYPO3-Solr/ext-solr/issues/3150
     */
    public function canDoAnInitialEmptySearchWithoutResults()
    {
        $this->markTestSkipped('Something is wrong with refactored pagination. See https://github.com/TYPO3-Solr/ext-solr/issues/3150');
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
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
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest($this->getPreparedRequest())->getBody();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringNotContainsString('results-entry', $resultPage1, 'No results should be visible since showResultsOfInitialEmptyQuery was set to false');
    }

    /**
     * @test
     * @group frontend
     */
    public function canDoAnInitialEmptySearchWithResults()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
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
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest($this->getPreparedRequest())->getBody();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringContainsString('results-entry', $resultPage1, 'Results should be visible since showResultsOfInitialEmptyQuery was set to true');
    }

    /**
     * @test
     * @group frontend
     * @todo: https://github.com/TYPO3-Solr/ext-solr/issues/3150
     */
    public function canDoAnInitialSearchWithoutResults()
    {
        $this->markTestSkipped('Something is wrong with refactored pagination. See https://github.com/TYPO3-Solr/ext-solr/issues/3150');
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
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
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest($this->getPreparedRequest())->getBody();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'fluidfacet should be generated since initializeWithQuery was configured with a query that should produce results');
        $this->assertStringNotContainsString('results-entry', $resultPage1, 'No results should be visible since showResultsOfInitialQuery was set to false');
    }


    /**
     * @test
     * @group frontend
     */
    public function canDoAnInitialSearchWithResults()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
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
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest($this->getPreparedRequest())->getBody();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'fluidfacet should be generated since initializeWithQuery was configured with a query that should produce results');
        $this->assertStringContainsString('results-entry', $resultPage1, 'Results should be visible since showResultsOfInitialQuery was set to true');
    }

    /**
     * @test
     * @group frontend
     */
    public function removeOptionLinkWillBeShownWhenFacetWasSelected()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.type {
                    label = Content Type
                    field = type
                }
            }
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[filter][0]', 'type:pages')
        )->getBody();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringContainsString('remove-facet-option', $resultPage1, 'No link to remove facet option found');
    }

    /**
     * @test
     * @group frontend
     */
    public function removeOptionLinkWillBeShownWhenAFacetOptionLeadsToAZeroResults()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.type {
                    label = Content Type
                    field = type
                }
            }
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[filter][0]', 'type:my_jobs')
        )->getBody();

        $this->assertStringContainsString('remove-facet-option', $resultPage1, 'No link to remove facet option found');
    }

    /**
     * @test
     * @group frontend
     */
    public function canFilterOnPageSections()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.search.query.filter.__pageSections = 2,3
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        // we should only find 2 results since a __pageSections filter should be applied
        $this->assertStringContainsString('Found 2 results', $resultPage1, 'No link to remove facet option found');
    }

    /**
     * @test
     * @group frontend
     */
    public function exceptionWillBeThrownWhenAWrongTemplateIsConfiguredForTheFacet()
    {
        // we expected that an exception will be thrown when a facet is rendered
        // where an unknown partialName is referenced
        $this->expectException(InvalidTemplateResourceException::class);
        $this->expectExceptionMessageMatches('#(.*The partial files.*NotFound.*|.*The Fluid template files .*NotFound.*)#');

        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.type {
                    partialName = NotFound
                    label = Content Type
                    field = type
                }
            }
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        );
    }

    /**
     * @test
     * @group frontend
     */
    public function canRenderAScoreAnalysisWhenBackendUserIsLoggedIn()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');

        $this->indexPages([1, 2]);

        // fake that a backend user is logged in
        $this->setUpBackendUserFromFixture(1);
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
            (new InternalRequestContext())->withBackendUserId(1)
        )->getBody();

        $this->assertStringContainsString('document-score-analysis', $resultPage1, 'No score analysis in response');
    }

    /**
     * @test
     * @group frontend
     */
    public function canSortFacetsByLex()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
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
            '
        );

        $womanPages = [4,5,8];
        $menPages = [2];
        $this->indexPages($womanPages);
        $this->indexPages($menPages);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $subtitleMenPosition = strpos($resultPage1, '>men</a> <span class="facet-result-count badge">1</span>');
        $subtitleWomanPosition =  strpos($resultPage1, '>woman</a> <span class="facet-result-count badge">3</span>');

        $this->assertGreaterThan(0, $subtitleMenPosition);
        $this->assertGreaterThan(0, $subtitleWomanPosition);
        $this->assertGreaterThan($subtitleMenPosition, $subtitleWomanPosition);

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringContainsString('pages</a> <span class="facet-result-count badge">', $resultPage1, 'Could not find facet option for pages');
    }

    /**
     * @test
     * @group frontend
     */
    public function canSortFacetsByOptionCountWhenNothingIsConfigured()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.search {
                faceting = 1
                faceting.facets.subtitle {
                    label = Subtitle
                    field = subTitle
                    keepAllOptionsOnSelection = 1
                }
            }
            '
        );

        $womanPages = [4,5,8];
        $menPages = [2];
        $this->indexPages($womanPages);
        $this->indexPages($menPages);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $subtitleMenPosition = strpos($resultPage1, '>men</a> <span class="facet-result-count badge">1</span>');
        $subtitleWomanPosition =  strpos($resultPage1, '>woman</a> <span class="facet-result-count badge">3</span>');

        $this->assertGreaterThan(0, $subtitleMenPosition);
        $this->assertGreaterThan(0, $subtitleWomanPosition);
        $this->assertGreaterThan($subtitleWomanPosition, $subtitleMenPosition);

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringContainsString('pages</a> <span class="facet-result-count badge">', $resultPage1, 'Could not find facet option for pages');
    }

    /**
     * @test
     * @group frontend
     */
    public function canRenderQueryGroupFacet()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
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
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $this->assertStringContainsString('Small (1 &amp; 2)', $resultPage1, 'Response did not contain expected small option of query facet');
        $this->assertStringContainsString('Medium (2 to 5)', $resultPage1, 'Response did not contain expected medium option of query facet');
        $this->assertStringContainsString('Large (5 to *)', $resultPage1, 'Response did not contain expected large option of query facet');
    }


    /**
     * @return void
     */
    protected function addPageHierarchyFacetConfiguration(): void
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
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
            '
        );
    }

    /**
     * @test
     * @group frontend
     */
    public function canRenderHierarchicalFacet()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addPageHierarchyFacetConfiguration();
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $this->assertStringContainsString('Found 8 results', $resultPage1, 'Assert to find 8 results without faceting');
        $this->assertStringContainsString('facet-type-hierarchy', $resultPage1, 'Did not render hierarchy facet in the response');
        $this->assertStringContainsString('data-facet-item-value="/1/2/"', $resultPage1, 'Hierarchy facet item did not contain expected data item');

        $this->assertStringContainsString('tx_solr%5Bfilter%5D%5B0%5D=pageHierarchy%3A%2F1%2F2%2F&amp;tx_solr%5Bq%5D=%2A', $resultPage1, 'Result page did not contain hierarchical facet link');

    }

    /**
     * @test
     * @group frontend
     */
    public function canFacetOnHierarchicalFacetItem()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addPageHierarchyFacetConfiguration();
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[filter][0]', 'pageHierarchy:/1/2/')
        )->getBody();

        $this->assertStringContainsString('Found 1 result', $resultPage1, 'Assert to only find one result after faceting');
        $this->assertStringContainsString('facet-type-hierarchy', $resultPage1, 'Did not render hierarchy facet in the response');
        $this->assertStringContainsString('data-facet-item-value="/1/2/"', $resultPage1, 'Hierarchy facet item did not contain expected data item');
        $this->assertStringContainsString('tx_solr%5Bfilter%5D%5B0%5D=pageHierarchy%3A%2F1%2F2%2F&amp;tx_solr%5Bq%5D=%2A', $resultPage1, 'Result page did not contain hierarchical facet link');
    }

    /**
     * @test
     * @group frontend
     */
    public function canFacetOnHierarchicalTextCategory()
    {
        $this->importDataSetFromFixture('can_render_path_facet_with_search_controller.xml');

        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
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
            '
        );

        $this->indexPages([2, 3, 4]);
        // we should have 3 documents in solr
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertStringContainsString('"numFound":3', $solrContent, 'Could not index document into solr');

        // but when we facet on the categoryPaths:/Men/Shoes \/ Socks/ we should only have one result since the others
        // do not have the category assigned
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[filter][0]', 'categoryPaths:/Men/Shoes \/ Socks/')
        )->getBody();

        $this->assertStringContainsString('Found 1 result', $resultPage1, 'Assert to only find one result after faceting');
    }

    /**
     * @test
     * @group frontend
     */
    public function canDefineAManualSortOrder()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets.subtitle {
                label = Subtitle
                field = subTitle
                keepAllOptionsOnSelection = 1
                manualSortOrder = men, woman
            }
            '
        );

        $womanPages = [4,5,8];
        $menPages = [2];
        $this->indexPages($womanPages);
        $this->indexPages($menPages);

        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $subtitleMenPosition = strpos($resultPage1, '>men</a> <span class="facet-result-count badge">1</span>');
        $subtitleWomanPosition =  strpos($resultPage1, '>woman</a> <span class="facet-result-count badge">3</span>');

        $this->assertGreaterThan(0, $subtitleMenPosition);
        $this->assertGreaterThan(0, $subtitleWomanPosition);
        $this->assertGreaterThan($subtitleMenPosition, $subtitleWomanPosition);

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringContainsString('pages</a> <span class="facet-result-count badge">', $resultPage1, 'Could not find facet option for pages');
    }

    /**
     * @test
     * @group frontend
     */
    public function canSeeTheParsedQueryWhenABackendUserIsLoggedIn()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');

        $this->indexPages([1, 2]);

        $this->setUpBackendUserFromFixture(1);
        $resultPage1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*'),
            (new InternalRequestContext())->withBackendUserId(1)
        )->getBody();

        $this->assertStringContainsString('Parsed Query:', $resultPage1, 'No parsed query in response');
    }

    /**
     * @return array
     */
    public function frontendWillRenderErrorMessageIfSolrNotAvailableDataProvider(): array
    {
        return [
            ['action' => 'results', 'getArguments' =>['q' => '*']],
            ['action' => 'detail', 'getArguments' =>['id' => 1]],
        ];
    }

    /**
     * @param string $action
     * @param array $getArguments
     * @dataProvider frontendWillRenderErrorMessageIfSolrNotAvailableDataProvider
     * @test
     * @group frontend
     *
     * Notes:
     *   Fits removed frontendWillRenderErrorMessageForSolrNotAvailableAction() test case as well.
     *   Removed code: https://github.com/TYPO3-Solr/ext-solr/blob/03080d4d55eeb9d50b15348f445d23e57e34e461/Tests/Integration/Controller/SearchControllerTest.php#L729-L747
     *
     * @todo: See: https://github.com/TYPO3/testing-framework/issues/324
     */
    public function frontendWillRenderErrorMessageIfSolrNotAvailable(string $action, array $getArguments)
    {
        $this->mergeSiteConfiguration(
            'integration_tree_one',
            [
                'solr_scheme_read' => 'http',
                'solr_host_read' => 'localhost',
                'solr_port_read' => 4711
            ]
        );

        $response = $this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[action]', $action)
                ->withQueryParameter('tx_solr[' . key($getArguments). ']', current($getArguments))
        );

        $this->assertStringContainsString("Search is currently not available.", (string)$response->getBody(), 'Response did not contain solr unavailable error message');
        $this->markTestIncomplete('The status code can not be checked currently. See: https://github.com/TYPO3/testing-framework/issues/324');
        //$this->assertEquals(503, $response->getStatusCode());
    }

    /**
     * @test
     * @group frontend
     * @todo: https://github.com/TYPO3-Solr/ext-solr/issues/3160
     *       The session must be shared between both requests.
     */
    public function canShowLastSearchesFromSessionInResponse()
    {
        $this->markTestIncomplete(
            'Last searches component seems to be fine, but the test does not fit that case currently.
            The last-searches component is not rendered. See: https://github.com/TYPO3-Solr/ext-solr/issues/3160'
        );
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr.search.lastSearches = 1
            plugin.tx_solr.search.lastSearches {
                limit = 10
                mode = user
            }
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()->withQueryParameter('tx_solr[q]', 'shoe')
        )->getBody();

        $resultSearch2 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $this->assertContainerByIdContains('>shoe</a>', $resultSearch2, 'tx-solr-lastsearches');
    }

    /**
     * @test
     * @group frontend
     */
    public function canShowLastSearchesFromDatabaseInResponse()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr.search.lastSearches = 1
            plugin.tx_solr.search.lastSearches {
                limit = 10
                mode = global
            }
            '
        );
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()->withQueryParameter('tx_solr[q]', 'shoe')
        )->getBody();

        $resultSearch2 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $this->assertContainerByIdContains('>shoe</a>', $resultSearch2, 'tx-solr-lastsearches');
    }

    /**
     * @test
     * @group frontend
     */
    public function canNotStoreQueyStringInLastSearchesWhenQueryDoesNotReturnAResult()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr.search.lastSearches = 1
            plugin.tx_solr.search.lastSearches {
                limit = 10
                mode = global
            }
            '
        );
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch1 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'nothingwillbefound')
        )->getBody();

        $resultSearch2 = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'nothingwillbefound')
        )->getBody();

        $this->assertContainerByIdNotContains('>nothingwillbefound</a>', $resultSearch2, 'tx-solr-lastsearches');
    }

    /**
     * @test
     * @group frontend
     */
    public function canOverwriteAFilterWithTheFlexformSettings()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update(
            'tt_content',
            ['pi_flexform' => $this->getFixtureContentByName('fakedFlexFormData.xml')],
            ['uid' => 2022]
        );

        $resultSearch = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $this->assertStringContainsString('Displaying results 1 to 4 of 4', $resultSearch);
    }

    /**
     * @test
     * @group frontend
     */
    public function canRenderDateRangeFacet()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets.myCreatedFacet {
                label = Created Between
                field = created
                type = dateRange
            }
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $this->assertStringContainsString('facet-type-dateRange', $resultSearch);
    }

    /**
     * @test
     * @group frontend
     */
    public function canRenderASecondFacetOnTheTypeField()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
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
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $resultSearch = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();

        $this->assertStringContainsString('id="facetmyType"', $resultSearch);
        $this->assertStringContainsString('id="facettype"', $resultSearch);
    }

    /**
     * @test
     * @group frontend
     */
    public function canSortByMetric()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->importDataSetFromFixture('can_sort_by_metric.xml');

        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr.search.faceting = 1
            plugin.tx_solr.search.faceting.facets {
                pid {
                    label = Content Type
                    field = pid
                    metrics {
                        newest = max(created)
                    }
                    sortBy = metrics_newest desc
                }
            }
            '
        );

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);

        $pid1Option = urlencode('tx_solr[filter][0]') . '=' . urlencode('pid:1');
        $pid2Option = urlencode('tx_solr[filter][0]') . '=' . urlencode('pid:2');

        $content = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', '*')
        )->getBody();
        $pid1OptionPosition = strpos($content, $pid1Option);
        $pid2OptionPosition = strpos($content, $pid2Option);

        $this->assertGreaterThan(0, $pid1OptionPosition, 'Pid 1 option does not appear in the content');
        $this->assertGreaterThan(0, $pid2OptionPosition, 'Pid 2 option does not appear in the content');
        $isPid2OptionBefore1Option = $pid2OptionPosition < $pid1OptionPosition;
        $this->assertTrue($isPid2OptionBefore1Option);
    }

    /**
     * @test
     * @group frontend
     * Notes:
     *   Fits removed canRenderSearchFormOnly() test case as well.
     *     Removed code: https://github.com/TYPO3-Solr/ext-solr/blob/03080d4d55eeb9d50b15348f445d23e57e34e461/Tests/Integration/Controller/SearchControllerTest.php#L1053-L1062
     */
    public function formActionIsRenderingTheForm()
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update(
            'tt_content',
            ['list_type' => 'solr_pi_search'],
            ['uid' => 2022]
        );

        $formContent = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
        )->getBody();
        $this->assertStringContainsString('<div class="tx-solr-search-form">', $formContent);
        $this->assertStringNotContainsString('id="tx-solr-search"', $formContent);
        $this->assertStringNotContainsString('id="tx-solr-search-functions"', $formContent);
    }

    /**
     * @test
     * @group frontend
     * @todo : https://github.com/TYPO3-Solr/ext-solr/issues/3166
     */
    public function searchingAndRenderingFrequentSearchesIsShowingTheTermAsFrequentSearch()
    {
        $this->markTestIncomplete('See: https://github.com/TYPO3-Solr/ext-solr/issues/3166');
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->indexPages([2]);

        $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->update(
                'tt_content',
                ['list_type' => 'solr_pi_frequentlysearched'],
                ['uid' => 2022]
            );


        $resultPage = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[q]', 'shoes')
        )->getBody();

        $this->assertContainerByIdContains('>shoes</a>', $resultPage, 'tx-solr-frequent-searches');
    }

    /**
     * @test
     * @group frontend
     */
    public function canRenderDetailAction()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->indexPages([2]);

        $resultPage = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
                ->withQueryParameter('tx_solr[action]', 'detail')
                ->withQueryParameter('tx_solr[documentId]', '002de2729efa650191f82900ea02a0a3189dfabb/pages/2/0/0/0')
        )->getBody();

        $this->assertStringContainsString("<h1>Socks</h1>", $resultPage);
        $this->assertStringContainsString("<p>Our awesome new sock products prices starting at 10 euro</p>", $resultPage);
        $this->assertStringContainsString("open</a>", $resultPage);
    }

    /**
     * The template root path is configured in the typoscript template to point to another folder-
     *
     * @test
     * @group frontend
     */
    public function canOverrideTemplatesAndPartialsViaTypoScriptSetup()
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr.view {
                templateRootPaths.20 = EXT:solr/Tests/Integration/Controller/Fixtures/customTemplates/
                partialRootPaths.20 = EXT:solr/Tests/Integration/Controller/Fixtures/customPartials/
            }
            '
        );

        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
        )->getBody();

        $this->assertStringContainsString('<h1>From custom "Results" template</h1>', $result, 'Can not overwrite template path');
        $this->assertStringContainsString('<h1>From custom "RelevanceBar" partial</h1>', $result, 'Can not overwrite partial path');
    }

    /**
     * The template root path is configured in the typoscript template to point to another folder
     *
     * @test
     * @group frontend
     */
    public function canOverrideTemplatesAndPartialsViaTypoScriptConstants()
    {
        $this->addTypoScriptConstantsToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr.view {
                templateRootPath = EXT:solr/Tests/Integration/Controller/Fixtures/customTemplates/
                partialRootPath = EXT:solr/Tests/Integration/Controller/Fixtures/customPartials/
            }
            '
        );

        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
        )->getBody();

        $this->assertStringContainsString('<h1>From custom "Results" template</h1>', $result, 'Can not overwrite template path');
        $this->assertStringContainsString('<h1>From custom "RelevanceBar" partial</h1>', $result, 'Can not overwrite partial path');
    }

    /**
     * @test
     * @group frontend
     */
    public function canPassCustomSettingsToView()
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr {
                settings.foo.bar = mytestsetting
                view {
                    templateRootPaths.20 = EXT:solr/Tests/Integration/Controller/Fixtures/customTemplates/
                    partialRootPaths.20 = EXT:solr/Tests/Integration/Controller/Fixtures/customPartials/
                }
            }
            '
        );

        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
        )->getBody();

        $this->assertStringContainsString('mytestsetting', $result, 'Can not output passed test setting');
    }

    /**
     * Only the entry template points to a different file.
     *
     * @test
     * @group frontend
     */
    public function canRenderAsUserObjectWithCustomEntryTemplateInTypoScript()
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang typoScript */ '
            plugin.tx_solr.view.templateFiles {
                results = EXT:solr/Tests/Integration/Controller/Fixtures/customTemplates/Search/MyResults.html
            }
            '
        );
        $result = (string)$this->executeFrontendSubRequest(
            $this->getPreparedRequest()
        )->getBody();

        $this->assertStringContainsString('<h1>From custom entry template</h1>', $result, 'Can not set entry template file name in typoscript');
    }


    /**
     * @param string $content
     * @param string $id
     * @return string
     */
    protected function getIdContent($content, $id)
    {
        if (strpos($content, $id) === false) {
            return '';
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $content);
        return $dom->saveXML($dom->getElementById($id));
    }

    /**
     * Assert that a docContainer with a specific id contains an expected content snipped.
     *
     * @param string $expectedToContain
     * @param string $content
     * @param $id
     */
    protected function assertContainerByIdContains($expectedToContain, $content, $id)
    {
        $containerContent = $this->getIdContent($content, $id);
        $this->assertStringContainsString($expectedToContain, $containerContent, 'Failed asserting that container with id ' . $id .' contains ' . $expectedToContain);
    }

    /**
     * Assert that a docContainer with a specific id contains an expected content snipped.
     *
     * @param string $expectedToContain
     * @param string $content
     * @param $id
     */
    protected function assertContainerByIdNotContains($expectedToContain, $content, $id)
    {
        $containerContent = $this->getIdContent($content, $id);
        $this->assertStringNotContainsString($expectedToContain, $containerContent, 'Failed asserting that container with id ' . $id .' not contains ' . $expectedToContain);
    }


    /**
     * Assertion to check if the pagination markup is present in the response.
     *
     * @param string $content
     */
    protected function assertPaginationVisible($content)
    {
        $this->assertStringContainsString('class="solr-pagination"', $content, 'No pagination container visible');
        $this->assertStringContainsString('ul class="pagination"', $content, 'Could not see pagination list');
    }

    /**
     * We fake in the frontend context, that a backend user is logged in.
     *
     * @return void
     */
    protected function fakeBackendUserLoggedInInFrontend()
    {
        /** @var  $context \TYPO3\CMS\Core\Context\Context::class */
        $context = GeneralUtility::makeInstance(Context::class);
        $userAspect = $this->getMockBuilder(UserAspect::class)->setMethods([])->getMock();
        $userAspect->expects($this->any())->method('get')->with('isLoggedIn')->willReturn(true);
        $context->setAspect('backend.user', $userAspect);
    }

    /**
     * In this method we initialize a few singletons with mocked classes to be able to generate links
     * for the frontend in the testing context.
     * @return void
     */
    protected function fakeSingletonsForFrontendContext()
    {
        $environmentServiceMock = $this->getMockBuilder(EnvironmentService::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $environmentServiceMock->expects($this->any())->method('isEnvironmentInFrontendMode')->willReturn(true);
        $environmentServiceMock->expects($this->any())->method('isEnvironmentInBackendMode')->willReturn(false);

        $configurationManagerMock = $this->getMockBuilder(ExtbaseConfigurationManager::class)->onlyMethods(['getContentObject'])
            ->setConstructorArgs([$this->getContainer()])->getMock();

        $configurationManagerMock->expects($this->any())->method('getContentObject')->willReturn(GeneralUtility::makeInstance(ContentObjectRenderer::class));

        GeneralUtility::setSingletonInstance(EnvironmentService::class, $environmentServiceMock);
        GeneralUtility::setSingletonInstance(ExtbaseConfigurationManager::class, $configurationManagerMock);
    }
}
