<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ApacheSolrDocument;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\Domain\Variants\IdBuilder;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Typo3PageContentExtractor;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Testcase for the Builder of ApacheSolrDocument
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class BuilderTest extends UnitTest
{
    /**
     * @var IdBuilder
     */
    protected $variantIdBuilderMock;

    /**
     * @var Site
     */
    protected $siteMock;

    /**
     * @var Typo3PageContentExtractor
     */
    protected $typo3PageExtractorMock;

    /**
     * @var Builder
     */
    protected $documentBuilder;

    public function setUp()
    {
        /** @var $variantIdBuilderMock */
        $this->variantIdBuilderMock = $this->getDumbMock(IdBuilder::class);
        $this->siteMock = $this->getDumbMock(Site::class);
        $this->typo3PageExtractorMock = $this->getDumbMock(Typo3PageContentExtractor::class);

        /** @var $documentBuilder Builder */
        $this->documentBuilder = $this->getMockBuilder(Builder::class)->setConstructorArgs([$this->variantIdBuilderMock ])->setMethods(
            ['getExtractorForPageContent', 'getSiteByPageId', 'getPageDocumentId']
        )->getMock();

        $this->documentBuilder->expects($this->any())->method('getExtractorForPageContent')->will($this->returnValue($this->typo3PageExtractorMock));
        $this->documentBuilder->expects($this->any())->method('getSiteByPageId')->will($this->returnValue($this->siteMock));
    }

    /**
     * @test
     */
    public function canBuildApacheSolrDocumentFromEmptyPage()
    {
        $fakePage = $this->getDumbMock(TypoScriptFrontendController::class);
        $fakeRootLine = $this->getDumbMock(Rootline::class);
        $fakeRootLine->expects($this->once())->method('getGroups')->will($this->returnValue([1]));

        $this->fakeDocumentId('siteHash/pages/4711');
        $this->fakeTagContent([]);

        $fakePage->page = [];
        $document = $this->documentBuilder->fromPage($fakePage, 'http://www.typo3-solr.com', $fakeRootLine, '');
        $idField = $document->getField('id');

        $this->assertInstanceOf(\Apache_Solr_Document::class, $document, 'Expect to get an Apache_Solr_Document back');
        $this->assertSame('siteHash/pages/4711', $idField['value'], 'Builder did not use documentId from mock');
    }

    /**
     * @test
     */
    public function canSetKeywordsForApacheSolrDocument()
    {
        $fakePage = $this->getDumbMock(TypoScriptFrontendController::class);
        $fakeRootLine = $this->getDumbMock(Rootline::class);
        $fakeRootLine->expects($this->once())->method('getGroups')->will($this->returnValue([1]));

        $this->fakeDocumentId('siteHash/pages/4711');
        $this->fakeTagContent([]);

        $fakePage->page = ['keywords' => 'foo,bar'];
        $document = $this->documentBuilder->fromPage($fakePage, 'http://www.typo3-solr.com', $fakeRootLine, '');
        $keywords = $document->getField('keywords');

        $this->assertSame($keywords['value'], ['foo', 'bar'], 'Could not set keywords from page document');
    }

    /**
     * @test
     */
    public function canSetEndtimeForApacheSolrDocument()
    {
        $fakePage = $this->getDumbMock(TypoScriptFrontendController::class);
        $fakeRootLine = $this->getDumbMock(Rootline::class);
        $fakeRootLine->expects($this->once())->method('getGroups')->will($this->returnValue([1]));

        $this->fakeDocumentId('siteHash/pages/4711');
        $this->fakeTagContent([]);

        $fakePage->page = ['endtime' => 1234];
        $document = $this->documentBuilder->fromPage($fakePage, 'http://www.typo3-solr.com', $fakeRootLine, '');
        $endtime = $document->getField('endtime');

        $this->assertSame($endtime['value'], 1234, 'Could not set endtime from page document');
    }

    /**
     * @test
     */
    public function canSetTagFieldsForApacheSolrDocument()
    {
        $fakePage = $this->getDumbMock(TypoScriptFrontendController::class);
        $fakeRootLine = $this->getDumbMock(Rootline::class);
        $fakeRootLine->expects($this->once())->method('getGroups')->will($this->returnValue([1]));

        $this->fakeDocumentId('siteHash/pages/4711');
        $this->fakeTagContent(['tagsH1' => 'Fake H1 content']);

        $fakePage->page = [];
        $document = $this->documentBuilder->fromPage($fakePage, 'http://www.typo3-solr.com', $fakeRootLine, '');
        $tagsH1 = $document->getField('tagsH1');

        $this->assertSame($tagsH1['value'], 'Fake H1 content', 'Could not assign extracted h1 heading to solr document');
    }

    /**
     * @param string $documentId
     */
    protected function fakeDocumentId($documentId)
    {
        $this->documentBuilder->expects($this->once())->method('getPageDocumentId')->will($this->returnValue($documentId));
    }

    /**
     * @param array $tagContent
     */
    protected function fakeTagContent($tagContent = [])
    {
        $this->typo3PageExtractorMock->expects($this->once())->method('getTagContent')->will($this->returnValue($tagContent));
    }
}
