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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Search\LastSearches;

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LastSearchesRepositoryTest extends IntegrationTestBase
{
    protected LastSearchesRepository $lastSearchesRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lastSearchesRepository = GeneralUtility::makeInstance(LastSearchesRepository::class);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/last_searches.csv');
    }

    /**
     * @test
     */
    public function canFindAllKeywords(): void
    {
        $actual = $this->lastSearchesRepository->findAllKeywords();
        self::assertSame(['4', '3', '2', '1', '0'], $actual);
    }

    /**
     * @test
     */
    public function addWillInsertNewRowIfLastSearchesLimitIsNotExceeded(): void
    {
        $this->lastSearchesRepository->add('5', 6);

        $actual = $this->lastSearchesRepository->findAllKeywords();
        self::assertSame(['5', '4', '3', '2', '1', '0'], $actual);
    }

    /**
     * @test
     */
    public function addWillUpdateOldestRowIfLastSearchesLimitIsExceeded(): void
    {
        $this->lastSearchesRepository->add('5', 5);

        $actual = $this->lastSearchesRepository->findAllKeywords();
        self::assertSame(['5', '4', '3', '2', '1'], $actual);
    }

    /**
     * @test
     */
    public function lastUpdatedRowIsOnFirstPosition(): void
    {
        $this->lastSearchesRepository->add('1', 5);

        $actual = $this->lastSearchesRepository->findAllKeywords();
        self::assertSame(['1', '4', '3', '2'], $actual);
    }
}
