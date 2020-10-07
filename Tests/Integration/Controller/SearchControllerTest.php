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

use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\Controller\SearchController;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager as ExtbaseConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidActionNameException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Response;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Integration testcase to test for the SearchController
 *
 * (c) 2010-2015 Timo Hund <timo.hund@dkd.de>
 * @author Timo Hund
 * @group frontend
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
     * @var Request
     */
    protected $searchRequest;

    /**
     * @var Response
     */
    protected $searchResponse;


    public function setUp(): void
    {
        parent::setUp();
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
        $this->fakeSingletonsForFrontendContext();

        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();

        /** @var  $searchController SearchController */
        $this->searchController = $this->objectManager->get(SearchController::class);
        $this->searchRequest = $this->getPreparedRequest();
        $this->searchResponse = $this->getPreparedResponse();
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][PageFieldMappingIndexer::class] = PageFieldMappingIndexer::class;
    }

    /**
     * Executed after each test. Emptys solr and checks if the index is empty
     */
    public function tearDown(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canShowSearchForm()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2]);

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $content = $this->searchResponse->getContent();
        $this->assertStringContainsString('id="tx-solr-search-form-pi-results"', $content, 'Response did not contain search css selector');
    }

    /**
     * @test
     */
    public function canSearchForPrices()
    {
        $_GET['q'] = 'prices';
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([1, 2, 3]);

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $result = $this->searchResponse->getContent();

        $this->assertMatchesRegularExpression('/Found [0-9]+ results in [0-9]+ milliseconds/i', $result);
        $this->assertStringContainsString('pages/3/0/0/0', $result, 'Could not find page 3 in result set');
        $this->assertStringContainsString('pages/2/0/0/0', $result, 'Could not find page 2 in result set');
    }

    /**
     * @test
     */
    public function canDoAPaginatedSearch()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $_GET['q'] = '*';

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertPaginationVisible($resultPage1);
        $this->assertStringContainsString('Displaying results 1 to 5 of 8', $resultPage1, 'Wrong result count indicated in template');
    }

    /**
     * @test
     */
    public function canOpenSecondPageOfPaginatedSearch()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //now we jump to the second page
        $_GET['q'] = '*';

        $this->searchRequest->setArgument('page', 2);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage2 = $this->searchResponse->getContent();

        $this->assertStringContainsString('pages/8/0/0/0', $resultPage2, 'Could not find page 8 in result set');
        $this->assertStringContainsString('Displaying results 6 to 8 of 8', $resultPage2, 'Wrong result count indicated in template');
    }

    /**
     * @test
     */
    public function canGetADidYouMeanProposalForATypo()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = 'shoo';

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();
        $this->assertStringContainsString("Did you mean", $resultPage1, 'Could not find did you mean in response');
        $this->assertStringContainsString("shoes", $resultPage1, 'Could not find shoes in response');
    }

    /**
     * @test
     */
    public function canAutoCorrectATypo()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = 'shoo';

        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['spellchecking.']['searchUsingSpellCheckerSuggestion'] = 1;
        $overwriteConfiguration['search.']['spellchecking.']['numberOfSuggestionsToTry'] = 1;

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString("Nothing found for shoo", $resultPage1, 'Could not find nothing found message');
        $this->assertStringContainsString("Showing results for shoes", $resultPage1, 'Could not find correction message');
    }

    /**
     * @test
     */
    public function canRenderAFacetWithFluid()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

            // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringContainsString('pages</a> <span class="facet-result-count badge">', $resultPage1, 'Could not find facet option for pages');
    }

    /**
     * @test
     */
    public function canDoAnInitialEmptySearchWithoutResults()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);


        // now we set the facet type for "type" facet to fluid and expect that we get a rendered facet
        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['initializeWithEmptyQuery'] = 1;
        $overwriteConfiguration['search.']['showResultsOfInitialEmptyQuery'] = 0;

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringNotContainsString('results-entry', $resultPage1, 'No results should be visible since showResultsOfInitialEmptyQuery was set to false');
    }

    /**
     * @test
     */
    public function canDoAnInitialEmptySearchWithResults()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);


        // now we set the facet type for "type" facet to fluid and expect that we get a rendered facet
        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['initializeWithEmptyQuery'] = 1;
        $overwriteConfiguration['search.']['showResultsOfInitialEmptyQuery'] = 1;

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringContainsString('results-entry', $resultPage1, 'Results should be visible since showResultsOfInitialEmptyQuery was set to true');
    }

    /**
     * @test
     */
    public function canDoAnInitialSearchWithoutResults()
    {

        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        // now we set the facet type for "type" facet to fluid and expect that we get a rendered facet
        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['initializeWithQuery'] = 'product';
        $overwriteConfiguration['search.']['showResultsOfInitialQuery'] = 0;

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'fluidfacet should be generated since initializeWithQuery was configured with a query that should produce results');
        $this->assertStringNotContainsString('results-entry', $resultPage1, 'No results should be visible since showResultsOfInitialQuery was set to false');
    }


    /**
     * @test
     */
    public function canDoAnInitialSearchWithResults()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        // now we set the facet type for "type" facet to fluid and expect that we get a rendered facet
        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['initializeWithQuery'] = 'product';
        $overwriteConfiguration['search.']['showResultsOfInitialQuery'] = 1;

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('fluidfacet', $resultPage1, 'fluidfacet should be generated since initializeWithQuery was configured with a query that should produce results');
        $this->assertStringContainsString('results-entry', $resultPage1, 'Results should be visible since showResultsOfInitialQuery was set to true');
    }

    /**
     * @test
     */
    public function removeOptionLinkWillBeShownWhenFacetWasSelected()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        $this->searchRequest->setArgument('filter', ['type:pages']);

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();
        $this->assertStringContainsString('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertStringContainsString('remove-facet-option', $resultPage1, 'No link to remove facet option found');
    }

    /**
     * @test
     */
    public function removeOptionLinkWillIsAlsoShownWhenAFacetIsNotInTheResponse()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        $this->searchRequest->setArgument('filter', ['type:my_jobs']);

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('remove-facet-option', $resultPage1, 'No link to remove facet option found');
    }

    /**
     * @test
     */
    public function canFilterOnPageSections()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['query.']['filter.']['__pageSections'] = '2,3';

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        // we should only find 2 results since a __pageSections filter should be applied
        $this->assertStringContainsString('Found 2 results', $resultPage1, 'No link to remove facet option found');
    }

    /**
     * @test
     */
    public function exceptionWillBeThrownWhenAWrongTemplateIsConfiguredForTheFacet()
    {
        // we expected that an exception will be thrown when a facet is rendered
        // where an unknown partialName is referenced
        $this->expectException(InvalidTemplateResourceException::class);
        $this->expectExceptionMessageMatches('#(.*The partial files.*NotFound.*|.*The Fluid template files .*NotFound.*)#');

        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        // now we set the facet type for "type" facet to fluid and expect that we get a rendered facet
        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['faceting.']['facets.']['type.']['partialName'] = 'NotFound';

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
    }

    /**
     * @test
     */
    public function canRenderAScoreAnalysisWhenBackendUserIsLoggedIn()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        // fake that a backend user is logged in
        $this->fakeBackendUserLoggedInInFrontend();

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('document-score-analysis', $resultPage1, 'No score analysis in response');
    }

    /**
     * @test
     */
    public function canSortFacetsByLex()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $womanPages = [4,5,8];
        $menPages = [2];
        $this->indexPages($womanPages);
        $this->indexPages($menPages);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        // when we sort by lex "men" should appear before "woman" even when only one option is available
        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['faceting.']['facets.']['subtitle.']['sortBy'] = 'lex';


        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

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
     */
    public function canSortFacetsByOptionCountWhenNothingIsConfigured()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $womanPages = [4,5,8];
        $menPages = [2];
        $this->indexPages($womanPages);
        $this->indexPages($menPages);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

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
     */
    public function canRenderQueryGroupFacet()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('Small (1 &amp; 2)', $resultPage1, 'Response did not contain expected small option of query facet');
        $this->assertStringContainsString('Medium (2 to 5)', $resultPage1, 'Response did not contain expected medium option of query facet');
        $this->assertStringContainsString('Large (5 to *)', $resultPage1, 'Response did not contain expected large option of query facet');
    }

    /**
     * @test
     */
    public function canRenderHierarchicalFacet()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');

        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('Found 8 results', $resultPage1, 'Assert to find 8 results without faceting');
        $this->assertStringContainsString('facet-type-hierarchy', $resultPage1, 'Did not render hierarchy facet in the response');
        $this->assertStringContainsString('data-facet-item-value="/1/2/"', $resultPage1, 'Hierarchy facet item did not contain expected data item');

        $this->assertStringContainsString('tx_solr%5Bfilter%5D%5B0%5D=pageHierarchy%3A%2F1%2F2%2F&amp;tx_solr%5Bq%5D=%2A', $resultPage1, 'Result page did not contain hierarchical facet link');

    }

    /**
     * @test
     */
    public function canFacetOnHierarchicalFacetItem()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        $this->searchRequest->setArgument('filter', ['pageHierarchy:/1/2/']);

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('Found 1 result', $resultPage1, 'Assert to only find one result after faceting');
        $this->assertStringContainsString('facet-type-hierarchy', $resultPage1, 'Did not render hierarchy facet in the response');
        $this->assertStringContainsString('data-facet-item-value="/1/2/"', $resultPage1, 'Hierarchy facet item did not contain expected data item');
        $this->assertStringContainsString('tx_solr%5Bfilter%5D%5B0%5D=pageHierarchy%3A%2F1%2F2%2F&amp;tx_solr%5Bq%5D=%2A', $resultPage1, 'Result page did not contain hierarchical facet link');
    }

    /**
     * @test
     */
    public function canFacetOnHierarchicalTextCategory()
    {
        $this->importDataSetFromFixture('can_render_path_facet_with_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3]);
        // we should have 3 documents in solr
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertStringContainsString('"numFound":3', $solrContent, 'Could not index document into solr');

        // but when we facet on the categoryPaths:/Men/Shoes \/ Socks/ we should only have one result since the others
        // do not have the category assigned
        $_GET['q'] = '*';
        $this->searchRequest->setArgument('filter', ['categoryPaths:/Men/Shoes \/ Socks/']);

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('Found 1 result', $resultPage1, 'Assert to only find one result after faceting');
    }

    /**
     * @test
     */
    public function canDefineAManualSortOrder()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $womanPages = [4,5,8];
        $menPages = [2];
        $this->indexPages($womanPages);
        $this->indexPages($menPages);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        // when we sort by lex "men" should appear before "woman" even when only one option is available
        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['faceting.']['facets.']['subtitle.']['manualSortOrder'] = 'men, woman';


        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

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
     */
    public function canSeeTheParsedQueryWhenABackendUserIsLoggedIn()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        // fake that a backend user is logged in
        $this->fakeBackendUserLoggedInInFrontend();

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertStringContainsString('Parsed Query:', $resultPage1, 'No parsed query in response');
    }

    /**
     * @test
     */
    public function frontendWillRenderErrorMessageForSolrNotAvailableAction()
    {
        $this->applyUsingErrorControllerForCMS9andAbove();

        // set a wrong port where no solr is running
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort('http','localhost', 4711);
        $this->importDataSetFromFixture('can_render_error_message_when_solr_unavailable.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->searchRequest->setControllerActionName('solrNotAvailable');
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertEquals('503 Service Unavailable', $this->searchResponse->getStatus());
        $this->assertStringContainsString("Search is currently not available.", $resultPage1, 'Response did not contain solr unavailable error message');
    }

    /**
     * @return array
     */
    public function frontendWillForwardsToErrorActionWhenSolrEndpointIsNotAvailableDataProvider()
    {
        return [
            ['action' => 'results', 'getArguments' =>['q' => '*']],
            ['action' => 'detail', 'getArguments' =>['id' => 1]],
        ];
    }

    /**
     * @param string $action
     * @param array $getArguments
     * @throws InvalidActionNameException
     * @throws UnsupportedRequestTypeException
     * @dataProvider frontendWillForwardsToErrorActionWhenSolrEndpointIsNotAvailableDataProvider
     * @test
     */
    public function frontendWillForwardsToErrorActionWhenSolrEndpointIsNotAvailable($action, $getArguments)
    {
        $this->applyUsingErrorControllerForCMS9andAbove();
        // set a wrong port where no solr is running
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort('http','localhost', 4711);
        $this->expectException(StopActionException::class);
        $this->expectExceptionMessage('forward');
        $this->expectExceptionCode(1476045801);

        $this->importDataSetFromFixture('can_render_error_message_when_solr_unavailable.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $_GET = $getArguments;
        $this->searchRequest->setControllerActionName($action);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
    }

    /**
     * @test
     */
    public function canShowLastSearchesFromSessionInResponse()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = 'shoe';
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);

        $searchRequest2 = $this->getPreparedRequest();
        $searchResponse2 = $this->getPreparedResponse();
        $this->searchController->processRequest($searchRequest2, $searchResponse2);
        $resultPage2 = $this->searchResponse->getContent();


        $this->assertContainerByIdContains('>shoe</a>', $resultPage2, 'tx-solr-lastsearches');
    }

    /**
     * @test
     */
    public function canChangeResultsPerPage()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        $this->searchRequest->setArgument('resultsPerPage', 10);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);

        $resultPage = $this->searchResponse->getContent();
        $this->assertStringContainsString("Displaying results 1 to 8 of 8", $resultPage, '');
        $this->assertContainerByIdContains('<option selected="selected" value="10">10</option>', $resultPage, 'results-per-page');
    }


    /**
     * @test
     */
    public function canShowLastSearchesFromDatabaseInResponse()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['lastSearches.']['mode'] = 'global';

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);
        $this->searchController->setResetConfigurationBeforeInitialize(false);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = 'shoe';
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);

        $searchRequest2 = $this->getPreparedRequest();
        $searchResponse2 = $this->getPreparedResponse();
        $this->searchController->processRequest($searchRequest2, $searchResponse2);
        $resultPage2 = $this->searchResponse->getContent();

        $this->assertContainerByIdContains('>shoe</a>', $resultPage2, 'tx-solr-lastsearches');
    }

    /**
     * @test
     */
    public function canNotStoreQueyStringInLastSearchesWhenQueryDoesNotReturnAResult()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['lastSearches.']['mode'] = 'global';

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);
        $this->searchController->setResetConfigurationBeforeInitialize(false);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = 'nothingwillbefound';
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);

        $searchRequest2 = $this->getPreparedRequest();
        $searchResponse2 = $this->getPreparedResponse();
        $this->searchController->processRequest($searchRequest2, $searchResponse2);
        $resultPage2 = $searchResponse2->getContent();

        $this->assertContainerByIdNotContains('>nothingwillbefound</a>', $resultPage2, 'tx-solr-lastsearches');
    }

    /**
     * @test
     */
    public function canOverwriteAFilterWithTheFlexformSettings()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $contentObjectRendererMock = $this->getMockBuilder(ContentObjectRenderer::class)->disableOriginalConstructor()->getMock();
        $flexFormData = $this->getFixtureContentByName('fakedFlexFormData.xml');
        $contentObjectRendererMock->data = ['pi_flexform' => $flexFormData];
        $this->searchController->setContentObjectRenderer($contentObjectRendererMock);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);

        $this->assertStringContainsString('Displaying results 1 to 4 of 4', $this->searchResponse->getContent());
    }

    /**
     * @test
     */
    public function canRenderDateRangeFacet()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['faceting.']['facets.']['myCreatedFacet.'] = [
            'label' => 'Created Between',
            'field' => 'created',
            'type' => 'dateRange'
        ];

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);
        $this->searchController->setResetConfigurationBeforeInitialize(false);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $this->assertStringContainsString('facet-type-dateRange', $this->searchResponse->getContent());
    }

    /**
     * @test
     */
    public function canRenderASecondFacetOnTheTypeField()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['faceting.']['facets.']['myType.'] = [
            'label' => 'My Type',
            'field' => 'type',
        ];

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);
        $this->searchController->setResetConfigurationBeforeInitialize(false);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $this->assertStringContainsString('id="facetmyType"', $this->searchResponse->getContent());
        $this->assertStringContainsString('id="facettype"', $this->searchResponse->getContent());

    }

    /**
     * @test
     */
    public function canSortByMetric()
    {
        $this->importDataSetFromFixture('can_sort_by_metric.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);

        $pid1Option = urlencode('tx_solr[filter][0]') . '=' . urlencode('pid:1');
        $pid2Option = urlencode('tx_solr[filter][0]') . '=' . urlencode('pid:2');

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);

        $content = $this->searchResponse->getContent();
        $pid1OptionPosition = strpos($content, $pid1Option);
        $pid2OptionPosition = strpos($content, $pid2Option);

        $this->assertGreaterThan(0, $pid1OptionPosition, 'Pid 1 option does not appear in the content');
        $this->assertGreaterThan(0, $pid2OptionPosition, 'Pid 2 option does not appear in the content');
        $isPid2OptionBefore1Option = $pid2OptionPosition < $pid1OptionPosition;
        $this->assertTrue($isPid2OptionBefore1Option);
    }

    /**
     * @test
     */
    public function formActionIsRenderingTheForm()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $formRequest = $this->getPreparedRequest('Search','form');
        $formResponse = $this->getPreparedResponse();
        $this->searchController->processRequest($formRequest, $formResponse);

        $formContent = $formResponse->getContent();
        $this->assertStringContainsString('<div class="tx-solr-search-form">', $formContent);
    }

    /**
     * @test
     */
    public function searchingAndRenderingFrequentSearchesIsShowingTheTermAsFrequentSearch()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1]);

        $searchRequest = $this->getPreparedRequest('Search', 'frequentlySearched', 'pi_frequentlySearched');
        $searchResponse = $this->getPreparedResponse();

        $this->searchController->processRequest($searchRequest, $searchResponse);
        $resultPage = $searchResponse->getContent();

        $this->assertContainerByIdContains('>shoes</a>', $resultPage, 'tx-solr-frequent-searches');
    }

    /**
     * @test
     */
    public function canRenderDetailAction()
    {
        $request = $this->getPreparedRequest('Search', 'detail');
        $request->setArgument('documentId', '002de2729efa650191f82900ea02a0a3189dfabb/pages/1/0/0/0');

        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->indexPages([1, 2]);

        $this->searchController->processRequest($request, $this->searchResponse);
        $this->assertStringContainsString("Products", $this->searchResponse->getContent());
    }

    /**
     * @test
     */
    public function canRenderSearchFormOnly()
    {
        $request = $this->getPreparedRequest('Search', 'form', 'pi_search');

        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);

        $this->searchController->processRequest($request, $this->searchResponse);
        $this->assertStringContainsString('id="tx-solr-search-form-pi-results"', $this->searchResponse->getContent());
    }

    /**
     * The template root path is configured in the typoscript template to point to another folder-
     *
     * @test
     */
    public function canRenderAsUserObjectWithCustomTemplatePath()
    {
        $_GET['q'] = '*';

        $this->importDataSetFromFixture('can_render_search_customTemplate.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);
        $this->searchRequest->setArgument('resultsPerPage', 5);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $result = $this->searchResponse->getContent();

        $this->assertStringContainsString('Custom Integration Test Search Templatepath', $result, 'Can not overwrite template path');
        $this->assertStringContainsString('Custom Integration Test Pagination Templatepath', $result, 'Can not overwrite template path');
    }

    /**
     * @test
     */
    public function canPassCustomSettingsToView()
    {
        $_GET['q'] = '*';

        $this->importDataSetFromFixture('can_render_search_customTemplate.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);


        $overwriteConfiguration = [];
        $overwriteConfiguration['settings.']['foo.']['bar'] = 'mytestsetting';

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);
        $this->searchController->setResetConfigurationBeforeInitialize(false);

        $this->searchRequest->setArgument('resultsPerPage', 5);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $result = $this->searchResponse->getContent();

        $this->assertStringContainsString('mytestsetting', $result, 'Can not output passed test setting');
    }

    /**
     * Only the entry template points to a different file.
     *
     * @test
     */
    public function canRenderAsUserObjectWithCustomTemplateInTypoScript()
    {
        $_GET['q'] = '*';
        $this->importDataSetFromFixture('can_render_search_customTemplateFromTs.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);
        $this->searchRequest->setArgument('resultsPerPage', 5);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $result = $this->searchResponse->getContent();

        $this->assertStringContainsString('Custom Integration Test Search Template entry Template', $result, 'Can not set entry template file name in typoscript');
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
        $dom = new \DOMDocument('1.0', 'UTF-8');
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
        $context = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Context\Context::class);
        $userAspect = $this->getMockBuilder(\TYPO3\CMS\Core\Context\UserAspect::class)->setMethods([])->getMock();
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

        $configurationManagerMock = $this->getMockBuilder(ExtbaseConfigurationManager::class)->setMethods(['getContentObject'])
            ->setConstructorArgs([$this->objectManager, $environmentServiceMock])->getMock();

        $configurationManagerMock->expects($this->any())->method('getContentObject')->willReturn(GeneralUtility::makeInstance(ContentObjectRenderer::class));

        GeneralUtility::setSingletonInstance(EnvironmentService::class, $environmentServiceMock);
        GeneralUtility::setSingletonInstance(ExtbaseConfigurationManager::class, $configurationManagerMock);
    }
}
