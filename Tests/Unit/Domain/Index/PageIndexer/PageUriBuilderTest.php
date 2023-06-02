<?php

declare(strict_types=1);

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\PageIndexer;

use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\PageUriBuilder;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageUriBuilderTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function testPageIndexingUriFromPageItemAndLanguageId(): void
    {
        $pageRecord = ['uid' => 55];
        $itemMock = $this->createMock(Item::class);
        $itemMock->expects(self::any())->method('getRecord')->willReturn($pageRecord);
        $itemMock->expects(self::any())->method('getRecordUid')->willReturn(55);
        $siteFinderMock = $this->getSiteFinderMock($pageRecord);

        $loggerMock = $this->createMock(SolrLogManager::class);

        $uriBuilder = GeneralUtility::makeInstance(PageUriBuilder::class, $loggerMock, $siteFinderMock, new NoopEventDispatcher());
        $uriBuilder->getPageIndexingUriFromPageItemAndLanguageId($itemMock, 2, 'foo');
    }

    /**
     * @test
     */
    public function canOverrideHost(): void
    {
        $pageRecord = ['uid' => 55];
        $itemMock = $this->createMock(Item::class);
        $itemMock->expects(self::any())->method('getRecord')->willReturn($pageRecord);
        $itemMock->expects(self::any())->method('getRecordUid')->willReturn(55);
        $siteFinderMock = $this->getSiteFinderMock($pageRecord);

        $loggerMock = $this->createMock(SolrLogManager::class);

        $uriBuilder = GeneralUtility::makeInstance(PageUriBuilder::class, $loggerMock, $siteFinderMock, new NoopEventDispatcher());
        $uri = $uriBuilder->getPageIndexingUriFromPageItemAndLanguageId($itemMock, 2, 'foo', ['frontendDataHelper.' => ['host' => 'www.secondsite.de']]);
        self::assertSame('http://www.secondsite.de/en/test', $uri, 'Solr site strategy generated unexpected uri');
    }

    /**
     * @test
     */
    public function canOverrideScheme(): void
    {
        $pageRecord = ['uid' => 55];
        $itemMock = $this->createMock(Item::class);
        $itemMock->expects(self::any())->method('getRecord')->willReturn($pageRecord);
        $itemMock->expects(self::any())->method('getRecordUid')->willReturn(55);
        $siteFinderMock = $this->getSiteFinderMock($pageRecord);

        $loggerMock = $this->createMock(SolrLogManager::class);

        $uriBuilder = GeneralUtility::makeInstance(PageUriBuilder::class, $loggerMock, $siteFinderMock, new NoopEventDispatcher());
        $uri = $uriBuilder->getPageIndexingUriFromPageItemAndLanguageId($itemMock, 2, 'foo', ['frontendDataHelper.' => ['scheme' => 'https']]);
        self::assertSame('https://www.site.de/en/test', $uri, 'Solr site strategy generated unexpected uri');
    }

    protected function getSiteFinderMock(array $pageRecord = []): SiteFinder
    {
        $uri = new Uri('http://www.site.de/en/test');

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects(self::once())->method('generateUri')->with($pageRecord, ['_language' => 2, 'MP' => 'foo'])
            ->willReturn($uri);

        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::once())->method('getRouter')->willReturn($routerMock);

        $siteFinderMock = $this->createMock(SiteFinder::class);
        $siteFinderMock->expects(self::once())->method('getSiteByPageId')->willReturn($siteMock);
        return $siteFinderMock;
    }
}
