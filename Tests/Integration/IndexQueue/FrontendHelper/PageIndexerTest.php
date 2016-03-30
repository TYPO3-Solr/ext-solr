<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

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

use ApacheSolrForTypo3\Solr\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;

/**
 * Testcase to check if we can index page documents using the PageIndexer
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
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
        sleep(3);

        $solrContent = file_get_contents('http://localhost:8080/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');
        $this->assertContains('"custom_stringS":"my text"', $solrContent, 'Document does not contains value build with typoscript');
    }

    /**
     * @test
     */
    public function canIndexPageIntoSolrWithAdditionalFields()
    {
        //@todo additional fields indexer requires the hook to be activated which is normally done in ext_localconf.php
            // this needs to be unified with the PageFieldMappingIndexer registration.
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer'] = 'ApacheSolrForTypo3\\Solr\\AdditionalFieldsIndexer';

        $this->importDataSetFromFixture('can_index_with_additional_fields_into_solr.xml');
        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        sleep(3);

        $solrContent = file_get_contents('http://localhost:8080/solr/core_en/select?q=*:*');

            // field values from index.queue.pages.fields.
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');

            // field values from index.additionalFields
        $this->assertContains('"additional_sortSubTitle_stringS":"subtitle"', $solrContent, 'Document does not contains value from index.additionFields');
        $this->assertContains('"additional_custom_stringS":"my text"', $solrContent, 'Document does not contains value from index.additionFields');
    }

    /**
     * @return void
     */
    protected function executePageIndexer()
    {
        $GLOBALS['TT'] = $this->getMock('\\TYPO3\\CMS\\Core\\TimeTracker\\TimeTracker', array(), array(), '', false);

        $TSFE = $this->getConfiguredTSFE();
        $TSFE->config['config']['index_enable'] = 1;
        $TSFE->cObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
        $GLOBALS['TSFE'] = $TSFE;


        /** @var $request \ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest */
        $request = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest');
        $request->setParameter('item', 4711);
        /** @var $request \ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse */
        $response = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse');

        /** @var $pageIndexer  \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer */
        $pageIndexer = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer');
        $pageIndexer->activate();
        $pageIndexer->processRequest($request, $response);
        $pageIndexer->hook_indexContent($TSFE);
    }
}
