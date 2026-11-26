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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Statistics;

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsFilterDto;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

final class StatisticsFilterDtoTest extends SetUpUnitTestCase
{
    private const TYPOSCRIPT = ['topHits.' => ['limit' => 7], 'noHits.' => ['limit' => 3]];

    #[Test]
    public function takesTheLimitsFromTypoScriptWhileNoneIsChosen(): void
    {
        $filter = (new StatisticsFilterDto())->setFromTypoScriptConstants(self::TYPOSCRIPT);

        self::assertSame(7, $filter->getTopHitsLimit());
        self::assertSame(3, $filter->getNoHitsLimit());
    }

    /**
     * The controller applies TypoScript after Extbase mapped the form.
     */
    #[Test]
    public function aChosenLimitWinsOverTypoScriptForBothLists(): void
    {
        $filter = (new StatisticsFilterDto())->setLimit(25)->setFromTypoScriptConstants(self::TYPOSCRIPT);

        self::assertSame(25, $filter->getTopHitsLimit());
        self::assertSame(25, $filter->getNoHitsLimit());
    }

    #[Test]
    public function ignoresALimitThatIsNoOption(): void
    {
        $filter = (new StatisticsFilterDto())->setLimit(9999)->setFromTypoScriptConstants(self::TYPOSCRIPT);

        self::assertSame(0, $filter->getLimit());
        self::assertSame(7, $filter->getTopHitsLimit());
        self::assertSame(3, $filter->getNoHitsLimit());
    }

    #[Test]
    public function filtersByTheChosenLanguageWhenItIsAllowed(): void
    {
        $filter = (new StatisticsFilterDto())->setAllowedLanguageIds([0, 2])->setLanguageId(2);

        self::assertSame([2], $filter->getLanguageIds());
    }

    #[Test]
    public function fallsBackToAllAllowedLanguagesForALanguageThatIsNotAllowed(): void
    {
        $filter = (new StatisticsFilterDto())->setAllowedLanguageIds([0, 2])->setLanguageId(1);

        self::assertSame([0, 2], $filter->getLanguageIds());
    }

    #[Test]
    public function coversAllAllowedLanguagesByDefault(): void
    {
        $filter = (new StatisticsFilterDto())->setAllowedLanguageIds([0, 2]);

        self::assertSame(StatisticsFilterDto::ALL_LANGUAGES, $filter->getLanguageId());
        self::assertSame([0, 2], $filter->getLanguageIds());
    }

    #[Test]
    public function mergesSimilarSearchesByDefault(): void
    {
        self::assertTrue((new StatisticsFilterDto())->isMergeSimilar());
        self::assertFalse((new StatisticsFilterDto())->setMergeSimilar(false)->isMergeSimilar());
    }
}
