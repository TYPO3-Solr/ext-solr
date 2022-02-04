<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Search\ResultSet;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\ResultSetReconstitutionProcessor;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;

/**
 * Unit test case for the ObjectReconstitutionProcessor.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ResultSetReconstitutionProcessorTest extends IntegrationTest
{

    /**
     * @test
     *
     * @throws TestingFrameworkCoreException
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     */
    public function canApplyRenderingInstructionsOnOptions()
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->fakeTSFE(1);

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type with special rendering',
                    'field' => 'type_stringS',
                    'renderingInstruction' => 'CASE',
                    'renderingInstruction.' => [
                        'key.' => [
                            'field' => 'optionValue',
                        ],
                        'page' => 'TEXT',
                        'page.' => [
                            'value' => 'Pages',
                        ],
                        'event' => 'TEXT',
                        'event.' => [
                            'value' => 'Events',
                        ],

                    ],
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var $facet OptionsFacet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);

        /** @var $option1 Option */ // @extensionScannerIgnoreLine
        $option1 = $facet->getOptions()->getByPosition(0);
        self::assertSame('Pages', $option1->getLabel(), 'Rendering instructions have not been applied on the facet options');
    }

    /**
     * @test
     *
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     * @throws TestingFrameworkCoreException
     */
    public function labelCanBeUsedAsCObject()
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->fakeTSFE(1);
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'TEXT',
                    'label.' => [
                        'value' => 'My Type with special rendering',
                        'stdWrap.' => ['case' => 'upper'],
                    ],
                    'field' => 'type_stringS',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var $facet OptionsFacet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);
        self::assertSame('MY TYPE WITH SPECIAL RENDERING', $facet->getLabel(), 'Rendering instructions have not been applied on the facet options');
    }

    /**
     * @param string $fixtureFile
     * @return SearchResultSet
     */
    protected function initializeSearchResultSetFromFakeResponse(string $fixtureFile): SearchResultSet
    {
        $searchRequestMock = $this->createMock(SearchRequest::class);

        $fakeResponseJson = $this->getFixtureContentByName($fixtureFile);
        $fakeResponse = new ResponseAdapter($fakeResponseJson);

        $searchResultSet = new SearchResultSet();
        $searchResultSet->setUsedSearchRequest($searchRequestMock);
        $searchResultSet->setResponse($fakeResponse);

        return $searchResultSet;
    }

    /**
     * @param array $facetConfiguration
     * @return array
     */
    protected function getConfigurationArrayFromFacetConfigurationArray(array $facetConfiguration): array
    {
        $configuration = [];
        $configuration['plugin.']['tx_solr.']['search.']['faceting.'] = $facetConfiguration;
        return $configuration;
    }

    /**
     * @param array $configuration
     * @param SearchResultSet $searchResultSet
     * @return ResultSetReconstitutionProcessor
     */
    protected function getConfiguredReconstitutionProcessor(array $configuration, SearchResultSet $searchResultSet): ResultSetReconstitutionProcessor
    {
        $typoScriptConfiguration = new TypoScriptConfiguration($configuration);

        /* @var SearchRequest|MockObject $usedSearchRequestMock */
        $usedSearchRequestMock = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequestMock->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($typoScriptConfiguration);
        $usedSearchRequestMock->expects(self::any())->method('getActiveFacetNames')->willReturn([]);

        $processor = new ResultSetReconstitutionProcessor();
        $fakeObjectManager = $this->getFakeObjectManager();
        $processor->setObjectManager($fakeObjectManager);
        return $processor;
    }
}
