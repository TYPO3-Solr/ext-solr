<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index;

use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\SolrSiteStrategy;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SolrSiteStrategyTest
 * @package ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index
 */
class SolrSiteStrategyTest extends UnitTest
{

    /**
     * @test
     */
    public function testPageIndexingUriFromPageItemAndLanguageId()
    {
        $itemMock = $this->getMockedItemWithSite(55, 'www.site.de');

        /** @var SolrSiteStrategy $strategy */
        $strategy = GeneralUtility::makeInstance(SolrSiteStrategy::class);
        $uri = $strategy->getPageIndexingUriFromPageItemAndLanguageId($itemMock, 2, 'xx');
        $this->assertSame('http://www.site.de/index.php?id=55&MP=xx&L=2', $uri, 'Solr site strategy generated unexpected uri');
    }

    /**
     * @test
     */
    public function canOverrideHost()
    {
        $itemMock = $this->getMockedItemWithSite(55, 'www.site.de');
        /** @var SolrSiteStrategy $strategy */
        $strategy = GeneralUtility::makeInstance(SolrSiteStrategy::class);
        $uri = $strategy->getPageIndexingUriFromPageItemAndLanguageId($itemMock, 2, 'xx', ['frontendDataHelper.' => ['host' => 'www.secondsite.de']]);
        $this->assertSame('http://www.secondsite.de/index.php?id=55&MP=xx&L=2', $uri, 'Solr site strategy generated unexpected uri');
    }

    /**
     * @test
     */
    public function canOverrideScheme()
    {
        $itemMock = $this->getMockedItemWithSite(55, 'www.site.de');
        /** @var SolrSiteStrategy $strategy */
        $strategy = GeneralUtility::makeInstance(SolrSiteStrategy::class);
        $uri = $strategy->getPageIndexingUriFromPageItemAndLanguageId($itemMock, 2, 'xx', ['frontendDataHelper.' => ['scheme' => 'https']]);
        $this->assertSame('https://www.site.de/index.php?id=55&MP=xx&L=2', $uri, 'Solr site strategy generated unexpected uri');
    }

    /**
     * @param int $pageId
     * @param string $domain
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockedItemWithSite($pageId, $domain)
    {
        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects($this->once())->method('getDomain')->willReturn($domain);

        $itemMock = $this->getDumbMock(Item::class);
        $itemMock->expects($this->any())->method('getSite')->willReturn($siteMock);
        $itemMock->expects($this->any())->method('getRecordUid')->willReturn($pageId);

        return $itemMock;
    }

}