<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt
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

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class LastSearchesServiceTest extends UnitTest
{
    /**
     * @var LastSearchesService
     */
    protected $lastSearchesService;

    /**
     * @var TypoScriptFrontendController
     */
    protected $tsfeMock;

    /**
     * @var DatabaseConnection
     */
    protected $databaseMock;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->tsfeMock = $this->getDumbMock('TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController');
        $this->databaseMock = $this->getDumbMock('TYPO3\CMS\Core\Database\DatabaseConnection');
        $this->configurationMock = $this->getDumbMock('\ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration');

        $this->lastSearchesService = $this->getMock('ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService', array('getLastSearchesFromFrontendSession'), array(
            $this->configurationMock,
            $this->tsfeMock,
            $this->databaseMock
        ));
    }

    /**
     * @test
     */
    public function canGetLastSearchesFromSessionInUserMode()
    {
        $fakedLastSearchesInSession = array('first search', 'second search');

        $this->lastSearchesService->expects($this->once())->method('getLastSearchesFromFrontendSession')->will($this->returnValue(
            $fakedLastSearchesInSession
        ));

        $this->assertDatabaseWillNeverBeQueried();
        $this->fakeLastSearchMode('user');
        $this->fakeLastSearchLimit(10);

        $lastSearches = $this->lastSearchesService->getLastSearches();
        $this->assertSame($fakedLastSearchesInSession, array_reverse($lastSearches), 'Did not get last searches from session in user mode');
    }

    /**
     * @test
     */
    public function canGetLastSearchesFromDatabaseInGlobalMode()
    {
        $fakedLastSearchesInDatabase = array(
            array('keywords' => 'test'),
            array('keywords' => 'test 2')
        );

        $this->fakeLastSearchMode('global');
        $this->fakeLastSearchLimit(10);
        $this->assertSessionWillNeverBeQueried();

        $this->databaseMock->expects($this->once())->method('exec_SELECTgetRows')->will($this->returnValue($fakedLastSearchesInDatabase));

        $lastSearches = $this->lastSearchesService->getLastSearches();

        $this->assertSame(array('test', 'test 2'), $lastSearches, 'Did not get last searches from database');
    }

    /**
     * @param string $mode
     */
    protected function fakeLastSearchMode($mode)
    {
        $this->configurationMock->expects($this->once())->method('getSearchLastSearchesMode')->will($this->returnValue($mode));
    }

    /**
     * @param integer $limit
     */
    protected function fakeLastSearchLimit($limit)
    {
        $this->configurationMock->expects($this->once())->method('getSearchLastSearchesLimit')->will($this->returnValue($limit));
    }

    /**
     * @return void
     */
    protected function assertDatabaseWillNeverBeQueried()
    {
        $this->databaseMock->expects($this->never())->method('exec_SELECTgetRows');
    }

    /**
     * @return void
     */
    protected function assertSessionWillNeverBeQueried()
    {
        $this->lastSearchesService->expects($this->never())->method('getLastSearchesFromFrontendSession');
    }
}
