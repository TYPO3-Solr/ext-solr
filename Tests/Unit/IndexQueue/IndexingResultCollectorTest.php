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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

use ApacheSolrForTypo3\Solr\IndexQueue\IndexingResultCollector;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

class IndexingResultCollectorTest extends SetUpUnitTestCase
{
    #[Test]
    public function finalizeUserGroupsReturnsPublicGroupWhenNoGroupsCollected(): void
    {
        $collector = new IndexingResultCollector();
        $collector->finalizeUserGroups();

        self::assertSame([0], $collector->getUserGroups());
    }

    #[Test]
    public function finalizeUserGroupsDeduplicatesGroups(): void
    {
        $collector = new IndexingResultCollector();
        $collector->addFrontendGroup(1);
        $collector->addFrontendGroup(1);
        $collector->addFrontendGroup(0);
        $collector->addFrontendGroup(0);
        $collector->finalizeUserGroups();

        self::assertSame([1, 0], $collector->getUserGroups());
    }

    #[Test]
    public function finalizeUserGroupsSortsDescending(): void
    {
        $collector = new IndexingResultCollector();
        $collector->addFrontendGroup(0);
        $collector->addFrontendGroup(3);
        $collector->addFrontendGroup(1);
        $collector->addFrontendGroup(2);
        $collector->finalizeUserGroups();

        self::assertSame([3, 2, 1, 0], $collector->getUserGroups());
    }

    #[Test]
    public function finalizeUserGroupsFilterOutsHideAtLoginGroup(): void
    {
        $collector = new IndexingResultCollector();
        $collector->addFrontendGroup(0);
        $collector->addFrontendGroup(-1);
        $collector->addFrontendGroup(1);
        $collector->finalizeUserGroups();

        self::assertSame([1, 0], $collector->getUserGroups());
    }

    #[Test]
    public function finalizeUserGroupsHandlesStringValues(): void
    {
        $collector = new IndexingResultCollector();
        $collector->addFrontendGroup('2');
        $collector->addFrontendGroup('0');
        $collector->addFrontendGroup('1');
        $collector->finalizeUserGroups();

        self::assertSame([2, 1, 0], $collector->getUserGroups());
    }

    #[Test]
    public function finalizeUserGroupsHandlesCommaDelimitedValues(): void
    {
        $collector = new IndexingResultCollector();
        $collector->addFrontendGroup('1,2');
        $collector->addFrontendGroup(0);
        $collector->finalizeUserGroups();

        self::assertSame([2, 1, 0], $collector->getUserGroups());
    }

    #[Test]
    public function resetClearsAllState(): void
    {
        $collector = new IndexingResultCollector();
        $collector->addFrontendGroup(1);
        $collector->setUserGroupDetectionActive(true);
        $collector->finalizeUserGroups();

        $collector->reset();

        self::assertSame([], $collector->getUserGroups());
        self::assertFalse($collector->isUserGroupDetectionActive());
    }

    #[DataProvider('realWorldScenariosDataProvider')]
    #[Test]
    public function finalizeUserGroupsHandlesRealWorldScenarios(
        array $collectedGroups,
        array $expectedResult,
    ): void {
        $collector = new IndexingResultCollector();
        foreach ($collectedGroups as $group) {
            $collector->addFrontendGroup($group);
        }
        $collector->finalizeUserGroups();

        self::assertSame($expectedResult, $collector->getUserGroups());
    }

    public static function realWorldScenariosDataProvider(): Traversable
    {
        yield 'public page with only public content' => [
            'collectedGroups' => [0, 0, 0],
            'expectedResult' => [0],
        ];

        yield 'public page with mixed content (public + group 1)' => [
            'collectedGroups' => [0, 1, 0],
            'expectedResult' => [1, 0],
        ];

        yield 'protected page with protected content (group 1 + group 2)' => [
            'collectedGroups' => [0, 2, 0],
            'expectedResult' => [2, 0],
        ];

        yield 'page with hide-at-login and protected content' => [
            'collectedGroups' => [-1, 1, 0],
            'expectedResult' => [1, 0],
        ];

        yield 'page with show-at-any-login content' => [
            'collectedGroups' => [-2, 0],
            'expectedResult' => [0, -2],
        ];

        yield 'empty collection falls back to public' => [
            'collectedGroups' => [],
            'expectedResult' => [0],
        ];
    }
}
