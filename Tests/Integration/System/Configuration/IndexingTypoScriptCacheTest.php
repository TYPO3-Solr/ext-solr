<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Configuration;

use ApacheSolrForTypo3\Solr\FrontendSimulation\FrontendAwareEnvironment;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TypoScript built outside a frontend request must only end up in the dedicated
 * "solr_indexing_typoscript" cache, and only if enabled by extension setting.
 */
final class IndexingTypoScriptCacheTest extends IntegrationTestBase
{
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'exceptionalErrors' => E_WARNING | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED,
            'caching' => [
                'cacheConfigurations' => [
                    'typoscript' => [
                        'backend' => SimpleFileBackend::class,
                    ],
                    'solr_indexing_typoscript' => [
                        'backend' => SimpleFileBackend::class,
                    ],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->importCSVDataSet(__DIR__ . '/../../FrontendEnvironment/Fixtures/can_initialize_tsfe_for_page_with_different_fe_groups_settings.csv');
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->getCache('typoscript')->flush();
        $cacheManager->getCache('solr_indexing_typoscript')->flush();
    }

    #[Test]
    public function typoScriptIsNotCachedByDefault(): void
    {
        GeneralUtility::makeInstance(FrontendAwareEnvironment::class)->getServerRequestByPageIdIgnoringLanguage(1);

        self::assertSame([], $this->getCacheEntryFiles('solr_indexing_typoscript'));
        self::assertSame([], $this->getCacheEntryFiles('typoscript'));
    }

    #[Test]
    public function typoScriptIsCachedInDedicatedCacheOnlyIfEnabled(): void
    {
        $request = GeneralUtility::makeInstance(FrontendAwareEnvironment::class)
            ->getServerRequestByPageIdIgnoringLanguage(1)
            ->withoutAttribute('frontend.typoscript');
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $indexingTypoScriptCache = $cacheManager->getCache('solr_indexing_typoscript');
        self::assertInstanceOf(PhpFrontend::class, $indexingTypoScriptCache);
        $configurationManager = new ConfigurationManager(
            $indexingTypoScriptCache,
            new ExtensionConfiguration(['enableIndexingTypoScriptCache' => 1]),
        );

        $configurationManager->getCoreTypoScriptFrontendByRequest($request);

        self::assertNotSame(
            [],
            $this->getCacheEntryFiles('solr_indexing_typoscript'),
            'TypoScript built for indexing must be cached in "solr_indexing_typoscript" when enabled.',
        );
        self::assertSame(
            [],
            $this->getCacheEntryFiles('typoscript'),
            'TypoScript built without frontend request must never be written to the core "typoscript" cache.',
        );
    }

    /**
     * @return string[]
     */
    private function getCacheEntryFiles(string $cacheIdentifier): array
    {
        $backend = GeneralUtility::makeInstance(CacheManager::class)->getCache($cacheIdentifier)->getBackend();
        self::assertInstanceOf(SimpleFileBackend::class, $backend);
        return array_values(array_filter(glob($backend->getCacheDirectory() . '*') ?: [], is_file(...)));
    }
}
