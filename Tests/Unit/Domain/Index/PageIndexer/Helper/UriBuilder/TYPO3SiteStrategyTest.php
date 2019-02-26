<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index;

use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\TYPO3SiteStrategy;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SolrSiteStrategyTest
 * @package ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index
 */
class TYPO3SiteStrategyTest extends UnitTest
{

    /**
     * @test
     */
    public function testPageIndexingUriFromPageItemAndLanguageId()
    {
        $pageRecord = ['uid' => 55];
        $itemMock = $this->getDumbMock(Item::class);
        $itemMock->expects($this->any())->method('getRecord')->willReturn($pageRecord);
        $itemMock->expects($this->any())->method('getRecordUid')->willReturn(55);

            /** @var $loggerMock SolrLogManager */
        $loggerMock = $this->getDumbMock(SolrLogManager::class);

        $uriMock = $this->getDumbMock(UriInterface::class);
        $uriMock->expects($this->any())->method('__toString')->willReturn('http://www.site.de/en/test');

        $routerMock = $this->getDumbMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('generateUri')->with($pageRecord, ['_language' => 2, 'MP' => 'foo'])
            ->willReturn($uriMock);

        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects($this->once())->method('getRouter')->willReturn($routerMock);

        /** @var SiteFinder $siteFinderMock */
        $siteFinderMock = $this->getDumbMock(SiteFinder::class);
        $siteFinderMock->expects($this->once())->method('getSiteByPageId')->willReturn($siteMock);


        $typo3SiteStrategy = GeneralUtility::makeInstance(TYPO3SiteStrategy::class, $loggerMock, $siteFinderMock);
        $typo3SiteStrategy->getPageIndexingUriFromPageItemAndLanguageId($itemMock, 2, 'foo');
    }
}