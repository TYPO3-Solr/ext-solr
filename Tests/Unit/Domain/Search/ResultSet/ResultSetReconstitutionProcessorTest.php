<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\ResultSetReconstitutionProcessor;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Tests\Unit\Helper\FakeObjectManager;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\ContentObject\CaseContentObject;
use TYPO3\CMS\Frontend\ContentObject\TextContentObject;

/**
 * Unit test case for the ObjectReconstitutionProcessor.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ResultSetReconstitutionProcessorTest extends UnitTest
{
    /**
     * @param $fixtureFile
     * @return SearchResultSet
     */
    protected function initializeSearchResultSetFromFakeResponse($fixtureFile)
    {
        $searchRequestMock = $this->getDumbMock(SearchRequest::class);

        $fakeResponseJson = $this->getFixtureContentByName($fixtureFile);
        $fakeResponse = new ResponseAdapter($fakeResponseJson);

        $searchResultSet = new SearchResultSet();
        $searchResultSet->setUsedSearchRequest($searchRequestMock);
        $searchResultSet->setResponse($fakeResponse);

        return $searchResultSet;
    }

    /**
     * @test
     */
    public function canReconstituteSpellCheckingModelsFromResponse()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_spellCheck.json');

        // before the reconstitution of the domain object from the response we expect that no spelling suggestions
        // are present
        $this->assertFalse($searchResultSet->getHasSpellCheckingSuggestions());


        $processor = new ResultSetReconstitutionProcessor();
        $processor->process($searchResultSet);

        // after the reconstitution they should be present
        $this->assertTrue($searchResultSet->getHasSpellCheckingSuggestions());
    }

    /**
     * @test
     */
    public function canReconstituteFacetModelFromResponse()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_one_fields_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ]
             ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 1 facet present
        $this->assertCount(1, $searchResultSet->getFacets());
    }


    /**
     * @test
     */
    public function canReconstituteJsonFacetModelFromResponse()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_jsonfacets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type'
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 1 facet present
        $this->assertCount(1, $searchResultSet->getFacets());

        /** @var $optionFacet OptionsFacet */
        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);
        // @extensionScannerIgnoreLine
        $this->assertSame('tx_myext_domain_model_mytype', $optionFacet->getOptions()->getByPosition(0)->getValue(), 'Custom type facet not found');
        // @extensionScannerIgnoreLine
        $this->assertSame(19, $optionFacet->getOptions()->getByPosition(0)->getDocumentCount(), 'Custom type facet count not correct');
    }

    /**
     * @test
     */
    public function canReconstituteFacetModelsFromResponse()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

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
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 2 facets present
        $this->assertCount(2, $searchResultSet->getFacets());
    }

    /**
     * @test
     */
    public function canSkipOptionsMarkedAsExcludeValue()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                    'excludeValues' => 'somethingelse, page, whatever'
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $this->assertCount(1, $searchResultSet->getFacets());

            /** @var $optionFacet OptionsFacet */
        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);
        // @extensionScannerIgnoreLine
        $this->assertCount(1, $optionFacet->getOptions());
        // @extensionScannerIgnoreLine
        $this->assertSame('event', $optionFacet->getOptions()->getByPosition(0)->getValue(), 'Skipping configured value not working as expected');
    }


    /**
     * @test
     */
    public function canSetRequirementsMetToFalseOnFacetThatMissesARequirement()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'myType.' => [
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                    'requirements.' => [
                        'categoryIsInternal.' => [
                            'facet' => 'myCategory',
                            'values' => 'internal'
                        ]
                    ]
                ],
                'myCategory.' => [
                    'label' => 'My Category',
                    'field' => 'category_stringM',
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 1 facet present
        $this->assertCount(2, $searchResultSet->getFacets());

        $firstFacet = $searchResultSet->getFacets()->getByPosition(0);
        $this->assertSame('myType', $firstFacet->getName(), 'Unexpected facet name for first facet');
        $this->assertFalse($firstFacet->getAllRequirementsMet(), 'Unexpected state of allRequirementsMet');
    }


    /**
     * @test
     */
    public function canSetRequirementsMetToTrueOnFacetThatFullFillsARequirement()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getActiveFacetValuesByName')->will(
            $this->returnCallback(function ($name) {
                return $name == 'myType' ? ['pages'] : [];
            })
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

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
                            'values' => 'pages'
                        ]
                    ]
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 1 facet present
        $this->assertCount(2, $searchResultSet->getFacets());

        $secondFacet = $searchResultSet->getFacets()->getByPosition(1);
        $this->assertSame('myCategory', $secondFacet->getName(), 'Unexpected facet name for first facet');
        $this->assertTrue($secondFacet->getAllRequirementsMet(), 'Unexpected state of allRequirementsMet');
    }

    /**
     * @test
     */
    public function canGetOptionsInExpectedOrder()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var $optionFacet OptionsFacet */
        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);

        /** @var $option1 Option */ // @extensionScannerIgnoreLine
        $option1 = $optionFacet->getOptions()->getByPosition(0);
        $this->assertSame('page', $option1->getValue());

        /** @var $option2 Option */ // @extensionScannerIgnoreLine
        $option2 = $optionFacet->getOptions()->getByPosition(1);
        $this->assertSame('event', $option2->getValue());
    }

    /**
     * @test
     */
    public function canGetOptionsInExpectedOrderWhenReversOrderIsApplied()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'reverseOrder' => 1,
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var $optionFacet OptionsFacet */
        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);

        /** @var $option1 Option */ // @extensionScannerIgnoreLine
        $option1 = $optionFacet->getOptions()->getByPosition(0);
        $this->assertSame('event', $option1->getValue());

        /** @var $option2 Option */ // @extensionScannerIgnoreLine
        $option2 = $optionFacet->getOptions()->getByPosition(1);
        $this->assertSame('page', $option2->getValue());
    }


    /**
     * @test
     */
    public function canGetOptionsInExpectedOrderWhenManualSortOrderIsApplied()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'manualSortOrder' => 'event,page',
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var $optionFacet OptionsFacet */
        $optionFacet = $searchResultSet->getFacets()->getByPosition(0);

        /** @var $option1 Option */ // @extensionScannerIgnoreLine
        $option1 = $optionFacet->getOptions()->getByPosition(0);
        $this->assertSame('event', $option1->getValue());

        /** @var $option2 Option */ // @extensionScannerIgnoreLine
        $option2 = $optionFacet->getOptions()->getByPosition(1);
        $this->assertSame('page', $option2->getValue());
    }

    /**
     * @test
     */
    public function canReconstituteFacetModelsWithSameFieldNameFromResponse()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type_stringS',
                ],
                'category.' => [
                    'label' => 'My Category',
                    'field' => 'category_stringM'
                ],
                'category2.' => [
                    'label' => 'My Category again',
                    'field' => 'category_stringM'
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution they should be 1 facet present
        $this->assertCount(3, $searchResultSet->getFacets());

        /** @var OptionsFacet $facet */
        $facets = $searchResultSet->getFacets();
        $this->assertCount(2, $facets->getByPosition(0)->getOptions());
    }


    /**
     * @test
     */
    public function canReconstituteUsedFacet()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getActiveFacetValuesByName')->will(
            $this->returnCallback(function ($name) {
                return $name == 'type' ? ['tx_solr_file'] : [];

            })
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ],
                'category.' => [
                    'label' => 'My Category',
                    'field' => 'category'
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        // after the reconstitution we should have two facets present
        $this->assertCount(2, $searchResultSet->getFacets());

        $facets = $searchResultSet->getFacets();

        /** @var OptionsFacet $facet1 */
        $facet1 = $facets->getByPosition(0);
        $this->assertEquals('My Type', $facet1->getLabel());
        $this->assertTrue($facet1->getIsUsed());

        /** @var OptionsFacet $facet2 */
        $facet2 = $facets->getByPosition(1);
        $this->assertEquals('My Category', $facet2->getLabel());
        $this->assertFalse($facet2->getIsUsed());
    }

    /**
     * @test
     */
    public function canMarkUsedOptionAsSelected()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getActiveFacetValuesByName')->will(
            $this->returnCallback(function ($name) {
                return $name == 'type' ? ['tx_solr_file'] : [];

            })
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

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
                    'field' => 'category'
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();

        $this->assertCount(2, $facets, 'we have two facets at all');
        $this->assertCount(1, $facets->getAvailable(), 'but only "type" is available');
        $this->assertCount(1, $facets->getUsed(), 'and also "type" is the only used facet');
    }

    /**
     * @test
     */
    public function includeIsUsedFacetsCanBeSetToFalse()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getActiveFacetValuesByName')->will(
            $this->returnCallback(function ($name) {
                return $name == 'type' ? ['tx_solr_file'] : [];

            })
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                    'includeInUsedFacets' => '0'
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();

        $this->assertCount(1, $facets, 'we have one facets at all');
        $this->assertCount(0, $facets->getUsed(), 'we should have 0 used facets because type has configuration includeInUsedFacets=0');
    }

    /**
     * @test
     */
    public function canGetConfiguredFacetNotInResponseAsUnavailableFacet()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getActiveFacetValuesByName')->will(
            $this->returnCallback(function ($name) {
                return $name == 'type' ? ['pages'] : [];

            })
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                ]
            ]

        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();

        /** @var OptionsFacet $facet1 */
        $facet1 = $facets->getByPosition(0);

        /** @var $firstOption Option */ // @extensionScannerIgnoreLine
        $firstOption = $facet1->getOptions()->getByPosition(0);
        $this->assertEquals('pages', $firstOption->getValue());
        $this->assertEquals(5, $firstOption->getDocumentCount());
        $this->asserttrue($firstOption->getSelected());
    }

    /**
     * @test
     */
    public function canGetTwoUsedFacetOptions()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_two_used_facets.json');
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getActiveFacetValuesByName')->will(
            $this->returnCallback(function ($name) {
                if ($name == 'mytitle') {
                    return ['jpeg', 'kasper"s'];
                } else {
                    return [];
                }

            })
        );

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'showEmptyFacets' => 1,
            'facets.' => [
                'mytitle.' => [
                    'label' => 'My Title',
                    'field' => 'title',
                ]
            ]

        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();

        /** @var OptionsFacet $facet1 */
        $facet1 = $facets->getByPosition(0);

        /** @var $firstOption Option */ // @extensionScannerIgnoreLine
        $firstOption = $facet1->getOptions()->getByPosition(0);
        $this->assertEquals('jpeg', $firstOption->getValue());
        $this->assertEquals(1, $firstOption->getDocumentCount());
        $this->assertTrue($firstOption->getSelected());
    }

    /**
     * @test
     */
    public function emptyFacetsAreNotReconstitutedWhenDisabled()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

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
                    'field' => 'category'
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        $this->assertCount(1, $facets, 'we have two facets at all');
    }

    /**
     * @test
     */
    public function emptyFacetIsKeptWhenNothingIsConfiguredGloballyButKeepingIsEnabledOnFacetLevel()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

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
                    'showEvenWhenEmpty' => 1
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        $this->assertCount(2, $facets, 'we have two facets at all');
    }

    /**
     * @test
     */
    public function includeInAvailableFacetsCanBeSetToFalse()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                    'includeInAvailableFacets' => 0
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        $this->assertCount(1, $facets, 'we have one facets at all');
        $this->assertCount(0, $facets->getAvailable(), 'but non is available, the first is set to includeInAvailableFacets=0');
    }

    /**
     * @test
     */
    public function includeInAvailableFacetsCanBeSetToTrue()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_used_facet.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type',
                    'field' => 'type',
                    'includeInAvailableFacets' => 1
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        $this->assertCount(1, $facets, 'we have one facets at all');
        $this->assertCount(1, $facets->getAvailable(), 'but non is available, the first is set to includeInAvailableFacets=0');
    }

    /**
     * @test
     */
    public function labelCanBeConfiguredAsAPlainText()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type with special rendering',
                    'field' => 'type_stringS',
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var $facet OptionsFacet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);
        $this->assertSame('My Type with special rendering', $facet->getLabel(), 'Could not get label for facet');
    }

    /**
     * @test
     */
    public function returnsCorrectSetUpFacetTypeForAQueryGroupFacet()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

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
                        'old' => ['query' => '[* TO NOW/DAY-1YEAR]']
                    ]
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $facets = $searchResultSet->getFacets();
        $this->assertCount(1, $facets, 'Facet not created');

        /** @var QueryGroupFacet $facet */
        $facet = $facets->getByPosition(0);
        $this->assertInstanceOf(QueryGroupFacet::class, $facet);

        // @extensionScannerIgnoreLine
        $this->assertCount(3, $facet->getOptions());
    }

    /**
     * @test
     */
    public function canGetOptionsInExpectedOrderForQueryGroupFacet()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

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
                        'old' => ['query' => '[* TO NOW/DAY-1YEAR]']
                    ]
                ]
            ]
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

        $this->assertSame('month', $firstValue, 'Could not get values in expected order from QueryGroupFacet');
        $this->assertSame('halfYear', $secondValue, 'Could not get values in expected order from QueryGroupFacet');
        $this->assertSame('old', $thirdValue, 'Could not get values in expected order from QueryGroupFacet');
    }

    /**
     * @test
     */
    public function canGetOptionsInExpectedOrderForQueryGroupFacetWithManualSortOrder()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

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
                        'old' => ['query' => '[* TO NOW/DAY-1YEAR]']
                    ]
                ]
            ]
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

        $this->assertSame('halfYear', $firstValue, 'Could not get values in expected order from QueryGroupFacet');
        $this->assertSame('month', $secondValue, 'Could not get values in expected order from QueryGroupFacet');
        $this->assertSame('old', $thirdValue, 'Could not get values in expected order from QueryGroupFacet');
    }

    /**
     * @test
     */
    public function canGetOptionsInExpectedOrderForQueryGroupFacetWithReversOrder()
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_query_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

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
                        'old' => ['query' => '[* TO NOW/DAY-1YEAR]']
                    ]
                ]
            ]
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

        $this->assertSame('old', $firstValue, 'Could not get values in expected order from QueryGroupFacet');
        $this->assertSame('halfYear', $secondValue, 'Could not get values in expected order from QueryGroupFacet');
        $this->assertSame('month', $thirdValue, 'Could not get values in expected order from QueryGroupFacet');
    }

    /**
     * @test
     */
    public function returnsResultSetWithConfiguredSortingOptions()
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
                        'label' => 'Relevance'
                    ]
                ]
            ]
        ];

        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getHasSorting')->will($this->returnValue(false));

        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $this->assertEquals(1, $searchResultSet->getSortings()->getCount(), 'No sorting was created');
        $this->assertFalse($searchResultSet->getSortings()->getHasSelected(), 'Expected that no selected sorting was present');
    }

    /**
     * @test
     */
    public function canReturnSortingsAndMarkedSelectedAsActive()
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
                        'label' => 'Relevance'
                    ],
                    'title.' => [
                        'field' => 'sortTitle',
                        'label' => 'Title'
                    ]

                ]
            ]
        ];

        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getHasSorting')->will($this->returnValue(true));
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getSortingName')->will($this->returnValue('title'));
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getSortingDirection')->will($this->returnValue('desc'));

        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        $this->assertEquals(2, $searchResultSet->getSortings()->getCount(), 'Unexpected amount of sorting options have been created');
        $this->assertTrue($searchResultSet->getSortings()->getHasSelected(), 'Expected that a selected sorting was present');
        $this->assertSame('desc', $searchResultSet->getSortings()->getSelected()->getDirection(), 'Selected sorting as unexpected direction');
    }

    /**
     * @param array $facetConfiguration
     * @return array
     */
    protected function getConfigurationArrayFromFacetConfigurationArray($facetConfiguration)
    {
        $configuration = [];
        $configuration['plugin.']['tx_solr.']['search.']['faceting.'] = $facetConfiguration;
        return $configuration;
    }

    /**
     * @param array $configuration
     * @param $searchResultSet
     * @return ResultSetReconstitutionProcessor
     */
    protected function getConfiguredReconstitutionProcessor($configuration, $searchResultSet)
    {
        $typoScriptConfiguration = new TypoScriptConfiguration($configuration);
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($typoScriptConfiguration));
        $searchResultSet->getUsedSearchRequest()->expects($this->any())->method('getActiveFacetNames')->will($this->returnValue([]));

        $processor = new ResultSetReconstitutionProcessor();
        $processor->setObjectManager(new FakeObjectManager());
        return $processor;
    }
}
