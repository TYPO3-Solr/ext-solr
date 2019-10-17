<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Access;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;

/**
 * Class RootlineTest
 */
class RootlineTest extends IntegrationTest {

    /**
     * @test
     */
    public function canGetAccessRootlineByPageId()
    {
        $this->importDataSetFromFixture('user_protected_page.xml');
        $accessRootline = Rootline::getAccessRootlineByPageId(10);
        $this->assertSame('10:4711', (string)$accessRootline, 'Did not determine expected access rootline for fe_group protected page');

        $accessRootline = Rootline::getAccessRootlineByPageId(1);
        $this->assertSame('', (string)$accessRootline, 'Access rootline for non protected page should be empty');
    }
}