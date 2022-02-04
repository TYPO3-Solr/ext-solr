<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Statistics;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsWriterProcessor;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Unit test case for the StatisticsWriterProcessor.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class StatisticsWriterProcessorTest extends UnitTest
{
    /**
     * @var StatisticsRepository|MockObject
     */
    protected $statisticsRepositoryMock;

    /**
     * @var SiteRepository|MockObject
     */
    protected $siteRepositoryMock;

    /**
     * @var StatisticsWriterProcessor|MockObject
     */
    protected $processor;

    /**
     * @var TypoScriptConfiguration|MockObject
     */
    protected $typoScriptConfigurationMock;

    /**
     * @var SearchRequest|MockObject
     */
    protected $searchRequestMock;

    /**
     * @var Query|MockObject
     */
    protected $queryMock;

    protected function setUp(): void
    {
        $this->statisticsRepositoryMock = $this->getMockBuilder(StatisticsRepository::class)->onlyMethods(['saveStatisticsRecord'])->getMock();

        $this->siteRepositoryMock = $this->getDumbMock(SiteRepository::class);
        $this->processor = $this->getMockBuilder(StatisticsWriterProcessor::class)->setConstructorArgs([$this->statisticsRepositoryMock, $this->siteRepositoryMock])->onlyMethods(['getTSFE', 'getTime', 'getUserIp'])->getMock();
        $this->typoScriptConfigurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->searchRequestMock = $this->getDumbMock(SearchRequest::class);
        $this->queryMock = $this->getDumbMock(Query::class);
        parent::setUp();
    }

    /**
     * @test
     */
    public function canWriteExpectedStatisticsData()
    {
        /* @var TypoScriptFrontendController $fakeTSFE */
        $fakeTSFE = $this->getDumbMock(TypoScriptFrontendController::class);
        $fakeTSFE->id = 888;
        $fakeTime = 100;
        $fakeIP = '192.168.2.22';

        $fakeSite = $this->getDumbMock(Site::class);
        $fakeSite->expects(self::once())->method('getRootPageId')->willReturn(4711);
        $this->siteRepositoryMock->expects(self::once())->method('getSiteByPageId')->with(888)->willReturn($fakeSite);

        $this->processor->expects(self::once())->method('getTSFE')->willReturn($fakeTSFE);
        $this->processor->expects(self::once())->method('getUserIp')->willReturn($fakeIP);
        $this->processor->expects(self::once())->method('getTime')->willReturn($fakeTime);
        $this->typoScriptConfigurationMock->expects(self::once())->method('getStatisticsAnonymizeIP')->willReturn(0);
        $this->searchRequestMock->expects(self::once())->method('getContextTypoScriptConfiguration')->willReturn($this->typoScriptConfigurationMock);

        $this->queryMock->expects(self::once())->method('getQuery')->willReturn('my search');

        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $resultSetMock->expects(self::once())->method('getUsedQuery')->willReturn($this->queryMock);
        $resultSetMock->expects(self::once())->method('getUsedSearchRequest')->willReturn($this->searchRequestMock);

        $self = $this;
        $this->statisticsRepositoryMock->expects(self::any())->method('saveStatisticsRecord')->willReturnCallback(function ($statisticData) use ($self) {
            $this->assertSame('my search', $statisticData['keywords'], 'Unexpected keywords given');
            $this->assertSame('192.168.2.22', $statisticData['ip'], 'Unexpected ip given');
            $this->assertSame(4711, $statisticData['root_pid'], 'Unexpected root pid given');
        });

        $this->processor->process($resultSetMock);
    }
}
