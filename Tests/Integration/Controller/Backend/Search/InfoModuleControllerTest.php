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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller\Backend\Search;

use ApacheSolrForTypo3\Solr\Controller\Backend\Search\InfoModuleController;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsFilterDto;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Property\PropertyMapper;

final class InfoModuleControllerTest extends IntegrationTestBase
{
    /**
     * The date pickers of the statistics filter send local date and time
     * without a time zone.
     */
    /**
     * An unticked checkbox sends its hidden empty value.
     */
    #[Test]
    public function mapsLanguageLimitAndAnUntickedMergeSwitch(): void
    {
        $filter = $this->mapStatisticsFilter(['languageId' => '2', 'limit' => '9999', 'mergeSimilar' => '']);

        $filter->setAllowedLanguageIds([0, 2])->setFromTypoScriptConstants(['topHits.' => ['limit' => 7]]);
        self::assertSame([2], $filter->getLanguageIds());
        self::assertSame(7, $filter->getTopHitsLimit());
        self::assertFalse($filter->isMergeSimilar());
    }

    #[Test]
    public function mapsATickedMergeSwitchAndAChosenLimit(): void
    {
        $filter = $this->mapStatisticsFilter(['languageId' => '-1', 'limit' => '25', 'mergeSimilar' => '1']);

        self::assertSame(25, $filter->getTopHitsLimit());
        self::assertTrue($filter->isMergeSimilar());
        self::assertSame(StatisticsFilterDto::ALL_LANGUAGES, $filter->getLanguageId());
    }

    /**
     * @param array<string, string> $formValues
     */
    private function mapStatisticsFilter(array $formValues): StatisticsFilterDto
    {
        $arguments = new Arguments();
        $argument = $arguments->addNewArgument('statisticsFilter', StatisticsFilterDto::class);
        $controller = (new ReflectionClass(InfoModuleController::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($controller, 'arguments'))->setValue($controller, $arguments);
        $controller->initializeIndexAction();
        $configuration = $argument->getPropertyMappingConfiguration();
        $configuration->allowAllProperties();

        $propertyMapper = $this->get(PropertyMapper::class);
        $filter = $propertyMapper->convert($formValues, StatisticsFilterDto::class, $configuration);

        self::assertFalse($propertyMapper->getMessages()->hasErrors(), (string)json_encode($propertyMapper->getMessages()->getFlattenedErrors()));
        self::assertInstanceOf(StatisticsFilterDto::class, $filter);
        return $filter;
    }

    #[Test]
    public function mapsTheDatesTheStatisticsFilterSends(): void
    {
        $arguments = new Arguments();
        $argument = $arguments->addNewArgument('statisticsFilter', StatisticsFilterDto::class);
        $controller = (new ReflectionClass(InfoModuleController::class))->newInstanceWithoutConstructor();
        $property = new ReflectionProperty($controller, 'arguments');
        $property->setValue($controller, $arguments);

        $controller->initializeIndexAction();

        $configuration = $argument->getPropertyMappingConfiguration();
        $configuration->allowAllProperties();
        $propertyMapper = $this->get(PropertyMapper::class);
        $filter = $propertyMapper->convert(
            ['startDate' => '2026-10-08T14:30:58', 'endDate' => '2026-11-07T13:30:58'],
            StatisticsFilterDto::class,
            $configuration,
        );

        self::assertFalse($propertyMapper->getMessages()->hasErrors(), (string)json_encode($propertyMapper->getMessages()->getFlattenedErrors()));
        self::assertInstanceOf(StatisticsFilterDto::class, $filter);
        self::assertSame('2026-10-08 14:30:58', $filter->getStartDate()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-07 13:30:58', $filter->getEndDate()?->format('Y-m-d H:i:s'));
    }
}
