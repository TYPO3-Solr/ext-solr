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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\AbstractUriStrategy;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockObject;

class PageIndexerTest extends UnitTest
{
    /**
     * @var PageIndexer
     */
    protected $pageIndexer;

    /**
     * @var PagesRepository
     */
    protected $pagesRepositoryMock;

    /**
     * @var Builder
     */
    protected $documentBuilderMock;

    /**
     * @var SolrLogManager
     */
    protected $solrLogManagerMock;

    /**
     * @var ConnectionManager
     */
    protected $connectionManagerMock;

    /**
     * @var PageIndexerRequest
     */
    protected $pageIndexerRequestMock;

    /**
     * @var AbstractUriStrategy
     */
    protected $uriStrategyMock;

    /**
     * @var MockObject|FrontendEnvironment
     */
    protected $frontendEnvironmentMock;

    protected function setUp(): void
    {
        $this->pagesRepositoryMock = $this->getDumbMock(PagesRepository::class);
        $this->documentBuilderMock = $this->getDumbMock(Builder::class);
        $this->solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);
        $this->connectionManagerMock = $this->getDumbMock(ConnectionManager::class);
        $this->pageIndexerRequestMock = $this->getDumbMock(PageIndexerRequest::class);
        $this->uriStrategyMock = $this->getDumbMock(AbstractUriStrategy::class);
        $this->frontendEnvironmentMock = $this->getDumbMock(FrontendEnvironment::class);
        parent::setUp();
    }

    /**
     * @param array $options
     * @return PageIndexer|MockObject
     */
    protected function getPageIndexerWithMockedDependencies(array $options = [])
    {
        $pageIndexer = $this->getMockBuilder(PageIndexer::class)
            ->setConstructorArgs(
                [
                    $options,
                    $this->pagesRepositoryMock,
                    $this->documentBuilderMock,
                    $this->solrLogManagerMock,
                    $this->connectionManagerMock,
                    $this->frontendEnvironmentMock,
                ]
            )
            ->onlyMethods(['getPageIndexerRequest', 'getAccessRootlineByPageId', 'getUriStrategy'])
            ->getMock();
        $pageIndexer->expects(self::any())->method('getPageIndexerRequest')->willReturn($this->pageIndexerRequestMock);
        $pageIndexer->expects(self::any())->method('getUriStrategy')->willReturn($this->uriStrategyMock);
        return $pageIndexer;
    }

    /**
     * @test
     */
    public function testIndexPageItemIsSendingFrontendRequestsToExpectedUrls()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects(self::once())->method('getAllSolrConnectionConfigurations')->willReturn([
            ['rootPageUid' => 88, 'language' => 0],
        ]);

        $siteMock->expects(self::any())->method('getRootPageId')->willReturn(88);
        $siteMock->expects(self::any())->method('getRootPage')->willReturn(['l18n_cfg' => 0, 'title' => 'mysiteroot']);

        $testUri = 'http://myfrontendurl.de/index.php?id=4711&L=0';
        $this->uriStrategyMock->expects(self::any())->method('getPageIndexingUriFromPageItemAndLanguageId')->willReturn($testUri);

        /* @var Item|MockObject $item */
        $item = $this->getDumbMock(Item::class);
        $item->expects(self::any())->method('getRootPageUid')->willReturn(88);
        $item->expects(self::any())->method('getRecordUid')->willReturn(4711);
        $item->expects(self::any())->method('getSite')->willReturn($siteMock);
        $item->expects(self::any())->method('getIndexingConfigurationName')->willReturn('pages');

        $accessGroupResponse = $this->getDumbMock(PageIndexerResponse::class);
        $accessGroupResponse->expects(self::once())->method('getActionResult')->with('findUserGroups')->willReturn([0]);

        $indexResponse = $this->getDumbMock(PageIndexerResponse::class);
        $indexResponse->expects(self::once())->method('getActionResult')->with('indexPage')->willReturn(['pageIndexed' => 'Success']);

        // Two requests will be send, the first one for the access groups, the second one for the indexing itself
        $this->pageIndexerRequestMock->expects(self::exactly(2))->method('send')->with('http://myfrontendurl.de/index.php?id=4711&L=0')->will(
            self::onConsecutiveCalls($accessGroupResponse, $indexResponse)
        );

        $pageIndexer = $this->getPageIndexerWithMockedDependencies([]);
        $pageRootLineMock = $this->getDumbMock(Rootline::class);
        $pageIndexer->expects(self::once())->method('getAccessRootlineByPageId')->willReturn($pageRootLineMock);

        $pageIndexer->index($item);
    }
}
