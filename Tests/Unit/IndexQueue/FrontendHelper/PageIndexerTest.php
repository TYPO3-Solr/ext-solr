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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\Domain\Variants\IdBuilder;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use ApacheSolrForTypo3\Solr\Typo3PageContentExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageInformation;

class PageIndexerTest extends SetUpUnitTestCase
{
    #[Test]
    #[DataProvider('canCreatePageDocumentDataProvider')]
    public function canCreatePageDocument(bool $vectorSearchEnabled): void
    {
        $subject = $this->getAccessibleMock(
            PageIndexer::class,
            ['getSolrConnection', 'generatePageUrl', 'substitutePageDocument', 'indexPage'],
        );

        $requestMock = new ServerRequest();
        $requestMock = $requestMock->withAttribute('language', $this->createMock(SiteLanguage::class));
        $requestMock = $requestMock->withAttribute('routing', $this->createMock(PageArguments::class));
        $pageInformationMock = $this->createMock(PageInformation::class);
        $pageInformationMock->method('getPageRecord')->willReturn([
            'uid' => 123,
            'pid' => 1,
            'crdate' => 1759928546,
            'SYS_LASTCHANGED' => 1759928546,
            'subtitle' => '',
            'nav_title' => '',
            'author' => '',
            'description' => '',
            'abstract' => '',
        ]);
        $requestMock = $requestMock->withAttribute('frontend.page.information', $pageInformationMock);

        // Page content as string (replaces TSFE->content)
        $pageContent = '<html><body>indexible page content</body></html>';

        $contentExtractorMock = $this->createMock(Typo3PageContentExtractor::class);
        $contentExtractorMock->method('getIndexableContent')->willReturn('indexible page content');
        $builderMock = $this->getAccessibleMock(
            Builder::class,
            ['getSiteByPageId', 'getPageDocumentId', 'getExtractorForPageContent'],
            [$this->createMock(IdBuilder::class), $this->createMock(ExtensionConfiguration::class)],
        );
        $builderMock->method('getExtractorForPageContent')->willReturn($contentExtractorMock);
        GeneralUtility::addInstance(Builder::class, $builderMock);

        $document = new Document();
        GeneralUtility::addInstance(Document::class, $document);

        $subject->expects(self::once())->method('substitutePageDocument')->willReturnArgument(0);
        $connectionMock = $this->createMock(SolrConnection::class);
        $subject->expects(self::once())->method('getSolrConnection')->willReturn($connectionMock);

        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('isVectorSearchEnabled')->willReturn($vectorSearchEnabled);
        $subject->_set('configuration', $configurationMock);

        $subject->_call('index', $this->createMock(Item::class), $requestMock, $pageContent);
        if ($vectorSearchEnabled) {
            self::assertNotEmpty($document['vectorContent']);
            self::assertEquals($document['vectorContent'], $document['content']);
        } else {
            self::assertArrayNotHasKey('vectorContent', $document->getFields());
        }

    }

    public static function canCreatePageDocumentDataProvider(): Traversable
    {
        yield 'vector search disabled' => [ false ];
        yield 'vector search enabled' => [ true ];
    }
}
