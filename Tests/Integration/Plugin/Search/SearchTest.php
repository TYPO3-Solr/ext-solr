<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Plugin\FrequentSearches;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Tests\Integration\Plugin\AbstractPluginTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageGenerator;

/**
 * Integration testcase to test the search plugin.
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
class SearchTest extends AbstractPluginTest
{

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
    public function canRenderSearchForm()
    {
        // trigger a search
        $searchForm = $this->importTestDataSetAndGetInitializedPlugin(array(1), 'can_render_search_plugin.xml', 'search');
        $searchFormOutput = $searchForm->main('', array());
        $this->assertContains('<form id="tx-solr-search-form-pi-search"', $searchFormOutput, 'Can not find searchform in plugin output');
    }

    /**
     * @test
     */
    public function canKeepAdditionalFiltersInSearchForm()
    {

        // trigger a search
        $searchForm = $this->importTestDataSetAndGetInitializedPlugin(array(1), 'can_render_search_plugin.xml', 'search');

        $overwriteConfiguration = array();
        $overwriteConfiguration['search.']['query.']['filter.']['subtitle:men'] = 'subTitle:men';

        /** @var $configurationManager \ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager');
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);

        $searchFormOutput = $searchForm->main('', array());

        $this->assertContains('<form id="tx-solr-search-form-pi-search"', $searchFormOutput, 'Can not find searchform in plugin output');
        $this->assertContains('eID=tx_solr_suggest&id=1&filters=%7B%22subtitle%3Amen%22%3A%22subTitle%3Amen%22%7D', $searchFormOutput, 'Can not find filters in eID');
    }
}
