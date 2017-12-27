<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Plugin\Results;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Controller\SearchController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Response;
use TYPO3\CMS\Fluid\View\Exception\InvalidTemplateResourceException;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageGenerator;

/**
 * Integration testcase to test for the SearchController
 *
 * @author Timo Hund
 */
class SearchControllerTest extends IntegrationTest
{
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

    public function setUp()
    {
        parent::setUp();
        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();

        /** @var  $searchController SearchController */
        $this->searchController = $this->objectManager->get(SearchController::class);
        $this->searchRequest = $this->getPreparedRequest();
        $this->searchResponse = $this->getPreparedResponse();
    }

    /**
     * Executed after each test. Emptys solr and checks if the index is empty
     */
    public function tearDown()
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
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);
        $this->indexPages([1, 2]);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $content = $this->searchResponse->getContent();
        $this->assertContains('id="tx-solr-search-form-pi-results"', $content, 'Response did not contain search css selector');
    }

    /**
     * @test
     */
    public function canSearchForPrices()
    {
        $_GET['q'] = 'prices';
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);
        $this->indexPages([1, 2, 3]);

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $result = $this->searchResponse->getContent();

        $this->assertContains('pages/3/0/0/0', $result, 'Could not find page 3 in result set');
        $this->assertContains('pages/2/0/0/0', $result, 'Could not find page 2 in result set');
    }

    /**
     * @test
     */
    public function canDoAPaginatedSearch()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $_GET['q'] = '*';

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertPaginationVisible($resultPage1);
        $this->assertContains('Displaying results 1 to 5 of 8', $resultPage1, 'Wrong result count indicated in template');
    }

    /**
     * @test
     */
    public function canOpenSecondPageOfPaginatedSearch()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //now we jump to the second page
        $_GET['q'] = '*';

        $this->searchRequest->setArgument('page', 2);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage2 = $this->searchResponse->getContent();

        $this->assertContains('pages/8/0/0/0', $resultPage2, 'Could not find page 8 in result set');
        $this->assertContains('Displaying results 6 to 8 of 8', $resultPage2, 'Wrong result count indicated in template');
    }

    /**
     * @test
     */
    public function canGetADidYouMeanProposalForATypo()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = 'shoo';

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertContains("Did you mean", $resultPage1, 'Could not find did you mean in response');
        $this->assertContains("shoes", $resultPage1, 'Could not find shoes in response');
    }

    /**
     * @test
     */
    public function canAutoCorrectATypo()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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

        $this->assertContains("Nothing found for shoo", $resultPage1, 'Could not find nothing found message');
        $this->assertContains("Search instead for shoes", $resultPage1, 'Could not find correction message');
    }

    /**
     * @test
     */
    public function canRenderAFacetWithFluid()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

            // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertContains('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertContains('pages</a> <span class="facet-result-count badge">', $resultPage1, 'Could not find facet option for pages');
    }

    /**
     * @test
     */
    public function canDoAnInitialEmptySearchWithoutResults()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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

        $this->assertContains('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertNotContains('results-entry', $resultPage1, 'No results should be visible since showResultsOfInitialEmptyQuery was set to false');
    }

    /**
     * @test
     */
    public function canDoAnInitialEmptySearchWithResults()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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

        $this->assertContains('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertContains('results-entry', $resultPage1, 'Results should be visible since showResultsOfInitialEmptyQuery was set to true');
    }

    /**
     * @test
     */
    public function canDoAnInitialSearchWithoutResults()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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

        $this->assertContains('fluidfacet', $resultPage1, 'fluidfacet should be generated since initializeWithQuery was configured with a query that should produce results');
        $this->assertNotContains('results-entry', $resultPage1, 'No results should be visible since showResultsOfInitialQuery was set to false');
    }


    /**
     * @test
     */
    public function canDoAnInitialSearchWithResults()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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

        $this->assertContains('fluidfacet', $resultPage1, 'fluidfacet should be generated since initializeWithQuery was configured with a query that should produce results');
        $this->assertContains('results-entry', $resultPage1, 'Results should be visible since showResultsOfInitialQuery was set to true');
    }

    /**
     * @test
     */
    public function removeOptionLinkWillBeShownWhenFacetWasSelected()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        $this->searchRequest->setArgument('filter', ['type:pages']);

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();
        $this->assertContains('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertContains('remove-facet-option', $resultPage1, 'No link to remove facet option found');
    }

    /**
     * @test
     */
    public function removeOptionLinkWillIsAlsoShownWhenAFacetIsNotInTheResponse()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        $this->searchRequest->setArgument('filter', ['type:my_jobs']);

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertContains('remove-facet-option', $resultPage1, 'No link to remove facet option found');
    }

    /**
     * @test
     */
    public function exceptionWillBeThrownWhenAWrongTemplateIsConfiguredForTheFacet()
    {
        // we expected that an exception will be thrown when a facet is rendered
        // where an unknown partialName is referenced
        $this->expectException(InvalidTemplateResourceException::class);
        $this->expectExceptionMessageRegExp('#(.*The partial files.*NotFound.*|.*The Fluid template files .*NotFound.*)#');

        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);


        $this->indexPages([1, 2]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        // fake that a backend user is logged in
        $GLOBALS['TSFE']->beUserLogin = true;

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertContains('document-score-analysis', $resultPage1, 'No score analysis in response');
    }

    /**
     * @test
     */
    public function canSortFacetsByLex()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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

        $this->assertContains('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertContains('pages</a> <span class="facet-result-count badge">', $resultPage1, 'Could not find facet option for pages');
    }

    /**
     * @test
     */
    public function canSortFacetsByOptionCountWhenNothingIsConfigured()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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

        $this->assertContains('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertContains('pages</a> <span class="facet-result-count badge">', $resultPage1, 'Could not find facet option for pages');
    }

    /**
     * @test
     */
    public function canRenderQueryGroupFacet()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        // since we overwrite the configuration in the testcase from outside we want to avoid that it will be resetted
        $this->searchController->setResetConfigurationBeforeInitialize(false);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertContains('Small (1 &amp; 2)', $resultPage1, 'Response did not contain expected small option of query facet');
        $this->assertContains('Medium (2 to 5)', $resultPage1, 'Response did not contain expected medium option of query facet');
        $this->assertContains('Large (5 to *)', $resultPage1, 'Response did not contain expected large option of query facet');
    }

    /**
     * @test
     */
    public function canDefineAManualSortOrder()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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

        $this->assertContains('fluidfacet', $resultPage1, 'Could not find fluidfacet class that indicates the facet was rendered with fluid');
        $this->assertContains('pages</a> <span class="facet-result-count badge">', $resultPage1, 'Could not find facet option for pages');
    }


    /**
     * @test
     */
    public function canSeeTheParsedQueryWhenABackendUserIsLoggedIn()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        // fake that a backend user is logged in
        $GLOBALS['TSFE']->beUserLogin = true;

        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertContains('Parsed Query:', $resultPage1, 'No parsed query in response');
    }

    /**
     * @test
     */
    public function frontendWillRenderErrorMessageForSolrNotAvailableAction()
    {
        $this->importDataSetFromFixture('can_render_error_message_when_solr_unavailable.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->searchRequest->setControllerActionName('solrNotAvailable');
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $resultPage1 = $this->searchResponse->getContent();

        $this->assertEquals('503 Service Unavailable', $this->searchResponse->getStatus());
        $this->assertContains("Search is currently not available.", $resultPage1, 'Response did not contain solr unavailable error message');
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
     * @dataProvider frontendWillForwardsToErrorActionWhenSolrEndpointIsNotAvailableDataProvider
     * @test
     */
    public function frontendWillForwardsToErrorActionWhenSolrEndpointIsNotAvailable($action, $getArguments)
    {
        $this->expectException(StopActionException::class);
        $this->expectExceptionMessage('forward');
        $this->expectExceptionCode(1476045801);

        $this->importDataSetFromFixture('can_render_error_message_when_solr_unavailable.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';

        $this->searchRequest->setArgument('resultsPerPage', 10);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);

        $resultPage = $this->searchResponse->getContent();
        $this->assertContains("Displaying results 1 to 8 of 8", $resultPage, '');
        $this->assertContainerByIdContains('<option selected="selected" value="10">10</option>', $resultPage, 'results-per-page');
    }


    /**
     * @test
     */
    public function canShowLastSearchesFromDatabaseInResponse()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $contentObjectRendererMock = $this->getMockBuilder(ContentObjectRenderer::class)->disableOriginalConstructor()->getMock();
        $flexFormData = $this->getFixtureContentByName('fakedFlexFormData.xml');
        $contentObjectRendererMock->data = ['pi_flexform' => $flexFormData];
        $this->searchController->setContentObjectRenderer($contentObjectRendererMock);

        //not in the content but we expect to get shoes suggested
        $_GET['q'] = '*';
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);

        $this->assertContains('Displaying results 1 to 4 of 4', $this->searchResponse->getContent());
    }

    /**
     * @test
     */
    public function canRenderDateRangeFacet()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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
        $this->assertContains('facet-type-dateRange', $this->searchResponse->getContent());
    }

    /**
     * @test
     */
    public function canRenderASecondFacetOnTheTypeField()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

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
        $this->assertContains('id="facetmyType"', $this->searchResponse->getContent());
        $this->assertContains('id="facettype"', $this->searchResponse->getContent());

    }

    /**
     * @test
     */
    public function formActionIsRenderingTheForm()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $formRequest = $this->getPreparedRequest('form');
        $formResponse = $this->getPreparedResponse();
        $this->searchController->processRequest($formRequest, $formResponse);

        $formContent = $formResponse->getContent();
        $this->assertContains('<div class="tx-solr-search-form">', $formContent);
    }

    /**
     * @test
     */
    public function searchingAndRenderingFrequentSearchesIsShowingTheTermAsFrequentSearch()
    {
        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1]);

        $searchRequest = $this->getPreparedRequest('frequentlySearched', 'pi_frequentlySearched');
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
        $request = $this->getPreparedRequest('detail');
        $request->setArgument('documentId', '23c51a0d5cf548afecc043a7068902e8f82a22a0/pages/1/0/0/0');

        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->indexPages([1, 2]);
        $this->searchController->processRequest($request, $this->searchResponse);
        $this->assertContains("Products", $this->searchResponse->getContent());
    }

    /**
     * @test
     */
    public function canRenderSearchFormOnly()
    {
        $request = $this->getPreparedRequest('form', 'pi_search');

        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);

        $this->searchController->processRequest($request, $this->searchResponse);
        $this->assertContains('id="tx-solr-search-form-pi-results"', $this->searchResponse->getContent());
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
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);
        $this->searchRequest->setArgument('resultsPerPage', 5);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $result = $this->searchResponse->getContent();

        $this->assertContains('Custom Integration Test Search Templatepath', $result, 'Can not overwrite template path');
        $this->assertContains('Custom Integration Test Pagination Templatepath', $result, 'Can not overwrite template path');
    }

    /**
     * @test
     */
    public function canPassCustomSettingsToView()
    {
        GeneralUtility::_GETset('q', '*');
        $this->importDataSetFromFixture('can_render_search_customTemplate.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);
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

        $this->assertContains('mytestsetting', $result, 'Can not output passed test setting');
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
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE([], 1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);
        $this->searchRequest->setArgument('resultsPerPage', 5);
        $this->searchController->processRequest($this->searchRequest, $this->searchResponse);
        $result = $this->searchResponse->getContent();

        $this->assertContains('Custom Integration Test Search Template entry Template', $result, 'Can not set entry template file name in typoscript');
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
        $this->assertContains($expectedToContain, $containerContent, 'Failed asserting that container with id ' . $id .' contains ' . $expectedToContain);
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
        $this->assertNotContains($expectedToContain, $containerContent, 'Failed asserting that container with id ' . $id .' not contains ' . $expectedToContain);
    }


    /**
     * Assertion to check if the pagination markup is present in the response.
     *
     * @param string $content
     */
    protected function assertPaginationVisible($content)
    {
        $this->assertContains('id="solr-pagination"', $content, 'No pagination container visible');
        $this->assertContains('ul class="pagination"', $content, 'Could not see pagination list');
    }

    /**
     * @param $importPageIds
     */
    protected function indexPages($importPageIds)
    {
        foreach ($importPageIds as $importPageId) {
            $fakeTSFE = $this->getConfiguredTSFE([], $importPageId);
            $GLOBALS['TSFE'] = $fakeTSFE;
            $fakeTSFE->newCObj();
            $fakeTSFE->preparePageContentGeneration();
            PageGenerator::renderContent();
            /** @var $pageIndexer \ApacheSolrForTypo3\Solr\Typo3PageIndexer */
            $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
            $pageIndexer->indexPage();
        }

        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;
        $this->waitToBeVisibleInSolr();
    }

    /**
     * @param string $actionName
     * @param string $plugin
     * @return Request
     */
    protected function getPreparedRequest($actionName = 'results', $plugin = 'pi_result')
    {
        /** @var Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setControllerName('Search');
        $request->setControllerActionName($actionName);
        $request->setControllerVendorName('ApacheSolrForTypo3');
        $request->setPluginName($plugin);
        $request->setFormat('html');
        $request->setControllerExtensionName('Solr');

        return $request;
    }


    /**
     * @return Response
     */
    protected function getPreparedResponse()
    {
        /** @var $response Response */
        $response = $this->objectManager->get(Response::class);

        return $response;
    }
}
