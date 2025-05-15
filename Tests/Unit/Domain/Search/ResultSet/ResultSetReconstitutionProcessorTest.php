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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\ResultSetReconstitutionProcessor;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit test case for the ObjectReconstitutionProcessor.
 */
class ResultSetReconstitutionProcessorTest extends SetUpUnitTestCase
{
    protected function initializeSearchResultSetFromFakeResponse(string $fixtureFile): SearchResultSet
    {
        /** @var SearchRequest|MockObject $searchRequestMock */
        $searchRequestMock = $this->createMock(SearchRequest::class);

        $fakeResponseJson = self::getFixtureContentByName($fixtureFile);
        $fakeResponse = new ResponseAdapter($fakeResponseJson);

        $searchResultSet = new SearchResultSet();
        $searchResultSet->setUsedSearchRequest($searchRequestMock);
        $searchResultSet->setResponse($fakeResponse);

        return $searchResultSet;
    }

    #[Test]
    public function canReconstituteSpellCheckingModelsFromResponse(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_spellCheck.json');

        // before the reconstitution of the domain object from the response we expect that no spelling suggestions
        // are present
        self::assertFalse($searchResultSet->getHasSpellCheckingSuggestions());

        $processor = new ResultSetReconstitutionProcessor();
        $processor->process($searchResultSet);

        // after the reconstitution they should be present
        self::assertTrue($searchResultSet->getHasSpellCheckingSuggestions());
    }

