<?php

declare(strict_types=1);

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Access;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class RootlineTest
 */
final class RootlineTest extends IntegrationTestBase
{
    #[Test]
    public function canGetAccessRootlineByPageId(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/user_protected_page.csv');
        $accessRootline = Rootline::getAccessRootlineByPageId(10);
        self::assertSame('10:4711', (string)$accessRootline, 'Did not determine expected access rootline for fe_group protected page');

        $accessRootline = Rootline::getAccessRootlineByPageId(1);
        self::assertSame('', (string)$accessRootline, 'Access rootline for non protected page should be empty');
    }

    #[Test]
    public function canGetAccessRootlineByPageIdForDeletedPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/deleted_page.csv');

        // getPage() returns an empty array for a deleted page, this must not
        // trigger a PHP warning for the missing "fe_group" array key.
        $accessRootline = Rootline::getAccessRootlineByPageId(10);
        self::assertSame('', (string)$accessRootline, 'Access rootline for a deleted page should be empty');
    }
}
