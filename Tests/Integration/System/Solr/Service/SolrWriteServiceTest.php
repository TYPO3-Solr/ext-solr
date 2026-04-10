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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Solr\Service;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Testcase to check if the solr write service is working as expected.
 */
class SolrWriteServiceTest extends IntegrationTestBase
{
    protected SolrWriteService $solrWriteService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->solrWriteService = $this->get(ConnectionManager::class)
            ->getConnectionByRootPageId(1)
            ->getWriteService();
    }

    #[Test]
    public function canWriteToSolr(): void
    {
        $this->assertSolrIsEmpty('core_en');

        $document = new Document();
        $document->setField('id', 'abcdefgh/pages/1/0/0/0');
        $document->setField('appKey', 'EXT:solr');
        $document->setField('type', 'pages');

        $this->solrWriteService->addDocuments([$document]);
        $this->solrWriteService->commit();
        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not add documents');

        $this->solrWriteService->deleteByType('pages', false);

        // commit changes manually
        // deleteByType() is performing a commit by default, but as deleteByType() is explicitly
        // not waiting for a new searcher, issues might occur during testing.
        $this->solrWriteService->commit();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"numFound":0', $solrContent, 'Could not delete document');
    }
}
