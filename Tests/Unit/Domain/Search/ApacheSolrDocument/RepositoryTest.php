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
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Test cases for ApacheSolrDocumentRepository
 */
class RepositoryTest extends SetUpUnitTestCase
{
    protected Search $search;
    protected ConnectionManager $solrConnectionManager;
    protected Site $mockedAsSingletonSite;

    #[Test]
    public function findOneByPageIdAndByLanguageIdReturnsFirstFoundDocument(): void
    {
        $apacheSolrDocumentCollection = [new Document(), new Document()];
        $apacheSolrDocumentRepository = $this->getAccessibleMock(
            Repository::class,
            ['findByPageIdAndByLanguageId'],
            [],
            '',
            false,
        );

        $apacheSolrDocumentRepository
            ->expects(self::exactly(1))
            ->method('findByPageIdAndByLanguageId')
            ->willReturn($apacheSolrDocumentCollection);

        /** @var Repository $apacheSolrDocumentRepository */
        self::assertSame($apacheSolrDocumentCollection[0], $apacheSolrDocumentRepository->findOneByPageIdAndByLanguageId(0, 0));
    }

    #[Test]
    public function findByPageIdAndByLanguageIdReturnsEmptyCollectionIfConnectionToSolrServerCanNotBeEstablished(): void
    {
        $apacheSolrDocumentRepository = $this->getAccessibleMock(
            Repository::class,
            ['initializeSearch'],
            [],
            '',
            false,
        );
        $apacheSolrDocumentRepository
            ->expects(self::exactly(1))
            ->method('initializeSearch')
            ->will(self::throwException(new NoSolrConnectionFoundException()));

        $apacheSolrDocumentCollection = $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(777, 0);
        self::assertEmpty($apacheSolrDocumentCollection);
    }

    #[Test]
    public function findByPageIdAndByLanguageIdReturnsResultFromSearch(): void
    {
        $solrConnectionMock = $this->createMock(SolrConnection::class);
        $solrConnectionManager = $this->getAccessibleMock(ConnectionManager::class, ['getConnectionByPageId'], [], '', false);
        $solrConnectionManager->expects(self::any())->method('getConnectionByPageId')->willReturn($solrConnectionMock);
        $mockedSingletons = [ConnectionManager::class => $solrConnectionManager];

        $search = $this->getAccessibleMock(Search::class, ['search'], [], '', false);

        GeneralUtility::resetSingletonInstances($mockedSingletons);

        $testDocuments = [new Document(), new Document()];

        $parsedData = new stdClass();
        // @extensionScannerIgnoreLine
        $parsedData->response = new stdClass();
        // @extensionScannerIgnoreLine
        $parsedData->response->docs = $testDocuments;
        $fakeResponse = $this->createMock(ResponseAdapter::class);
        $fakeResponse->expects(self::once())->method('getParsedData')->willReturn($parsedData);
        $search->expects(self::any())->method('search')->willReturn($fakeResponse);

        $queryBuilderMock = $this->createMock(QueryBuilder::class);

        $apacheSolrDocumentRepository = $this->getAccessibleMock(
            Repository::class,
            [
                'getSearch',
            ],
            [
                null,
                $this->createMock(TypoScriptConfiguration::class),
                $queryBuilderMock,
            ],
        );
        $apacheSolrDocumentRepository->expects(self::once())->method('getSearch')->willReturn($search);
        $queryMock = $this->createMock(Query::class);
        $queryBuilderMock->expects(self::any())->method('buildPageQuery')->willReturn($queryMock);
        $actualApacheSolrDocumentCollection = $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(777, 0);

        self::assertSame($testDocuments, $actualApacheSolrDocumentCollection);
    }
}
