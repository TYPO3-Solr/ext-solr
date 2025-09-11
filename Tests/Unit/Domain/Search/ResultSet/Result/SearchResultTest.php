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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Result;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit test case for the SearchResult.
 */
class SearchResultTest extends SetUpUnitTestCase
{
    protected SearchResult $searchResult;

    protected function setUp(): void
    {
        $fields = [
            'id' => '4711',
            'title' => 'The title',
            'score' => 0.55,
            'content' => 'foobar',
            'isElevated' => true,
            'url' => '://mytestdomain.com/test',
            'type' => 'pages',
        ];
        $this->searchResult = new SearchResult($fields);
        parent::setUp();
    }

    #[Test]
    public function canGetId(): void
    {
        self::assertSame(
            '4711',
            $this->searchResult->getId(),
            'Could not get id from searchResult'
        );
    }

    #[Test]
    public function canGetScore(): void
    {
        self::assertSame(
            0.55,
            $this->searchResult->getScore(),
            'Could not get score from searchResult'
        );
    }

    #[Test]
    public function canGetContent(): void
    {
        self::assertSame(
            'foobar',
            $this->searchResult->getContent(),
            'Could not get content from searchResult'
        );
    }

    #[Test]
    public function canGetType(): void
    {
        self::assertSame(
            'pages',
            $this->searchResult->getType(),
            'Could not get type from searchResult'
        );
    }

    #[Test]
    public function canGetTitle(): void
    {
        self::assertSame(
            'The title',
            $this->searchResult->getTitle(),
            'Could not get title from searchResult'
        );
    }

    #[Test]
    public function canGetUrl(): void
    {
        self::assertSame(
            '://mytestdomain.com/test',
            $this->searchResult->getUrl(),
            'Could not get url from searchResult'
        );
    }

    #[Test]
    public function canGetIsElevated(): void
    {
        self::assertTrue(
            $this->searchResult->getIsElevated(),
            'Could not get isElevated from searchResult'
        );
    }

    #[Test]
    public function getOnUnexistingFieldReturnsNull(): void
    {
        self::assertNull(
            /** @phpstan-ignore-next-line */
            $this->searchResult->getUnexistingField(),
            'Calling getter for unexisting field does not return null'
        );
    }

    #[Test]
    public function canGetVectorSimilarityScore(): void
    {
        self::assertSame(
            0.0,
            $this->searchResult->getVectorSimilarityScore(),
        );

        self::assertSame(
            85.0,
            (new SearchResult(['$q_vector' => 85]))->getVectorSimilarityScore(),
        );
    }
}
