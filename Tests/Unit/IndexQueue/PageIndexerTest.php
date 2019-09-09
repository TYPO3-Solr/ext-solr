<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\AbstractUriStrategy;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Url\UrlHelper;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;

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

    public function setUp()
    {
        $this->pagesRepositoryMock = $this->getDumbMock(PagesRepository::class);
        $this->documentBuilderMock = $this->getDumbMock(Builder::class);
        $this->solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);
        $this->connectionManagerMock = $this->getDumbMock(ConnectionManager::class);
        $this->pageIndexerRequestMock = $this->getDumbMock(PageIndexerRequest::class);
        $this->uriStrategyMock = $this->getDumbMock(AbstractUriStrategy::class);
    }

    /**
     * @param array $options
     * @return PageIndexer
     */
    protected function getPageIndexerWithMockedDependencies($options = [])
    {
        $pageIndexer = $this->getMockBuilder(PageIndexer::class)
            ->setConstructorArgs(
                [
                    $options,
                    $this->pagesRepositoryMock,
                    $this->documentBuilderMock,
                    $this->solrLogManagerMock,
                    $this->connectionManagerMock
                ])
            ->setMethods(['getPageIndexerRequest', 'getAccessRootlineByPageId', 'getUriStrategy'])
            ->getMock();
        $pageIndexer->expects($this->any())->method('getPageIndexerRequest')->willReturn($this->pageIndexerRequestMock);
        $pageIndexer->expects($this->any())->method('getUriStrategy')->willReturn($this->uriStrategyMock);
        return $pageIndexer;
    }

    /**
     * @test
     */
    public function testIndexPageItemIsSendingFrontendRequestsToExpectedUrls()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects($this->once())->method('getAllSolrConnectionConfigurations')->willReturn([
            ['rootPageUid' => 88, 'language' => 0]
        ]);

        $siteMock->expects($this->any())->method('getRootPageId')->willReturn(88);
        $siteMock->expects($this->any())->method('getRootPage')->willReturn(['l18n_cfg' => 0, 'title' => 'mysiteroot']);

        $testUri = 'http://myfrontendurl.de/index.php?id=4711&L=0';
        $this->uriStrategyMock->expects($this->any())->method('getPageIndexingUriFromPageItemAndLanguageId')->willReturn($testUri);

            /** @var $item Item */
        $item = $this->getDumbMock(Item::class);
        $item->expects($this->any())->method('getRecordUid')->willReturn(4711);
        $item->expects($this->any())->method('getSite')->willReturn($siteMock);


        $accessGroupResponse = $this->getDumbMock(PageIndexerResponse::class);
        $accessGroupResponse->expects($this->once())->method('getActionResult')->with('findUserGroups')->willReturn([0]);

        $indexResponse = $this->getDumbMock(PageIndexerResponse::class);
        $indexResponse->expects($this->once())->method('getActionResult')->with('indexPage')->willReturn(['pageIndexed' => 'Success']);

            // Two requests will be send, the first one for the access groups, the second one for the indexing itself
        $this->pageIndexerRequestMock->expects($this->exactly(2))->method('send')->with('http://myfrontendurl.de/index.php?id=4711&L=0')->will(
            $this->onConsecutiveCalls($accessGroupResponse, $indexResponse)
        );

        $pageIndexer = $this->getPageIndexerWithMockedDependencies([]);
        $pageRootLineMock = $this->getDumbMock(Rootline::class);
        $pageIndexer->expects($this->once())->method('getAccessRootlineByPageId')->willReturn($pageRootLineMock);

        $pageIndexer->index($item);
    }
}
