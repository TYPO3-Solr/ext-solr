<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Markus Friedrich <markus.friedrich@dkd.de>
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

use ApacheSolrForTypo3\Solr\ViewHelper\SortUrl;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * PHP Unit test for sort url view helper (ApacheSolrForTypo3\Solr\ViewHelper\SortUrl)
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class SortUrlTest extends AbstractViewHelperTest
{
    /**
     * Sort url view helper
     *
     * @var SortUrl
     */
    protected $viewHelper;

    /**
     * Set up the view helper test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        // set sorting configuration
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['sorting'] = 1;
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['sorting.'] = array(
            'defaultOrder' => 'asc',
            'options.' => array(
                'title.' => array(
                    'field' => 'sortTitle',
                    'label' => 'Title'
                ),
                'type.' => array(
                    'field' => 'type',
                    'label' => 'Title'
                ),
                'author.' => array(
                    'field' => 'sortAuthor',
                    'label' => 'Author',
                    'fixedOrder' => 'desc'
                )
            )
        );

        // prepare solr request handler
        $solrRequestHandler = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search', $this->getDumbMock('ApacheSolrForTypo3\Solr\SolrService'));
        $this->inject($solrRequestHandler, 'query', GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'testQuery'));

        // prepare link builder mock
        $linkBuilderMock = $this->getDumbMock('ApacheSolrForTypo3\Solr\Query\LinkBuilder');
        $linkBuilderMock->expects($this->any())->method('getQueryUrl')->willReturnArgument(0);

        // init view helper
        $this->viewHelper = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ViewHelper\\SortUrl');
        $this->inject($this->viewHelper, 'queryLinkBuilder', $linkBuilderMock);
    }

    /**
     * Tear down
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        GeneralUtility::purgeInstances();
    }

    /**
     * Provides data for the sort urltests
     *
     * @return array
     */
    public function sortUrlCreationDataProvider()
    {
        return array(
            array('currentSorting' => 'title asc', 'sorting' => 'title', 'expectedResult' => 'title desc'),
            array('currentSorting' => 'title desc', 'sorting' => 'title', 'expectedResult' => 'title asc'),
            array('currentSorting' => '', 'sorting' => 'title', 'expectedResult' => 'title asc'),
            array('currentSorting' => 'type asc', 'sorting' => 'title', 'expectedResult' => 'title asc'),
            array('currentSorting' => 'type asc', 'sorting' => 'author', 'expectedResult' => 'author desc'),
            array('currentSorting' => 'author desc', 'sorting' => 'author', 'expectedResult' => 'author desc'),
            array('currentSorting' => '', 'sorting' => 'author', 'expectedResult' => 'author desc'),
            array('currentSorting' => 'title', 'sorting' => 'author,type', 'expectedResult' => 'author desc, type asc'),
            array('currentSorting' => 'type asc', 'sorting' => 'title,author,type', 'expectedResult' => 'title asc, author desc, type desc')
        );
    }

    /**
     * Tests the sort url creation
     *
     * @dataProvider sortUrlCreationDataProvider
     * @test
     *
     * @param string $currentSorting the current sort parameters
     * @param string $sorting the requested sorting
     * @param string $expectedResult
     * @return void
     */
    public function canCreateSortUrls($currentSorting, $sorting, $expectedResult)
    {
        GeneralUtility::_GETset(array('sort' => $currentSorting), 'tx_solr');
        $sortUrl = $this->viewHelper->execute(array($sorting));
        $this->assertEquals($expectedResult, $sortUrl['sort'], 'Sort url parameter "' . $sortUrl['sort'] . '"  doesn\'t match the expected parameters: ' . $expectedResult);
    }
}
