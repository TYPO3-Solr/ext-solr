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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\LastSearches;

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesRepository;
use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Session\FrontendUserSession;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

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

    protected function setUp(): void
    {
        $this->sessionMock = $this->getDumbMock(FrontendUserSession::class);
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);

        $this->lastSearchesRepositoryMock = $this->getMockBuilder(LastSearchesRepository::class)
            ->onlyMethods(['getLastSearchesResultSet', 'findAllKeywords'])
            ->getMock();

        $this->lastSearchesService = $this->getMockBuilder(LastSearchesService::class)
            ->onlyMethods([])
            ->setConstructorArgs([
                $this->configurationMock,
                $this->sessionMock,
                $this->lastSearchesRepositoryMock,
                ])->getMock();
        parent::setUp();
    }

    /**
     * @test
     */
    public function canGetLastSearchesFromSessionInUserMode()
    {
        $fakedLastSearchesInSession = ['first search', 'second search'];

        $this->sessionMock->expects(self::once())->method('getLastSearches')->willReturn(
            $fakedLastSearchesInSession
        );

        $this->assertRepositoryWillNeverBeCalled();
        $this->fakeLastSearchMode('user');
        $this->fakeLastSearchLimit(10);

        $lastSearches = $this->lastSearchesService->getLastSearches();
        self::assertSame($fakedLastSearchesInSession, array_reverse($lastSearches), 'Did not get last searches from session in user mode');
    }

    /**
     * @test
     */
    public function canGetLastSearchesFromDatabaseInGlobalMode()
    {
        $fakedLastSearchesFromRepository = [
            'test',
            'test 2',
        ];

        $this->fakeLastSearchMode('global');
        $this->fakeLastSearchLimit(10);

        $this->lastSearchesRepositoryMock->method('findAllKeywords')->willReturn($fakedLastSearchesFromRepository);

        $lastSearches = $this->lastSearchesService->getLastSearches();

        self::assertSame($fakedLastSearchesFromRepository, $lastSearches, 'Did not get last searches from database');
    }

    /**
     * @param string $mode
     */
    protected function fakeLastSearchMode($mode)
    {
        $this->configurationMock->expects(self::once())->method('getSearchLastSearchesMode')->willReturn($mode);
    }

    /**
     * @param int $limit
     */
    protected function fakeLastSearchLimit($limit)
    {
        $this->configurationMock->expects(self::once())->method('getSearchLastSearchesLimit')->willReturn($limit);
    }

    protected function assertRepositoryWillNeverBeCalled()
    {
        $this->lastSearchesRepositoryMock->expects(self::never())->method('findAllKeywords');
    }
}
