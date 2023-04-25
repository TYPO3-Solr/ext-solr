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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Search\ApacheSolrDocument;

use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Repository;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;

class ApacheSolrDocumentRepositoryTest extends IntegrationTest
{
    /**
     * @var Repository|null
     */
    protected ?Repository $apacheSolrDocumentRepository = null;

    /**
     * @throws AspectNotFoundException
     * @throws DBALDriverException
     * @throws DBALException
     * @throws InternalServerErrorException
     * @throws NoSuchCacheException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     * @throws TestingFrameworkCoreException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->writeDefaultSolrTestSiteConfiguration();
        $_SERVER['HTTP_HOST'] = 'testone.site';
        $_SERVER['REQUEST_URI'] = '/search.html';
        // trigger an index
        $this->importCSVDataSet(__DIR__ . '/../../../Controller/Fixtures/indexing_data.csv');
        $this->indexPageIds([1, 2, 3, 4, 5]);

        $this->waitToBeVisibleInSolr();

        /* @var Repository $apacheSolrDocumentRepository */
        $this->apacheSolrDocumentRepository = GeneralUtility::makeInstance(Repository::class);
    }

    /**
     * Executed after each test. Empties solr and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        unset($this->apacheSolrDocumentRepository);
        parent::tearDown();
    }

    /**
     * @test
     *
     * @throws DBALDriverException
     */
    public function canFindByPageIdAndByLanguageId()
    {
        $apacheSolrDocumentsCollection = $this->apacheSolrDocumentRepository->findByPageIdAndByLanguageId(3, 0);

        self::assertIsArray($apacheSolrDocumentsCollection, 'Repository did not get Document collection from pageId 3.');
        self::assertNotEmpty($apacheSolrDocumentsCollection, 'Repository did not get apache solr documents from pageId 3.');
        self::assertInstanceOf(Document::class, $apacheSolrDocumentsCollection[0], 'ApacheSolrDocumentRepository returned not an array of type Document.');
    }

    /**
     * @test
     *
     * @throws DBALDriverException
     */
    public function canReturnEmptyCollectionIfNoConnectionToSolrServerIsEstablished()
    {
        $apacheSolrDocumentsCollection = $this->apacheSolrDocumentRepository->findByPageIdAndByLanguageId(3, 777);
        self::assertEmpty($apacheSolrDocumentsCollection, 'ApacheSolrDocumentRepository does not return empty collection if no connection to core can be established.');
    }
}
