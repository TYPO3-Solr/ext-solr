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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\GarbageRemover;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover\PageStrategy;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\QueueInterface;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

/**
 * PageStrategy tests
 */
final class PageStrategyTest extends AbstractStrategyTestBase
{
    protected QueueInterface|MockObject $queueMock;
    protected ConnectionManager|MockObject $connectionManagerMock;
    protected SiteRepository|MockObject $siteRepositoryMock;
    // $subject is typed as AbstractStrategy by the base class, which hides the PageStrategy API
    protected PageStrategy&AccessibleObjectInterface $pageStrategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueMock = $this->createMock(QueueInterface::class);
        $this->connectionManagerMock = $this->createMock(ConnectionManager::class);
        $this->siteRepositoryMock = $this->createMock(SiteRepository::class);
        $this->subject = $this->pageStrategy = $this->getAccessibleMock(
            PageStrategy::class,
            null,
            [
                $this->queueMock,
                $this->connectionManagerMock,
                $this->siteRepositoryMock,
                $this->createMock(QueueItemRepository::class),
            ],
        );
    }

    #[Test]
    public function removeGarbageOfDefaultLanguageDeletesOnlyInDefaultLanguageConnection(): void
    {
        $site = $this->getSiteMock(1, 'siteHashOne');
        $this->queueMock->method('getItems')->with('pages', 42)->willReturn([$this->getItemMock($site)]);
        $this->siteRepositoryMock->expects(self::never())->method('getAvailableSites');
        $this->connectionManagerMock->method('getConnectionsBySite')->with($site)->willReturn([
            0 => $this->getConnectionMock('type:pages AND uid:42 AND siteHash:siteHashOne'),
            1 => $this->getConnectionMock(null),
            2 => $this->getConnectionMock(null),
        ]);

        $this->pageStrategy->removeGarbageOfDefaultLanguage(42);
    }

    #[Test]
    public function removeGarbageOfDefaultLanguageDeletesOncePerSiteOfQueueItems(): void
    {
        $siteOne = $this->getSiteMock(1, 'siteHashOne');
        $siteTwo = $this->getSiteMock(111, 'siteHashTwo');
        $this->queueMock->method('getItems')->willReturn([
            $this->getItemMock($siteOne),
            $this->getItemMock($siteOne),
            $this->getItemMock($siteTwo),
        ]);
        $this->siteRepositoryMock->expects(self::never())->method('getAvailableSites');
        $this->connectionManagerMock->method('getConnectionsBySite')->willReturnMap([
            [$siteOne, [0 => $this->getConnectionMock('type:pages AND uid:42 AND siteHash:siteHashOne'), 1 => $this->getConnectionMock(null)]],
            [$siteTwo, [0 => $this->getConnectionMock('type:pages AND uid:42 AND siteHash:siteHashTwo'), 1 => $this->getConnectionMock(null)]],
        ]);

        $this->pageStrategy->removeGarbageOfDefaultLanguage(42);
    }

    #[Test]
    public function removeGarbageOfDefaultLanguageRemovesQueueItemsWithoutSite(): void
    {
        $site = $this->getSiteMock(1, 'siteHashOne');
        $this->queueMock->method('getItems')->willReturn([$this->getItemWithoutSiteMock(7), $this->getItemMock($site)]);
        $this->siteRepositoryMock->expects(self::never())->method('getAvailableSites');
        $this->queueMock->expects(self::once())->method('deleteItem')->with('pages', 7);
        $this->connectionManagerMock->expects(self::once())->method('getConnectionsBySite')->with($site)->willReturn([
            0 => $this->getConnectionMock('type:pages AND uid:42 AND siteHash:siteHashOne'),
        ]);

        $this->pageStrategy->removeGarbageOfDefaultLanguage(42);
    }

    /**
     * Same as deleteIndexDocuments(): a record queued only for sites that are gone is not searched in all sites.
     */
    #[Test]
    public function removeGarbageOfDefaultLanguageDeletesNothingWhenNoSiteOfQueueItemsExists(): void
    {
        $this->queueMock->method('getItems')->willReturn([$this->getItemWithoutSiteMock(7)]);
        $this->queueMock->expects(self::once())->method('deleteItem')->with('pages', 7);
        $this->siteRepositoryMock->expects(self::never())->method('getAvailableSites');
        $this->connectionManagerMock->expects(self::never())->method('getConnectionsBySite');

        $this->pageStrategy->removeGarbageOfDefaultLanguage(42);
    }

    #[Test]
    public function deleteIndexDocumentsDeletesOncePerSiteOfQueueItems(): void
    {
        $site = $this->getSiteMock(1, 'siteHashOne');
        $this->queueMock->method('getItems')->willReturn([$this->getItemMock($site), $this->getItemMock($site)]);
        $this->connectionManagerMock->method('getConnectionsBySite')->willReturn([
            0 => $this->getConnectionMock('type:pages AND uid:42 AND siteHash:siteHashOne'),
            1 => $this->getConnectionMock('type:pages AND uid:42 AND siteHash:siteHashOne'),
        ]);

        $this->subject->_call('deleteIndexDocuments', 'pages', 42);
    }

    #[Test]
    public function deleteIndexDocumentsRemovesQueueItemsWithoutSite(): void
    {
        $site = $this->getSiteMock(1, 'siteHashOne');
        $this->queueMock->method('getItems')->willReturn([$this->getItemWithoutSiteMock(7), $this->getItemMock($site)]);
        $this->queueMock->expects(self::once())->method('deleteItem')->with('pages', 7);
        $this->connectionManagerMock->expects(self::once())->method('getConnectionsBySite')->with($site)->willReturn([
            0 => $this->getConnectionMock('type:pages AND uid:42 AND siteHash:siteHashOne'),
        ]);

        $this->subject->_call('deleteIndexDocuments', 'pages', 42);
    }

    #[Test]
    public function removeGarbageOfDefaultLanguageDeletesInAllSitesWhenRecordIsNotQueued(): void
    {
        $siteOne = $this->getSiteMock(1, 'siteHashOne');
        $siteTwo = $this->getSiteMock(111, 'siteHashTwo');
        $this->queueMock->method('getItems')->willReturn([]);
        $this->siteRepositoryMock->expects(self::once())->method('getAvailableSites')->willReturn([$siteOne, $siteTwo]);
        $this->connectionManagerMock->method('getConnectionsBySite')->willReturnMap([
            [$siteOne, [0 => $this->getConnectionMock('type:pages AND uid:42 AND siteHash:siteHashOne'), 1 => $this->getConnectionMock(null)]],
            [$siteTwo, [0 => $this->getConnectionMock('type:pages AND uid:42 AND siteHash:siteHashTwo'), 1 => $this->getConnectionMock(null)]],
        ]);

        $this->pageStrategy->removeGarbageOfDefaultLanguage(42);
    }

    #[Test]
    public function removeGarbageOfDefaultLanguageSkipsSitesWithoutDefaultLanguageConnection(): void
    {
        $site = $this->getSiteMock(1, 'siteHashOne');
        $this->queueMock->method('getItems')->willReturn([$this->getItemMock($site)]);
        $this->connectionManagerMock->method('getConnectionsBySite')->willReturn([
            1 => $this->getConnectionMock(null),
        ]);

        $this->pageStrategy->removeGarbageOfDefaultLanguage(42);
    }

    /**
     * Unlike deleteIndexDocuments(), a language greater than 0 must not fall back to all connections
     * when the site has no connection for it.
     */
    #[Test]
    public function deleteIndexDocumentsInLanguageDeletesOnlyInConnectionOfGivenLanguage(): void
    {
        $site = $this->getSiteMock(1, 'siteHashOne');
        $this->queueMock->method('getItems')->with('tx_fakeextension_foo', 123)->willReturn([$this->getItemMock($site)]);
        $this->connectionManagerMock->method('getConnectionsBySite')->willReturn([
            0 => $this->getConnectionMock(null),
            1 => $this->getConnectionMock('type:tx_fakeextension_foo AND uid:123 AND siteHash:siteHashOne'),
        ]);

        $this->subject->_call('deleteIndexDocumentsInLanguage', 'tx_fakeextension_foo', 123, 1);
    }

    #[Test]
    public function deleteIndexDocumentsInLanguageDeletesNothingWithoutConnectionOfGivenLanguage(): void
    {
        $site = $this->getSiteMock(1, 'siteHashOne');
        $this->queueMock->method('getItems')->willReturn([$this->getItemMock($site)]);
        $this->connectionManagerMock->method('getConnectionsBySite')->willReturn([
            0 => $this->getConnectionMock(null),
            1 => $this->getConnectionMock(null),
        ]);

        $this->subject->_call('deleteIndexDocumentsInLanguage', 'pages', 42, 2);
    }

    protected function getSiteMock(int $rootPageId, string $siteHash): Site|MockObject
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->method('getEnableCommits')->willReturn(true);

        $siteMock = $this->createMock(Site::class);
        $siteMock->method('getRootPageId')->willReturn($rootPageId);
        $siteMock->method('getSiteHash')->willReturn($siteHash);
        $siteMock->method('getSolrConfiguration')->willReturn($configurationMock);
        return $siteMock;
    }

    protected function getItemMock(Site $site): Item|MockObject
    {
        $itemMock = $this->createMock(Item::class);
        $itemMock->method('getSite')->willReturn($site);
        return $itemMock;
    }

    protected function getItemWithoutSiteMock(int $indexQueueUid): Item|MockObject
    {
        $itemMock = $this->createMock(Item::class);
        $itemMock->method('getSite')->willThrowException(new InvalidArgumentException('no site'));
        $itemMock->method('getType')->willReturn('pages');
        $itemMock->method('getIndexQueueUid')->willReturn($indexQueueUid);
        return $itemMock;
    }

    /**
     * Returns a connection that expects exactly one delete by the given query, or none if the query is null.
     */
    protected function getConnectionMock(?string $expectedDeleteQuery): SolrConnection|MockObject
    {
        $writeServiceMock = $this->createMock(SolrWriteService::class);
        $connectionMock = $this->createMock(SolrConnection::class);
        $connectionMock->method('getWriteService')->willReturn($writeServiceMock);

        if ($expectedDeleteQuery === null) {
            $writeServiceMock->expects(self::never())->method('deleteByQuery');
            return $connectionMock;
        }

        $writeServiceMock
            ->expects(self::once())
            ->method('deleteByQuery')
            ->with($expectedDeleteQuery)
            ->willReturn(new ResponseAdapter('', 200));
        return $connectionMock;
    }
}
