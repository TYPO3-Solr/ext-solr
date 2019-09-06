<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ApacheSolrDocument;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Rafael Kähm <rafael.kaehm@dkd.de>
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Repository;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\DocumentEscapeService;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\SolrService;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Test cases for ApacheSolrDocumentRepository
 */
class RepositoryTest extends UnitTest
{

    /**
     * @var Search
     */
    protected $search;

    /**
     * @var ConnectionManager
     */
    protected $solrConnectionManager;

    /**
     * @var Site
     */
    protected $mockedAsSingletonSite;

    /**
     * @test
     */
    public function findOneByPageIdAndByLanguageIdReturnsFirstFoundDocument()
    {
        $apacheSolrDocumentCollection = [new Document(), new Document()];
        $apacheSolrDocumentRepository = $this->getAccessibleMock(Repository::class, ['findByPageIdAndByLanguageId']);
        $apacheSolrDocumentRepository->expects($this->at(0))->method('findByPageIdAndByLanguageId')->will($this->returnValue($apacheSolrDocumentCollection));

        /* @var $apacheSolrDocumentRepository Repository */
        $this->assertSame($apacheSolrDocumentCollection[0], $apacheSolrDocumentRepository->findOneByPageIdAndByLanguageId(0, 0));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 1487332926
     */
    public function findByPageIdAndByLanguageIdThrowsInvalidArgumentExceptionIfPageIdIsNotSet()
    {
        /* @var $apacheSolrDocumentRepository Repository */
        $apacheSolrDocumentRepository = GeneralUtility::makeInstance(Repository::class);
        $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(null, 3);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 1487335178
     */
    public function findByPageIdAndByLanguageIdThrowsInvalidArgumentExceptionIfLanguageIdIsNotInteger()
    {
        /* @var $apacheSolrDocumentRepository Repository */
        $apacheSolrDocumentRepository = GeneralUtility::makeInstance(Repository::class);
        $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(1, 'Abc');
    }

    /**
     * @test
     */
    public function findByPageIdAndByLanguageIdReturnsEmptyCollectionIfConnectionToSolrServerCanNotBeEstablished()
    {
        /* @var $apacheSolrDocumentRepository Repository */
        $apacheSolrDocumentRepository = $this->getAccessibleMock(Repository::class, ['initializeSearch']);
        $apacheSolrDocumentRepository->expects($this->at(0))->method('initializeSearch')->will($this->throwException(new NoSolrConnectionFoundException()));

        $apacheSolrDocumentCollection = $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(777, 0);
        $this->assertEmpty($apacheSolrDocumentCollection);

    }

    /**
     * @test
     */
    public function findByPageIdAndByLanguageIdReturnsResultFromSearch()
    {

        $solrServiceMock = $this->getDumbMock(SolrService::class);
        $solrConnectionManager = $this->getAccessibleMock(ConnectionManager::class, ['getConnectionByPageId'], [], '', false);
        $solrConnectionManager->expects($this->any())->method('getConnectionByPageId')->will($this->returnValue($solrServiceMock));
        $mockedSingletons = [ConnectionManager::class => $solrConnectionManager];

        $search = $this->getAccessibleMock(Search::class, ['search', 'getResultDocumentsEscaped'], [], '', false);

        GeneralUtility::resetSingletonInstances($mockedSingletons);

        $testDocuments = [new Document(), new Document()];

        $parsedData = new \stdClass();
        // @extensionScannerIgnoreLine
        $parsedData->response = new \stdClass();
        // @extensionScannerIgnoreLine
        $parsedData->response->docs = $testDocuments;
        $fakeResponse = $this->getDumbMock(ResponseAdapter::class);
        $fakeResponse->expects($this->once())->method('getParsedData')->will($this->returnValue($parsedData));
        $search->expects($this->any())->method('search')->willReturn($fakeResponse);

        $queryBuilderMock = $this->getDumbMock(QueryBuilder::class);

        /* @var $apacheSolrDocumentRepository Repository */
        $apacheSolrDocumentRepository = $this->getAccessibleMock(Repository::class, ['getQueryForPage', 'getSearch'],[null, null, $queryBuilderMock]);
        $apacheSolrDocumentRepository->expects($this->once())->method('getSearch')->willReturn($search);
        $queryMock = $this->getDumbMock(Query::class);
        $queryBuilderMock->expects($this->any())->method('buildPageQuery')->willReturn($queryMock);
        $actualApacheSolrDocumentCollection = $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(777, 0);

        $this->assertSame($testDocuments, $actualApacheSolrDocumentCollection);
    }

}
