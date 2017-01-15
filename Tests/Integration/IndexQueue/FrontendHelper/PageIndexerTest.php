<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Testcase to check if we can index page documents using the PageIndexer
 *
 * @author Timo Schmidt
 */
class PageIndexerTest extends IntegrationTest
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
    public function canIndexPageIntoSolr()
    {
        $this->importDataSetFromFixture('can_index_into_solr.xml');

        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');
        $this->assertContains('"custom_stringS":"my text"', $solrContent, 'Document does not contains value build with typoscript');
    }

    /**
     * @test
     */
    public function canIndexPageWithCustomPageTypeIntoSolr()
    {
        $this->importDataSetFromFixture('can_index_custom_pagetype_into_solr.xml');

        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"custom_stringS":"my text from custom page type"', $solrContent, 'Document does not contains value build with typoscript');
    }

    /**
     * @test
     */
    public function canIndexPageIntoSolrWithAdditionalFields()
    {
        //@todo additional fields indexer requires the hook to be activated which is normally done in ext_localconf.php
            // this needs to be unified with the PageFieldMappingIndexer registration.
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer'] = AdditionalFieldsIndexer::class;

        $this->importDataSetFromFixture('can_index_with_additional_fields_into_solr.xml');
        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');

            // field values from index.queue.pages.fields.
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');

            // field values from index.additionalFields
        $this->assertContains('"additional_sortSubTitle_stringS":"subtitle"', $solrContent, 'Document does not contains value from index.additionFields');
        $this->assertContains('"additional_custom_stringS":"my text"', $solrContent, 'Document does not contains value from index.additionFields');
    }

    /**
     * @test
     */
    public function canExecutePostProcessor()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPagePostProcessPageDocument']['TestPostProcessor'] = TestPostProcessor::class;

        $this->importDataSetFromFixture('can_index_into_solr.xml');
        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"postProcessorField_stringS":"postprocessed"', $solrContent, 'Field from post processor was not added');
    }

    /**
     * @test
     */
    public function canExecuteAdditionalPageIndexer()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments']['TestAdditionalPageIndexer'] = TestAdditionalPageIndexer::class;

        $this->importDataSetFromFixture('can_index_into_solr.xml');
        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":2', $solrContent, 'Could not index document into solr');
        $this->assertContains('"custom_stringS":"my text"', $solrContent, 'Field from post processor was not added');
        $this->assertContains('"custom_stringS":"additional text"', $solrContent, 'Field from post processor was not added');
    }

    /**
     * @return void
     */
    protected function executePageIndexer()
    {
        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();

        $TSFE = $this->getConfiguredTSFE();
        $TSFE->config['config']['index_enable'] = 1;
        $TSFE->cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $GLOBALS['TSFE'] = $TSFE;

        /** @var $request \ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest */
        $request = GeneralUtility::makeInstance(PageIndexerRequest::class);
        $request->setParameter('item', 4711);
        /** @var $request \ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse */
        $response = GeneralUtility::makeInstance(PageIndexerResponse::class);

        /** @var $pageIndexer  \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer */
        $pageIndexer = GeneralUtility::makeInstance(PageIndexer::class);
        $pageIndexer->activate();
        $pageIndexer->processRequest($request, $response);
        $pageIndexer->hook_indexContent($TSFE);
    }
}
