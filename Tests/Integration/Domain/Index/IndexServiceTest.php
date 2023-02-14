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
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Environment\CliEnvironment;
use ApacheSolrForTypo3\Solr\System\Environment\WebRootAllReadyDefinedException;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\DBAL\Schema\SchemaException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Schema\Exception\StatementException;
use TYPO3\CMS\Core\Database\Schema\Exception\UnexpectedSignalReturnValueTypeException;
use TYPO3\CMS\Core\Localization\LanguageService;
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

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @throws DBALException
     * @throws NoSuchCacheException
     * @throws TestingFrameworkCoreException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->writeDefaultSolrTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);

        /** @var $beUser  BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;

        /** @var $languageService  LanguageService */
        $languageService = GeneralUtility::makeInstance(LanguageService::class);

        $GLOBALS['LANG'] = $languageService;
    }

    /**
     * @param string $table
     * @param int $uid
     */
    protected function addToIndexQueue($table, $uid): void
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
     * @throws DoctrineDBALException
     * @throws SchemaException
     * @throws StatementException
     * @throws TestingFrameworkCoreException
     * @throws WebRootAllReadyDefinedException
     *
     * @throws UnexpectedSignalReturnValueTypeException
     * @test
     */
    public function canResolveBaseAsPrefix($absRefPrefix, $expectedUrl)
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePathByName('fake_extension2_directrelated_tca.php'));

        $this->importDataSetFromFixture('can_index_custom_record_withBasePrefix_' . $absRefPrefix . '.xml');

        $this->mergeSiteConfiguration('integration_tree_one', ['base' => '/' . $absRefPrefix . '/']);

        $this->addToIndexQueue('tx_fakeextension_domain_model_bar', 111);

        /** @var  $cliEnvironment CliEnvironment */
        $cliEnvironment = GeneralUtility::makeInstance(CliEnvironment::class);
        $cliEnvironment->backup();
        $cliEnvironment->initialize(Environment::getPublicPath() . '/');

        /* @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        /** @var $indexService IndexService */
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
