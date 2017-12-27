<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\LastSearches;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt
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

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesRepository;
use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Session\FrontendUserSession;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class LastSearchesServiceTest extends UnitTest
{
    /**
     * @var LastSearchesService
     */
    protected $lastSearchesService;

    /**
     * @var FrontendUserSession
     */
    protected $sessionMock;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @var LastSearchesRepository
     */
    protected $lastSearchesRepositoryMock;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->sessionMock = $this->getDumbMock(FrontendUserSession::class);
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);

        $this->lastSearchesRepositoryMock = $this->getMockBuilder(LastSearchesRepository::class)
            ->setMethods(['getLastSearchesResultSet', 'findAllKeywords'])->getMock();

        $this->lastSearchesService = $this->getMockBuilder(LastSearchesService::class)
            ->setMethods(['getLastSearchesFromFrontendSession'])
            ->setConstructorArgs([  $this->configurationMock,
                $this->sessionMock,
                $this->lastSearchesRepositoryMock])->getMock();
    }

    /**
     * @test
     */
    public function canGetLastSearchesFromSessionInUserMode()
    {
        $fakedLastSearchesInSession = ['first search', 'second search'];

        $this->sessionMock->expects($this->once())->method('getLastSearches')->will($this->returnValue(
            $fakedLastSearchesInSession
        ));

        $this->assertRepositoryWillNeverBeCalled();
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
        $fakedLastSearchesFromRepository = [
            'test',
            'test 2'
        ];

        $this->fakeLastSearchMode('global');
        $this->fakeLastSearchLimit(10);
        $this->assertSessionWillNeverBeQueried();

        $this->lastSearchesRepositoryMock->method('findAllKeywords')->will($this->returnValue($fakedLastSearchesFromRepository));

        $lastSearches = $this->lastSearchesService->getLastSearches();

        $this->assertSame($fakedLastSearchesFromRepository, $lastSearches, 'Did not get last searches from database');
    }

    /**
     * @param string $mode
     */
    protected function fakeLastSearchMode($mode)
    {
        $this->configurationMock->expects($this->once())->method('getSearchLastSearchesMode')->will($this->returnValue($mode));
    }

    /**
     * @param int $limit
     */
    protected function fakeLastSearchLimit($limit)
    {
        $this->configurationMock->expects($this->once())->method('getSearchLastSearchesLimit')->will($this->returnValue($limit));
    }

    /**
     * @return void
     */
    protected function assertRepositoryWillNeverBeCalled()
    {
        $this->lastSearchesRepositoryMock->expects($this->never())->method('findAllKeywords');
    }

    /**
     * @return void
     */
    protected function assertSessionWillNeverBeQueried()
    {
        $this->lastSearchesService->expects($this->never())->method('getLastSearchesFromFrontendSession');
    }
}
