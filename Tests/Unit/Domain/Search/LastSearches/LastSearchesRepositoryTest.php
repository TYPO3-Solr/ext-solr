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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\LastSearches;

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesRepository;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

class LastSearchesRepositoryTest extends UnitTest
{
    /**
     * @var LastSearchesRepository
     */
    protected $lastSearchesRepositoryMock;

    protected function setUp(): void
    {
        $this->lastSearchesRepositoryMock = $this->getMockBuilder(LastSearchesRepository::class)
            ->onlyMethods(['getLastSearchesResultSet'])
            ->getMock();
        parent::setUp();
    }

    /**
     * @test
     */
    public function findAllKeywordsWillDecoteKeywordsAsHTMLEntities()
    {
        $givenKeywords = [
            ['keywords' => 'test'],
            ['keywords' => 'test 2'],
            ['keywords' => '&#34;test X&#34;'],
            ['keywords' => '&#x0027;test Y&#x0027;'],
        ];
        $this->lastSearchesRepositoryMock->method('getLastSearchesResultSet')->willReturn($givenKeywords);

        $lastSearches = $this->lastSearchesRepositoryMock->findAllKeywords();

        $expectedDecotedLastSearches = [
            'test',
            'test 2',
            '"test X"',
            '\'test Y\'',
        ];

        self::assertSame($expectedDecotedLastSearches, $lastSearches);
    }
}
