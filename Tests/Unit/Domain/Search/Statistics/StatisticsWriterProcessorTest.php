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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Statistics;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsWriterProcessor;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Unit test case for the StatisticsWriterProcessor.
 */
class StatisticsWriterProcessorTest extends SetUpUnitTestCase
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

        $this->siteRepositoryMock = $this->createMock(SiteRepository::class);
        $this->processor = $this->getMockBuilder(StatisticsWriterProcessor::class)->setConstructorArgs([$this->statisticsRepositoryMock, $this->siteRepositoryMock])->onlyMethods(['getTime', 'getUserIp'])->getMock();
        $this->typoScriptConfigurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->searchRequestMock = $this->createMock(SearchRequest::class);
        $this->queryMock = $this->createMock(Query::class);
        parent::setUp();
    }

    #[Test]
    public function canWriteExpectedStatisticsData(): void
    {
        $serverRequest = (new ServerRequest('https://typo3-solr.com/', 'GET'))
            ->withAttribute('frontend.user', $this->createMock(FrontendUserAuthentication::class))
            ->withAttribute('routing', new PageArguments(888, '0', []))
            ->withAttribute('language', new SiteLanguage(0, 'en-US', new Uri('https://typo3-solr.com/'), []));
        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        $fakeTime = 100;
        $fakeIP = '192.168.2.22';

        $fakeSite = $this->createMock(Site::class);
        $fakeSite->expects(self::once())->method('getRootPageId')->willReturn(4711);
        $this->siteRepositoryMock->expects(self::once())->method('getSiteByPageId')->with(888)->willReturn($fakeSite);

        $this->processor->expects(self::once())->method('getUserIp')->willReturn($fakeIP);
        $this->processor->expects(self::once())->method('getTime')->willReturn($fakeTime);
        $this->typoScriptConfigurationMock->expects(self::once())->method('getStatisticsAnonymizeIP')->willReturn(0);
        $this->searchRequestMock->expects(self::once())->method('getContextTypoScriptConfiguration')->willReturn($this->typoScriptConfigurationMock);

        $this->queryMock->expects(self::once())->method('getQuery')->willReturn('my search');

        $resultSetMock = $this->createMock(SearchResultSet::class);
        $resultSetMock->expects(self::once())->method('getUsedQuery')->willReturn($this->queryMock);
        $resultSetMock->expects(self::once())->method('getUsedSearchRequest')->willReturn($this->searchRequestMock);

        $this->statisticsRepositoryMock->expects(self::any())->method('saveStatisticsRecord')->willReturnCallback(function($statisticData) {
            $this->assertSame('my search', $statisticData['keywords'], 'Unexpected keywords given');
            $this->assertSame('192.168.2.22', $statisticData['ip'], 'Unexpected ip given');
            $this->assertSame(4711, $statisticData['root_pid'], 'Unexpected root pid given');
        });

        $this->processor->process($resultSetMock);
    }
}
