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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ApacheSolrDocument;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Repository;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use InvalidArgumentException;
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
        $apacheSolrDocumentRepository
            ->expects(self::exactly(1))
            ->method('findByPageIdAndByLanguageId')
            ->willReturn($apacheSolrDocumentCollection);

        /* @var $apacheSolrDocumentRepository Repository */
        self::assertSame($apacheSolrDocumentCollection[0], $apacheSolrDocumentRepository->findOneByPageIdAndByLanguageId(0, 0));
    }

    /**
     * @test
     */
    public function findByPageIdAndByLanguageIdThrowsInvalidArgumentExceptionIfPageIdIsNotSet()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1487332926);
        /* @var $apacheSolrDocumentRepository Repository */
        $apacheSolrDocumentRepository = GeneralUtility::makeInstance(Repository::class);
        $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(null, 3);
    }

    /**
     * @test
     */
    public function findByPageIdAndByLanguageIdThrowsInvalidArgumentExceptionIfLanguageIdIsNotInteger()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1487335178);
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
        $apacheSolrDocumentRepository
            ->expects(self::exactly(1))
            ->method('initializeSearch')
            ->will(self::throwException(new NoSolrConnectionFoundException()));

        $apacheSolrDocumentCollection = $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(777, 0);
        self::assertEmpty($apacheSolrDocumentCollection);
    }

    /**
     * @test
     */
    public function findByPageIdAndByLanguageIdReturnsResultFromSearch()
    {
        $solrConnectionMock = $this->getDumbMock(SolrConnection::class);
        $solrConnectionManager = $this->getAccessibleMock(ConnectionManager::class, ['getConnectionByPageId'], [], '', false);
        $solrConnectionManager->expects(self::any())->method('getConnectionByPageId')->willReturn($solrConnectionMock);
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
        $fakeResponse->expects(self::once())->method('getParsedData')->willReturn($parsedData);
        $search->expects(self::any())->method('search')->willReturn($fakeResponse);

        $queryBuilderMock = $this->getDumbMock(QueryBuilder::class);

        /* @var $apacheSolrDocumentRepository Repository */
        $apacheSolrDocumentRepository = $this->getAccessibleMock(Repository::class, ['getQueryForPage', 'getSearch'], [null, null, $queryBuilderMock]);
        $apacheSolrDocumentRepository->expects(self::once())->method('getSearch')->willReturn($search);
        $queryMock = $this->getDumbMock(Query::class);
        $queryBuilderMock->expects(self::any())->method('buildPageQuery')->willReturn($queryMock);
        $actualApacheSolrDocumentCollection = $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(777, 0);

        self::assertSame($testDocuments, $actualApacheSolrDocumentCollection);
    }
}
