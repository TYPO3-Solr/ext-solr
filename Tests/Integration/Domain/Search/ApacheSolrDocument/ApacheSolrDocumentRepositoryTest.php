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
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ApacheSolrDocumentRepositoryTest extends IntegrationTest
{
    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    /**
     * @var Repository
     */
    protected $apacheSolrDocumentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeDefaultSolrTestSiteConfiguration();
        $_SERVER['HTTP_HOST'] = 'testone.site';
        $_SERVER['REQUEST_URI'] = '/search.html';
        // trigger a search
        $this->indexPageIdsFromFixture('can_get_apacheSolrDocuments.xml', [1, 2, 3, 4, 5]);

        $this->waitToBeVisibleInSolr();

        /* @var $apacheSolrDocumentRepository Repository */
        $this->apacheSolrDocumentRepository = GeneralUtility::makeInstance(Repository::class);
    }

    /**
     * Executed after each test. Emptys solr and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * @test
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
     */
    public function canReturnEmptyCollectionIfNoConnectionToSolrServerIsEstablished()
    {
        $apacheSolrDocumentsCollection = $this->apacheSolrDocumentRepository->findByPageIdAndByLanguageId(3, 777);
        self::assertEmpty($apacheSolrDocumentsCollection, 'ApacheSolrDocumentRepository does not return empty collection if no connection to core can be established.');
    }
}
