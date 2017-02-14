<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Repository;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Rafael Kähm <rafael.kaehm@dkd.de>
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\Repository\ApacheSolrDocumentRepository;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\SolrService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Test cases for ApacheSolrDocumentRepository
 */
class ApacheSolrDocumentRepositoryTest extends UnitTest
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
        $apacheSolrDocumentCollection = [new \Apache_Solr_Document(), new \Apache_Solr_Document()];
        $apacheSolrDocumentRepository = $this->getAccessibleMock(ApacheSolrDocumentRepository::class, ['findByPageIdAndByLanguageId']);
        $apacheSolrDocumentRepository->expects($this->at(0))->method('findByPageIdAndByLanguageId')->will($this->returnValue($apacheSolrDocumentCollection));

        /* @var $apacheSolrDocumentRepository ApacheSolrDocumentRepository */
        $this->assertSame($apacheSolrDocumentCollection[0], $apacheSolrDocumentRepository->findOneByPageIdAndByLanguageId(0, 0));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 1487332926
     */
    public function findByPageIdAndByLanguageIdThrowsInvalidArgumentExceptionIfPageIdIsNotSet()
    {
        /* @var $apacheSolrDocumentRepository ApacheSolrDocumentRepository */
        $apacheSolrDocumentRepository = GeneralUtility::makeInstance(ApacheSolrDocumentRepository::class);
        $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(null, 3);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 1487335178
     */
    public function findByPageIdAndByLanguageIdThrowsInvalidArgumentExceptionIfLanguageIdIsNotInteger()
    {
        /* @var $apacheSolrDocumentRepository ApacheSolrDocumentRepository */
        $apacheSolrDocumentRepository = GeneralUtility::makeInstance(ApacheSolrDocumentRepository::class);
        $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(1, 'Abc');
    }

    /**
     * @test
     */
    public function findByPageIdAndByLanguageIdReturnsEmptyCollectionIfConnectionToSolrServerCanNotBeEstablished()
    {
        /* @var $apacheSolrDocumentRepository ApacheSolrDocumentRepository */
        $apacheSolrDocumentRepository = $this->getAccessibleMock(ApacheSolrDocumentRepository::class, ['initializeSearch']);
        $apacheSolrDocumentRepository->expects($this->at(0))->method('initializeSearch')->will($this->throwException(new NoSolrConnectionFoundException()));

        $apacheSolrDocumentCollection = $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(777, 0);
        $this->assertEmpty($apacheSolrDocumentCollection);

    }

    /**
     * @test
     */
    public function findByPageIdAndByLanguageIdReturnsResultFromSearch()
    {

        $solrServiceMock = $this->createMock(SolrService::class, [], [], '', false);
        $solrConnectionManager = $this->getAccessibleMock(ConnectionManager::class, ['getConnectionByPageId'], [], '', false);
        $solrConnectionManager->expects($this->any())->method('getConnectionByPageId')->will($this->returnValue($solrServiceMock));
        $mockedSingletons = [ConnectionManager::class => $solrConnectionManager];

        $search = $this->getAccessibleMock(Search::class, ['search', 'getResultDocumentsEscaped'], [], '', false);
        $mockedSingletons[Search::class] = $search;
        GeneralUtility::resetSingletonInstances($mockedSingletons);

        $expectedApacheSolrDocumentCollection = [new \Apache_Solr_Document(), new \Apache_Solr_Document()];
        $search->expects($this->any())->method('search')->willReturn('Something what is not needed.');
        $search->expects($this->any())->method('getResultDocumentsEscaped')->willReturn($expectedApacheSolrDocumentCollection);

        /* @var $apacheSolrDocumentRepository ApacheSolrDocumentRepository */
        $apacheSolrDocumentRepository = $this->getAccessibleMock(ApacheSolrDocumentRepository::class, ['getQueryForPage']);
        $apacheSolrDocumentRepository->expects($this->any())->method('getQueryForPage')->willReturn(GeneralUtility::makeInstance(Query::class, ''));
        $actualApacheSolrDocumentCollection = $apacheSolrDocumentRepository->findByPageIdAndByLanguageId(777, 0);

        $this->assertSame($expectedApacheSolrDocumentCollection, $actualApacheSolrDocumentCollection);
    }

}
