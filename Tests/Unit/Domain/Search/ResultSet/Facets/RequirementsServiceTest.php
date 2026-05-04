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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RequirementsService;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to test the RequirementsService
 */
class RequirementsServiceTest extends SetUpUnitTestCase
{
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function getRequirementsMetReturnTrueWhenNothingConfigured(): void
    {
        $facet = $this->createMock(OptionsFacet::class);
        $service = new RequirementsService();
        self::assertTrue($service->getAllRequirementsMet($facet), 'Facet without any requirements should met all requirements');
    }

    #[Test]
    public function getAllRequirementsMetIsReturnsFalseWhenARequirementIsNotMet(): void
    {
        $resultSet = new SearchResultSet();
        $colorConfig = [];
        $colorFacet = new OptionsFacet($resultSet, 'mycolor', 'colors_stringM', 'Colors', $colorConfig);
        $resultSet->addFacet($colorFacet);

        $categoryConfig = [
            'requirements.' => [
                'matchesColor' => [
                    'facet' => 'mycolor',
                    'values' => 'red',
                ],
            ],
        ];
        $categoryFacet = new OptionsFacet($resultSet, 'mycategory', 'category_stringM', 'Category', $categoryConfig);
        $resultSet->addFacet($categoryFacet);
        $service = new RequirementsService();
        self::assertFalse($service->getAllRequirementsMet($categoryFacet), 'Requirement is not met');
    }

    #[Test]
    public function getAllRequirementsMetIsReturnsTrueWhenRequirementIsMet(): void
    {
        $resultSet = new SearchResultSet();
        $colorConfig = [];
        $colorFacet = new OptionsFacet($resultSet, 'mycolor', 'colors_stringM', 'Colors', $colorConfig);
        $redOption = new Option($colorFacet, 'Red', 'red', 42, true);

        $colorFacet->addOption($redOption);
        $colorFacet->setIsUsed(true);

        $resultSet->addFacet($colorFacet);

        $categoryConfig = [
            'requirements.' => [
                'matchesColor' => [
                    'facet' => 'mycolor',
                    'values' => 'red,green',
                ],
            ],
        ];
        $categoryFacet = new OptionsFacet($resultSet, 'mycategory', 'category_stringM', 'Category', $categoryConfig);
        $resultSet->addFacet($categoryFacet);
        $service = new RequirementsService();
        self::assertTrue($service->getAllRequirementsMet($categoryFacet), 'Requirement should be met, because color option is present, but is indicated to not be met');
    }

    #[Test]
    public function getAllRequirementsMetIsReturnsTrueWhenRequirementIsMetForMultipleFacets(): void
    {
        $resultSet = new SearchResultSet();
        $colorFacet = new OptionsFacet($resultSet, 'mycolor', 'colors_stringM', 'Colors', []);
        $redOption = new Option($colorFacet, 'Red', 'red', 42, true);
        $colorFacet->addOption($redOption);
        $colorFacet->setIsUsed(true);
        $resultSet->addFacet($colorFacet);

        $siteFacet = new OptionsFacet($resultSet, 'mysize', 'sizes_stringM', 'Sizes', []);
        $xlOption = new Option($colorFacet, 'XL', 'xl', 12, true);
        $siteFacet->addOption($xlOption);
        $siteFacet->setIsUsed(true);
        $resultSet->addFacet($siteFacet);

        $categoryConfig = [
            'requirements.' => [
                'matchesColor' => [
                    'facet' => 'mycolor',
                    'values' => 'red,green',
                ],
                'matchesSize' => [
                    'facet' => 'mysize',
                    'values' => 'xl',
                ],

            ],
        ];
        $categoryFacet = new OptionsFacet($resultSet, 'mycategory', 'category_stringM', 'Category', $categoryConfig);
        $resultSet->addFacet($categoryFacet);
        $service = new RequirementsService();
        self::assertTrue($service->getAllRequirementsMet($categoryFacet), 'Requirement should be met, because color and size option is present, but is indicated to not be met');
    }

