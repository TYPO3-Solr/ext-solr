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
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LastSearchesRepositoryTest extends IntegrationTest
{
    /**
     * @var LastSearchesRepository
     */
    protected $lastSearchesRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lastSearchesRepository = GeneralUtility::makeInstance(LastSearchesRepository::class);
    }

    /**
     * @test
     */
    public function canFindAllKeywords()
    {
        $this->importDataSetFromFixture('can_find_and_add_last_searches.xml');
        $actual = $this->lastSearchesRepository->findAllKeywords(10);
        self::assertSame(['4', '3', '2', '1', '0'], $actual);
    }

    /**
     * @test
     */
    public function addWillInsertNewRowIfLastSearchesLimitIsNotExceeded()
    {
        $this->importDataSetFromFixture('can_find_and_add_last_searches.xml');

        $this->lastSearchesRepository->add('5', 6);

        $actual = $this->lastSearchesRepository->findAllKeywords(10);
        self::assertSame(['5', '4', '3', '2', '1', '0'], $actual);
    }

    /**
     * @test
     */
    public function addWillUpdateOldestRowIfLastSearchesLimitIsExceeded()
    {
        $this->importDataSetFromFixture('can_find_and_add_last_searches.xml');

        $this->lastSearchesRepository->add('5', 5);

        $actual = $this->lastSearchesRepository->findAllKeywords();
        self::assertSame(['5', '4', '3', '2', '1'], $actual);
    }

    /**
     * @test
     */
    public function lastUpdatedRowIsOnFirstPosition()
    {
        $this->importDataSetFromFixture('can_find_and_add_last_searches.xml');

        $this->lastSearchesRepository->add('1', 5);

        $actual = $this->lastSearchesRepository->findAllKeywords();
        self::assertSame(['1', '4', '3', '2'], $actual);
    }
}
