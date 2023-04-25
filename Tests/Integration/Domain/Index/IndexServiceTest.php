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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Index;

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Environment\CliEnvironment;
use ApacheSolrForTypo3\Solr\System\Environment\WebRootAllReadyDefinedException;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;

/**
 * Testcase for the record indexer
 *
 * @author Timo Schmidt
 */
class IndexServiceTest extends IntegrationTest
{
    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/solr',
        '../vendor/apache-solr-for-typo3/solr/Tests/Integration/Fixtures/Extensions/fake_extension2',
    ];

    protected ?Queue $indexQueue = null;

    /**
     * @throws NoSuchCacheException
     * @throws TestingFrameworkCoreException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->writeDefaultSolrTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
    }

    /**
     * @throws DoctrineDBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function addToIndexQueue(string $table, int $uid): void
    {
        // write an index queue item
        $this->indexQueue->updateItem($table, $uid, time());
    }

    public function canResolveBaseAsPrefixDataProvider(): array
    {
        return [
            'absRefPrefixIsFoo' => [
                'absRefPrefix' => 'foo',
                'expectedUrl' => '/foo/en/?tx_ttnews%5Btt_news%5D=111&cHash=a14e458509b71459d1edaafd1d5a84a1',
            ],
        ];
    }

    /**
     * @dataProvider canResolveBaseAsPrefixDataProvider
     *
     * @param string $absRefPrefix
     * @param string $expectedUrl
     *
     *
     * @throws ConnectionException
     * @throws DoctrineDBALException
     * @throws TestingFrameworkCoreException
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws WebRootAllReadyDefinedException
     *
     * @test
     */
    public function canResolveBaseAsPrefix(string $absRefPrefix, string $expectedUrl)
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_withBasePrefix_' . $absRefPrefix . '.csv');

        $this->mergeSiteConfiguration('integration_tree_one', ['base' => '/' . $absRefPrefix . '/']);

        $this->addToIndexQueue('tx_fakeextension_domain_model_bar', 111);

        /* @var CliEnvironment $cliEnvironment */
        $cliEnvironment = GeneralUtility::makeInstance(CliEnvironment::class);
        $cliEnvironment->backup();
        $cliEnvironment->initialize(Environment::getPublicPath() . '/');

        /* @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        /* @var IndexService $indexService */
        $indexService = GeneralUtility::makeInstance(IndexService::class, $site);

        // run the indexer
        $indexService->indexItems(1);

        $cliEnvironment->restore();

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"url":"' . $expectedUrl, $solrContent, 'Generated unexpected url with absRefPrefix = auto');
        $this->cleanUpSolrServerAndAssertEmpty();
    }
}
