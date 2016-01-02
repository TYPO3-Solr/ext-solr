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
     * @test
     */
    public function canIndexPageIntoSolr()
    {
        $this->importDataSetFromFixture('can_index_into_solr.xml');

        $GLOBALS['TT'] = $this->getMock('\\TYPO3\\CMS\\Core\\TimeTracker\\TimeTracker', array(), array(), '', false);

        $TSFE = $this->getConfiguredTSFE();
        $TSFE->config['config']['index_enable'] = 1;
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

        // we wait to make sure the document will be available in solr
        sleep(3);

        $solrContent = file_get_contents('http://localhost:8080/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello solr"', $solrContent, 'Could not index document into solr');

        // cleanup the solr server
        /** @var  $connectionManager \ApacheSolrForTypo3\Solr\ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\ConnectionManager');
        $solrServices = $connectionManager->getAllConnections();
        foreach ($solrServices as $solrService) {
            $solrService->deleteByQuery('*:*');
            $solrService->commit();
        }

        // we wait to make sure the document will be deleted in solr
        sleep(3);

        $solrContent = file_get_contents('http://localhost:8080/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":0', $solrContent, 'Could not index document into solr');
    }

    /**
     * @return \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
     */
    protected function getConfiguredTSFE($TYPO3_CONF_VARS = array(), $id = 1, $type = 0)
    {
        /** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController',
            $TYPO3_CONF_VARS, $id, $type);

        \TYPO3\CMS\Frontend\Utility\EidUtility::initLanguage();
        $TSFE->initFEuser();
        $TSFE->set_no_cache();
        $TSFE->checkAlternativeIdMethods();
        $TSFE->determineId();
        $TSFE->initTemplate();
        $TSFE->getConfigArray();
        \TYPO3\CMS\Core\Core\Bootstrap::getInstance();
        $TSFE->settingLanguage();
        $TSFE->settingLocale();

        return $TSFE;
    }
}
