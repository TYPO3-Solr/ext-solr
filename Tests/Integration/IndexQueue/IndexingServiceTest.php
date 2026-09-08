<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class IndexingServiceTest extends IntegrationTestBase
{
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    // Set pages cache database backend, testing-framework sets this to NullBackend by default.
                    'pages' => [
                        'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                    ],
                    'hash' => [
                        'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                    ],
                    'rootline' => [
                        'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                    ],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeDefaultSolrTestSiteConfiguration();
    }

    #[Test]
    public function indexingSimpleCacheablePageStillReachesSolr(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexingSimpleCacheablePageStillReachesSolr.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');

        // trigger page cache
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://testone.site/'))->withPageId(4));
        $body = (string)$response->getBody();
        self::assertStringContainsString('Subsubpage in site 1', $body);
        $dbConnection = $this->getConnectionPool()->getConnectionForTable('cache_pages');
        $cacheIdentifier = $dbConnection->executeQuery('SELECT identifier FROM cache_pages WHERE identifier LIKE "4_%";')->fetchOne();
        $cache = $this->get(CacheManager::class)->getCache('pages')->get($cacheIdentifier);
        self::assertStringContainsString('Subsubpage in site 1', $cache['content']);

        $this->indexQueuedItems(1);

        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString(
            '"numFound":1',
            $solrContent,
            'Page was not indexed - likely FrontendTypoScript::getSetupArray() '
            . 'throwing during content extraction after the internal find-user-groups '
            . 'sub-request already warmed the page cache.',
        );
        self::assertStringContainsString(
            'Subsubpage in site 1',
            $solrContent,
            'Wrong Page was indexed - likely paratests use wrong Apache Solr Server core.',
        );
    }
}
