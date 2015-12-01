<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Test class to perform a search on a real solr server
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
class SearchTest extends IntegrationTest
{
    /**
     * @test
     */
    public function canSearchForADocument()
    {
        $this->importDataSetFromFixture('can_search.xml');

        $GLOBALS['TT'] = $this->getMock('\\TYPO3\\CMS\\Core\\TimeTracker\\TimeTracker', array(), array(), '', false);
        $fakeTSFE = $this->getConfiguredTSFE();
        $GLOBALS['TSFE'] = $fakeTSFE;

        /** @var $pageIndexer \ApacheSolrForTypo3\Solr\Typo3PageIndexer */
        $pageIndexer = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\Typo3PageIndexer', $fakeTSFE);
        $pageIndexer->indexPage();

        sleep(3);

            /** @var $searchInstance \ApacheSolrForTypo3\Solr\Search */
        $searchInstance = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\Search');

            /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\Query');
        $query->useRawQueryString(true);
        $query->setQueryFieldsFromString('content^40.0, title^5.0, keywords^2.0, tagsH1^5.0, tagsH2H3^3.0, tagsH4H5H6^2.0, tagsInline^1.0');
        $query->setQueryString('hello');

        $searchResponse = $searchInstance->search($query);
        $rawResponse = $searchResponse->getRawResponse();
        $this->assertContains('"numFound":1', $rawResponse, 'Could not index document into solr');
        $this->assertContains('"title":"Hello Search Test"', $rawResponse, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }
}