    #[Test]
    public function getAllRequirementsMetIsReturnsFalseWhenOnlyOneConfiguredRequirementIsMet(): void
    {
        $resultSet = new SearchResultSet();
        $colorFacet = new OptionsFacet($resultSet, 'mycolor', 'colors_stringM', 'Colors', []);
        $redOption = new Option($colorFacet, 'Red', 'red', 42, true);
        $colorFacet->addOption($redOption);
        $colorFacet->setIsUsed(true);
        $resultSet->addFacet($colorFacet);

        $sizeFacet = new OptionsFacet($resultSet, 'mysize', 'size_stringM', 'Size', []);
        $resultSet->addFacet($sizeFacet);

        $categoryConfig = [
            'requirements.' => [
                'matchesColor' => [
                    'facet' => 'mycolor',
                    'values' => 'red,green',
                ],
                'matchesSize' => [
                    'facet' => 'mysize',
                    'values' => 'xl',
                ],

            ],
        ];
        $categoryFacet = new OptionsFacet($resultSet, 'mycategory', 'category_stringM', 'Category', $categoryConfig);
        $resultSet->addFacet($categoryFacet);
        $service = new RequirementsService();
        self::assertFalse($service->getAllRequirementsMet($categoryFacet), 'Requirement should not be met since the matchesSize requirement is not met.');
    }

    #[Test]
    public function getAllRequirementsMetIsReturnsFalseWhenRequiredFacetHasADifferentValue(): void
    {
        $resultSet = new SearchResultSet();
        $colorConfig = [];
        $colorFacet = new OptionsFacet($resultSet, 'mycolor', 'colors_stringM', 'Colors', $colorConfig);
        $redOption = new Option($colorFacet, 'Red', 'red');
        $colorFacet->addOption($redOption);
        $colorFacet->setIsUsed(true);

        $resultSet->addFacet($colorFacet);

        $categoryConfig = [
            'requirements.' => [
                'matchesColor' => [
                    'facet' => 'mycolor',
                    'values' => 'blue,green',
                ],
            ],
        ];
        $categoryFacet = new OptionsFacet($resultSet, 'mycategory', 'category_stringM', 'Category', $categoryConfig);
        $resultSet->addFacet($categoryFacet);
        $service = new RequirementsService();
        self::assertFalse($service->getAllRequirementsMet($categoryFacet), 'Requirement should not be met because the facet has not the require value');
    }

    #[Test]
    public function getAllRequirementsMetIsReturnsFalseIfRequiredFacetValueIsNotSelected(): void
    {
        $resultSet = new SearchResultSet();
        $colorConfig = [];
        $colorFacet = new OptionsFacet($resultSet, 'mycolor', 'colors_stringM', 'Colors', $colorConfig);
        $redOption = new Option($colorFacet, 'Red', 'red', 0, false);
        $colorFacet->addOption($redOption);
        $colorFacet->setIsUsed(true);

        $resultSet->addFacet($colorFacet);

        $categoryConfig = [
            'requirements.' => [
                'matchesColor' => [
                    'facet' => 'mycolor',
                    'values' => 'red,blue,green',
                ],
            ],
        ];
        $categoryFacet = new OptionsFacet($resultSet, 'mycategory', 'category_stringM', 'Category', $categoryConfig);
        $resultSet->addFacet($categoryFacet);
        $service = new RequirementsService();
        self::assertFalse($service->getAllRequirementsMet($categoryFacet), 'Requirement should not be met because the required option is not selected');
    }

    #[Test]
    public function exceptionIsThrownForRequirementWithNotExistingFacet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Requirement for non-existing facet configured');
        $resultSet = new SearchResultSet();

        $categoryConfig = [
            'requirements.' => [
                'matchesColor' => [
                    'facet' => 'mycolor',
                    'values' => 'red,green',
                ],
            ],
        ];
        $categoryFacet = new OptionsFacet($resultSet, 'mycategory', 'category_stringM', 'Category', $categoryConfig);
        $resultSet->addFacet($categoryFacet);
        $service = new RequirementsService();
        $service->getAllRequirementsMet($categoryFacet);
    }

    #[Test]
    public function canNegateRequirementsResult(): void
    {
        $resultSet = new SearchResultSet();
        $colorConfig = [];
        $colorFacet = new OptionsFacet($resultSet, 'mycolor', 'colors_stringM', 'Colors', $colorConfig);
        $redOption = new Option($colorFacet, 'Red', 'red');
        $colorFacet->addOption($redOption);
        $colorFacet->setIsUsed(true);

        $resultSet->addFacet($colorFacet);

        $categoryConfig = [
            'requirements.' => [
                'matchesColor' => [
                    'facet' => 'mycolor',
                    'values' => 'blue,green',
                    'negate' => '1',
                ],
            ],
        ];
        $categoryFacet = new OptionsFacet($resultSet, 'mycategory', 'category_stringM', 'Category', $categoryConfig);
        $resultSet->addFacet($categoryFacet);
        $service = new RequirementsService();
        self::assertTrue($service->getAllRequirementsMet($categoryFacet), 'Requirement should be met because of negate but is not met');
    }
}
