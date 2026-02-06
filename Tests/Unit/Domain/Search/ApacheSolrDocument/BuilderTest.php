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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ApacheSolrDocument;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Variants\IdBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use ApacheSolrForTypo3\Solr\Typo3PageContentExtractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Testcase for the Builder of ApacheSolrDocument
 */
class BuilderTest extends SetUpUnitTestCase
{
    public const FAKE_PAGE_RECORD = [
        'pid' => 4710,
        'crdate' => 1635537721,
        'SYS_LASTCHANGED' => 1635537721,
        'endtime' => null,
        'subtitle' => 'fake page test',
        'nav_title' => null,
        'author' => null,
        'description' => null,
        'abstract' => null,
    ];

    protected IdBuilder|MockObject $variantIdBuilderMock;
    protected Site|MockObject $siteMock;
    protected Typo3PageContentExtractor|MockObject $typo3PageExtractorMock;
    protected Builder|MockObject $documentBuilder;
    protected MockObject|ExtensionConfiguration $extensionConfigurationMock;

    protected function setUp(): void
    {
        $this->variantIdBuilderMock = $this->createMock(IdBuilder::class);
        $this->siteMock = $this->createMock(Site::class);
        $this->typo3PageExtractorMock = $this->createMock(Typo3PageContentExtractor::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        $this->documentBuilder = $this->getMockBuilder(Builder::class)
            ->setConstructorArgs([
                $this->variantIdBuilderMock,
                $this->extensionConfigurationMock,
            ])->onlyMethods([
                'getExtractorForPageContent',
                'getSiteByPageId',
                'getPageDocumentId',
                'getDocumentId',
            ])->getMock();

        $this->documentBuilder->expects(self::any())->method('getExtractorForPageContent')->willReturn($this->typo3PageExtractorMock);
        $this->documentBuilder->expects(self::any())->method('getSiteByPageId')->willReturn($this->siteMock);
        parent::setUp();
    }

    #[Test]
    public function canBuildApacheSolrDocumentFromEmptyPage(): void
    {
        $fakeRootLine = $this->createMock(Rootline::class);
        $fakeRootLine->expects(self::once())->method('getGroups')->willReturn([1]);

        $this->fakePageDocumentId('siteHash/pages/4711');
        $this->fakeTagContent([]);

        $pageInformation = new PageInformation();
        $pageInformation->setId(4711);
        $pageInformation->setPageRecord(self::FAKE_PAGE_RECORD);
        $pageArguments = new PageArguments(4711, '0', []);
        $siteLanguage = new SiteLanguage(1, 'en_US', new Uri('http://www.typo3-solr.com'), []);
        $pageContent = '<html><body>Test content</body></html>';
        $document = $this->documentBuilder->fromPage($pageInformation, $pageArguments, $siteLanguage, $pageContent, 'http://www.typo3-solr.com', $fakeRootLine);

        self::assertInstanceOf(Document::class, $document, 'Expect to get an ' . Document::class . ' back');
        self::assertSame('siteHash/pages/4711', $document['id'], 'Builder did not use documentId from mock');
    }

    #[Test]
    public function canSetKeywordsForApacheSolrDocument(): void
    {
        $fakeRootLine = $this->createMock(Rootline::class);
        $fakeRootLine->expects(self::once())->method('getGroups')->willReturn([1]);

        $this->fakePageDocumentId('siteHash/pages/4711');
        $this->fakeTagContent([]);

        $pageInformation = new PageInformation();
        $pageInformation->setId(4711);
        $pageInformation->setPageRecord(array_merge(self::FAKE_PAGE_RECORD, ['keywords' => 'foo,bar']));
        $pageArguments = new PageArguments(4711, '0', []);
        $siteLanguage = new SiteLanguage(1, 'en_US', new Uri('http://www.typo3-solr.com'), []);
        $pageContent = '<html><body>Test content</body></html>';
        $document = $this->documentBuilder->fromPage($pageInformation, $pageArguments, $siteLanguage, $pageContent, 'http://www.typo3-solr.com', $fakeRootLine);

        self::assertSame($document['keywords'], ['foo', 'bar'], 'Could not set keywords from page document');
    }

    #[Test]
    public function canSetEndtimeForApacheSolrDocument(): void
    {
        $fakeRootLine = $this->createMock(Rootline::class);
        $fakeRootLine->expects(self::once())->method('getGroups')->willReturn([1]);

        $this->fakePageDocumentId('siteHash/pages/4711');
        $this->fakeTagContent([]);

        $pageInformation = new PageInformation();
        $pageInformation->setId(4711);
        $pageInformation->setPageRecord(array_merge(self::FAKE_PAGE_RECORD, ['endtime' => 1234]));
        $pageArguments = new PageArguments(4711, '0', []);
        $siteLanguage = new SiteLanguage(1, 'en_US', new Uri('http://www.typo3-solr.com'), []);
        $pageContent = '<html><body>Test content</body></html>';
        $document = $this->documentBuilder->fromPage($pageInformation, $pageArguments, $siteLanguage, $pageContent, 'http://www.typo3-solr.com', $fakeRootLine);

        self::assertSame($document['endtime'], 1234, 'Could not set endtime from page document');
    }

    #[Test]
    public function canSetTagFieldsForApacheSolrDocument(): void
    {
        $fakeRootLine = $this->createMock(Rootline::class);
        $fakeRootLine->expects(self::once())->method('getGroups')->willReturn([1]);

        $this->fakePageDocumentId('siteHash/pages/4711');
        $this->fakeTagContent(['tagsH1' => 'Fake H1 content']);

        $pageInformation = new PageInformation();
        $pageInformation->setId(4711);
        $pageInformation->setPageRecord(self::FAKE_PAGE_RECORD);
        $pageArguments = new PageArguments(4711, '0', []);
        $siteLanguage = new SiteLanguage(1, 'en_US', new Uri('http://www.typo3-solr.com'), []);
        $pageContent = '<html><body><h1>Fake H1 content</h1></body></html>';
        $document = $this->documentBuilder->fromPage($pageInformation, $pageArguments, $siteLanguage, $pageContent, 'http://www.typo3-solr.com', $fakeRootLine);

        self::assertSame($document['tagsH1'], 'Fake H1 content', 'Could not assign extracted h1 heading to solr document');
    }

    #[Test]
    public function canBuildFromRecord(): void
    {
        $fakeRecord = ['uid' => 4711, 'pid' => 88, 'type' => 'news'];
        $type = 'news';
        $this->fakeDocumentId('testSiteHash/news/4711');

        $this->siteMock->expects(self::any())->method('getRootPageId')->willReturn(99);
        $this->siteMock->expects(self::any())->method('getDomain')->willReturn('test.typo3.org');
        $this->siteMock->expects(self::any())->method('getSiteHash')->willReturn('testSiteHash');
        $this->variantIdBuilderMock->expects(self::once())->method('buildFromTypeAndUid')->with($type, 4711, $fakeRecord, $this->siteMock)->willReturn('testVariantId');

        $document = $this->documentBuilder->fromRecord($fakeRecord, $type, 99, 'r:0');

        self::assertSame(4711, $document->uid, 'Uid field was not set as expected');
        self::assertSame(88, $document->pid, 'Pid field was not set as expected');
        self::assertSame('test.typo3.org', $document->site, 'Site field was not set as expected');
        self::assertSame('testSiteHash', $document->siteHash, 'SiteHash field was not set as expected');
        self::assertSame('testVariantId', $document->variantId, 'VariantId field was not set as expected');
        self::assertSame('r:0', $document->access, 'Access field was not set as expected');
        self::assertSame('testSiteHash/news/4711', $document->id, 'Id field was not set as expected');
        self::assertSame('news', $document->type, 'Type field was not set as expected');
        self::assertSame('EXT:solr', $document->appKey, 'appKey field was not set as expected');
    }

    protected function fakePageDocumentId(string $documentId): void
    {
        $this->documentBuilder->expects(self::once())->method('getPageDocumentId')->willReturn($documentId);
    }

    protected function fakeDocumentId(string $documentId): void
    {
        $this->documentBuilder->expects(self::once())->method('getDocumentId')->willReturn($documentId);
    }

    protected function fakeTagContent($tagContent = []): void
    {
        $this->typo3PageExtractorMock->expects(self::once())->method('getTagContent')->willReturn($tagContent);
    }
}
