<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrReadService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Solarium\Client;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SearchTest extends SetUpUnitTestCase
{
    protected SolrConnection|MockObject $solrConnectionMock;
    protected SolrReadService|MockObject $solrReadServiceMock;
    protected Search $search;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->set(SiteFinder::class, $this->createMock(SiteFinder::class));
        GeneralUtility::setContainer($container);
        $this->solrReadServiceMock = $this->getAccessibleMock(
            SolrReadService::class,
            [
                'search',
            ],
            [
                $this->createMock(Client::class),
            ],
        );

        $this->solrConnectionMock = $this->createMock(SolrConnection::class);
        $this->solrConnectionMock->expects(self::any())->method('getReadService')->willReturn($this->solrReadServiceMock);
        $this->search = new Search($this->solrConnectionMock);
    }

    #[Test]
    public function canPassLimit(): void
    {
        $query = new SearchQuery();
        $limit = 99;
        $this->solrReadServiceMock->expects(self::once())->method('search')->willReturnCallback(
            function ($query) use ($limit) {
                $this->assertSame($limit, $query->getRows(), 'Unexpected limit was passed');
                return $this->createMock(ResponseAdapter::class);
            },
        );

        $this->search->search($query, 0, $limit);
    }

    #[Test]
    public function canKeepLimitWhenNullWasPassedAsLimit(): void
    {
        $query = new SearchQuery();
        $limit = 99;
        $query->setRows($limit);

        $this->solrReadServiceMock->expects(self::once())->method('search')->willReturnCallback(
            function ($query) use ($limit) {
                $this->assertSame($limit, $query->getRows(), 'Unexpected limit was passed');
                return $this->createMock(ResponseAdapter::class);
            },
        );

        $this->search->search($query, 0, null);
    }
}
