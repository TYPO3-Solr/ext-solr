<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Search\LastSearches;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class LastSearchesRepositoryTest extends IntegrationTest
{
    /**
     * @var LastSearchesRepository
     */
    protected $lastSearchesRepository;

    public function setUp()
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
        $this->assertSame(['4', '3', '2', '1', '0'], $actual);
    }

    /**
     * @test
     */
    public function addWillInsertNewRowIfLastSearchesLimitIsNotExceeded()
    {
        $this->importDataSetFromFixture('can_find_and_add_last_searches.xml');

        $this->lastSearchesRepository->add('5', 6);

        $actual = $this->lastSearchesRepository->findAllKeywords(10);
        $this->assertSame(['5', '4', '3', '2', '1', '0'], $actual);
    }

    /**
     * @test
     */
    public function addWillUpdateOldestRowIfLastSearchesLimitIsExceeded()
    {
        $this->importDataSetFromFixture('can_find_and_add_last_searches.xml');

        $this->lastSearchesRepository->add('5', 5);

        $actual = $this->lastSearchesRepository->findAllKeywords();
        $this->assertSame(['5', '4', '3', '2', '1'], $actual);
    }
}
