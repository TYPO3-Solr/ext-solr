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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class LastSearchesServiceTest extends SetUpUnitTestCase
{
    protected LastSearchesService|MockObject $lastSearchesService;
    protected FrontendUserSession|MockObject $sessionMock;
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected LastSearchesRepository|MockObject $lastSearchesRepositoryMock;

    protected function setUp(): void
    {
        $this->sessionMock = $this->createMock(FrontendUserSession::class);
        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);

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
    public function canGetLastSearchesFromSessionInUserMode(): void
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
    public function canGetLastSearchesFromDatabaseInGlobalMode(): void
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

    protected function fakeLastSearchMode(string $mode): void
    {
        $this->configurationMock->expects(self::once())->method('getSearchLastSearchesMode')->willReturn($mode);
    }

    protected function fakeLastSearchLimit(int $limit): void
    {
        $this->configurationMock->expects(self::once())->method('getSearchLastSearchesLimit')->willReturn($limit);
    }

    protected function assertRepositoryWillNeverBeCalled(): void
    {
        $this->lastSearchesRepositoryMock->expects(self::never())->method('findAllKeywords');
    }
}
