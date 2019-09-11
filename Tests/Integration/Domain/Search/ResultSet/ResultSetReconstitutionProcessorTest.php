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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\ResultSetReconstitutionProcessor;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Tests\Unit\Helper\FakeObjectManager;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\ContentObject\CaseContentObject;
use TYPO3\CMS\Frontend\ContentObject\TextContentObject;

/**
 * Unit test case for the ObjectReconstitutionProcessor.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ResultSetReconstitutionProcessorTest extends IntegrationTest
{
    /**
     * @param $fixtureFile
     * @return SearchResultSet
     */
    protected function initializeSearchResultSetFromFakeResponse($fixtureFile)
    {
        $searchRequestMock = $this->getMockBuilder(SearchRequest::class)->getMock();

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
    public function canApplyRenderingInstructionsOnOptions()
    {
        $this->fakeTSFEToUseCObject();

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type with special rendering',
                    'field' => 'type_stringS',
                    'renderingInstruction' => 'CASE',
                    'renderingInstruction.' => [
                        'key.' => [
                            'field' => 'optionValue'
                        ],
                        'page' => 'TEXT',
                        'page.' => [
                            'value' => 'Pages'
                        ],
                        'event' => 'TEXT',
                        'event.' => [
                            'value' => 'Events'
                        ]

                    ]
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var $facet OptionsFacet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);

        /** @var $option1 Option */ // @extensionScannerIgnoreLine
        $option1 = $facet->getOptions()->getByPosition(0);
        $this->assertSame('Pages', $option1->getLabel(), 'Rendering instructions have not been applied on the facet options');
    }

    /**
     * @test
     */
    public function labelCanBeUsedAsCObject()
    {
        $this->fakeTSFEToUseCObject();
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        $this->assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'TEXT',
                    'label.' => [
                        'value' => 'My Type with special rendering',
                        'stdWrap.' => ['case' => 'upper']
                    ],
                    'field' => 'type_stringS',
                ]
            ]
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var $facet OptionsFacet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);
        $this->assertSame('MY TYPE WITH SPECIAL RENDERING', $facet->getLabel(), 'Rendering instructions have not been applied on the facet options');
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

    /**
     *
     */
    protected function fakeTSFEToUseCObject()
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'], ['TEXT' => TextContentObject::class, 'CASE' => CaseContentObject::class, ]);

        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, [], 1, 0);
        $TSFE->cObjectDepthCounter = 5;
        $TSFE->fe_user = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $GLOBALS['TSFE'] = $TSFE;
        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();
    }
}