    #[Test]
    public function canReconstituteFacetModelFromResponse(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_one_fields_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);
        // after the reconstitution they should be 1 facet present
        self::assertCount(1, $searchResultSet->getFacets());
    }

    #[Test]
    public function canReconstituteJsonFacetModelFromResponse(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_jsonfacets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 1 facet present
        self::assertCount(1, $searchResultSet->getFacets());

        /** @var OptionsFacet $optionFacet */
        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);
        // @extensionScannerIgnoreLine
        self::assertSame('tx_myext_domain_model_mytype', $optionFacet->getOptions()->getByPosition(0)->getValue(), 'Custom type facet not found');
        // @extensionScannerIgnoreLine
        self::assertSame(19, $optionFacet->getOptions()->getByPosition(0)->getDocumentCount(), 'Custom type facet count not correct');
    }

    #[Test]
    public function canReconstituteFacetModelsFromResponse(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                ],
                'category.' => [
                    'label' => 'My Category',
                    'field' => 'category_stringM',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 2 facets present
        self::assertCount(2, $searchResultSet->getFacets());
    }

    #[Test]
    public function canSkipOptionsMarkedAsExcludeValue(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                    'excludeValues' => 'somethingelse, page, whatever',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        self::assertCount(1, $searchResultSet->getFacets());

        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);
        self::assertCount(1, $optionFacet->getOptions());
        self::assertSame('event', $optionFacet->getOptions()->getByPosition(0)->getValue(), 'Skipping configured value not working as expected');
    }

    #[Test]
    public function canSetRequirementsMetToFalseOnFacetThatMissesARequirement(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'myType.' => [
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                    'requirements.' => [
                        'categoryIsInternal.' => [
                            'facet' => 'myCategory',
                            'values' => 'internal',
                        ],
                    ],
                ],
                'myCategory.' => [
                    'label' => 'My Category',
                    'field' => 'category_stringM',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 1 facet present
        self::assertCount(2, $searchResultSet->getFacets());

        $firstFacet = $searchResultSet->getFacets()->getByPosition(0);
        self::assertSame('myType', $firstFacet->getName(), 'Unexpected facet name for first facet');
        self::assertFalse($firstFacet->getAllRequirementsMet(), 'Unexpected state of allRequirementsMet');
    }

    #[Test]
    public function canSetRequirementsMetToTrueOnFacetThatFullFillsARequirement(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        /** @var MockObject|SearchRequest $usedSearchRequest */
        $usedSearchRequest = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequest->expects(self::any())->method('getActiveFacetValuesByName')->willReturnCallback(
            function ($name) {
                return $name == 'myType' ? ['pages'] : [];
            },
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'myType.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ],
                'myCategory.' => [
                    'label' => 'My Category',
                    'field' => 'category',
                    'requirements.' => [
                        'typeIsPage.' => [
                            'facet' => 'myType',
                            'values' => 'pages',
                        ],
                    ],
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 1 facet present
        self::assertCount(2, $searchResultSet->getFacets());

        $secondFacet = $searchResultSet->getFacets()->getByPosition(1);
        self::assertSame('myCategory', $secondFacet->getName(), 'Unexpected facet name for first facet');
        self::assertTrue($secondFacet->getAllRequirementsMet(), 'Unexpected state of allRequirementsMet');
    }

    #[Test]
    public function canGetOptionsInExpectedOrder(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);

        $option1 = $optionFacet->getOptions()->getByPosition(0);
        self::assertSame('page', $option1->getValue());

        $option2 = $optionFacet->getOptions()->getByPosition(1);
        self::assertSame('event', $option2->getValue());
    }

    #[Test]
    public function canGetOptionsInExpectedOrderWhenReversOrderIsApplied(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'reverseOrder' => 1,
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);

        $option1 = $optionFacet->getOptions()->getByPosition(0);
        self::assertSame('event', $option1->getValue());

        $option2 = $optionFacet->getOptions()->getByPosition(1);
        self::assertSame('page', $option2->getValue());
    }

    #[Test]
    public function canGetOptionsInExpectedOrderWhenManualSortOrderIsApplied(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'manualSortOrder' => 'event,page',
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);

        $option1 = $optionFacet->getOptions()->getByPosition(0);
        self::assertSame('event', $option1->getValue());

        $option2 = $optionFacet->getOptions()->getByPosition(1);
        self::assertSame('page', $option2->getValue());
    }

    #[Test]
    public function canReconstituteFacetModelsWithSameFieldNameFromResponse(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                ],
                'category.' => [
                    'label' => 'My Category',
                    'field' => 'category_stringM',
                ],
                'category2.' => [
                    'label' => 'My Category again',
                    'field' => 'category_stringM',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 1 facet present
        self::assertCount(3, $searchResultSet->getFacets());

        $facets = $searchResultSet->getFacets();
        self::assertCount(2, $facets->getByPosition(0)->getOptions());
    }

    #[Test]
    public function canReconstituteUsedFacet(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        /** @var MockObject|SearchRequest $usedSearchRequest */
        $usedSearchRequest = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequest->expects(self::any())->method('getActiveFacetValuesByName')->willReturnCallback(
            function ($name) {
                return $name == 'type' ? ['tx_solr_file'] : [];
            },
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ],
                'category.' => [
                    'label' => 'My Category',
                    'field' => 'category',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution we should have two facets present
        self::assertCount(2, $searchResultSet->getFacets());

        $facets = $searchResultSet->getFacets();

        /** @var OptionsFacet $facet1 */
        $facet1 = $facets->getByPosition(0);
        self::assertEquals('My Type', $facet1->getLabel());
        self::assertTrue($facet1->getIsUsed());

        /** @var OptionsFacet $facet2 */
        $facet2 = $facets->getByPosition(1);
        self::assertEquals('My Category', $facet2->getLabel());
        self::assertFalse($facet2->getIsUsed());
    }

    #[Test]
    public function canMarkUsedOptionAsSelected(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        /** @var MockObject|SearchRequest $usedSearchRequest */
        $usedSearchRequest = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequest->expects(self::any())->method('getActiveFacetValuesByName')->willReturnCallback(
            function ($name) {
                return $name == 'type' ? ['tx_solr_file'] : [];
            },
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ],
                // category is configured but not available
                'category.' => [
                    'label' => 'My Category',
                    'field' => 'category',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();

        self::assertCount(2, $facets, 'we have two facets at all');
        self::assertCount(1, $facets->getAvailable(), 'but only "type" is available');
        self::assertCount(1, $facets->getUsed(), 'and also "type" is the only used facet');
    }

    #[Test]
    public function includeIsUsedFacetsCanBeSetToFalse(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        /** @var MockObject|SearchRequest $usedSearchRequest */
        $usedSearchRequest = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequest->expects(self::any())->method('getActiveFacetValuesByName')->willReturnCallback(
            function ($name) {
                return $name == 'type' ? ['tx_solr_file'] : [];
            },
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                    'includeInUsedFacets' => '0',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();

        self::assertCount(1, $facets, 'we have one facets at all');
        self::assertCount(0, $facets->getUsed(), 'we should have 0 used facets because type has configuration includeInUsedFacets=0');
    }

    #[Test]
    public function canGetConfiguredFacetNotInResponseAsUnavailableFacet(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        /** @var MockObject|SearchRequest $usedSearchRequest */
        $usedSearchRequest = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequest->expects(self::any())->method('getActiveFacetValuesByName')->willReturnCallback(
            function ($name) {
                return $name == 'type' ? ['pages'] : [];
            },
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ],
            ],

        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();

        /** @var OptionsFacet $facet1 */
        $facet1 = $facets->getByPosition(0);

        $firstOption = $facet1->getOptions()->getByPosition(0);
        self::assertEquals('pages', $firstOption->getValue());
        self::assertEquals(5, $firstOption->getDocumentCount());
        $this->asserttrue($firstOption->getSelected());
    }

    #[Test]
    public function canGetTwoUsedFacetOptions(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_two_used_facets.json');
        /** @var MockObject|SearchRequest $usedSearchRequest */
        $usedSearchRequest = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequest->expects(self::any())->method('getActiveFacetValuesByName')->willReturnCallback(
            function ($name) {
                if ($name == 'mytitle') {
                    return ['jpeg', 'kasper"s'];
                }
                return [];
            },
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'mytitle.' => [
                    'label' => 'My Title',
                    'field' => 'title',
                ],
            ],

        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();

        /** @var OptionsFacet $facet1 */
        $facet1 = $facets->getByPosition(0);

        $firstOption = $facet1->getOptions()->getByPosition(0);
        self::assertEquals('jpeg', $firstOption->getValue());
        self::assertEquals(1, $firstOption->getDocumentCount());
        self::assertTrue($firstOption->getSelected());
    }

    #[Test]
    public function emptyFacetsAreNotReconstitutedWhenDisabled(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 0,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ],
                // category is configured but not available
                'category.' => [
                    'label' => 'My Category',
                    'field' => 'category',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        self::assertCount(1, $facets, 'we have two facets at all');
    }

    #[Test]
    public function emptyFacetIsKeptWhenNothingIsConfiguredGloballyButKeepingIsEnabledOnFacetLevel(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ],
                // category is configured but not available
                'category.' => [
                    'label' => 'My Category',
                    'field' => 'category',
                    'showEvenWhenEmpty' => 1,
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        self::assertCount(2, $facets, 'we have two facets at all');
    }

    #[Test]
    public function includeInAvailableFacetsCanBeSetToFalse(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                    'includeInAvailableFacets' => 0,
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        self::assertCount(1, $facets, 'we have one facets at all');
        self::assertCount(0, $facets->getAvailable(), 'but non is available, the first is set to includeInAvailableFacets=0');
    }

    #[Test]
    public function includeInAvailableFacetsCanBeSetToTrue(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                    'includeInAvailableFacets' => 1,
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        self::assertCount(1, $facets, 'we have one facets at all');
        self::assertCount(1, $facets->getAvailable(), 'but non is available, the first is set to includeInAvailableFacets=0');
    }

    #[Test]
    public function labelCanBeConfiguredAsAPlainText(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type with special rendering',
                    'field' => 'type_stringS',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facet = $searchResultSet->getFacets()->getByPosition(0);
        self::assertSame('My Type with special rendering', $facet->getLabel(), 'Could not get label for facet');
    }

    #[Test]
    public function returnsCorrectSetUpFacetTypeForAQueryGroupFacet(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'age.' => [
                    'type' => 'queryGroup',
                    'label' => 'Age',
                    'field' => 'created',
                    'queryGroup.' => [
                        'week' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                        'month' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
                        'halfYear' => ['query' => '[NOW/DAY-6MONTHS TO NOW/DAY-1MONTH]'],
                        'year' => ['query' => '[NOW/DAY-1YEAR TO NOW/DAY-6MONTHS]'],
                        'old' => ['query' => '[* TO NOW/DAY-1YEAR]'],
                    ],
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        self::assertCount(1, $facets, 'Facet not created');

        /** @var QueryGroupFacet $facet */
        $facet = $facets->getByPosition(0);
        self::assertInstanceOf(QueryGroupFacet::class, $facet);

        // @extensionScannerIgnoreLine
        self::assertCount(3, $facet->getOptions());
    }

    #[Test]
    public function canGetOptionsInExpectedOrderForQueryGroupFacet(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'age.' => [
                    'type' => 'queryGroup',
                    'label' => 'Age',
                    'field' => 'created',
                    'queryGroup.' => [
                        'week' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                        'month' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
                        'halfYear' => ['query' => '[NOW/DAY-6MONTHS TO NOW/DAY-1MONTH]'],
                        'year' => ['query' => '[NOW/DAY-1YEAR TO NOW/DAY-6MONTHS]'],
                        'old' => ['query' => '[* TO NOW/DAY-1YEAR]'],
                    ],
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var QueryGroupFacet $facet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);

        // @extensionScannerIgnoreLine
        $firstValue = $facet->getOptions()->getByPosition(0)->getValue();
        // @extensionScannerIgnoreLine
        $secondValue = $facet->getOptions()->getByPosition(1)->getValue();
        // @extensionScannerIgnoreLine
        $thirdValue = $facet->getOptions()->getByPosition(2)->getValue();

        self::assertSame('month', $firstValue, 'Could not get values in expected order from QueryGroupFacet');
        self::assertSame('halfYear', $secondValue, 'Could not get values in expected order from QueryGroupFacet');
        self::assertSame('old', $thirdValue, 'Could not get values in expected order from QueryGroupFacet');
    }

    #[Test]
    public function canGetOptionsInExpectedOrderForQueryGroupFacetWithManualSortOrder(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'age.' => [
                    'type' => 'queryGroup',
                    'manualSortOrder' => 'halfYear,month,old',
                    'label' => 'Age',
                    'field' => 'created',
                    'queryGroup.' => [
                        'week' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                        'month' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
                        'halfYear' => ['query' => '[NOW/DAY-6MONTHS TO NOW/DAY-1MONTH]'],
                        'year' => ['query' => '[NOW/DAY-1YEAR TO NOW/DAY-6MONTHS]'],
                        'old' => ['query' => '[* TO NOW/DAY-1YEAR]'],
                    ],
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var QueryGroupFacet $facet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);

        // @extensionScannerIgnoreLine
        $firstValue = $facet->getOptions()->getByPosition(0)->getValue();
        // @extensionScannerIgnoreLine
        $secondValue = $facet->getOptions()->getByPosition(1)->getValue();
        // @extensionScannerIgnoreLine
        $thirdValue = $facet->getOptions()->getByPosition(2)->getValue();

        self::assertSame('halfYear', $firstValue, 'Could not get values in expected order from QueryGroupFacet');
        self::assertSame('month', $secondValue, 'Could not get values in expected order from QueryGroupFacet');
        self::assertSame('old', $thirdValue, 'Could not get values in expected order from QueryGroupFacet');
    }

    #[Test]
    public function canGetOptionsInExpectedOrderForQueryGroupFacetWithReversOrder(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'age.' => [
                    'type' => 'queryGroup',
                    'label' => 'Age',
                    'field' => 'created',
                    'reverseOrder' => 1,
                    'queryGroup.' => [
                        'week' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                        'month' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
                        'halfYear' => ['query' => '[NOW/DAY-6MONTHS TO NOW/DAY-1MONTH]'],
                        'year' => ['query' => '[NOW/DAY-1YEAR TO NOW/DAY-6MONTHS]'],
                        'old' => ['query' => '[* TO NOW/DAY-1YEAR]'],
                    ],
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var QueryGroupFacet $facet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);

        // @extensionScannerIgnoreLine
        $firstValue = $facet->getOptions()->getByPosition(0)->getValue();
        // @extensionScannerIgnoreLine
        $secondValue = $facet->getOptions()->getByPosition(1)->getValue();
        // @extensionScannerIgnoreLine
        $thirdValue = $facet->getOptions()->getByPosition(2)->getValue();

        self::assertSame('old', $firstValue, 'Could not get values in expected order from QueryGroupFacet');
        self::assertSame('halfYear', $secondValue, 'Could not get values in expected order from QueryGroupFacet');
        self::assertSame('month', $thirdValue, 'Could not get values in expected order from QueryGroupFacet');
    }

    #[Test]
    public function returnsResultSetWithConfiguredSortingOptions(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        $configuration = [];
        $configuration['plugin.']['tx_solr.']['search.'] = [
            'sorting' => 1,
            'sorting.' => [
                'defaultOrder' => 'asc',
                'options.' => [
                    'relevance.' => [
                        'field' => 'relevance',
                        'label' => 'Relevance',
                    ],
                ],
            ],
        ];

        /** @var MockObject|SearchRequest $usedSearchRequest */
        $usedSearchRequest = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequest->expects(self::any())->method('getHasSorting')->willReturn(false);

        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        self::assertEquals(1, $searchResultSet->getSortings()->getCount(), 'No sorting was created');
        self::assertEquals('relevance', $searchResultSet->getSortings()->getSelected()->getName());
        self::assertTrue($searchResultSet->getSortings()->getHasSelected(), 'The sorting by "relevance/score" is active but not marked as selected.');
    }

    #[Test]
    public function canReturnSortingsAndMarkedSelectedAsActive(): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        $configuration = [];
        $configuration['plugin.']['tx_solr.']['search.'] = [
            'sorting' => 1,
            'sorting.' => [
                'defaultOrder' => 'asc',
                'options.' => [
                    'relevance.' => [
                        'field' => 'relevance',
                        'label' => 'Relevance',
                    ],
                    'title.' => [
                        'field' => 'sortTitle',
                        'label' => 'Title',
                    ],

                ],
            ],
        ];

        /** @var MockObject|SearchRequest $usedSearchRequest */
        $usedSearchRequest = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequest->expects(self::any())->method('getHasSorting')->willReturn(true);
        $usedSearchRequest->expects(self::any())->method('getSortingName')->willReturn('title');
        $usedSearchRequest->expects(self::any())->method('getSortingDirection')->willReturn('desc');
        $usedSearchRequest->expects(self::any())->method('getSeperatedSortings')->willReturn(
            ['title' => 'desc', 'relevance' => 'asc'],
        );

        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        self::assertEquals(2, $searchResultSet->getSortings()->getCount(), 'Unexpected amount of sorting options have been created');
        self::assertTrue($searchResultSet->getSortings()->getHasSelected(), 'Expected that a selected sorting was present');
        self::assertSame('desc', $searchResultSet->getSortings()->getSelected()->getDirection(), 'Selected sorting as unexpected direction');
    }

    protected function getConfigurationArrayFromFacetConfigurationArray(array $facetConfiguration): array
    {
        $configuration = [];
        $configuration['plugin.']['tx_solr.']['search.']['faceting.'] = $facetConfiguration;
        return $configuration;
    }

    protected function getConfiguredReconstitutionProcessor(array $configuration, SearchResultSet $searchResultSet): ResultSetReconstitutionProcessor
    {
        $typoScriptConfiguration = new TypoScriptConfiguration($configuration);
        /** @var MockObject|SearchRequest $usedSearchRequest */
        $usedSearchRequest = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($typoScriptConfiguration);
        $usedSearchRequest->expects(self::any())->method('getActiveFacetNames')->willReturn([]);

        $processor = new ResultSetReconstitutionProcessor();
        return $processor;
    }
}
